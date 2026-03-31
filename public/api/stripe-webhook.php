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
        // Find ALL bookings linked to this session (multi-unit bookings share one session)
        $bookings = db_fetchall(
            'SELECT id, booking_number, customer_name, total_amount, customer_email, customer_phone, dumpster_id
               FROM bookings WHERE stripe_session_id = ?',
            [$session_id]
        );

        if (!empty($bookings)) {
            $total_paid = 0.0;
            foreach ($bookings as $booking) {
                db_update('bookings', [
                    'payment_status'    => 'paid',
                    'booking_status'    => 'confirmed',
                    'stripe_payment_id' => $payment_id ?: null,
                    'updated_at'        => date('Y-m-d H:i:s'),
                ], 'id', (int)$booking['id']);

                if (!empty($booking['dumpster_id'])) {
                    db_update('dumpsters', [
                        'status'     => 'reserved',
                        'updated_at' => date('Y-m-d H:i:s'),
                    ], 'id', (int)$booking['dumpster_id']);
                }

                $total_paid += (float)$booking['total_amount'];
            }

            // Push notification to admins
            $autoload_push = $_admin_root . '/vendor/autoload.php';
            if (file_exists($autoload_push)) {
                require_once $autoload_push;
            }
            if (file_exists(INC_PATH . '/push.php')) {
                require_once INC_PATH . '/push.php';

                $first    = $bookings[0];
                $cust     = $first['customer_name'] ?? 'Customer';
                $total    = '$' . number_format($total_paid, 2);
                $bk_label = count($bookings) === 1
                    ? ($first['booking_number'] ?? '')
                    : count($bookings) . ' bookings';
                $view_url = defined('APP_URL')
                    ? APP_URL . '/modules/bookings/index.php'
                    : '/admin/modules/bookings/index.php';

                push_notify_admins(
                    '💳 Payment Received — ' . $bk_label,
                    $cust . ' paid ' . $total . ' via Stripe',
                    $view_url
                );

                // Notify customer(s) via push
                foreach (array_unique(array_filter([
                    !empty($first['customer_email']) ? strtolower(trim($first['customer_email'])) : '',
                    !empty($first['customer_phone']) ? preg_replace('/\D/', '', $first['customer_phone']) : '',
                ])) as $id) {
                    push_notify_customer(
                        $id,
                        '✅ Payment Confirmed — ' . $bk_label,
                        'Your Stripe payment of ' . $total . ' has been received.'
                    );
                }
            }
        }
    }
}

http_response_code(200);
echo json_encode(['received' => true]);
