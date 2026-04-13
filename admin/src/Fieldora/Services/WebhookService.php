<?php

namespace TrashPanda\Fieldora\Services;

use RuntimeException;

class WebhookService
{
    public static function supportedEvents(): array
    {
        return [
            'booking.created' => 'A new booking is submitted',
            'booking.updated' => 'A booking is updated in admin',
            'booking.approved' => 'A requested booking is approved',
            'booking.cancelled' => 'A booking is cancelled',
            'payment.completed' => 'A payment completes successfully',
            'payment.failed' => 'A payment fails',
            'invoice.sent' => 'An invoice is sent',
            'invoice.paid' => 'An invoice is fully paid',
            'job.created' => 'A job is created',
            'job.updated' => 'A job is updated',
            'job.completed' => 'A job is completed',
        ];
    }

    public static function generateSecret(): string
    {
        return bin2hex(random_bytes(24));
    }

    public static function isValidEndpointUrl(string $url): bool
    {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        return in_array($scheme, ['https', 'http'], true);
    }

    public static function queueEvent(int $tenantId, string $eventKey, array $payload): void
    {
        if (!array_key_exists($eventKey, self::supportedEvents())) {
            throw new RuntimeException('Unsupported webhook event.');
        }

        $endpoints = \db_fetchall(
            'SELECT * FROM webhook_endpoints WHERE tenant_id = ? AND is_active = 1',
            [$tenantId]
        );

        $body = self::buildPayload($tenantId, $eventKey, $payload);
        $bodyJson = json_encode($body, JSON_UNESCAPED_SLASHES);
        if (!is_string($bodyJson) || $bodyJson === '') {
            throw new RuntimeException('Unable to encode webhook payload.');
        }

        foreach ($endpoints as $endpoint) {
            $events = json_decode((string) ($endpoint['events_json'] ?? '[]'), true) ?: [];
            if (!in_array($eventKey, $events, true)) {
                continue;
            }

            $signature = hash_hmac('sha256', $bodyJson, (string) $endpoint['secret']);
            \db_insert('webhook_deliveries', [
                'tenant_id' => $tenantId,
                'webhook_endpoint_id' => $endpoint['id'],
                'event_key' => $eventKey,
                'signature' => $signature,
                'payload_json' => $bodyJson,
                'status' => 'queued',
                'attempts' => 0,
                'next_attempt_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public static function deliver(int $deliveryId): array
    {
        $delivery = \db_fetch(
            'SELECT wd.*, we.endpoint_url, we.secret, we.name
             FROM webhook_deliveries wd
             INNER JOIN webhook_endpoints we ON we.id = wd.webhook_endpoint_id
             WHERE wd.id = ? LIMIT 1',
            [$deliveryId]
        );

        if (!$delivery) {
            throw new RuntimeException('Webhook delivery not found.');
        }

        $payload = (string) $delivery['payload_json'];
        $headers = [
            'Content-Type: application/json',
            'X-Fieldora-Signature: ' . $delivery['signature'],
            'X-Fieldora-Event: ' . $delivery['event_key'],
        ];
        $opts = [
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers) . "\r\n",
                'content' => $payload,
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ];

        $response = @file_get_contents((string) $delivery['endpoint_url'], false, stream_context_create($opts));
        $responseHeaders = $http_response_header ?? [];
        $responseCode = 0;
        foreach ($responseHeaders as $headerLine) {
            if (preg_match('/\s(\d{3})\s/', $headerLine, $matches)) {
                $responseCode = (int) $matches[1];
                break;
            }
        }

        $attempts = (int) $delivery['attempts'] + 1;
        $delivered = $response !== false && $responseCode >= 200 && $responseCode < 300;
        $nextAttemptAt = null;
        if (!$delivered && $attempts < 5) {
            $nextAttemptAt = date('Y-m-d H:i:s', time() + (int) pow(2, $attempts - 1) * 300);
        }

        \db_execute(
            'UPDATE webhook_deliveries
             SET status = ?, attempts = ?, response_code = ?, response_body = ?, delivered_at = ?, next_attempt_at = ?, updated_at = NOW()
             WHERE id = ?',
            [
                $delivered ? 'delivered' : 'failed',
                $attempts,
                $responseCode ?: null,
                $response !== false ? substr($response, 0, 4000) : 'Request failed',
                $delivered ? date('Y-m-d H:i:s') : null,
                $nextAttemptAt,
                $deliveryId,
            ]
        );

        return [
            'success' => $delivered,
            'response_code' => $responseCode,
            'response_body' => $response !== false ? substr($response, 0, 600) : 'Request failed',
            'attempts' => $attempts,
        ];
    }

    public static function sendTest(int $tenantId, int $endpointId, string $eventKey): array
    {
        $endpoint = \db_fetch('SELECT * FROM webhook_endpoints WHERE tenant_id = ? AND id = ? LIMIT 1', [$tenantId, $endpointId]);
        if (!$endpoint) {
            throw new RuntimeException('Webhook endpoint not found.');
        }
        if (!array_key_exists($eventKey, self::supportedEvents())) {
            throw new RuntimeException('Unsupported webhook event.');
        }

        $payload = [
            'event' => $eventKey,
            'tenant_id' => $tenantId,
            'timestamp' => gmdate('c'),
            'data' => [
                'test' => true,
                'message' => 'This is a Fieldora test event.',
                'resource' => [
                    'id' => 123,
                    'reference' => 'TEST-123',
                ],
            ],
        ];

        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
        if (!is_string($payloadJson) || $payloadJson === '') {
            throw new RuntimeException('Unable to encode test payload.');
        }
        $deliveryId = (int) \db_insert('webhook_deliveries', [
            'tenant_id' => $tenantId,
            'webhook_endpoint_id' => $endpointId,
            'event_key' => $eventKey,
            'signature' => hash_hmac('sha256', $payloadJson, (string) $endpoint['secret']),
            'payload_json' => $payloadJson,
            'status' => 'queued',
            'attempts' => 0,
            'next_attempt_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $result = self::deliver($deliveryId);
        \db_execute('UPDATE webhook_endpoints SET last_tested_at = NOW(), updated_at = NOW() WHERE id = ?', [$endpointId]);
        return $result + ['delivery_id' => $deliveryId];
    }

    public static function buildPayload(int $tenantId, string $eventKey, array $payload): array
    {
        return [
            'event' => $eventKey,
            'tenant_id' => $tenantId,
            'timestamp' => gmdate('c'),
            'data' => self::hydratePayloadData($tenantId, $eventKey, $payload),
        ];
    }

    private static function hydratePayloadData(int $tenantId, string $eventKey, array $payload): array
    {
        $data = $payload;

        if (!empty($payload['booking_id'])) {
            $booking = \db_fetch(
                'SELECT b.id, b.booking_number, b.status, b.approval_mode, b.scheduled_date, b.start_time, b.total_amount, b.payment_state,
                        c.id AS customer_id, c.first_name, c.last_name, c.email, c.phone
                 FROM bookings b
                 LEFT JOIN customers c ON c.id = b.customer_id
                 WHERE b.tenant_id = ? AND b.id = ? LIMIT 1',
                [$tenantId, (int) $payload['booking_id']]
            );
            if ($booking) {
                $data['booking'] = [
                    'id' => (int) $booking['id'],
                    'booking_number' => $booking['booking_number'],
                    'status' => $booking['status'],
                    'approval_mode' => $booking['approval_mode'],
                    'scheduled_date' => $booking['scheduled_date'],
                    'start_time' => $booking['start_time'],
                    'total_amount' => (float) $booking['total_amount'],
                    'payment_state' => $booking['payment_state'],
                    'customer' => [
                        'id' => (int) ($booking['customer_id'] ?? 0),
                        'name' => trim((string) (($booking['first_name'] ?? '') . ' ' . ($booking['last_name'] ?? ''))),
                        'email' => $booking['email'],
                        'phone' => $booking['phone'],
                    ],
                ];
            }
            unset($data['booking_id']);
        }

        if (!empty($payload['invoice_id'])) {
            $invoice = \db_fetch(
                'SELECT id, invoice_number, status, total, amount_paid, balance_due, due_date
                 FROM invoices WHERE tenant_id = ? AND id = ? LIMIT 1',
                [$tenantId, (int) $payload['invoice_id']]
            );
            if ($invoice) {
                $data['invoice'] = [
                    'id' => (int) $invoice['id'],
                    'invoice_number' => $invoice['invoice_number'],
                    'status' => $invoice['status'],
                    'total' => (float) $invoice['total'],
                    'amount_paid' => (float) $invoice['amount_paid'],
                    'balance_due' => (float) $invoice['balance_due'],
                    'due_date' => $invoice['due_date'],
                ];
            }
            unset($data['invoice_id']);
        }

        if (!empty($payload['payment_id'])) {
            $payment = \db_fetch(
                'SELECT id, amount, currency, payment_method, payment_status, payment_type, paid_at
                 FROM payments WHERE tenant_id = ? AND id = ? LIMIT 1',
                [$tenantId, (int) $payload['payment_id']]
            );
            if ($payment) {
                $data['payment'] = [
                    'id' => (int) $payment['id'],
                    'amount' => (float) $payment['amount'],
                    'currency' => $payment['currency'],
                    'payment_method' => $payment['payment_method'],
                    'payment_status' => $payment['payment_status'],
                    'payment_type' => $payment['payment_type'],
                    'paid_at' => $payment['paid_at'],
                ];
            }
            unset($data['payment_id']);
        }

        if (!empty($payload['job_id'])) {
            $job = \db_fetch(
                'SELECT j.id, j.job_number, j.title, j.status, j.scheduled_date, j.start_time, j.assigned_user_id, u.name AS assigned_name
                 FROM jobs j
                 LEFT JOIN users u ON u.id = j.assigned_user_id
                 WHERE j.tenant_id = ? AND j.id = ? LIMIT 1',
                [$tenantId, (int) $payload['job_id']]
            );
            if ($job) {
                $data['job'] = [
                    'id' => (int) $job['id'],
                    'job_number' => $job['job_number'],
                    'title' => $job['title'],
                    'status' => $job['status'],
                    'scheduled_date' => $job['scheduled_date'],
                    'start_time' => $job['start_time'],
                    'assigned_user' => [
                        'id' => (int) ($job['assigned_user_id'] ?? 0),
                        'name' => $job['assigned_name'],
                    ],
                ];
            }
            unset($data['job_id']);
        }

        unset($data['customer_id']);
        return $data;
    }
}
