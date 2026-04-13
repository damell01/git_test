<?php
require_once __DIR__ . '/_bootstrap.php';

require_permission('analytics.view');
require_feature('analytics_enhanced');

$tenantId = current_tenant_id();
$bookings = (int) (db_fetch('SELECT COUNT(*) AS cnt FROM bookings WHERE tenant_id = ?', [$tenantId])['cnt'] ?? 0);
$revenue = (float) (db_fetch("SELECT COALESCE(SUM(amount),0) AS total FROM payments WHERE tenant_id = ? AND payment_status = 'completed'", [$tenantId])['total'] ?? 0);
$invoiceSummary = db_fetch("SELECT SUM(status='draft') AS drafts, SUM(status='sent') AS sent, SUM(status='paid') AS paid FROM invoices WHERE tenant_id = ?", [$tenantId]) ?: ['drafts' => 0, 'sent' => 0, 'paid' => 0];

fieldora_layout_start('Analytics', 'analytics');
?>
<div class="grid three">
    <section class="card"><div class="metric"><?= $bookings ?></div><div class="muted">Bookings</div></section>
    <section class="card"><div class="metric">$<?= number_format($revenue, 2) ?></div><div class="muted">Revenue</div></section>
    <section class="card"><div class="metric"><?= (int) $invoiceSummary['paid'] ?></div><div class="muted">Paid invoices</div></section>
</div>
<div class="grid two" style="margin-top:20px;">
    <section class="card"><h3>Invoice status summary</h3><p class="muted">Drafts: <?= (int) $invoiceSummary['drafts'] ?> - Sent: <?= (int) $invoiceSummary['sent'] ?> - Paid: <?= (int) $invoiceSummary['paid'] ?></p></section>
    <section class="card"><h3>Upgrade-ready analytics</h3><p class="muted">Growth and Pro can expose deeper charts, cohorting, route efficiency, and payment trend views from the same data model.</p></section>
</div>
<?php fieldora_layout_end();
