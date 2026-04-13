<?php
require_once __DIR__ . '/_bootstrap.php';

use TrashPanda\Fieldora\Services\WebhookService;

require_permission('webhooks.manage');
require_feature('webhooks');

$tenantId = current_tenant_id();
$id = (int) ($_GET['id'] ?? 0);
$row = db_fetch('SELECT * FROM webhook_endpoints WHERE tenant_id = ? AND id = ? LIMIT 1', [$tenantId, $id]);
if (!$row) {
    http_response_code(404);
    exit('Webhook not found');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = trim((string) ($_POST['action'] ?? 'test'));
    try {
        if ($action === 'delete') {
            db_execute('DELETE FROM webhook_endpoints WHERE tenant_id = ? AND id = ?', [$tenantId, $id]);
            log_fieldora_event('webhook.deleted', 'Webhook endpoint deleted.', 'webhook_endpoint', $id, ['endpoint_url' => $row['endpoint_url']]);
            flash_success('Webhook endpoint deleted.');
            redirect(APP_URL . '/modules/fieldora/webhooks.php');
        }

        if ($action === 'toggle') {
            $next = (int) $row['is_active'] === 1 ? 0 : 1;
            db_execute('UPDATE webhook_endpoints SET is_active = ?, updated_at = NOW() WHERE tenant_id = ? AND id = ?', [$next, $tenantId, $id]);
            log_fieldora_event('webhook.toggled', 'Webhook endpoint status changed.', 'webhook_endpoint', $id, ['is_active' => $next]);
            flash_success($next === 1 ? 'Webhook endpoint enabled.' : 'Webhook endpoint disabled.');
            redirect($_SERVER['REQUEST_URI']);
        }

        if ($action === 'rotate_secret') {
            $secret = WebhookService::generateSecret();
            db_execute('UPDATE webhook_endpoints SET secret = ?, updated_at = NOW() WHERE tenant_id = ? AND id = ?', [$secret, $tenantId, $id]);
            log_fieldora_event('webhook.secret_rotated', 'Webhook signing secret rotated.', 'webhook_endpoint', $id);
            flash_success('Signing secret rotated. Update your integration before sending live events.');
            redirect($_SERVER['REQUEST_URI']);
        }

        $result = WebhookService::sendTest($tenantId, $id, trim((string) ($_POST['test_event'] ?? 'booking.created')));
        log_fieldora_event('webhook.test_sent', 'Webhook test event sent.', 'webhook_endpoint', $id, ['event' => $_POST['test_event'] ?? 'booking.created', 'response_code' => $result['response_code'] ?? null]);
        flash_success('Test sent. Response ' . (int) $result['response_code'] . ': ' . substr((string) $result['response_body'], 0, 180));
    } catch (Throwable $e) {
        flash_error($e->getMessage());
    }
    redirect($_SERVER['REQUEST_URI']);
}

$events = json_decode((string) ($row['events_json'] ?? '[]'), true) ?: [];
$deliveries = db_fetchall('SELECT * FROM webhook_deliveries WHERE tenant_id = ? AND webhook_endpoint_id = ? ORDER BY created_at DESC LIMIT 10', [$tenantId, $id]);
$supported = WebhookService::supportedEvents();

fieldora_layout_start('Webhook Detail', 'webhooks');
?>
<div class="grid two">
    <section class="card stack">
        <div>
            <p class="muted">Endpoint</p>
            <h3><?= e($row['name']) ?></h3>
        </div>
        <p><strong>URL:</strong> <?= e($row['endpoint_url']) ?></p>
        <p><strong>Status:</strong> <span class="tag"><?= (int) $row['is_active'] === 1 ? 'enabled' : 'disabled' ?></span></p>
        <p><strong>Secret:</strong> <code><?= e($row['secret']) ?></code></p>
        <p><strong>Last tested:</strong> <?= e((string) ($row['last_tested_at'] ?: 'Never')) ?></p>
        <div class="topbar-actions">
            <a class="primary-btn" href="<?= e(APP_URL) ?>/modules/fieldora/webhook_edit.php?id=<?= $id ?>">Edit</a>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle">
                <button class="ghost-link" type="submit"><?= (int) $row['is_active'] === 1 ? 'Disable' : 'Enable' ?></button>
            </form>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="rotate_secret">
                <button class="ghost-link" type="submit">Rotate secret</button>
            </form>
            <form method="post" onsubmit="return confirm('Delete this webhook endpoint?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <button class="ghost-link" type="submit">Delete</button>
            </form>
        </div>
    </section>
    <section class="card">
        <h3>Subscribed events</h3>
        <?php if ($events === []): ?>
            <p class="muted">No events selected. Edit this endpoint to choose the events it should receive.</p>
        <?php else: ?>
            <?php foreach ($events as $event): ?>
                <label class="service-option"><span><strong><?= e($event) ?></strong><small><?= e($supported[$event] ?? '') ?></small></span></label>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>

<form method="post" class="card form-grid" style="margin-top:20px;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="test">
    <label><span>Send test event</span><select name="test_event"><?php foreach ($supported as $key => $label): ?><option value="<?= e($key) ?>"><?= e($key) ?></option><?php endforeach; ?></select></label>
    <button class="primary-btn" type="submit">Send test event</button>
</form>

<section class="table-wrap" style="margin-top:20px;">
    <table>
        <thead><tr><th>Event</th><th>Status</th><th>Code</th><th>Attempted</th><th>Response</th></tr></thead>
        <tbody>
        <?php if ($deliveries === []): ?>
            <tr><td colspan="5">No deliveries yet. Send a test event to inspect the response and confirm the integration is reachable.</td></tr>
        <?php else: ?>
            <?php foreach ($deliveries as $delivery): ?>
                <tr>
                    <td><?= e($delivery['event_key']) ?></td>
                    <td><?= e($delivery['status']) ?></td>
                    <td><?= e((string) ($delivery['response_code'] ?? '')) ?></td>
                    <td><?= e((string) ($delivery['updated_at'] ?? $delivery['created_at'])) ?></td>
                    <td><?= e(substr((string) ($delivery['response_body'] ?? ''), 0, 140) ?: 'No response body captured.') ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</section>
<?php fieldora_layout_end();
