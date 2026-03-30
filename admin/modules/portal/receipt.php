<?php
/**
 * Customer Portal – Payment Receipt
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once INC_PATH . '/db.php';
require_once INC_PATH . '/helpers.php';
require_once INC_PATH . '/auth.php';

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');

session_name('tp_portal_session');
session_start();

if (empty($_SESSION['portal_customer_id'])) {
    header('Location: ' . APP_URL . '/modules/portal/index.php');
    exit;
}

$cust_id = (int)$_SESSION['portal_customer_id'];
$pay_id  = (int)($_GET['id'] ?? 0);

$pay = db_fetch(
    'SELECT p.*, wo.wo_number, wo.service_address, wo.service_city,
            i.invoice_number,
            c.name AS cust_name
     FROM payments p
     LEFT JOIN work_orders wo ON p.work_order_id = wo.id
     LEFT JOIN invoices    i  ON p.invoice_id     = i.id
     LEFT JOIN customers   c  ON p.customer_id    = c.id
     WHERE p.id = ? AND p.customer_id = ? LIMIT 1',
    [$pay_id, $cust_id]
);

if (!$pay) {
    header('Location: ' . APP_URL . '/modules/portal/dashboard.php');
    exit;
}

$company_name   = get_setting('company_name',    'Trash Panda Roll-Offs');
$company_phone  = get_setting('company_phone',   '');
$company_email  = get_setting('company_email',   '');
$receipt_number = 'REC-' . str_pad((string)$pay['id'], 5, '0', STR_PAD_LEFT);
$paid_at        = $pay['paid_at'] ? date('F j, Y g:i A', strtotime($pay['paid_at'])) : date('F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Receipt | <?= htmlspecialchars($company_name, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <style>
        @media print { .no-print { display: none !important; } body { background: #fff !important; } }
        body { background: #f3f4f6; font-family: Arial, sans-serif; }
        .receipt-wrap { max-width: 600px; margin: 32px auto; background: #fff; border-radius: 10px;
                        box-shadow: 0 4px 24px rgba(0,0,0,.1); overflow: hidden; }
        .receipt-header { background: #1a1d27; color: #e5e7eb; padding: 24px 32px; }
        .receipt-header h1 { color: #f97316; font-size: 1.4rem; font-weight: 700; margin: 0 0 4px; }
        .receipt-body { padding: 28px 32px; }
        .receipt-total { background: #f9fafb; border-top: 2px solid #e5e7eb; padding: 18px 32px;
                         font-size: 1.1rem; font-weight: 700; }
        .receipt-footer { background: #f3f4f6; padding: 16px 32px; font-size: .8rem;
                          color: #9ca3af; text-align: center; }
    </style>
</head>
<body>

<div class="no-print text-center mt-3 mb-2">
    <button onclick="window.print()" class="btn btn-sm btn-outline-secondary me-2">🖨 Print</button>
    <a href="dashboard.php" class="btn btn-sm btn-outline-dark">← My Account</a>
</div>

<div class="receipt-wrap">
    <div class="receipt-header">
        <h1>🗑 <?= htmlspecialchars($company_name, ENT_QUOTES, 'UTF-8') ?></h1>
        <div style="font-size:.82rem;color:#9ca3af;">
            <?php if ($company_phone): ?>📞 <?= htmlspecialchars($company_phone, ENT_QUOTES, 'UTF-8') ?>&nbsp;&nbsp;<?php endif; ?>
            <?php if ($company_email): ?>✉ <?= htmlspecialchars($company_email, ENT_QUOTES, 'UTF-8') ?><?php endif; ?>
        </div>
    </div>

    <div class="receipt-body">
        <div class="d-flex justify-content-between align-items-start mb-3">
            <div>
                <h4 class="fw-bold" style="font-size:1.1rem;">RECEIPT</h4>
                <div class="text-muted" style="font-size:.85rem;">Receipt #: <strong><?= htmlspecialchars($receipt_number, ENT_QUOTES, 'UTF-8') ?></strong></div>
                <div class="text-muted" style="font-size:.85rem;">Date: <?= htmlspecialchars($paid_at, ENT_QUOTES, 'UTF-8') ?></div>
                <?php if ($pay['invoice_number']): ?>
                <div class="text-muted" style="font-size:.85rem;">Invoice #: <?= htmlspecialchars($pay['invoice_number'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
                <?php if ($pay['wo_number']): ?>
                <div class="text-muted" style="font-size:.85rem;">Work Order #: <?= htmlspecialchars($pay['wo_number'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>
            </div>
            <div>
                <span class="badge bg-success" style="font-size:.85rem;padding:8px 14px;">✓ PAID</span>
            </div>
        </div>

        <hr>

        <table class="table table-sm" style="font-size:.9rem;">
            <tr><td class="text-muted" style="width:140px;">Paid By</td><td><?= htmlspecialchars($pay['cust_name'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td></tr>
            <?php if ($pay['service_address']): ?>
            <tr><td class="text-muted">Service Address</td>
                <td><?= htmlspecialchars($pay['service_address'] . ($pay['service_city'] ? ', '.$pay['service_city'] : ''), ENT_QUOTES, 'UTF-8') ?></td></tr>
            <?php endif; ?>
            <tr><td class="text-muted">Method</td><td><?= htmlspecialchars(ucfirst($pay['method']), ENT_QUOTES, 'UTF-8') ?></td></tr>
        </table>
    </div>

    <div class="receipt-total d-flex justify-content-between">
        <span>Amount Paid</span>
        <span style="color:#22c55e;">$<?= number_format((float)$pay['amount'], 2) ?></span>
    </div>

    <div class="receipt-footer">
        <p style="margin:0 0 2px;font-weight:500;">Thank you for choosing <?= htmlspecialchars($company_name, ENT_QUOTES, 'UTF-8') ?>!</p>
        <p style="margin:0;">Powered by Trash Panda Roll-Offs Manager</p>
    </div>
</div>

</body>
</html>
