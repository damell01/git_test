<?php

namespace TrashPanda\Fieldora\Services;

use RuntimeException;

class PaymentService
{
    public static function paymentConfig(int $tenantId): array
    {
        return [
            'allow_full_payment' => TenantService::setting($tenantId, 'allow_full_payment', '1') === '1',
            'require_deposit' => TenantService::setting($tenantId, 'require_deposit', '1') === '1',
            'default_deposit_mode' => TenantService::setting($tenantId, 'default_deposit_mode', 'percent'),
            'default_deposit_value' => (float) TenantService::setting($tenantId, 'default_deposit_value', '25'),
            'invoice_payments_enabled' => TenantService::setting($tenantId, 'invoice_payments_enabled', '1') === '1',
            'application_fee_percent' => (float) TenantService::setting($tenantId, 'application_fee_percent', '0'),
        ];
    }

    public static function createBookingCheckout(array $tenant, array $booking, array $items): ?string
    {
        return self::createCheckoutSession($tenant, [
            'mode' => 'booking',
            'booking' => $booking,
            'items' => $items,
            'success_path' => '/book/' . rawurlencode((string) $tenant['slug']) . '?success=1&booking=' . rawurlencode((string) $booking['public_token']),
            'cancel_path' => '/book/' . rawurlencode((string) $tenant['slug']) . '?canceled=1',
        ]);
    }

    public static function createInvoiceCheckout(array $tenant, array $invoice, array $items): ?string
    {
        if (!self::paymentConfig((int) $tenant['id'])['invoice_payments_enabled']) {
            throw new RuntimeException('Invoice payments are disabled for this business.');
        }

        return self::createCheckoutSession($tenant, [
            'mode' => 'invoice',
            'invoice' => $invoice,
            'items' => $items,
            'success_path' => APP_URL . '/modules/fieldora/invoice_view.php?id=' . (int) $invoice['id'] . '&paid=1',
            'cancel_path' => APP_URL . '/modules/fieldora/invoice_view.php?id=' . (int) $invoice['id'] . '&canceled=1',
        ]);
    }

