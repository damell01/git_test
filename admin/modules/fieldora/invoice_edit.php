<?php
require_once __DIR__ . '/_bootstrap.php';
require_permission('invoices.manage');
use TrashPanda\Fieldora\Services\PaymentService;
$id=(int)($_GET['id']??0); $row=db_fetch('SELECT * FROM invoices WHERE tenant_id = ? AND id = ? LIMIT 1',[current_tenant_id(),$id]); if(!$row){http_response_code(404);exit('Invoice not found');}
if($_SERVER['REQUEST_METHOD']==='POST'){csrf_check(); db_execute('UPDATE invoices SET status = ?, due_date = ?, notes = ?, terms = ?, total = ?, balance_due = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?',[trim((string)$_POST['status']), $_POST['due_date'] ?: null, trim((string)($_POST['notes'] ?? '')), trim((string)($_POST['terms'] ?? '')), (float)$_POST['total'], max(0, (float)$_POST['total'] - (float)$row['amount_paid']), $id, current_tenant_id()]); flash_success('Invoice updated.'); redirect(APP_URL.'/modules/fieldora/invoice_view.php?id='.$id);}
fieldora_layout_start('Edit Invoice','invoices'); ?>
<form method="post" class="card form-grid"><?= csrf_field() ?><select name="status"><?php foreach(['draft','sent','partially_paid','paid','void','canceled'] as $status): ?><option value="<?= e($status) ?>"<?= $row['status']===$status?' selected':'' ?>><?= e($status) ?></option><?php endforeach; ?></select><input name="due_date" type="date" value="<?= e($row['due_date']) ?>"><input name="total" type="number" step="0.01" value="<?= e((string)$row['total']) ?>"><textarea name="notes"><?= e($row['notes']) ?></textarea><textarea name="terms"><?= e($row['terms']) ?></textarea><button class="primary-btn" type="submit">Save invoice</button></form>
<?php fieldora_layout_end();
