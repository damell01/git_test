<?php
/**
 * Stripe Webhook Handler — Trash Panda Roll-Offs
 * POST /api/stripe-webhook.php
 * Verifies Stripe signature, handles checkout.session.completed and
 * checkout.session.expired events.
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
require_once INC_PATH . '/mailer.php';

// Load push helper (best-effort)
if (file_exists(INC_PATH . '/push.php')) {
    require_once INC_PATH . '/push.php';
}

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

// ── checkout.session.completed ────────────────────────────────────────────────
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

            // Send booking confirmation emails (customer + admin)
            foreach ($bookings as $booking) {
                try {
                    $full_booking = db_fetch('SELECT * FROM bookings WHERE id = ? LIMIT 1', [(int)$booking['id']]);
                    if ($full_booking) {
                        notify_booking_confirmed($full_booking);
                    }
                } catch (\Throwable $e) {
                    error_log('[Webhook] notify_booking_confirmed failed for booking ' . $booking['id'] . ': ' . $e->getMessage());
                }
            }

            // Push notification to admins
            if (function_exists('push_notify_admins')) {
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

// ── checkout.session.expired ──────────────────────────────────────────────────
// When a customer abandons the Stripe checkout and the session expires,
// cancel the pending bookings and release the reserved dumpsters so
// they are available for other customers.
if ($event->type === 'checkout.session.expired') {
    $session    = $event->data->object;
    $session_id = $session->id ?? '';

    if (!empty($session_id)) {
        $bookings = db_fetchall(
            "SELECT id, booking_number, dumpster_id
               FROM bookings
              WHERE stripe_session_id = ?
                AND booking_status IN ('pending','confirmed')
                AND payment_status IN ('pending','unpaid')",
            [$session_id]
        );

        foreach ($bookings as $booking) {
            db_update('bookings', [
                'booking_status' => 'canceled',
                'payment_status' => 'canceled',
                'updated_at'     => date('Y-m-d H:i:s'),
            ], 'id', (int)$booking['id']);

            if (!empty($booking['dumpster_id'])) {
                // Only restore to available if still in the 'reserved' state we set
                db_execute(
                    "UPDATE dumpsters SET status = 'available', updated_at = ? WHERE id = ? AND status = 'reserved'",
                    [date('Y-m-d H:i:s'), (int)$booking['dumpster_id']]
                );
            }

            error_log('[Webhook] Canceled expired Stripe session booking: ' . ($booking['booking_number'] ?? $booking['id']));
        }
    }
}

http_response_code(200);
echo json_encode(['received' => true]);
