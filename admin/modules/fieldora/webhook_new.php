<?php
require_once __DIR__ . '/_bootstrap.php';

use TrashPanda\Fieldora\Services\WebhookService;

require_permission('webhooks.manage');
require_feature('webhooks');

$events = WebhookService::supportedEvents();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $url = trim((string) ($_POST['endpoint_url'] ?? ''));
    if (!WebhookService::isValidEndpointUrl($url)) {
        flash_error('Enter a valid webhook URL.');
        redirect($_SERVER['REQUEST_URI']);
    }

    $selected = array_values(array_intersect(array_keys($events), (array) ($_POST['events'] ?? [])));
    if ($selected === []) {
        flash_error('Select at least one event.');
        redirect($_SERVER['REQUEST_URI']);
    }

    $id = (int) db_insert('webhook_endpoints', [
        'tenant_id' => current_tenant_id(),
        'name' => trim((string) ($_POST['name'] ?? 'Integration endpoint')),
        'endpoint_url' => $url,
        'secret' => WebhookService::generateSecret(),
        'events_json' => json_encode($selected, JSON_UNESCAPED_SLASHES),
        'is_active' => (int) ($_POST['is_active'] ?? 1),
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    log_fieldora_event('webhook.created', 'Webhook endpoint created.', 'webhook_endpoint', $id, ['endpoint_url' => $url, 'events' => $selected]);
    flash_success('Webhook endpoint created.');
    redirect(APP_URL . '/modules/fieldora/webhook_view.php?id=' . $id);
}

fieldora_layout_start('New Webhook', 'webhooks');
?>
<form method="post" class="stack">
    <?= csrf_field() ?>
    <section class="card form-grid">
        <label><span>Endpoint name</span><input name="name" placeholder="Zapier bookings"></label>
        <label><span>Endpoint URL</span><input name="endpoint_url" placeholder="https://hooks.zapier.com/... " required></label>
        <label><span>Status</span><select name="is_active"><option value="1">Enabled</option><option value="0">Disabled</option></select></label>
    </section>
    <section class="card">
        <p class="muted">Choose the events this endpoint should receive. Fieldora signs each JSON payload and sends it asynchronously so customer-facing flows stay fast.</p>
        <h3>Events</h3>
        <?php foreach ($events as $key => $label): ?>
            <label class="service-option"><input type="checkbox" name="events[]" value="<?= e($key) ?>"><span><strong><?= e($key) ?></strong><small><?= e($label) ?></small></span></label>
        <?php endforeach; ?>
    </section>
    <section class="card">
        <p class="muted">Start with webhook.site for testing, then move the same URL pattern into Zapier, Make, or n8n once you trust the payload.</p>
        <a class="ghost-link" href="<?= e(APP_URL) ?>/modules/fieldora/webhook_docs.php">Open webhook docs</a>
    </section>
    <button class="primary-btn" type="submit">Create webhook</button>
</form>
<?php fieldora_layout_end();
