<?php
/**
 * Public API: Create Booking
 * POST /api/create-booking.php
 * Accepts JSON or form POST.
 * Supports single unit (unit_id) or multiple units (unit_ids array).
 */

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// Prevent PHP notices/warnings from corrupting the JSON response while still logging them
@ini_set('display_errors', '0');
@ini_set('log_errors',     '1');
error_reporting(E_ALL);

$_admin_root = dirname(__DIR__, 2) . '/admin';

// Top-level catch: any uncaught exception returns a clean JSON error
// instead of an HTML error page (which causes "Network error" on the client).
set_exception_handler(function (\Throwable $e) {
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: application/json');
    }
    echo json_encode(['success' => false, 'error' => 'Server error. Please try again.']);
    error_log('[create-booking.php] Uncaught: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    exit;
});

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

// Extract and sanitise common inputs
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

// Support both unit_ids (array) and unit_id (single)
if (!empty($data['unit_ids']) && is_array($data['unit_ids'])) {
    $unit_ids = array_map('intval', $data['unit_ids']);
    $unit_ids = array_filter($unit_ids, fn($id) => $id > 0);
    $unit_ids = array_values($unit_ids);
} elseif (!empty($data['unit_ids']) && is_string($data['unit_ids'])) {
    // comma-separated string fallback
    $unit_ids = array_filter(array_map('intval', explode(',', $data['unit_ids'])), fn($id) => $id > 0);
    $unit_ids = array_values($unit_ids);
} else {
    $single = (int)($data['unit_id'] ?? 0);
    $unit_ids = $single > 0 ? [$single] : [];
}

