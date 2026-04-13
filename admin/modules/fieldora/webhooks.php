<?php
require_once __DIR__ . '/_bootstrap.php';

require_permission('webhooks.manage');
require_feature('webhooks');

$tenantId = current_tenant_id();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $id = (int) ($_POST['id'] ?? 0);
    $action = trim((string) ($_POST['action'] ?? ''));
    $row = db_fetch('SELECT * FROM webhook_endpoints WHERE tenant_id = ? AND id = ? LIMIT 1', [$tenantId, $id]);
    if (!$row) {
        flash_error('Webhook endpoint not found.');
        redirect($_SERVER['REQUEST_URI']);
    }

    if ($action === 'toggle') {
        $next = (int) $row['is_active'] === 1 ? 0 : 1;
        db_execute('UPDATE webhook_endpoints SET is_active = ?, updated_at = NOW() WHERE tenant_id = ? AND id = ?', [$next, $tenantId, $id]);
        log_fieldora_event('webhook.toggled', 'Webhook endpoint status changed.', 'webhook_endpoint', $id, ['is_active' => $next]);
        flash_success($next === 1 ? 'Webhook endpoint enabled.' : 'Webhook endpoint disabled.');
    }

    redirect($_SERVER['REQUEST_URI']);
}

$rows = db_fetchall('SELECT * FROM webhook_endpoints WHERE tenant_id = ? ORDER BY created_at DESC', [$tenantId]);
$deliveries = db_fetchall(
    'SELECT wd.*, we.name
     FROM webhook_deliveries wd
     INNER JOIN webhook_endpoints we ON we.id = wd.webhook_endpoint_id
     WHERE wd.tenant_id = ?
     ORDER BY wd.created_at DESC
     LIMIT 10',
    [$tenantId]
);

fieldora_layout_start('Webhooks', 'webhooks');
?>
<section class="card">
    <h3>Integration-ready outbound webhooks</h3>
    <p class="muted">Connect Fieldora to Zapier, n8n, Make, or custom systems with signed JSON payloads and delivery logs.</p>
    <div class="topbar-actions">
        <a class="primary-btn" href="<?= e(APP_URL) ?>/modules/fieldora/webhook_new.php">New webhook</a>
        <a class="ghost-link" href="<?= e(APP_URL) ?>/modules/fieldora/webhook_logs.php">View logs</a>
        <a class="ghost-link" href="<?= e(APP_URL) ?>/modules/fieldora/webhook_docs.php">Docs</a>
    </div>
</section>

<div class="grid two" style="margin-top:20px;">
    <section class="table-wrap">
        <table>
            <thead><tr><th>Name</th><th>URL</th><th>Status</th><th>Events</th></tr></thead>
            <tbody>
            <?php if ($rows === []): ?>
                <tr><td colspan="4">No webhook endpoints yet. Create one to send booking, payment, invoice, and job events to Zapier, n8n, or Make.</td></tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <?php $events = json_decode((string) ($row['events_json'] ?? '[]'), true) ?: []; ?>
                    <tr>
                        <td>
                            <a href="<?= e(APP_URL) ?>/modules/fieldora/webhook_view.php?id=<?= (int) $row['id'] ?>"><?= e($row['name']) ?></a>
                            <div class="muted">Updated <?= e((string) $row['updated_at']) ?></div>
                        </td>
                        <td><?= e($row['endpoint_url']) ?></td>
                        <td>
                            <span class="tag"><?= (int) $row['is_active'] === 1 ? 'Enabled' : 'Disabled' ?></span>
                            <form method="post" style="margin-top:8px;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                <input type="hidden" name="action" value="toggle">
                                <button class="ghost-link" type="submit"><?= (int) $row['is_active'] === 1 ? 'Disable' : 'Enable' ?></button>
                            </form>
                        </td>
                        <td><?= e(implode(', ', $events)) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
    <section class="table-wrap">
        <table>
            <thead><tr><th>Recent delivery</th><th>Status</th><th>Attempts</th><th>Code</th></tr></thead>
            <tbody>
            <?php if ($deliveries === []): ?>
                <tr><td colspan="4">No deliveries yet. Use "Send test event" on an endpoint to verify your integration.</td></tr>
            <?php else: ?>
                <?php foreach ($deliveries as $row): ?>
                    <tr>
                        <td><?= e($row['name']) ?> - <?= e($row['event_key']) ?></td>
                        <td><?= e($row['status']) ?></td>
                        <td><?= (int) $row['attempts'] ?></td>
                        <td><?= e((string) ($row['response_code'] ?? '')) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </section>
</div>
<?php fieldora_layout_end();
