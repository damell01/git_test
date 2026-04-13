<?php
require_once __DIR__ . '/_bootstrap.php';
require_permission('bookings.manage');
$row = db_fetch('SELECT * FROM services WHERE tenant_id = ? AND id = ? LIMIT 1', [current_tenant_id(), (int) ($_GET['id'] ?? 0)]);
if (!$row) { http_response_code(404); exit('Service not found'); }
fieldora_layout_start('Service Detail', 'services'); ?>
<div class="grid two">
    <section class="card"><h3><?= e($row['name']) ?></h3><p class="muted"><?= e($row['description']) ?></p><p>Price: $<?= number_format((float)$row['price'],2) ?></p><p>Duration: <?= e((string)($row['duration_minutes'] ?: 'Flexible')) ?></p><p>Deposit: <?= e($row['deposit_mode']) ?> <?= e((string)$row['deposit_value']) ?></p></section>
    <section class="card"><a class="primary-btn" href="<?= e(APP_URL) ?>/modules/fieldora/service_edit.php?id=<?= (int)$row['id'] ?>">Edit service</a></section>
</div>
<?php fieldora_layout_end();
