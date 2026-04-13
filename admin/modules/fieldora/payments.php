<?php
require_once __DIR__ . '/_bootstrap.php';
require_permission('payments.view');
$tenantId = current_tenant_id();
$search = trim((string) ($_GET['search'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$rows = db_fetchall('SELECT p.*, b.booking_number, i.invoice_number FROM payments p LEFT JOIN bookings b ON b.id = p.booking_id LEFT JOIN invoices i ON i.id = p.invoice_id WHERE p.tenant_id = ? AND (? = "" OR p.payment_status = ?) AND (? = "" OR p.external_reference LIKE ? OR b.booking_number LIKE ? OR i.invoice_number LIKE ?) ORDER BY p.created_at DESC', [$tenantId, $status, $status, $search, '%' . $search . '%', '%' . $search . '%', '%' . $search . '%']);
fieldora_layout_start('Payments', 'payments'); ?>
<div class="topbar-actions" style="margin-bottom:20px;"><a class="primary-btn" href="<?= e(APP_URL) ?>/modules/fieldora/payment_create.php">Record manual payment</a></div>
<form method="get" class="card form-grid"><input name="search" value="<?= e($search) ?>" placeholder="Search references"><select name="status"><option value="">All statuses</option><?php foreach(['pending','completed','failed','refunded','partial'] as $option): ?><option value="<?= e($option) ?>"<?= $status===$option?' selected':'' ?>><?= e($option) ?></option><?php endforeach; ?></select><button class="primary-btn" type="submit">Filter</button></form>
<section class="table-wrap" style="margin-top:20px;"><table><thead><tr><th>Reference</th><th>Method</th><th>Status</th><th>Type</th><th>Amount</th><th>Paid</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><a href="<?= e(APP_URL) ?>/modules/fieldora/payment_view.php?id=<?= (int)$row['id'] ?>"><?= e($row['booking_number'] ?: $row['invoice_number'] ?: $row['external_reference']) ?></a></td><td><?= e($row['payment_method']) ?></td><td><span class="tag"><?= e($row['payment_status']) ?></span></td><td><?= e($row['payment_type']) ?></td><td>$<?= number_format((float)$row['amount'],2) ?></td><td><?= e($row['paid_at']) ?></td></tr><?php endforeach; ?></tbody></table></section>
<?php fieldora_layout_end();
