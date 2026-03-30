<?php
/**
 * Public API: Look up bookings by email or phone number
 * POST /api/lookup-bookings.php
 */
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

$_admin_root = dirname(__DIR__, 2) . '/admin';
require_once $_admin_root . '/config/config.php';
require_once INC_PATH . '/db.php';
require_once INC_PATH . '/helpers.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}

$raw  = file_get_contents('php://input');
$data = json_decode($raw ?: '', true);
if (!is_array($data)) { $data = $_POST; }

$identifier = trim($data['identifier'] ?? '');

if (empty($identifier)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Please enter your email or phone number.']);
    exit;
}

// Simple rate-limit: max 10 lookups per IP per hour via activity_log
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
try {
    $recent = db_fetch(
        "SELECT COUNT(*) AS cnt FROM activity_log
          WHERE action = 'booking_lookup' AND ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
        [$ip]
    );
    if ((int)($recent['cnt'] ?? 0) >= 10) {
        http_response_code(429);
        echo json_encode(['success' => false, 'error' => 'Too many lookups. Please try again later.']);
        exit;
    }
    db_execute(
        "INSERT INTO activity_log (user_id, action, description, entity_type, ip_address, created_at)
         VALUES (0, 'booking_lookup', ?, 'booking', ?, NOW())",
        [substr($identifier, 0, 50), $ip]
    );
} catch (\Throwable $e) {
    // Non-fatal; continue
}

// Determine if identifier is email or phone
$is_email = filter_var($identifier, FILTER_VALIDATE_EMAIL) !== false;
$digits   = preg_replace('/\D/', '', $identifier);

if (!$is_email && strlen($digits) < 7) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Please enter a valid email address or phone number.']);
    exit;
}

try {
    if ($is_email) {
        $bookings = db_fetchall(
            "SELECT b.id, b.booking_number, b.customer_name, b.customer_email, b.customer_phone,
                    b.unit_code, b.unit_size, b.rental_start, b.rental_end, b.rental_days,
                    b.total_amount, b.payment_method, b.payment_status, b.booking_status,
                    b.customer_address, b.customer_city, b.notes, b.created_at
               FROM bookings b
              WHERE LOWER(TRIM(b.customer_email)) = ?
              ORDER BY b.rental_start DESC
              LIMIT 20",
            [strtolower($identifier)]
        );
    } else {
        // Match on digits-only phone
        $bookings = db_fetchall(
            "SELECT b.id, b.booking_number, b.customer_name, b.customer_email, b.customer_phone,
                    b.unit_code, b.unit_size, b.rental_start, b.rental_end, b.rental_days,
                    b.total_amount, b.payment_method, b.payment_status, b.booking_status,
                    b.customer_address, b.customer_city, b.notes, b.created_at
               FROM bookings b
              WHERE REGEXP_REPLACE(b.customer_phone, '[^0-9]', '') LIKE ?
              ORDER BY b.rental_start DESC
              LIMIT 20",
            ['%' . $digits . '%']
        );
    }
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database error. Please try again.']);
    exit;
}

// Format for output
$result = [];
foreach ($bookings as $b) {
    $result[] = [
        'booking_number' => $b['booking_number'],
        'unit'           => trim(($b['unit_code'] ?? '') . ' — ' . ($b['unit_size'] ?? ''), ' —'),
        'rental_start'   => $b['rental_start'],
        'rental_end'     => $b['rental_end'],
        'rental_days'    => (int)$b['rental_days'],
        'total_amount'   => (float)$b['total_amount'],
        'payment_method' => $b['payment_method'],
        'payment_status' => $b['payment_status'],
        'booking_status' => $b['booking_status'],
        'address'        => trim(($b['customer_address'] ?? '') . ($b['customer_city'] ? ', ' . $b['customer_city'] : ''), ', '),
        'customer_name'  => $b['customer_name'],
        'created_at'     => $b['created_at'],
    ];
}

echo json_encode(['success' => true, 'bookings' => $result, 'count' => count($result)]);
