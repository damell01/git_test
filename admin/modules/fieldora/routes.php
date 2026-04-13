<?php
require_once __DIR__ . '/_bootstrap.php';
require_feature('route_tools');
$tenantId = current_tenant_id();
$date = $_GET['date'] ?? date('Y-m-d');
$jobs = db_fetchall("SELECT * FROM jobs WHERE tenant_id = ? AND scheduled_date = ? AND deleted_at IS NULL ORDER BY city ASC, address_line1 ASC",[$tenantId,$date]);
fieldora_layout_start('Route Tools', 'routes'); ?>
<section class="card"><h3>Daily route for <?= e($date) ?></h3><p class="muted">Group jobs for the day and hand the route to Google Maps.</p></section>
<section class="table-wrap"><table><thead><tr><th>Job</th><th>Address</th><th>Maps</th></tr></thead><tbody><?php foreach ($jobs as $row): $maps = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode(trim(($row['address_line1'] ?? '') . ' ' . ($row['city'] ?? '') . ' ' . ($row['state'] ?? '') . ' ' . ($row['postal_code'] ?? ''))); ?><tr><td><?= e($row['job_number']) ?> · <?= e($row['title']) ?></td><td><?= e(trim(($row['address_line1'] ?? '') . ', ' . ($row['city'] ?? '') . ', ' . ($row['state'] ?? '') . ' ' . ($row['postal_code'] ?? ''))) ?></td><td><a class="ghost-link" href="<?= e($maps) ?>" target="_blank" rel="noopener">Open route</a></td></tr><?php endforeach; ?></tbody></table></section>
<?php fieldora_layout_end();