    public static function createManualPayment(int $tenantId, array $data): int
    {
        $paymentId = (int) \db_insert('payments', [
            'tenant_id' => $tenantId,
            'booking_id' => $data['booking_id'] ?? null,
            'invoice_id' => $data['invoice_id'] ?? null,
            'customer_id' => $data['customer_id'] ?? null,
            'amount' => (float) ($data['amount'] ?? 0),
            'currency' => strtolower((string) ($data['currency'] ?? TenantService::setting($tenantId, 'currency', 'usd'))),
            'payment_method' => $data['payment_method'] ?? 'manual',
            'payment_status' => 'completed',
            'payment_type' => $data['payment_type'] ?? 'manual',
            'external_reference' => trim((string) ($data['external_reference'] ?? '')),
            'metadata_json' => json_encode($data['metadata'] ?? [], JSON_UNESCAPED_SLASHES),
            'paid_at' => date('Y-m-d H:i:s'),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        self::logEvent($tenantId, $paymentId, 'payment.manual_recorded', $data);
        return $paymentId;
    }

    public static function reconcileSuccessfulCheckout(string $sessionId, array $session): void
    {
        $payment = \db_fetch('SELECT * FROM payments WHERE stripe_checkout_session_id = ? LIMIT 1', [$sessionId]);
        if (!$payment || ($payment['payment_status'] ?? '') === 'completed') {
            return;
        }

        \db_execute(
            'UPDATE payments SET payment_status = ?, stripe_payment_intent_id = ?, amount = ?, paid_at = NOW(), updated_at = NOW() WHERE id = ?',
            [
                'completed',
                (string) ($session['payment_intent'] ?? ''),
                ((float) ($session['amount_total'] ?? 0)) / 100,
                $payment['id'],
            ]
        );

        self::applyPaymentToRecords((int) $payment['id']);
    }

    public static function markCheckoutFailed(string $paymentIntentId, array $payload): void
    {
        $payment = $paymentIntentId !== '' ? \db_fetch('SELECT * FROM payments WHERE stripe_payment_intent_id = ? LIMIT 1', [$paymentIntentId]) : false;
        if (!$payment && !empty($payload['metadata']['booking_id'])) {
            $payment = \db_fetch('SELECT * FROM payments WHERE booking_id = ? AND payment_status = ? ORDER BY id DESC LIMIT 1', [(int) $payload['metadata']['booking_id'], 'pending']);
        }
        if (!$payment && !empty($payload['metadata']['invoice_id'])) {
            $payment = \db_fetch('SELECT * FROM payments WHERE invoice_id = ? AND payment_status = ? ORDER BY id DESC LIMIT 1', [(int) $payload['metadata']['invoice_id'], 'pending']);
        }
        if (!$payment) {
            return;
        }

        \db_execute(
            'UPDATE payments SET payment_status = ?, stripe_payment_intent_id = COALESCE(NULLIF(?, ""), stripe_payment_intent_id), updated_at = NOW() WHERE id = ?',
            ['failed', $paymentIntentId, $payment['id']]
        );

        if (!empty($payment['booking_id'])) {
            \db_execute('UPDATE bookings SET payment_state = ?, updated_at = NOW() WHERE id = ?', ['failed', $payment['booking_id']]);
        }

        self::logEvent((int) $payment['tenant_id'], (int) $payment['id'], 'payment.failed', $payload);
    }

    public static function applyPaymentToRecords(int $paymentId): void
    {
        $payment = \db_fetch('SELECT * FROM payments WHERE id = ? LIMIT 1', [$paymentId]);
        if (!$payment) {
            return;
        }

        if (!empty($payment['booking_id'])) {
            $booking = \db_fetch('SELECT * FROM bookings WHERE id = ? LIMIT 1', [$payment['booking_id']]);
            if ($booking) {
                $state = ((float) $payment['amount']) >= ((float) $booking['total_amount']) ? 'paid' : 'deposit_paid';
                \db_execute('UPDATE bookings SET payment_state = ?, status = CASE WHEN status = "requested" THEN "confirmed" ELSE status END, updated_at = NOW(), confirmed_at = COALESCE(confirmed_at, NOW()) WHERE id = ?', [$state, $booking['id']]);
                WebhookService::queueEvent((int) $payment['tenant_id'], 'payment.completed', ['payment_id' => $paymentId, 'booking_id' => $booking['id']]);
                AutomationService::queueTrigger((int) $payment['tenant_id'], 'payment.completed', 'payment', $paymentId, ['booking_id' => $booking['id']]);
                $customer = \db_fetch('SELECT CONCAT_WS(" ", first_name, last_name) AS customer_name, email, phone FROM customers WHERE id = ? LIMIT 1', [$booking['customer_id']]);
                if (!empty($customer['email'])) {
                    NotificationService::queueTemplate((int) $payment['tenant_id'], 'payment_confirmation', 'email', (string) $customer['email'], [
                        'customer_name' => (string) ($customer['customer_name'] ?? 'Customer'),
                        'booking_number' => (string) ($booking['booking_number'] ?? ''),
                        'amount' => number_format((float) $payment['amount'], 2),
                    ], [
                        'booking_id' => (int) $booking['id'],
                        'customer_id' => (int) $booking['customer_id'],
                    ]);
                }
            }
        }

        if (!empty($payment['invoice_id'])) {
            $invoice = \db_fetch('SELECT * FROM invoices WHERE id = ? LIMIT 1', [$payment['invoice_id']]);
            if ($invoice) {
                $newPaid = (float) $invoice['amount_paid'] + (float) $payment['amount'];
                $balance = max(0, (float) $invoice['total'] - $newPaid);
                $status = $balance <= 0 ? 'paid' : 'partially_paid';
                \db_execute('UPDATE invoices SET amount_paid = ?, balance_due = ?, status = ?, paid_at = CASE WHEN ? = "paid" THEN NOW() ELSE paid_at END, updated_at = NOW() WHERE id = ?', [$newPaid, $balance, $status, $status, $invoice['id']]);
                WebhookService::queueEvent((int) $payment['tenant_id'], $status === 'paid' ? 'invoice.paid' : 'payment.completed', ['payment_id' => $paymentId, 'invoice_id' => $invoice['id']]);
                $customer = \db_fetch('SELECT CONCAT_WS(" ", first_name, last_name) AS customer_name, email FROM customers WHERE id = ? LIMIT 1', [$invoice['customer_id']]);
                if ($status === 'paid' && !empty($customer['email'])) {
                    NotificationService::queueTemplate((int) $payment['tenant_id'], 'invoice_paid', 'email', (string) $customer['email'], [
                        'customer_name' => (string) ($customer['customer_name'] ?? 'Customer'),
                        'invoice_number' => (string) ($invoice['invoice_number'] ?? ''),
                        'balance_due' => number_format($balance, 2),
                    ], [
                        'invoice_id' => (int) $invoice['id'],
                        'customer_id' => (int) $invoice['customer_id'],
                    ]);
                }
            }
        }
    }

    public static function logEvent(int $tenantId, ?int $paymentId, string $eventKey, array $payload): void
    {
        \db_insert('payment_event_logs', [
            'tenant_id' => $tenantId,
            'payment_id' => $paymentId,
            'event_key' => $eventKey,
            'payload_json' => json_encode($payload, JSON_UNESCAPED_SLASHES),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private static function createCheckoutSession(array $tenant, array $context): ?string
    {
        if (!class_exists(\Stripe\StripeClient::class) || STRIPE_PLATFORM_SECRET_KEY === '') {
            return null;
        }

        $account = ConnectService::status((int) $tenant['id']);
        if (
            !$account
            || empty($account['stripe_account_id'])
            || ($account['account_status'] ?? '') !== 'connected'
            || !(int) ($account['charges_enabled'] ?? 0)
        ) {
            throw new RuntimeException('Stripe Connect is not connected for this business.');
        }

        $config = self::paymentConfig((int) $tenant['id']);
        $currency = strtolower(TenantService::setting((int) $tenant['id'], 'currency', 'usd'));
        $items = $context['items'] ?? [];
        if ($items === []) {
            throw new RuntimeException('Nothing to charge.');
        }

        $lineItems = [];
        $totalCents = 0;
        foreach ($items as $item) {
            $amount = (float) ($item['amount'] ?? 0);
            $unitAmount = max(0, (int) round($amount * 100));
            $totalCents += $unitAmount;
            $lineItems[] = [
                'price_data' => [
                    'currency' => $currency,
                    'product_data' => [
                        'name' => (string) ($item['item_name'] ?? $item['description'] ?? 'Fieldora item'),
                        'description' => (string) ($item['description'] ?? ''),
                    ],
                    'unit_amount' => $unitAmount,
                ],
                'quantity' => max(1, (int) round((float) ($item['quantity'] ?? 1))),
            ];
        }

        $applicationFeeAmount = 0;
        if ($config['application_fee_percent'] > 0) {
            $applicationFeeAmount = (int) round($totalCents * ($config['application_fee_percent'] / 100));
        }

        $meta = [
            'tenant_id' => (string) $tenant['id'],
            'mode' => $context['mode'],
        ];
        if (!empty($context['booking']['id'])) {
            $meta['booking_id'] = (string) $context['booking']['id'];
            $meta['booking_number'] = (string) $context['booking']['booking_number'];
        }
        if (!empty($context['invoice']['id'])) {
            $meta['invoice_id'] = (string) $context['invoice']['id'];
            $meta['invoice_number'] = (string) $context['invoice']['invoice_number'];
        }

        $session = ConnectService::client()->checkout->sessions->create([
            'mode' => 'payment',
            'success_url' => SITE_URL . $context['success_path'],
            'cancel_url' => str_starts_with($context['cancel_path'], 'http') ? $context['cancel_path'] : SITE_URL . $context['cancel_path'],
            'line_items' => $lineItems,
            'metadata' => $meta,
            'payment_intent_data' => [
                'application_fee_amount' => $applicationFeeAmount > 0 ? $applicationFeeAmount : null,
                'transfer_data' => [
                    'destination' => (string) $account['stripe_account_id'],
                ],
                'on_behalf_of' => (string) $account['stripe_account_id'],
                'metadata' => $meta,
            ],
        ]);

        if (!empty($context['booking']['id'])) {
            \db_execute('UPDATE payments SET stripe_checkout_session_id = ?, updated_at = NOW() WHERE booking_id = ? AND tenant_id = ? ORDER BY id DESC LIMIT 1', [$session->id, $context['booking']['id'], $tenant['id']]);
        }
        if (!empty($context['invoice']['id'])) {
            \db_execute('UPDATE payments SET stripe_checkout_session_id = ?, updated_at = NOW() WHERE invoice_id = ? AND tenant_id = ? ORDER BY id DESC LIMIT 1', [$session->id, $context['invoice']['id'], $tenant['id']]);
            \db_execute('UPDATE invoices SET payment_link_url = ?, updated_at = NOW() WHERE id = ?', [$session->url, $context['invoice']['id']]);
        }

        return (string) $session->url;
    }
}
