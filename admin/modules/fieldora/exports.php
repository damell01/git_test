<?php
require_once __DIR__ . '/_bootstrap.php';
require_permission('exports.view');
$tenantId = current_tenant_id();
if (isset($_GET['download'])) {
    $type = $_GET['download'];
    $allowed = ['bookings','invoices','payments'];
    if (in_array($type, $allowed, true)) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $type . '-export.csv"');
        $out = fopen('php://output', 'w');
        if ($type === 'bookings') { fputcsv($out, ['booking_number','status','payment_state','total_amount','scheduled_date']); foreach (db_fetchall('SELECT booking_number,status,payment_state,total_amount,scheduled_date FROM bookings WHERE tenant_id = ?',[$tenantId]) as $row) { fputcsv($out, $row); } }
        if ($type === 'invoices') { fputcsv($out, ['invoice_number','status','total','amount_paid','balance_due','due_date']); foreach (db_fetchall('SELECT invoice_number,status,total,amount_paid,balance_due,due_date FROM invoices WHERE tenant_id = ?',[$tenantId]) as $row) { fputcsv($out, $row); } }
        if ($type === 'payments') { fputcsv($out, ['payment_method','payment_status','payment_type','amount','paid_at']); foreach (db_fetchall('SELECT payment_method,payment_status,payment_type,amount,paid_at FROM payments WHERE tenant_id = ?',[$tenantId]) as $row) { fputcsv($out, $row); } }
        fclose($out);
        exit;
    }
}
fieldora_layout_start('Exports', 'exports'); ?>
<div class="grid three">
    <a class="card" href="?download=bookings"><strong>Bookings CSV</strong><p class="muted">Export booking volume, status, payment state, and schedule dates.</p></a>
    <a class="card" href="?download=invoices"><strong>Invoices CSV</strong><p class="muted">Export invoice status, totals, paid amount, and balance due.</p></a>
    <a class="card" href="?download=payments"><strong>Payments CSV</strong><p class="muted">Export method, status, type, amount, and paid date.</p></a>
</div>
<?php fieldora_layout_end();
