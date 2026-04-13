<?php
require_once __DIR__ . '/_bootstrap.php';
require_permission('customers.view');
$tenantId = current_tenant_id();
$search = trim((string) ($_GET['search'] ?? ''));
$rows = db_fetchall('SELECT c.*, (SELECT COUNT(*) FROM bookings b WHERE b.customer_id = c.id) AS booking_count FROM customers c WHERE c.tenant_id = ? AND c.deleted_at IS NULL AND (? = "" OR CONCAT_WS(" ", c.first_name, c.last_name) LIKE ? OR c.email LIKE ? OR c.phone LIKE ?) ORDER BY c.created_at DESC', [$tenantId, $search, '%' . $search . '%', '%' . $search . '%', '%' . $search . '%']);
fieldora_layout_start('Customers', 'customers'); ?>
<form method="get" class="card form-grid"><input name="search" value="<?= e($search) ?>" placeholder="Search customers"><button class="primary-btn" type="submit">Filter</button></form>
<section class="table-wrap" style="margin-top:20px;"><table><thead><tr><th>Name</th><th>Email</th><th>Phone</th><th>Bookings</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><a href="<?= e(APP_URL) ?>/modules/fieldora/customer_view.php?id=<?= (int)$row['id'] ?>"><?= e(trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''))) ?></a></td><td><?= e($row['email']) ?></td><td><?= e($row['phone']) ?></td><td><?= (int)$row['booking_count'] ?></td></tr><?php endforeach; ?></tbody></table></section>
<?php fieldora_layout_end();
