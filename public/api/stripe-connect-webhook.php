<?php

require_once dirname(__DIR__, 2) . '/admin/includes/bootstrap.php';

use TrashPanda\Fieldora\Services\ConnectService;
use TrashPanda\Fieldora\Services\PaymentService;

header('Content-Type: application/json');

try {
    if (!class_exists(\Stripe\Webhook::class) || STRIPE_CONNECT_WEBHOOK_SECRET === '') {
        throw new RuntimeException('Stripe webhook is not configured.');
    }

    $payload = file_get_contents('php://input') ?: '';
    $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    $event = \Stripe\Webhook::constructEvent($payload, $signature, STRIPE_CONNECT_WEBHOOK_SECRET);

    $accountId = (string) ($event->account ?? '');
    $tenantRecord = $accountId !== '' ? db_fetch('SELECT * FROM tenant_payment_accounts WHERE stripe_account_id = ? LIMIT 1', [$accountId]) : null;
    $tenantId = (int) ($tenantRecord['tenant_id'] ?? 0) ?: null;

    $existing = db_fetch('SELECT id FROM processed_webhooks WHERE provider = ? AND event_id = ? LIMIT 1', ['stripe_connect', $event->id]);
    if ($existing) {
        echo json_encode(['received' => true, 'duplicate' => true]);
        exit;
    }

    db_insert('processed_webhooks', [
        'provider' => 'stripe_connect',
        'event_id' => $event->id,
        'tenant_id' => $tenantId,
        'processed_at' => date('Y-m-d H:i:s'),
    ]);

    if ($event->type === 'account.updated' && $tenantId) {
        ConnectService::syncAccount($tenantId, $accountId);
    }

    if ($event->type === 'checkout.session.completed') {
        $session = $event->data->object;
        PaymentService::reconcileSuccessfulCheckout((string) $session->id, [
            'payment_intent' => $session->payment_intent ?? null,
            'amount_total' => $session->amount_total ?? 0,
        ]);
        if ($tenantId) {
            PaymentService::logEvent($tenantId, null, 'checkout.session.completed', ['id' => $session->id, 'account' => $accountId]);
        }
    }

    if ($event->type === 'payment_intent.payment_failed') {
        $intent = $event->data->object;
        if ($tenantId) {
            PaymentService::markCheckoutFailed((string) ($intent->id ?? ''), [
                'id' => $intent->id ?? null,
                'account' => $accountId,
                'metadata' => (array) ($intent->metadata ?? []),
                'last_payment_error' => $intent->last_payment_error->message ?? null,
            ]);
        }
    }

    echo json_encode(['received' => true]);
} catch (Throwable $e) {
    if (function_exists('db_insert') && fieldora_table_exists('error_logs')) {
        db_insert('error_logs', [
            'tenant_id' => null,
            'level' => 'error',
            'message' => 'Stripe webhook processing failed',
            'context_json' => json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_SLASHES),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    http_response_code(400);
    echo json_encode(['received' => false, 'error' => $e->getMessage()]);
}
