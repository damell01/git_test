<?php
/**
 * Work Orders – Invoice View / Generate
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once INC_PATH . '/mailer.php';
require_once TMPL_PATH . '/layout.php';
require_login();

$wo_id = (int)($_GET['wo_id'] ?? 0);
if (!$wo_id) {
    flash_error('Invalid work order.');
    redirect(APP_URL . '/modules/work_orders/index.php');
}

$wo = db_fetch(
    'SELECT wo.*,
            c.name AS cust_name_db, c.email AS cust_email_db,
            c.address AS cust_address, c.city AS cust_city, c.state AS cust_state, c.zip AS cust_zip,
            c.phone AS cust_phone_db
     FROM work_orders wo
     LEFT JOIN customers c ON wo.customer_id = c.id
     WHERE wo.id = ? LIMIT 1',
    [$wo_id]
);

if (!$wo) {
    flash_error('Work order not found.');
    redirect(APP_URL . '/modules/work_orders/index.php');
}

// ── Auto-create invoice if it doesn't exist ───────────────────────────────────
$inv = db_fetch('SELECT * FROM invoices WHERE work_order_id = ? LIMIT 1', [$wo_id]);

if (!$inv) {
    $inv_number = next_number('INV', 'invoices', 'invoice_number');
    $inv_id = db_insert('invoices', [
        'invoice_number' => $inv_number,
        'work_order_id'  => $wo_id,
        'customer_id'    => $wo['customer_id'],
        'amount'         => $wo['amount'],
        'amount_paid'    => 0,
        'status'         => 'unpaid',
        'due_date'       => date('Y-m-d', strtotime('+30 days')),
        'created_at'     => date('Y-m-d H:i:s'),
        'updated_at'     => date('Y-m-d H:i:s'),
    ]);
    $inv = db_fetch('SELECT * FROM invoices WHERE id = ? LIMIT 1', [(int)$inv_id]);
    log_activity('create', 'Invoice ' . $inv_number . ' created for WO# ' . $wo['wo_number'], 'invoice', (int)$inv_id);
}

// ── Handle actions ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $action = trim($_POST['action'] ?? '');

    if ($action === 'send_email') {
        $result = send_invoice_email((int)$inv['id']);
        if ($result) {
            flash_success('Invoice emailed to customer.');
        } else {
            flash_error('Failed to send email. Ensure customer has a valid email address.');
        }
        redirect(APP_URL . '/modules/work_orders/invoice.php?wo_id=' . $wo_id);
    }

    if ($action === 'mark_paid') {
        $method = trim($_POST['method'] ?? 'cash');
        $pay_id = db_insert('payments', [
            'work_order_id' => $wo_id,
            'customer_id'   => $wo['customer_id'],
            'invoice_id'    => $inv['id'],
            'amount'        => (float)$inv['amount'] - (float)$inv['amount_paid'],
            'method'        => $method,
            'status'        => 'paid',
            'paid_at'       => date('Y-m-d H:i:s'),
            'created_by'    => (int)($_SESSION['user_id'] ?? 0),
            'created_at'    => date('Y-m-d H:i:s'),
        ]);
        db_update('invoices', [
            'amount_paid' => $inv['amount'],
            'status'      => 'paid',
            'updated_at'  => date('Y-m-d H:i:s'),
        ], 'id', (int)$inv['id']);
        log_activity('payment', 'Invoice marked paid (' . $method . ') for WO# ' . $wo['wo_number'], 'invoice', (int)$inv['id']);
        flash_success('Invoice marked as paid.');
        redirect(APP_URL . '/modules/work_orders/invoice.php?wo_id=' . $wo_id);
    }

    if ($action === 'void') {
        db_update('invoices', ['status' => 'void', 'updated_at' => date('Y-m-d H:i:s')], 'id', (int)$inv['id']);
        log_activity('update', 'Invoice voided for WO# ' . $wo['wo_number'], 'invoice', (int)$inv['id']);
        flash_success('Invoice voided.');
        redirect(APP_URL . '/modules/work_orders/invoice.php?wo_id=' . $wo_id);
    }
}

// ── Payment history for this invoice ─────────────────────────────────────────
$payments = db_fetchall(
    "SELECT p.*, u.name AS created_by_name
     FROM payments p
     LEFT JOIN users u ON p.created_by = u.id
     WHERE p.invoice_id = ?
     ORDER BY p.created_at ASC",
    [(int)$inv['id']]
);

// ── Company settings ──────────────────────────────────────────────────────────
$company_name    = get_setting('company_name',    'Trash Panda Roll-Offs');
$company_phone   = get_setting('company_phone',   '');
$company_email   = get_setting('company_email',   '');
$company_address = get_setting('company_address', '');
$tax_rate        = (float)get_setting('tax_rate', '0');

// Derived values
$cust_name  = $wo['cust_name']  ?: ($wo['cust_name_db']  ?? '—');
$cust_email = $wo['cust_email'] ?: ($wo['cust_email_db'] ?? '—');
$cust_phone = $wo['cust_phone'] ?: ($wo['cust_phone_db'] ?? '');

$is_print = isset($_GET['print']);

if ($is_print):
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice <?= e($inv['invoice_number']) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <style>
        @media print { .no-print { display: none !important; } }
        body { font-family: Arial, sans-serif; color: #1f2937; }
        .inv-header { background: #1a1d27; color: #fff; padding: 24px 32px; margin-bottom: 24px; }
        .inv-header h1 { color: #f97316; font-size: 1.5rem; font-weight: 700; margin: 0; }
    </style>
</head>
<body>
<div class="no-print text-center mt-3 mb-2">
    <button onclick="window.print()" class="btn btn-sm btn-secondary">🖨 Print</button>
    <a href="invoice.php?wo_id=<?= $wo_id ?>" class="btn btn-sm btn-outline-dark ms-2">← Back</a>
</div>
<?php else: ?>
<?php layout_start('Invoice ' . $inv['invoice_number'], 'work_orders'); ?>
<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h5 class="mb-0">Invoice <?= e($inv['invoice_number']) ?></h5>
    <div class="d-flex gap-2 flex-wrap">
        <a href="invoice.php?wo_id=<?= $wo_id ?>&print=1" class="btn-tp-ghost btn-tp-sm" target="_blank">
            <i class="fa-solid fa-print"></i> Print
        </a>
        <a href="<?= e(APP_URL) ?>/modules/payments/charge.php?invoice_id=<?= (int)$inv['id'] ?>" class="btn-tp-primary btn-tp-sm">
            <i class="fa-solid fa-credit-card"></i> Charge via Stripe
        </a>
    </div>
</div>
<?php endif; ?>

<!-- Invoice document -->
<div style="max-width:780px;background:#fff;border-radius:10px;box-shadow:0 2px 12px rgba(0,0,0,.08);overflow:hidden;margin-bottom:24px;">

    <!-- Header -->
    <div style="background:#1a1d27;color:#e5e7eb;padding:28px 36px;">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <h2 style="color:#f97316;font-size:1.5rem;font-weight:700;margin:0 0 4px;">
                    🗑 <?= e($company_name) ?>
                </h2>
                <?php if ($company_address): ?><div style="font-size:.85rem;color:#9ca3af;"><?= e($company_address) ?></div><?php endif; ?>
                <?php if ($company_phone): ?><div style="font-size:.85rem;color:#9ca3af;">📞 <?= e($company_phone) ?></div><?php endif; ?>
                <?php if ($company_email): ?><div style="font-size:.85rem;color:#9ca3af;">✉ <?= e($company_email) ?></div><?php endif; ?>
            </div>
            <div style="text-align:right;">
                <div style="font-size:1.3rem;font-weight:700;color:#f97316;">INVOICE</div>
                <div style="font-size:.9rem;color:#9ca3af;">
                    #<?= e($inv['invoice_number']) ?><br>
                    Date: <?= e(date('M j, Y', strtotime($inv['created_at']))) ?><br>
                    <?php if ($inv['due_date']): ?>Due: <?= e(date('M j, Y', strtotime($inv['due_date']))) ?><br><?php endif; ?>
                </div>
                <?php
                $status_colors = ['unpaid'=>'warning','partial'=>'info','paid'=>'success','void'=>'secondary'];
                $sc = $status_colors[$inv['status']] ?? 'secondary';
                ?>
                <span class="badge bg-<?= $sc ?> mt-1" style="font-size:.85rem;padding:6px 12px;">
                    <?= e(ucfirst($inv['status'])) ?>
                </span>
            </div>
        </div>
    </div>

    <div style="padding:28px 36px;">

        <!-- Bill To / Service -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <h6 style="font-weight:700;color:#374151;margin-bottom:8px;">Bill To</h6>
                <div style="font-weight:600;"><?= e($cust_name) ?></div>
                <?php if ($cust_phone): ?><div style="font-size:.9rem;color:#6b7280;"><?= e(fmt_phone($cust_phone)) ?></div><?php endif; ?>
                <div style="font-size:.9rem;color:#6b7280;"><?= e($cust_email) ?></div>
                <?php if ($wo['cust_address'] ?? $wo['service_address']): ?>
                <div style="font-size:.9rem;color:#6b7280;"><?= e($wo['service_address']) ?><?= $wo['service_city'] ? ', ' . e($wo['service_city']) : '' ?></div>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <h6 style="font-weight:700;color:#374151;margin-bottom:8px;">Service Details</h6>
                <div style="font-size:.9rem;">
                    <div>Work Order: <strong><?= e($wo['wo_number']) ?></strong></div>
                    <div>Address: <?= e($wo['service_address'] . ($wo['service_city'] ? ', '.$wo['service_city'] : '')) ?></div>
                    <?php if ($wo['size']): ?><div>Size: <?= e($wo['size']) ?></div><?php endif; ?>
                    <?php if ($wo['delivery_date']): ?><div>Delivery: <?= e(date('M j, Y', strtotime($wo['delivery_date']))) ?></div><?php endif; ?>
                    <?php if ($wo['pickup_date']): ?><div>Pickup: <?= e(date('M j, Y', strtotime($wo['pickup_date']))) ?></div><?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Line items -->
        <table class="table" style="font-size:.9rem;">
            <thead style="background:#f9fafb;">
                <tr>
                    <th>Description</th>
                    <th class="text-end">Amount</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Dumpster Rental — <?= e($wo['size'] ?? 'Standard') ?></td>
                    <td class="text-end"><?= fmt_money($wo['amount']) ?></td>
                </tr>
            </tbody>
            <tfoot style="border-top:2px solid #e5e7eb;">
                <tr>
                    <td class="text-end fw-semibold" style="padding-top:12px;">Subtotal</td>
                    <td class="text-end fw-semibold" style="padding-top:12px;"><?= fmt_money($wo['amount']) ?></td>
                </tr>
                <?php if ($tax_rate > 0): ?>
                <tr>
                    <td class="text-end text-muted">Tax (<?= e(number_format($tax_rate, 2)) ?>%)</td>
                    <td class="text-end text-muted"><?= fmt_money($wo['amount'] * $tax_rate / 100) ?></td>
                </tr>
                <?php endif; ?>
                <tr>
                    <td class="text-end fw-bold" style="font-size:1.1rem;">Total</td>
                    <td class="text-end fw-bold" style="color:#f97316;font-size:1.1rem;"><?= fmt_money($inv['amount']) ?></td>
                </tr>
                <?php if ((float)$inv['amount_paid'] > 0): ?>
                <tr>
                    <td class="text-end text-muted">Amount Paid</td>
                    <td class="text-end" style="color:#22c55e;">— <?= fmt_money($inv['amount_paid']) ?></td>
                </tr>
                <tr>
                    <td class="text-end fw-bold">Balance Due</td>
                    <td class="text-end fw-bold" style="font-size:1.2rem;">
                        <?= fmt_money(max(0, (float)$inv['amount'] - (float)$inv['amount_paid'])) ?>
                    </td>
                </tr>
                <?php endif; ?>
            </tfoot>
        </table>

    </div>
</div>

<?php if (!$is_print): ?>

<!-- Actions -->
<div class="tp-card mb-3" style="max-width:780px;">
    <h6 class="fw-bold mb-3">Actions</h6>
    <div class="d-flex gap-2 flex-wrap">

        <!-- Send Invoice Email -->
        <?php if ($cust_email && $cust_email !== '—'): ?>
        <form method="POST" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="send_email">
            <button type="submit" class="btn-tp-ghost btn-tp-sm">
                <i class="fa-solid fa-envelope"></i> Send Invoice by Email
            </button>
        </form>
        <?php endif; ?>

        <!-- Mark Paid (cash/check) -->
        <?php if ($inv['status'] !== 'paid' && $inv['status'] !== 'void'): ?>
        <button type="button" class="btn-tp-ghost btn-tp-sm" data-bs-toggle="modal" data-bs-target="#markPaidModal">
            <i class="fa-solid fa-money-bill"></i> Mark as Paid (Cash/Check)
        </button>

        <!-- Charge via Stripe -->
        <a href="<?= e(APP_URL) ?>/modules/payments/charge.php?invoice_id=<?= (int)$inv['id'] ?>" class="btn-tp-primary btn-tp-sm">
            <i class="fa-solid fa-credit-card"></i> Charge via Stripe
        </a>
        <?php endif; ?>

        <?php if ($inv['status'] !== 'void' && $inv['status'] !== 'paid'): ?>
        <form method="POST" class="d-inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="void">
            <button type="submit" class="btn-tp-ghost btn-tp-sm" style="border-color:#ef4444;color:#ef4444;"
                    onclick="return confirm('Void this invoice?')">
                <i class="fa-solid fa-ban"></i> Void Invoice
            </button>
        </form>
        <?php endif; ?>

    </div>
</div>

<!-- Payment History -->
<?php if (!empty($payments)): ?>
<div class="tp-card" style="max-width:780px;">
    <h6 class="fw-bold mb-3">Payment History</h6>
    <div class="table-responsive">
        <table class="table tp-table table-sm mb-0">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>By</th>
                    <th>Receipt</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($payments as $pay): ?>
            <tr>
                <td><?= fmt_date($pay['paid_at'] ?: $pay['created_at']) ?></td>
                <td style="color:#22c55e;font-weight:600;"><?= fmt_money($pay['amount']) ?></td>
                <td><?= e(ucfirst($pay['method'])) ?></td>
                <td><span class="badge bg-<?= $pay['status'] === 'paid' ? 'success' : 'secondary' ?>"><?= e(ucfirst($pay['status'])) ?></span></td>
                <td><?= e($pay['created_by_name'] ?? '—') ?></td>
                <td><a href="<?= e(APP_URL) ?>/modules/payments/receipt.php?id=<?= (int)$pay['id'] ?>"><i class="fa-solid fa-receipt"></i></a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Mark Paid Modal -->
<div class="modal fade" id="markPaidModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title fw-bold">Mark Invoice as Paid</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="mark_paid">
                <div class="modal-body">
                    <label class="form-label">Payment Method</label>
                    <select name="method" class="form-select">
                        <option value="cash">Cash</option>
                        <option value="check">Check</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn-tp-primary btn-tp-sm">Mark Paid</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php layout_end(); ?>

<?php else: ?>
<script>window.onload = function() { window.print(); };</script>
</body>
</html>
<?php endif; ?>
