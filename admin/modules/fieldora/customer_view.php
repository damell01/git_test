<?php
require_once __DIR__ . '/_bootstrap.php';
require_permission('customers.view');
$id = (int) ($_GET['id'] ?? 0);
$customer = db_fetch('SELECT * FROM customers WHERE tenant_id = ? AND id = ? AND deleted_at IS NULL LIMIT 1',[current_tenant_id(),$id]);
if(!$customer){http_response_code(404);exit('Customer not found');}
$bookings = db_fetchall('SELECT booking_number,status,total_amount,scheduled_date FROM bookings WHERE customer_id = ? ORDER BY created_at DESC',[$id]);
$invoices = db_fetchall('SELECT invoice_number,status,total,balance_due FROM invoices WHERE customer_id = ? ORDER BY created_at DESC',[$id]);
$payments = db_fetchall('SELECT payment_method,payment_status,amount,paid_at FROM payments WHERE customer_id = ? ORDER BY created_at DESC',[$id]);
$notes = db_fetchall('SELECT cn.*, u.name AS user_name FROM customer_notes cn LEFT JOIN users u ON u.id = cn.user_id WHERE cn.customer_id = ? ORDER BY cn.created_at DESC',[$id]);
if($_SERVER['REQUEST_METHOD']==='POST'){csrf_check(); if(($_POST['action'] ?? '')==='note'){db_insert('customer_notes',['tenant_id'=>current_tenant_id(),'customer_id'=>$id,'user_id'=>$_SESSION['user_id'],'note'=>trim((string)$_POST['note']),'created_at'=>date('Y-m-d H:i:s')]); flash_success('Customer note added.'); redirect($_SERVER['REQUEST_URI']);}}
fieldora_layout_start('Customer Detail','customers'); ?>
<div class="grid two"><section class="card"><h3><?= e(trim(($customer['first_name']??'').' '.($customer['last_name']??''))) ?></h3><p class="muted"><?= e($customer['email']) ?> · <?= e($customer['phone']) ?></p><p><?= e(trim(($customer['address_line1']??'').' '.($customer['city']??'').' '.($customer['state']??'').' '.($customer['postal_code']??''))) ?></p><a class="primary-btn" href="<?= e(APP_URL) ?>/modules/fieldora/customer_edit.php?id=<?= $id ?>">Edit customer</a></section><section class="card"><form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="action" value="note"><textarea name="note" placeholder="Add note"></textarea><button class="primary-btn" type="submit">Add note</button></form></section></div>
<div class="grid three" style="margin-top:20px;"><section class="table-wrap"><table><thead><tr><th>Bookings</th><th>Status</th></tr></thead><tbody><?php foreach($bookings as $row): ?><tr><td><?= e($row['booking_number']) ?></td><td><?= e($row['status']) ?></td></tr><?php endforeach; ?></tbody></table></section><section class="table-wrap"><table><thead><tr><th>Invoices</th><th>Status</th></tr></thead><tbody><?php foreach($invoices as $row): ?><tr><td><?= e($row['invoice_number']) ?></td><td><?= e($row['status']) ?></td></tr><?php endforeach; ?></tbody></table></section><section class="table-wrap"><table><thead><tr><th>Notes</th><th>When</th></tr></thead><tbody><?php foreach($notes as $row): ?><tr><td><?= e($row['note']) ?></td><td><?= e($row['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></section></div>
<?php fieldora_layout_end();
