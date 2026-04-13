<?php
require_once __DIR__ . '/_bootstrap.php';
require_permission('invoices.view');
$tenantId = current_tenant_id();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $customerId = (int) ($_POST['customer_id'] ?? 0) ?: null;
    $total = (float) ($_POST['total'] ?? 0);
    $countRow = db_fetch('SELECT COUNT(*) AS cnt FROM invoices WHERE tenant_id = ?', [$tenantId]);
    $invoiceNumber = 'INV-' . str_pad((string) (((int) ($countRow['cnt'] ?? 0)) + 1), 5, '0', STR_PAD_LEFT);

    db_insert('invoices', [
        'tenant_id' => $tenantId,
        'invoice_number' => $invoiceNumber,
        'customer_id' => $customerId,
        'status' => 'draft',
        'subtotal' => $total,
        'tax_amount' => 0,
        'total' => $total,
        'amount_paid' => 0,
        'balance_due' => $total,
        'due_date' => $_POST['due_date'] ?: null,
        'notes' => trim((string) ($_POST['notes'] ?? '')),
        'terms' => 'Payment due on receipt.',
        'created_by' => $_SESSION['user_id'],
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    flash_success('Invoice created.');
    redirect($_SERVER['REQUEST_URI']);
}
$search = trim((string) ($_GET['search'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$rows = db_fetchall('SELECT i.*, CONCAT_WS(" ", c.first_name, c.last_name) AS customer_name FROM invoices i LEFT JOIN customers c ON c.id = i.customer_id WHERE i.tenant_id = ? AND i.deleted_at IS NULL AND (? = "" OR i.status = ?) AND (? = "" OR i.invoice_number LIKE ? OR CONCAT_WS(" ", c.first_name, c.last_name) LIKE ?) ORDER BY i.created_at DESC', [$tenantId, $status, $status, $search, '%' . $search . '%', '%' . $search . '%']);
$customers = db_fetchall('SELECT id, CONCAT_WS(" ", first_name, last_name) AS name FROM customers WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY first_name ASC', [$tenantId]);
fieldora_layout_start('Invoices', 'invoices'); ?>
<form method="post" class="card form-grid"><?= csrf_field() ?><select name="customer_id"><option value="">Select customer</option><?php foreach ($customers as $customer): ?><option value="<?= (int)$customer['id'] ?>"><?= e($customer['name']) ?></option><?php endforeach; ?></select><input name="total" type="number" step="0.01" placeholder="Total" required><input name="due_date" type="date"><textarea name="notes" placeholder="Notes"></textarea><button class="primary-btn" type="submit">Create invoice</button></form>
<form method="get" class="card form-grid" style="margin-top:20px;"><input name="search" value="<?= e($search) ?>" placeholder="Search invoices"><select name="status"><option value="">All statuses</option><?php foreach(['draft','sent','partially_paid','paid','void','canceled'] as $option): ?><option value="<?= e($option) ?>"<?= $status===$option?' selected':'' ?>><?= e($option) ?></option><?php endforeach; ?></select><button class="primary-btn" type="submit">Filter</button></form>
<section class="table-wrap" style="margin-top:20px;"><table><thead><tr><th>Invoice</th><th>Customer</th><th>Status</th><th>Total</th><th>Balance</th></tr></thead><tbody><?php foreach ($rows as $row): ?><tr><td><a href="<?= e(APP_URL) ?>/modules/fieldora/invoice_view.php?id=<?= (int)$row['id'] ?>"><?= e($row['invoice_number']) ?></a></td><td><?= e($row['customer_name']) ?></td><td><span class="tag"><?= e($row['status']) ?></span></td><td>$<?= number_format((float)$row['total'],2) ?></td><td>$<?= number_format((float)$row['balance_due'],2) ?></td></tr><?php endforeach; ?></tbody></table></section>
<?php fieldora_layout_end();
