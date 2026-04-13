<?php

namespace TrashPanda\Fieldora\Services;

use RuntimeException;

class BookingService
{
    public static function tenantServices(int $tenantId): array
    {
        return \db_fetchall(
            'SELECT s.*, c.name AS category_name
             FROM services s
             LEFT JOIN service_categories c ON c.id = s.category_id
             WHERE s.tenant_id = ? AND s.is_active = 1
             ORDER BY s.sort_order ASC, s.name ASC',
            [$tenantId]
        );
    }

    public static function createPublicBooking(array $tenant, array $payload): array
    {
        $tenantId = (int) $tenant['id'];
        $serviceIds = array_values(array_unique(array_filter(array_map('intval', $payload['service_ids'] ?? []))));
        if ($serviceIds === []) {
            throw new RuntimeException('Choose at least one service.');
        }

        $customerName = trim((string) ($payload['customer_name'] ?? ''));
        if ($customerName === '') {
            throw new RuntimeException('Customer name is required.');
        }

        $scheduledDate = trim((string) ($payload['scheduled_date'] ?? ''));
        if ($scheduledDate === '') {
            throw new RuntimeException('A requested date is required.');
        }

        $requestedTime = trim((string) ($payload['start_time'] ?? ''));
        self::validateRequestedSlot($tenantId, $scheduledDate, $requestedTime);

        $services = \db_fetchall(
            'SELECT * FROM services WHERE tenant_id = ? AND is_active = 1 AND id IN (' . implode(',', array_fill(0, count($serviceIds), '?')) . ')',
            array_merge([$tenantId], $serviceIds)
        );
        if (count($services) !== count($serviceIds)) {
            throw new RuntimeException('One or more selected services are unavailable.');
        }

        $customerId = self::findOrCreateCustomer($tenantId, $payload);
        $bookingNumber = self::nextSequence('bookings', 'booking_number', 'BK');
        $publicToken = bin2hex(random_bytes(12));

        $subtotal = 0.0;
        $depositAmount = 0.0;
        foreach ($services as $service) {
            $subtotal += (float) $service['price'];
            if (($service['deposit_mode'] ?? '') === 'percent') {
                $depositAmount += ((float) $service['price']) * (((float) $service['deposit_value']) / 100);
            } elseif (($service['deposit_mode'] ?? '') === 'fixed') {
                $depositAmount += (float) $service['deposit_value'];
            }
        }

        $allowFullPayment = TenantService::setting($tenantId, 'allow_full_payment', '1') === '1';
        $paymentOption = (string) ($payload['payment_option'] ?? ($allowFullPayment ? 'full' : 'deposit'));
        if ($paymentOption === 'full' && !$allowFullPayment) {
            $paymentOption = 'deposit';
        }

        $requireDeposit = TenantService::setting($tenantId, 'require_deposit', '1') === '1';
        if (!$requireDeposit && $paymentOption === 'deposit' && $depositAmount <= 0) {
            $paymentOption = 'manual';
        }

        $chargeAmount = $paymentOption === 'full' ? $subtotal : ($depositAmount > 0 ? $depositAmount : ($requireDeposit ? $subtotal : 0.0));
        $instant = TenantService::setting($tenantId, 'allow_instant_booking', '0') === '1'
            || TenantService::setting($tenantId, 'booking_approval_mode', 'request') === 'instant';

        $paymentMethod = (string) ($payload['payment_method'] ?? 'stripe');
        if ($paymentMethod === 'stripe' && $chargeAmount > 0) {
            $account = ConnectService::status($tenantId);
            if (!$account || ($account['account_status'] ?? '') !== 'connected' || !(int) ($account['charges_enabled'] ?? 0)) {
                throw new RuntimeException('Online payments are not available for this business yet.');
            }
        }

        $bookingId = (int) \db_insert('bookings', [
            'tenant_id' => $tenantId,
            'booking_number' => $bookingNumber,
            'customer_id' => $customerId,
            'status' => $instant ? 'confirmed' : 'requested',
            'approval_mode' => $instant ? 'instant' : 'request',
            'scheduled_date' => $scheduledDate,
            'start_time' => $requestedTime !== '' ? $requestedTime : null,
            'end_time' => trim((string) ($payload['end_time'] ?? '')) ?: null,
            'request_window' => trim((string) ($payload['request_window'] ?? '')),
            'notes' => trim((string) ($payload['notes'] ?? '')),
            'subtotal' => $subtotal,
            'deposit_amount' => $depositAmount,
            'amount_due' => $chargeAmount,
            'total_amount' => $subtotal,
            'currency' => strtolower(TenantService::setting($tenantId, 'currency', 'usd')),
            'payment_state' => $chargeAmount > 0 ? 'pending' : 'manual',
            'payment_option' => in_array($paymentOption, ['deposit', 'full', 'manual'], true) ? $paymentOption : 'deposit',
            'source' => 'public',
            'public_token' => $publicToken,
            'confirmed_at' => $instant ? date('Y-m-d H:i:s') : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        foreach ($services as $service) {
            \db_insert('booking_items', [
                'booking_id' => $bookingId,
                'service_id' => $service['id'],
                'item_name' => $service['name'],
                'quantity' => 1,
                'unit_price' => $service['price'],
                'amount' => $service['price'],
                'duration_minutes' => $service['duration_minutes'],
                'deposit_mode' => $service['deposit_mode'],
                'deposit_value' => $service['deposit_value'],
            ]);
        }

        $jobId = (int) \db_insert('jobs', [
            'tenant_id' => $tenantId,
            'booking_id' => $bookingId,
            'customer_id' => $customerId,
            'job_number' => self::nextSequence('jobs', 'job_number', 'JOB'),
            'title' => $services[0]['name'] . (count($services) > 1 ? ' +' . (count($services) - 1) . ' more' : ''),
            'status' => $instant ? 'scheduled' : 'waiting',
            'scheduled_date' => $scheduledDate,
            'start_time' => $requestedTime !== '' ? $requestedTime : null,
            'end_time' => trim((string) ($payload['end_time'] ?? '')) ?: null,
            'address_line1' => trim((string) ($payload['address_line1'] ?? '')),
            'city' => trim((string) ($payload['city'] ?? '')),
            'state' => trim((string) ($payload['state'] ?? '')),
            'postal_code' => trim((string) ($payload['postal_code'] ?? '')),
            'notes' => trim((string) ($payload['notes'] ?? '')),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        $paymentId = null;
        if ($chargeAmount > 0 || $paymentMethod !== 'stripe') {
            $paymentId = (int) \db_insert('payments', [
                'tenant_id' => $tenantId,
                'booking_id' => $bookingId,
                'customer_id' => $customerId,
                'amount' => $chargeAmount,
                'currency' => strtolower(TenantService::setting($tenantId, 'currency', 'usd')),
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentMethod === 'stripe' && $chargeAmount > 0 ? 'pending' : 'completed',
                'payment_type' => $paymentOption === 'full' ? 'full' : ($paymentOption === 'deposit' ? 'deposit' : 'manual'),
                'idempotency_key' => 'booking:' . $bookingNumber,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
                'paid_at' => $paymentMethod === 'stripe' && $chargeAmount > 0 ? null : date('Y-m-d H:i:s'),
            ]);
        }

        $booking = \db_fetch('SELECT * FROM bookings WHERE id = ? LIMIT 1', [$bookingId]) ?: [];
        $items = \db_fetchall('SELECT * FROM booking_items WHERE booking_id = ?', [$bookingId]);

        $recipient = strtolower(trim((string) ($payload['email'] ?? '')));
        if ($recipient !== '') {
            NotificationService::queueTemplate($tenantId, 'booking_confirmation', 'email', $recipient, [
                'customer_name' => $customerName,
                'booking_number' => $bookingNumber,
                'amount' => number_format($chargeAmount, 2),
            ], [
                'booking_id' => $bookingId,
                'customer_id' => $customerId,
            ]);
        }

        if (tenant_has_feature_runtime($tenantId, 'sms_notifications') && trim((string) ($payload['phone'] ?? '')) !== '') {
            NotificationService::queueTemplate($tenantId, 'booking_confirmation', 'sms', trim((string) ($payload['phone'] ?? '')), [
                'customer_name' => $customerName,
                'booking_number' => $bookingNumber,
            ], [
                'booking_id' => $bookingId,
                'customer_id' => $customerId,
            ]);
        }

        WebhookService::queueEvent($tenantId, 'booking.created', ['booking_id' => $bookingId, 'job_id' => $jobId, 'payment_id' => $paymentId]);
        WebhookService::queueEvent($tenantId, 'job.created', ['job_id' => $jobId, 'booking_id' => $bookingId]);
        AutomationService::queueTrigger($tenantId, 'booking.created', 'booking', $bookingId, ['job_id' => $jobId]);

        $checkoutUrl = null;
        if ($paymentMethod === 'stripe' && $chargeAmount > 0) {
            $checkoutUrl = PaymentService::createBookingCheckout($tenant, $booking, $items);
        } else {
            \db_execute('UPDATE bookings SET payment_state = ? WHERE id = ?', ['manual', $bookingId]);
        }

        return [
            'booking' => $booking,
            'job_id' => $jobId,
            'checkout_url' => $checkoutUrl,
        ];
    }

    public static function validateRequestedSlot(int $tenantId, string $scheduledDate, string $startTime = '', int $excludeBookingId = 0): void
    {
        $date = \DateTimeImmutable::createFromFormat('Y-m-d', $scheduledDate);
        if (!$date) {
            throw new RuntimeException('Choose a valid booking date.');
        }

        $today = new \DateTimeImmutable('today');
        if ($date < $today) {
            throw new RuntimeException('Bookings cannot be made in the past.');
        }

        $blackout = \db_fetch('SELECT id FROM blackout_dates WHERE tenant_id = ? AND blackout_date = ? LIMIT 1', [$tenantId, $scheduledDate]);
        if ($blackout) {
            throw new RuntimeException('That date is unavailable.');
        }

        $weekday = (int) $date->format('w');
        $workingHours = \db_fetch('SELECT * FROM working_hours WHERE tenant_id = ? AND weekday = ? LIMIT 1', [$tenantId, $weekday]);
        if (!$workingHours || (int) ($workingHours['is_active'] ?? 0) !== 1) {
            throw new RuntimeException('That day is not available for booking.');
        }

        $minimumNoticeHours = (int) TenantService::setting($tenantId, 'minimum_notice_hours', '2');
        if ($minimumNoticeHours > 0) {
            $scheduledDateTime = new \DateTimeImmutable($scheduledDate . ' ' . ($startTime !== '' ? $startTime : '23:59:59'));
            $minimumAllowed = new \DateTimeImmutable('now +' . $minimumNoticeHours . ' hours');
            if ($scheduledDateTime < $minimumAllowed) {
                throw new RuntimeException('This booking does not meet the minimum notice requirement.');
            }
        }

        if ($startTime !== '') {
            if (($workingHours['start_time'] ?? '') !== null && $startTime < substr((string) $workingHours['start_time'], 0, 5)) {
                throw new RuntimeException('Selected time is before working hours.');
            }
            if (($workingHours['end_time'] ?? '') !== null && $startTime > substr((string) $workingHours['end_time'], 0, 5)) {
                throw new RuntimeException('Selected time is outside working hours.');
            }
        }

        $maxPerDay = (int) TenantService::setting($tenantId, 'max_bookings_per_day', '0');
        if ($maxPerDay > 0) {
            $count = \db_fetch(
                'SELECT COUNT(*) AS cnt FROM bookings WHERE tenant_id = ? AND scheduled_date = ? AND deleted_at IS NULL AND status != "canceled" AND id != ?',
                [$tenantId, $scheduledDate, $excludeBookingId]
            );
            if ((int) ($count['cnt'] ?? 0) >= $maxPerDay) {
                throw new RuntimeException('This date is fully booked.');
            }
        }
    }

    public static function bookingRulesSummary(int $tenantId): array
    {
        $hours = \db_fetchall('SELECT * FROM working_hours WHERE tenant_id = ? ORDER BY weekday ASC', [$tenantId]);
        $labels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        $days = [];
        foreach ($hours as $hour) {
            if ((int) ($hour['is_active'] ?? 0) !== 1) {
                continue;
            }
            $days[] = $labels[(int) $hour['weekday']] . ' ' . substr((string) $hour['start_time'], 0, 5) . '-' . substr((string) $hour['end_time'], 0, 5);
        }

        return [
            'minimum_notice_hours' => (int) TenantService::setting($tenantId, 'minimum_notice_hours', '2'),
            'working_hours_text' => $days !== [] ? implode(', ', $days) : 'Contact us for availability',
            'approval_mode' => TenantService::setting($tenantId, 'booking_approval_mode', 'request'),
        ];
    }

    private static function findOrCreateCustomer(int $tenantId, array $payload): int
    {
        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $phone = trim((string) ($payload['phone'] ?? ''));

        $customer = null;
        if ($email !== '') {
            $customer = \db_fetch('SELECT * FROM customers WHERE tenant_id = ? AND email = ? AND deleted_at IS NULL LIMIT 1', [$tenantId, $email]);
        }
        if (!$customer && $phone !== '') {
            $customer = \db_fetch('SELECT * FROM customers WHERE tenant_id = ? AND phone = ? AND deleted_at IS NULL LIMIT 1', [$tenantId, $phone]);
        }

        $name = trim((string) ($payload['customer_name'] ?? ''));
        $parts = preg_split('/\s+/', $name, 2) ?: [];
        $firstName = $parts[0] ?? $name;
        $lastName = $parts[1] ?? '';

        if ($customer) {
            \db_execute(
                'UPDATE customers SET first_name = ?, last_name = ?, phone = ?, address_line1 = ?, city = ?, state = ?, postal_code = ?, updated_at = NOW() WHERE id = ?',
                [$firstName, $lastName, $phone, trim((string) ($payload['address_line1'] ?? '')), trim((string) ($payload['city'] ?? '')), trim((string) ($payload['state'] ?? '')), trim((string) ($payload['postal_code'] ?? '')), $customer['id']]
            );
            return (int) $customer['id'];
        }

        return (int) \db_insert('customers', [
            'tenant_id' => $tenantId,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'company' => trim((string) ($payload['company'] ?? '')),
            'email' => $email,
            'phone' => $phone,
            'address_line1' => trim((string) ($payload['address_line1'] ?? '')),
            'city' => trim((string) ($payload['city'] ?? '')),
            'state' => trim((string) ($payload['state'] ?? '')),
            'postal_code' => trim((string) ($payload['postal_code'] ?? '')),
            'notes' => trim((string) ($payload['notes'] ?? '')),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private static function nextSequence(string $table, string $column, string $prefix): string
    {
        $row = \db_fetch("SELECT COUNT(*) AS cnt FROM `{$table}`");
        $next = (int) ($row['cnt'] ?? 0) + 1;
        return $prefix . '-' . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }
}
