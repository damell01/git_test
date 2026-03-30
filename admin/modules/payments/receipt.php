<?php
/**
 * Payments – Printable Receipt
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_login();

$id  = (int)($_GET['id'] ?? 0);
$pay = db_fetch(
    'SELECT p.*,
            wo.wo_number, wo.service_address, wo.service_city, wo.size,
            c.name AS cust_name, c.email AS cust_email, c.phone AS cust_phone,
            c.address AS cust_address, c.city AS cust_city, c.state AS cust_state,
            i.invoice_number,
            u.name AS processed_by
     FROM payments p
     LEFT JOIN work_orders wo ON p.work_order_id = wo.id
     LEFT JOIN customers   c  ON p.customer_id   = c.id
     LEFT JOIN invoices    i  ON p.invoice_id     = i.id
     LEFT JOIN users       u  ON p.created_by     = u.id
     WHERE p.id = ? LIMIT 1',
    [$id]
);

if (!$pay) {
    http_response_code(404);
    die('Receipt not found.');
}

$print_mode       = isset($_GET['print']);
$company_name     = get_setting('company_name',    'Trash Panda Roll-Offs');
$company_phone    = get_setting('company_phone',   '');
$company_email    = get_setting('company_email',   '');
$company_address  = get_setting('company_address', '');
$receipt_number   = 'REC-' . str_pad((string)$pay['id'], 5, '0', STR_PAD_LEFT);
$paid_at          = $pay['paid_at'] ? date('F j, Y g:i A', strtotime($pay['paid_at'])) : date('F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Receipt <?= e($receipt_number) ?> | <?= e($company_name) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: #fff !important; }
        }
        body { background: #f3f4f6; }
        .receipt-wrap { max-width: 680px; margin: 40px auto; background: #fff; border-radius: 10px;
                        box-shadow: 0 4px 24px rgba(0,0,0,.1); overflow: hidden; }
        .receipt-header { background: #1a1d27; color: #e5e7eb; padding: 28px 36px; }
        .receipt-header h1 { color: #f97316; font-size: 1.5rem; font-weight: 700; margin: 0 0 4px; }
        .receipt-body { padding: 32px 36px; }
        .receipt-total { background: #f9fafb; border-top: 2px solid #e5e7eb; padding: 20px 36px;
                         font-size: 1.2rem; font-weight: 700; }
        .receipt-footer { background: #f3f4f6; padding: 18px 36px; font-size: .8rem; color: #9ca3af; text-align: center; }
        .label-cell { color: #6b7280; width: 160px; }
    </style>
</head>
<body>

<div class="no-print text-center mt-3 mb-2">
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary me-2">
        🖨 Print Receipt
    </button>
    <a href="index.php" class="btn btn-sm btn-outline-dark">← Back to Payments</a>
</div>

<div class="receipt-wrap">

    <!-- Header -->
    <div class="receipt-header">
        <h1>🗑 <?= e($company_name) ?></h1>
        <div style="font-size:.85rem;color:#9ca3af;">
            <?php if ($company_phone): ?>📞 <?= e($company_phone) ?>&nbsp;&nbsp;<?php endif; ?>
            <?php if ($company_email): ?>✉ <?= e($company_email) ?>&nbsp;&nbsp;<?php endif; ?>
            <?php if ($company_address): ?>📍 <?= e($company_address) ?><?php endif; ?>
        </div>
    </div>

    <!-- Body -->
    <div class="receipt-body">

        <div class="d-flex justify-content-between align-items-start mb-4">
            <div>
                <h4 style="font-weight:700;font-size:1.2rem;">RECEIPT</h4>
                <div style="color:#6b7280;font-size:.85rem;">Receipt #: <strong><?= e($receipt_number) ?></strong></div>
                <div style="color:#6b7280;font-size:.85rem;">Date: <?= e($paid_at) ?></div>
                <?php if ($pay['invoice_number']): ?>
                <div style="color:#6b7280;font-size:.85rem;">Invoice #: <?= e($pay['invoice_number']) ?></div>
                <?php endif; ?>
                <?php if ($pay['wo_number']): ?>
                <div style="color:#6b7280;font-size:.85rem;">Work Order #: <?= e($pay['wo_number']) ?></div>
                <?php endif; ?>
            </div>
            <div style="text-align:right;">
                <div style="background:#dcfce7;color:#166534;padding:6px 14px;border-radius:20px;font-size:.85rem;font-weight:700;">
                    ✓ PAID
                </div>
            </div>
        </div>

        <!-- Bill To -->
        <h6 style="font-weight:600;color:#374151;margin-bottom:8px;">Bill To</h6>
        <p style="margin:0 0 4px;font-weight:500;"><?= e($pay['cust_name'] ?? '—') ?></p>
        <?php if ($pay['cust_phone']): ?>
        <p style="margin:0;font-size:.9rem;color:#6b7280;"><?= e(fmt_phone($pay['cust_phone'])) ?></p>
        <?php endif; ?>
        <?php if ($pay['cust_email']): ?>
        <p style="margin:0;font-size:.9rem;color:#6b7280;"><?= e($pay['cust_email']) ?></p>
        <?php endif; ?>

        <hr class="my-3">

        <!-- Service Details -->
        <h6 style="font-weight:600;color:#374151;margin-bottom:8px;">Service Details</h6>
        <table class="table table-sm" style="font-size:.9rem;">
            <?php if ($pay['service_address']): ?>
            <tr>
                <td class="label-cell">Service Address</td>
                <td><?= e($pay['service_address'] . ($pay['service_city'] ? ', ' . $pay['service_city'] : '')) ?></td>
            </tr>
            <?php endif; ?>
            <?php if ($pay['size']): ?>
            <tr><td class="label-cell">Dumpster Size</td><td><?= e($pay['size']) ?></td></tr>
            <?php endif; ?>
            <tr><td class="label-cell">Payment Method</td>
                <td><?= e(ucfirst($pay['method'])) ?><?= $pay['method'] === 'card' && $pay['stripe_charge_id'] ? ' (Card via Stripe)' : '' ?></td></tr>
            <?php if ($pay['processed_by']): ?>
            <tr><td class="label-cell">Processed By</td><td><?= e($pay['processed_by']) ?></td></tr>
            <?php endif; ?>
            <?php if ($pay['notes']): ?>
            <tr><td class="label-cell">Notes</td><td><?= e($pay['notes']) ?></td></tr>
            <?php endif; ?>
        </table>

    </div><!-- /.receipt-body -->

    <!-- Total -->
    <div class="receipt-total d-flex justify-content-between">
        <span>Amount Paid</span>
        <span style="color:#22c55e;"><?= fmt_money($pay['amount']) ?></span>
    </div>

    <!-- Footer -->
    <div class="receipt-footer">
        <p style="margin:0 0 4px;font-weight:500;">Thank you for choosing <?= e($company_name) ?>!</p>
        <p style="margin:0;">Powered by Trash Panda Roll-Offs Manager</p>
    </div>

</div><!-- /.receipt-wrap -->

<?php if ($print_mode): ?>
<script>window.onload = function() { window.print(); };</script>
<?php endif; ?>

</body>
</html>
