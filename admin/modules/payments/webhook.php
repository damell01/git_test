<?php
/**
 * Stripe Webhook Handler
 * Trash Panda Roll-Offs
 *
 * This file must NOT include bootstrap.php (no session, no auth).
 * It's called directly by Stripe with a POST request.
 */

// Minimal bootstrap: only config and db
require_once dirname(__DIR__, 2) . '/config/config.php';
require_once INC_PATH . '/db.php';
require_once INC_PATH . '/stripe.php';

// Read raw body
$payload    = file_get_contents('php://input');
$sig_header = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';

// Verify signature
$event = stripe_construct_webhook_event($payload, $sig_header, STRIPE_WEBHOOK_SECRET);

if (isset($event['error'])) {
    http_response_code(400);
    echo 'Webhook Error: ' . $event['error'];
    exit;
}

$event_type = $event['type'] ?? '';
$data_obj   = $event['data']['object'] ?? [];

switch ($event_type) {

    case 'payment_intent.succeeded':
        _handle_pi_succeeded($data_obj);
        break;

    case 'payment_intent.payment_failed':
        _handle_pi_failed($data_obj);
        break;

    case 'charge.refunded':
        _handle_charge_refunded($data_obj);
        break;

    default:
        // Unknown event — still respond 200
        break;
}

http_response_code(200);
echo 'OK';
exit;

// ─────────────────────────────────────────────────────────────────────────────

function _handle_pi_succeeded(array $pi): void
{
    $pi_id     = $pi['id']             ?? '';
    $charge_id = $pi['latest_charge']  ?? null;

    if (empty($pi_id)) {
        return;
    }

    $pay = db_fetch(
        "SELECT * FROM payments WHERE stripe_payment_intent_id = ? LIMIT 1",
        [$pi_id]
    );

    if (!$pay) {
        return;
    }

    // Update payment to paid
    db_execute(
        "UPDATE payments SET status = 'paid', stripe_charge_id = ?, paid_at = NOW() WHERE id = ?",
        [$charge_id, $pay['id']]
    );

    // Update invoice
    if ($pay['invoice_id']) {
        $inv = db_fetch('SELECT * FROM invoices WHERE id = ? LIMIT 1', [$pay['invoice_id']]);
        if ($inv) {
            $new_paid   = (float)$inv['amount_paid'] + (float)$pay['amount'];
            $new_status = $new_paid >= (float)$inv['amount'] ? 'paid' : 'partial';
            db_execute(
                'UPDATE invoices SET amount_paid = ?, status = ?, updated_at = NOW() WHERE id = ?',
                [$new_paid, $new_status, $inv['id']]
            );
        }
    }

    // Log
    db_execute(
        "INSERT INTO activity_log (user_id, action, description, entity_type, entity_id, ip_address, created_at)
         VALUES (0, 'payment', ?, 'payment', ?, '', NOW())",
        ['Stripe webhook: payment_intent.succeeded for PI ' . $pi_id, $pay['id']]
    );
}

function _handle_pi_failed(array $pi): void
{
    $pi_id = $pi['id'] ?? '';

    if (empty($pi_id)) {
        return;
    }

    $pay = db_fetch(
        "SELECT * FROM payments WHERE stripe_payment_intent_id = ? LIMIT 1",
        [$pi_id]
    );

    if (!$pay) {
        return;
    }

    db_execute(
        "UPDATE payments SET status = 'failed' WHERE id = ?",
        [$pay['id']]
    );

    db_execute(
        "INSERT INTO activity_log (user_id, action, description, entity_type, entity_id, ip_address, created_at)
         VALUES (0, 'payment_failed', ?, 'payment', ?, '', NOW())",
        ['Stripe webhook: payment_intent.payment_failed for PI ' . $pi_id, $pay['id']]
    );
}

function _handle_charge_refunded(array $charge): void
{
    $charge_id = $charge['id'] ?? '';

    if (empty($charge_id)) {
        return;
    }

    $pay = db_fetch(
        "SELECT * FROM payments WHERE stripe_charge_id = ? LIMIT 1",
        [$charge_id]
    );

    if (!$pay) {
        return;
    }

    db_execute(
        "UPDATE payments SET status = 'refunded' WHERE id = ?",
        [$pay['id']]
    );

    db_execute(
        "INSERT INTO activity_log (user_id, action, description, entity_type, entity_id, ip_address, created_at)
         VALUES (0, 'refund', ?, 'payment', ?, '', NOW())",
        ['Stripe webhook: charge.refunded for charge ' . $charge_id, $pay['id']]
    );
}
