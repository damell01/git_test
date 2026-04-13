<?php
require_once __DIR__ . '/_bootstrap.php';

use TrashPanda\Fieldora\Services\WebhookService;

require_permission('webhooks.manage');
require_feature('webhooks');

$tenantId = current_tenant_id();
$status = trim((string) ($_GET['status'] ?? ''));
$event = trim((string) ($_GET['event'] ?? ''));
$supported = WebhookService::supportedEvents();

$rows = db_fetchall(
    'SELECT wd.*, we.name, we.endpoint_url
     FROM webhook_deliveries wd
     INNER JOIN webhook_endpoints we ON we.id = wd.webhook_endpoint_id
     WHERE wd.tenant_id = ?
       AND (? = "" OR wd.status = ?)
       AND (? = "" OR wd.event_key = ?)
     ORDER BY wd.updated_at DESC, wd.created_at DESC
     LIMIT 100',
    [$tenantId, $status, $status, $event, $event]
);

fieldora_layout_start('Webhook Logs', 'webhooks');
?>
<form method="get" class="card form-grid">
    <label><span>Status</span><select name="status"><option value="">All</option><option value="queued"<?= $status === 'queued' ? ' selected' : '' ?>>Queued</option><option value="delivered"<?= $status === 'delivered' ? ' selected' : '' ?>>Delivered</option><option value="failed"<?= $status === 'failed' ? ' selected' : '' ?>>Failed</option></select></label>
    <label><span>Event</span><select name="event"><option value="">All events</option><?php foreach ($supported as $key => $label): ?><option value="<?= e($key) ?>"<?= $event === $key ? ' selected' : '' ?>><?= e($key) ?></option><?php endforeach; ?></select></label>
    <button class="primary-btn" type="submit">Filter</button>
</form>
<section class="table-wrap" style="margin-top:20px;">
    <table>
        <thead><tr><th>Event</th><th>Endpoint</th><th>Status</th><th>Code</th><th>Attempts</th><th>Last attempt</th><th>Response</th></tr></thead>
        <tbody>
        <?php if ($rows === []): ?>
            <tr><td colspan="7">No webhook deliveries match those filters yet.</td></tr>
        <?php else: ?>
            <?php foreach ($rows as $row): ?>
                <tr>
                    <td><?= e($row['event_key']) ?></td>
                    <td><?= e($row['name']) ?><br><span class="muted"><?= e($row['endpoint_url']) ?></span></td>
                    <td><?= e($row['status']) ?></td>
                    <td><?= e((string) ($row['response_code'] ?? '')) ?></td>
                    <td><?= (int) $row['attempts'] ?></td>
                    <td><?= e((string) ($row['updated_at'] ?? $row['created_at'])) ?></td>
                    <td><?= e(substr((string) ($row['response_body'] ?? ''), 0, 140) ?: 'No response body captured.') ?></td>
                </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</section>
<?php fieldora_layout_end();
