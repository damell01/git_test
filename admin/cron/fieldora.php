<?php

require_once dirname(__DIR__) . '/includes/bootstrap.php';

use TrashPanda\Fieldora\Services\NotificationService;
use TrashPanda\Fieldora\Services\WebhookService;

if (($_GET['key'] ?? '') !== CRON_KEY && PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Invalid cron key');
}

$tenantSetting = static function (int $tenantId, string $key, string $default = ''): string {
    $row = db_fetch('SELECT setting_value FROM tenant_settings WHERE tenant_id = ? AND setting_key = ? LIMIT 1', [$tenantId, $key]);
    return (string) ($row['setting_value'] ?? $default);
};

$runs = db_fetchall("SELECT * FROM automation_runs WHERE status = 'queued' AND (scheduled_for IS NULL OR scheduled_for <= NOW()) ORDER BY created_at ASC LIMIT 50");
foreach ($runs as $run) {
    db_execute('UPDATE automation_runs SET status = ? WHERE id = ?', ['running', $run['id']]);
    $rule = db_fetch('SELECT * FROM automation_rules WHERE id = ? LIMIT 1', [$run['automation_rule_id']]);
    $actions = json_decode((string) ($rule['actions_json'] ?? '[]'), true) ?: [];
    foreach ($actions as $action) {
        if (($action['type'] ?? '') === 'send_email') {
            NotificationService::queue(
                (int) $run['tenant_id'],
                'email',
                $tenantSetting((int) $run['tenant_id'], 'smtp_from_email', $tenantSetting((int) $run['tenant_id'], 'business_email', '')),
                'Automation notice',
                'Triggered automation: ' . ($rule['name'] ?? ''),
                ['provider' => 'smtp']
            );
        }
        if (($action['type'] ?? '') === 'send_sms') {
            NotificationService::queue(
                (int) $run['tenant_id'],
                'sms',
                $tenantSetting((int) $run['tenant_id'], 'twilio_from', ''),
                null,
                'Triggered automation: ' . ($rule['name'] ?? ''),
                ['provider' => 'twilio-ready']
            );
        }
    }
    db_execute('UPDATE automation_runs SET status = ?, ran_at = NOW() WHERE id = ?', ['completed', $run['id']]);
}

$deliveries = db_fetchall("SELECT wd.id, wd.tenant_id FROM webhook_deliveries wd WHERE wd.status IN ('queued','failed') AND (wd.next_attempt_at IS NULL OR wd.next_attempt_at <= NOW()) ORDER BY wd.created_at ASC LIMIT 25");
foreach ($deliveries as $delivery) {
    $result = WebhookService::deliver((int) $delivery['id']);
    if (!$result['success'] && fieldora_table_exists('error_logs')) {
        db_insert('error_logs', [
            'tenant_id' => $delivery['tenant_id'],
            'level' => 'error',
            'message' => 'Webhook delivery failed',
            'context_json' => json_encode(['delivery_id' => $delivery['id'], 'response_code' => $result['response_code'], 'attempts' => $result['attempts']], JSON_UNESCAPED_SLASHES),
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}

NotificationService::processQueued(50);

echo "Fieldora cron complete\n";
