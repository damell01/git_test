<?php
require_once __DIR__ . '/_bootstrap.php';
require_permission('payments.view');
$id=(int)($_GET['id']??0); $row=db_fetch('SELECT p.*, b.booking_number, i.invoice_number FROM payments p LEFT JOIN bookings b ON b.id = p.booking_id LEFT JOIN invoices i ON i.id = p.invoice_id WHERE p.tenant_id = ? AND p.id = ? LIMIT 1',[current_tenant_id(),$id]); if(!$row){http_response_code(404);exit('Payment not found');}
$events=db_fetchall('SELECT * FROM payment_event_logs WHERE tenant_id = ? AND (payment_id = ? OR payment_id IS NULL) ORDER BY created_at DESC LIMIT 20',[current_tenant_id(),$id]);
fieldora_layout_start('Payment Detail','payments'); ?>
<div class="grid two"><section class="card"><h3>$<?= number_format((float)$row['amount'],2) ?></h3><p class="muted"><?= e($row['payment_method']) ?> · <?= e($row['payment_type']) ?></p><p>Status: <span class="tag"><?= e($row['payment_status']) ?></span></p><p>Reference: <?= e($row['booking_number'] ?: $row['invoice_number'] ?: $row['external_reference']) ?></p><p>Stripe session: <?= e((string)$row['stripe_checkout_session_id']) ?></p><p>Stripe intent: <?= e((string)$row['stripe_payment_intent_id']) ?></p></section><section class="table-wrap"><table><thead><tr><th>Event</th><th>When</th></tr></thead><tbody><?php foreach($events as $event): ?><tr><td><?= e($event['event_key']) ?></td><td><?= e($event['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></section></div>
<?php fieldora_layout_end();
