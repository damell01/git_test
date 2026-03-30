<?php
/**
 * Payments – Manual Payment Entry
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once TMPL_PATH . '/layout.php';
require_login();

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $wo_id    = (int)($_POST['work_order_id'] ?? 0);
    $amount   = (float)($_POST['amount']      ?? 0);
    $method   = trim($_POST['method']         ?? 'cash');
    $notes    = trim($_POST['notes']          ?? '');

    if ($wo_id <= 0 || $amount <= 0) {
        flash_error('Work order and a valid amount are required.');
        redirect('create.php');
    }

    $wo = db_fetch('SELECT * FROM work_orders WHERE id = ? LIMIT 1', [$wo_id]);
    if (!$wo) {
        flash_error('Work order not found.');
        redirect('create.php');
    }

    // Find or create invoice
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
    } else {
        $inv_id = $inv['id'];
    }

    $pay_id = db_insert('payments', [
        'work_order_id' => $wo_id,
        'customer_id'   => $wo['customer_id'],
        'invoice_id'    => $inv_id,
        'amount'        => $amount,
        'method'        => $method,
        'status'        => 'paid',
        'notes'         => $notes,
        'paid_at'       => date('Y-m-d H:i:s'),
        'created_by'    => (int)($_SESSION['user_id'] ?? 0),
        'created_at'    => date('Y-m-d H:i:s'),
    ]);

    // Update invoice amount_paid and status
    $inv_row = db_fetch('SELECT * FROM invoices WHERE id = ? LIMIT 1', [(int)$inv_id]);
    if ($inv_row) {
        $new_paid  = (float)$inv_row['amount_paid'] + $amount;
        $inv_total = (float)$inv_row['amount'];
        $inv_status = $new_paid >= $inv_total ? 'paid' : ($new_paid > 0 ? 'partial' : 'unpaid');
        db_update('invoices', [
            'amount_paid' => $new_paid,
            'status'      => $inv_status,
            'updated_at'  => date('Y-m-d H:i:s'),
        ], 'id', (int)$inv_id);
    }

    log_activity('create', 'Manual payment ' . fmt_money($amount) . ' for WO# ' . $wo['wo_number'], 'payment', (int)$pay_id);

    // Optionally send receipt email
    if (!empty($_POST['send_receipt'])) {
        require_once INC_PATH . '/mailer.php';
        send_receipt_email((int)$pay_id);
    }

    flash_success('Payment recorded successfully.');
    redirect('index.php');
}

// ── Work orders for dropdown ───────────────────────────────────────────────────
$work_orders = db_fetchall(
    "SELECT wo.id, wo.wo_number, wo.cust_name, wo.amount,
            (SELECT COALESCE(SUM(amount_paid),0) FROM invoices WHERE work_order_id = wo.id) AS paid
     FROM work_orders wo
     WHERE wo.status NOT IN ('completed','canceled')
     ORDER BY wo.created_at DESC"
);

$preselect_wo = (int)($_GET['wo_id'] ?? 0);

layout_start('Record Payment', 'payments');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Record Manual Payment</h5>
    <a href="index.php" class="btn-tp-ghost btn-tp-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
</div>

<div class="tp-card" style="max-width:600px;">
    <form method="POST" action="create.php">
        <?= csrf_field() ?>

        <div class="mb-3">
            <label class="form-label" for="work_order_id">Work Order <span class="text-danger">*</span></label>
            <select name="work_order_id" id="work_order_id" class="form-select" required>
                <option value="">— Select work order —</option>
                <?php foreach ($work_orders as $wo): ?>
                <option value="<?= (int)$wo['id'] ?>" <?= $preselect_wo === (int)$wo['id'] ? 'selected' : '' ?>>
                    <?= e($wo['wo_number']) ?> — <?= e($wo['cust_name']) ?>
                    (Total: <?= fmt_money($wo['amount']) ?>, Paid: <?= fmt_money($wo['paid']) ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label" for="amount">Amount <span class="text-danger">*</span></label>
            <div class="input-group">
                <span class="input-group-text">$</span>
                <input type="number" id="amount" name="amount" class="form-control"
                       step="0.01" min="0.01" placeholder="0.00" required>
            </div>
        </div>

        <div class="mb-3">
            <label class="form-label" for="method">Payment Method</label>
            <select name="method" id="method" class="form-select">
                <option value="cash">Cash</option>
                <option value="check">Check</option>
                <option value="card">Card</option>
                <option value="other">Other</option>
            </select>
        </div>

        <div class="mb-3">
            <label class="form-label" for="notes">Notes</label>
            <textarea name="notes" id="notes" class="form-control" rows="3"
                      placeholder="Check number, transaction reference, etc."></textarea>
        </div>

        <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="send_receipt" id="send_receipt" value="1">
            <label class="form-check-label" for="send_receipt">Send receipt email to customer</label>
        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn-tp-primary">
                <i class="fa-solid fa-check"></i> Record Payment
            </button>
            <a href="index.php" class="btn-tp-ghost">Cancel</a>
        </div>
    </form>
</div>

<?php layout_end(); ?>