// Validation
if (empty($unit_ids)) {
    api_error('Please select at least one unit.');
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

// Calculate days once (shared across all units)
$d1   = new \DateTime($rental_start);
$d2   = new \DateTime($rental_end);
$days = max(1, (int)$d1->diff($d2)->days);

// Payment / booking status maps
$pay_status_map = [
    'stripe' => 'pending',
    'cash'   => 'pending_cash',
    'check'  => 'pending_check',
];
$payment_status = $pay_status_map[$payment_method] ?? 'pending';
$booking_status = ($payment_method === 'stripe') ? 'pending' : 'confirmed';

// Validate each unit, check availability, collect booking data
$units_data = [];   // validated unit rows
$grand_total = 0.0;

foreach ($unit_ids as $unit_id) {
    $unit = db_fetch(
        "SELECT id, unit_code, type, size, daily_rate, active, status
         FROM dumpsters WHERE id = ? LIMIT 1",
        [$unit_id]
    );
    if (!$unit) {
        api_error('Unit #' . $unit_id . ' not found.');
    }
    if (!$unit['active']) {
        api_error('Unit ' . $unit['unit_code'] . ' is not available for booking.');
    }
    if ($unit['status'] === 'maintenance') {
        api_error('Unit ' . $unit['unit_code'] . ' is currently under maintenance.');
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
        api_error('Unit ' . $unit['unit_code'] . ' is already booked for the selected dates. Please choose different dates or a different unit.');
    }

    // Overlap check: inventory blocks
    $block = db_fetch(
        "SELECT COUNT(*) AS cnt FROM inventory_blocks
         WHERE dumpster_id = ?
           AND block_start <= ? AND block_end >= ?",
        [$unit_id, $rental_end, $rental_start]
    );
    if ((int)($block['cnt'] ?? 0) > 0) {
        api_error('Unit ' . $unit['unit_code'] . ' is blocked for the selected dates. Please choose different dates.');
    }

    $daily_rate   = (float)$unit['daily_rate'];
    $unit_total   = round($daily_rate * $days, 2);
    $grand_total += $unit_total;

    $units_data[] = [
        'unit'        => $unit,
        'daily_rate'  => $daily_rate,
        'unit_total'  => $unit_total,
    ];
}

// Load push helper (best-effort)
$_push_autoload = $_admin_root . '/vendor/autoload.php';
if (file_exists($_push_autoload)) {
    require_once $_push_autoload;
}
$push_loaded = file_exists(INC_PATH . '/push.php');
if ($push_loaded) {
    require_once INC_PATH . '/push.php';
}
unset($_push_autoload);

// Generate a booking_group_id when multiple units are booked together
$booking_group_id = count($unit_ids) > 1 ? bin2hex(random_bytes(8)) : null;

// Create one booking record per unit
$new_ids        = [];
$booking_numbers = [];

foreach ($units_data as $ud) {
    $unit           = $ud['unit'];
    $booking_number = next_number('BK', 'bookings', 'booking_number');

    $row_data = [
        'booking_number'   => $booking_number,
        'customer_name'    => $customer_name,
        'customer_phone'   => $customer_phone ?: null,
        'customer_email'   => $customer_email ?: null,
        'customer_address' => $customer_address ?: null,
        'customer_city'    => $customer_city ?: null,
        'dumpster_id'      => (int)$unit['id'],
        'unit_code'        => $unit['unit_code'],
        'unit_type'        => $unit['type'],
        'unit_size'        => $unit['size'],
        'rental_start'     => $rental_start,
        'rental_end'       => $rental_end,
        'rental_days'      => $days,
        'daily_rate'       => $ud['daily_rate'],
        'total_amount'     => $ud['unit_total'],
        'payment_method'   => $payment_method,
        'payment_status'   => $payment_status,
        'booking_status'   => $booking_status,
        'notes'            => $notes ?: null,
        'created_at'       => date('Y-m-d H:i:s'),
        'updated_at'       => date('Y-m-d H:i:s'),
    ];
    if ($booking_group_id !== null) {
        $row_data['booking_group_id'] = $booking_group_id;
    }

    $new_id = db_insert('bookings', $row_data);

    if (!$new_id) {
        api_error('Failed to create booking for unit ' . $unit['unit_code'] . '. Please try again.', 500);
    }

    // Mark dumpster as reserved immediately (prevents double-booking regardless of payment state)
    db_update('dumpsters', [
        'status'     => 'reserved',
        'updated_at' => date('Y-m-d H:i:s'),
    ], 'id', (int)$unit['id']);

    $new_ids[]        = (int)$new_id;
    $booking_numbers[] = $booking_number;
}

// Push notification to admins for new booking(s) (best-effort)
if ($push_loaded) {
    $pm_label  = ['stripe' => 'Card', 'cash' => 'Cash', 'check' => 'Check'][$payment_method] ?? $payment_method;
    $view_url  = defined('APP_URL') ? APP_URL . '/modules/bookings/index.php' : '/admin/modules/bookings/index.php';
    $bk_label  = count($booking_numbers) === 1
        ? $booking_numbers[0]
        : count($booking_numbers) . ' bookings (' . implode(', ', $booking_numbers) . ')';
    push_notify_admins(
        '📦 New Booking — ' . $bk_label,
        $customer_name . ' · $' . number_format($grand_total, 2) . ' (' . $pm_label . ')',
        $view_url
    );

    // Notify customer immediately for cash/check bookings (Stripe bookings get notified via webhook)
    if ($payment_method !== 'stripe') {
        $cust_push_ids = array_unique(array_filter([
            !empty($customer_email) ? strtolower(trim($customer_email)) : '',
            !empty($customer_phone) ? preg_replace('/\D/', '', $customer_phone) : '',
        ]));
        $bk_label_short = count($booking_numbers) === 1
            ? $booking_numbers[0]
            : count($booking_numbers) . ' units';
        foreach ($cust_push_ids as $cid) {
            push_notify_customer(
                $cid,
                '📦 Booking Confirmed — ' . $bk_label_short,
                'Your ' . $pm_label . ' booking for $' . number_format($grand_total, 2) . ' is confirmed.'
            );
        }
    }
}

// Build token and success URL
// Use comma-joined IDs as the token base so all bookings are covered
$ids_str = implode(',', $new_ids);
$token   = hash_hmac('sha256', $ids_str, get_setting('stripe_secret_key', 'booking-token-secret'));
$first_id = $new_ids[0];

// Stripe checkout
if ($payment_method === 'stripe') {
    $autoload = $_admin_root . '/vendor/autoload.php';
    if (!file_exists($autoload)) {
        // Stripe SDK not installed — fall back to cash confirmation
        error_log('[Booking ' . $ids_str . '] Stripe SDK not found. Falling back to cash payment. Run `composer install` in the admin directory.');
        foreach ($new_ids as $bid) {
            db_update('bookings', [
                'payment_method' => 'cash',
                'payment_status' => 'pending_cash',
                'booking_status' => 'confirmed',
                'updated_at'     => date('Y-m-d H:i:s'),
            ], 'id', $bid);
        }
        http_response_code(200);
        echo json_encode(['success' => true, 'redirect' => '/book-success.php?ids=' . urlencode($ids_str) . '&token=' . urlencode($token)]);
        exit;
    }

    require_once $autoload;
    require_once INC_PATH . '/stripe.php';

    try {
        $scheme      = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host        = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $success_url = $scheme . '://' . $host . '/book-success.php?ids=' . urlencode($ids_str) . '&token=' . urlencode($token);
        $cancel_url  = $scheme . '://' . $host . '/book-cancel.php';

        // Fetch all booking rows for the Stripe session
        $booking_rows = [];
        foreach ($new_ids as $bid) {
            $row = db_fetch('SELECT * FROM bookings WHERE id = ? LIMIT 1', [$bid]);
            if ($row) $booking_rows[] = $row;
        }

        $session = count($booking_rows) === 1
            ? stripe_create_checkout($booking_rows[0], $success_url, $cancel_url)
            : stripe_create_multi_checkout($booking_rows, $success_url, $cancel_url);

        // Save session ID to all bookings
        foreach ($new_ids as $bid) {
            db_update('bookings', [
                'stripe_session_id' => $session->id,
                'updated_at'        => date('Y-m-d H:i:s'),
            ], 'id', $bid);
        }

        http_response_code(200);
        echo json_encode(['success' => true, 'checkout_url' => $session->url]);
        exit;

    } catch (\Throwable $e) {
        error_log('[Booking ' . $ids_str . '] Stripe Checkout error: ' . $e->getMessage());
        http_response_code(200);
        echo json_encode([
            'success'  => true,
            'redirect' => '/book-success.php?ids=' . urlencode($ids_str) . '&token=' . urlencode($token),
        ]);
        exit;
    }
}

// Cash / check — all dumpsters already marked reserved above
http_response_code(200);
echo json_encode([
    'success'  => true,
    'redirect' => '/book-success.php?ids=' . urlencode($ids_str) . '&token=' . urlencode($token),
]);
