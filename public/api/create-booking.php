<?php
/**
 * Public API: Create Booking
 * POST /api/create-booking.php
 * Accepts JSON or form POST.
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

$_admin_root = dirname(__DIR__, 2) . '/admin';
require_once $_admin_root . '/config/config.php';
require_once INC_PATH . '/db.php';
require_once INC_PATH . '/helpers.php';

function api_error(string $message, int $status = 400): void
{
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    api_error('Method not allowed.', 405);
}

// Parse input (JSON or form POST)
$raw  = file_get_contents('php://input');
$data = [];
if (!empty($raw)) {
    $decoded = json_decode($raw, true);
    if (is_array($decoded)) {
        $data = $decoded;
    }
}
if (empty($data)) {
    $data = $_POST;
}

// Extract and sanitise inputs
$unit_id          = (int)($data['unit_id']          ?? 0);
$rental_start     = trim($data['rental_start']       ?? '');
$rental_end       = trim($data['rental_end']         ?? '');
$customer_name    = trim($data['customer_name']      ?? '');
$customer_phone   = trim($data['customer_phone']     ?? '');
$customer_email   = trim($data['customer_email']     ?? '');
$customer_address = trim($data['customer_address']   ?? '');
$customer_city    = trim($data['customer_city']      ?? '');
$payment_method   = trim($data['payment_method']     ?? 'stripe');
$notes            = trim($data['notes']              ?? '');
$terms_accepted   = !empty($data['terms_accepted']);

// Validation
if ($unit_id <= 0) {
    api_error('Please select a unit.');
}
if ($customer_name === '') {
    api_error('Customer name is required.');
}
if (!$terms_accepted) {
    api_error('You must accept the terms and conditions.');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $rental_start) || !strtotime($rental_start)) {
    api_error('Invalid start date.');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $rental_end) || !strtotime($rental_end)) {
    api_error('Invalid end date.');
}
if ($rental_end <= $rental_start) {
    api_error('End date must be after start date.');
}
if (!in_array($payment_method, ['stripe', 'cash', 'check'], true)) {
    api_error('Invalid payment method.');
}

// Unit validation
$unit = db_fetch(
    "SELECT id, unit_code, type, size, daily_rate, active, status
     FROM dumpsters WHERE id = ? LIMIT 1",
    [$unit_id]
);
if (!$unit) {
    api_error('Unit not found.');
}
if (!$unit['active']) {
    api_error('This unit is not available for booking.');
}
if ($unit['status'] === 'maintenance') {
    api_error('This unit is currently under maintenance.');
}

// Overlap check: bookings
$overlap = db_fetch(
    "SELECT COUNT(*) AS cnt FROM bookings
     WHERE dumpster_id = ?
       AND booking_status != 'canceled'
       AND rental_start <= ? AND rental_end >= ?",
    [$unit_id, $rental_end, $rental_start]
);
if ((int)($overlap['cnt'] ?? 0) > 0) {
    api_error('This unit is already booked for the selected dates. Please choose different dates.');
}

// Overlap check: inventory blocks
$block = db_fetch(
    "SELECT COUNT(*) AS cnt FROM inventory_blocks
     WHERE dumpster_id = ?
       AND block_start <= ? AND block_end >= ?",
    [$unit_id, $rental_end, $rental_start]
);
if ((int)($block['cnt'] ?? 0) > 0) {
    api_error('This unit is blocked for the selected dates. Please choose different dates.');
}

// Calculate totals
$days       = max(1, (int)((strtotime($rental_end) - strtotime($rental_start)) / 86400));
$daily_rate = (float)$unit['daily_rate'];
$total      = round($daily_rate * $days, 2);

// Generate booking number
$booking_number = next_number('BK', 'bookings', 'booking_number');

// Payment status
$pay_status_map = [
    'stripe' => 'pending',
    'cash'   => 'pending_cash',
    'check'  => 'pending_check',
];
$payment_status = $pay_status_map[$payment_method] ?? 'pending';

// Initial booking_status: pending for stripe (awaiting payment), confirmed for cash/check
$booking_status = ($payment_method === 'stripe') ? 'pending' : 'confirmed';

// Insert booking
$new_id = db_insert('bookings', [
    'booking_number'   => $booking_number,
    'customer_name'    => $customer_name,
    'customer_phone'   => $customer_phone ?: null,
    'customer_email'   => $customer_email ?: null,
    'customer_address' => $customer_address ?: null,
    'customer_city'    => $customer_city ?: null,
    'dumpster_id'      => $unit_id,
    'unit_code'        => $unit['unit_code'],
    'unit_type'        => $unit['type'],
    'unit_size'        => $unit['size'],
    'rental_start'     => $rental_start,
    'rental_end'       => $rental_end,
    'rental_days'      => $days,
    'daily_rate'       => $daily_rate,
    'total_amount'     => $total,
    'payment_method'   => $payment_method,
    'payment_status'   => $payment_status,
    'booking_status'   => $booking_status,
    'notes'            => $notes ?: null,
    'created_at'       => date('Y-m-d H:i:s'),
    'updated_at'       => date('Y-m-d H:i:s'),
]);

if (!$new_id) {
    api_error('Failed to create booking. Please try again.', 500);
}

// Token for success page (prevents enumeration)
$token = hash_hmac('sha256', (string)$new_id, get_setting('stripe_secret_key', 'booking-token-secret'));

// Stripe checkout
if ($payment_method === 'stripe') {
    $autoload = $_admin_root . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        // Stripe not installed — fall back to cash-style confirmation
        db_update('bookings', ['payment_method' => 'cash', 'payment_status' => 'pending_cash', 'booking_status' => 'confirmed', 'updated_at' => date('Y-m-d H:i:s')], 'id', (int)$new_id);
        $token = hash_hmac('sha256', (string)$new_id, get_setting('stripe_secret_key', 'booking-token-secret'));
        http_response_code(200);
        echo json_encode(['success' => true, 'redirect' => '/book-success.php?id=' . (int)$new_id . '&token=' . urlencode($token)]);
        exit;
    }

    require_once $autoload;
    require_once INC_PATH . '/stripe.php';

    try {
        $scheme       = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host         = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $success_url  = $scheme . '://' . $host . '/book-success.php?id=' . (int)$new_id . '&token=' . urlencode($token);
        $cancel_url   = $scheme . '://' . $host . '/book-cancel.php';

        $booking_row = db_fetch('SELECT * FROM bookings WHERE id = ? LIMIT 1', [(int)$new_id]);
        $session     = stripe_create_checkout($booking_row, $success_url, $cancel_url);

        // Save session ID
        db_update('bookings', [
            'stripe_session_id' => $session->id,
            'updated_at'        => date('Y-m-d H:i:s'),
        ], 'id', (int)$new_id);

        http_response_code(200);
        echo json_encode(['success' => true, 'checkout_url' => $session->url]);
        exit;

    } catch (\Throwable $e) {
        // Stripe error — still created booking, return generic confirmation
        http_response_code(200);
        echo json_encode([
            'success'  => true,
            'redirect' => '/book-success.php?id=' . (int)$new_id . '&token=' . urlencode($token),
        ]);
        exit;
    }
}

// Cash / check
http_response_code(200);
echo json_encode([
    'success'  => true,
    'redirect' => '/book-success.php?id=' . (int)$new_id . '&token=' . urlencode($token),
]);
