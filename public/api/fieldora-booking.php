<?php

require_once dirname(__DIR__, 2) . '/admin/includes/bootstrap.php';

use TrashPanda\Fieldora\Services\BookingService;
use TrashPanda\Fieldora\Services\TenantService;

header('Content-Type: application/json');

try {
    $tenantSlug = trim((string) ($_POST['tenant_slug'] ?? ''));
    $tenant = TenantService::findBySlug($tenantSlug);
    if (!$tenant) {
        throw new RuntimeException('Tenant not found.');
    }

    $serviceIds = $_POST['service_ids'] ?? [];
    if (!is_array($serviceIds)) {
        $serviceIds = [$serviceIds];
    }

    $result = BookingService::createPublicBooking($tenant, [
        'service_ids' => $serviceIds,
        'customer_name' => $_POST['customer_name'] ?? '',
        'email' => $_POST['email'] ?? '',
        'phone' => $_POST['phone'] ?? '',
        'scheduled_date' => $_POST['scheduled_date'] ?? '',
        'start_time' => $_POST['start_time'] ?? '',
        'request_window' => $_POST['request_window'] ?? '',
        'address_line1' => $_POST['address_line1'] ?? '',
        'city' => $_POST['city'] ?? '',
        'state' => $_POST['state'] ?? '',
        'postal_code' => $_POST['postal_code'] ?? '',
        'payment_option' => $_POST['payment_option'] ?? 'deposit',
        'payment_method' => $_POST['payment_method'] ?? 'stripe',
        'notes' => $_POST['notes'] ?? '',
    ]);

    echo json_encode([
        'success' => true,
        'checkout_url' => $result['checkout_url'] ?? null,
        'booking_number' => $result['booking']['booking_number'] ?? null,
    ]);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
