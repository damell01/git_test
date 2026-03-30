<?php
/**
 * Stripe Webhook Handler — Trash Panda Roll-Offs
 * POST /api/stripe-webhook.php
 * Verifies Stripe signature, handles checkout.session.completed.
 * No session, no output buffering.
 */

$_admin_root = dirname(__DIR__, 2) . '/admin';
require_once $_admin_root . '/config/config.php';
require_once INC_PATH . '/db.php';
require_once INC_PATH . '/helpers.php';

$autoload = $_admin_root . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(503);
    echo json_encode(['error' => 'Stripe SDK not installed.']);
    exit;
}
require_once $autoload;
require_once INC_PATH . '/stripe.php';

header('Content-Type: application/json');

$payload    = file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

if (empty($payload) || empty($sig_header)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing payload or signature.']);
    exit;
}

try {
    $event = stripe_verify_webhook($payload, $sig_header);
} catch (\Stripe\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Webhook signature verification failed.']);
    exit;
} catch (\Throwable $e) {
    http_response_code(400);
    echo json_encode(['error' => 'Webhook error.']);
    exit;
}

if ($event->type === 'checkout.session.completed') {
    $session    = $event->data->object;
    $session_id = $session->id ?? '';
    $payment_id = $session->payment_intent ?? '';

    if (!empty($session_id)) {
        $booking = db_fetch(
            'SELECT id, booking_number FROM bookings WHERE stripe_session_id = ? LIMIT 1',
            [$session_id]
        );

        if ($booking) {
            db_update('bookings', [
                'payment_status'    => 'paid',
                'booking_status'    => 'confirmed',
                'stripe_payment_id' => $payment_id ?: null,
                'updated_at'        => date('Y-m-d H:i:s'),
            ], 'id', (int)$booking['id']);
        }
    }
}

http_response_code(200);
echo json_encode(['received' => true]);
