<?php
require_once __DIR__ . '/_bootstrap.php';
require_permission('bookings.view');
$rows = db_fetchall('SELECT * FROM notifications WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 50', [current_tenant_id()]);
fieldora_layout_start('Notifications', 'notifications'); ?>
<section class="table-wrap"><table><thead><tr><th>Channel</th><th>Recipient</th><th>Template</th><th>Status</th><th>Created</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><?= e($row['channel']) ?></td><td><?= e($row['recipient']) ?></td><td><?= e($row['template_key']) ?></td><td><span class="tag"><?= e($row['status']) ?></span></td><td><?= e($row['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></section>
<?php fieldora_layout_end();
