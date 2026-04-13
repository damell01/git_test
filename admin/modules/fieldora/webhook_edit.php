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

$supported = WebhookService::supportedEvents();
$selectedEvents = json_decode((string) ($row['events_json'] ?? '[]'), true) ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $url = trim((string) ($_POST['endpoint_url'] ?? ''));
    if (!WebhookService::isValidEndpointUrl($url)) {
        flash_error('Enter a valid webhook URL.');
        redirect($_SERVER['REQUEST_URI']);
    }

    $selected = array_values(array_intersect(array_keys($supported), (array) ($_POST['events'] ?? [])));
    if ($selected === []) {
        flash_error('Select at least one event.');
        redirect($_SERVER['REQUEST_URI']);
    }

    db_execute(
        'UPDATE webhook_endpoints SET name = ?, endpoint_url = ?, secret = ?, events_json = ?, is_active = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?',
        [
            trim((string) ($_POST['name'] ?? '')),
            $url,
            trim((string) ($_POST['secret'] ?? '')) ?: WebhookService::generateSecret(),
            json_encode($selected, JSON_UNESCAPED_SLASHES),
            (int) ($_POST['is_active'] ?? 1),
            $id,
            $tenantId,
        ]
    );
    log_fieldora_event('webhook.updated', 'Webhook endpoint updated.', 'webhook_endpoint', $id, ['endpoint_url' => $url, 'events' => $selected]);
    flash_success('Webhook endpoint updated.');
    redirect(APP_URL . '/modules/fieldora/webhook_view.php?id=' . $id);
}

fieldora_layout_start('Edit Webhook', 'webhooks');
?>
<form method="post" class="stack">
    <?= csrf_field() ?>
    <section class="card form-grid">
        <label><span>Name</span><input name="name" value="<?= e($row['name']) ?>" required></label>
        <label><span>Endpoint URL</span><input name="endpoint_url" value="<?= e($row['endpoint_url']) ?>" required></label>
        <label><span>Signing secret</span><input name="secret" value="<?= e($row['secret']) ?>"></label>
        <label><span>Status</span><select name="is_active"><option value="1"<?= (int) $row['is_active'] === 1 ? ' selected' : '' ?>>Enabled</option><option value="0"<?= (int) $row['is_active'] === 0 ? ' selected' : '' ?>>Disabled</option></select></label>
    </section>
    <section class="card">
        <p class="muted">Only selected events will be delivered. Rotate or replace the signing secret only after your destination is ready to trust the new value.</p>
        <h3>Events</h3>
        <?php foreach ($supported as $key => $label): ?>
            <label class="service-option"><input type="checkbox" name="events[]" value="<?= e($key) ?>"<?= in_array($key, $selectedEvents, true) ? ' checked' : '' ?>><span><strong><?= e($key) ?></strong><small><?= e($label) ?></small></span></label>
        <?php endforeach; ?>
    </section>
    <button class="primary-btn" type="submit">Save webhook</button>
</form>
<?php fieldora_layout_end();
