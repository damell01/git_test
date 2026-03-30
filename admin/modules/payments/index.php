<?php
/**
 * Payments – Payment History List
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once TMPL_PATH . '/layout.php';
require_login();

// ── Filters ───────────────────────────────────────────────────────────────────
$filter_status = trim($_GET['status'] ?? '');
$filter_from   = trim($_GET['date_from'] ?? '');
$filter_to     = trim($_GET['date_to']   ?? '');

$where  = ['1=1'];
$params = [];

if ($filter_status !== '') {
    $where[]  = 'p.status = ?';
    $params[] = $filter_status;
}
if ($filter_from !== '') {
    $where[]  = 'DATE(p.created_at) >= ?';
    $params[] = $filter_from;
}
if ($filter_to !== '') {
    $where[]  = 'DATE(p.created_at) <= ?';
    $params[] = $filter_to;
}

$where_sql = implode(' AND ', $where);

// ── Pagination ────────────────────────────────────────────────────────────────
$total_row = db_fetch(
    "SELECT COUNT(*) AS cnt FROM payments p WHERE $where_sql",
    $params
);
$total = (int)($total_row['cnt'] ?? 0);
$pager = paginate($total, (int)($_GET['page'] ?? 1), 25);

// ── Fetch Payments ────────────────────────────────────────────────────────────
$payments = db_fetchall(
    "SELECT p.*,
            wo.wo_number,
            c.name  AS customer_name,
            u.name  AS created_by_name,
            i.invoice_number
     FROM payments p
     LEFT JOIN work_orders wo ON p.work_order_id = wo.id
     LEFT JOIN customers   c  ON p.customer_id   = c.id
     LEFT JOIN users       u  ON p.created_by    = u.id
     LEFT JOIN invoices    i  ON p.invoice_id     = i.id
     WHERE $where_sql
     ORDER BY p.created_at DESC
     LIMIT {$pager['per_page']} OFFSET {$pager['offset']}",
    $params
);

// ── Monthly Total ─────────────────────────────────────────────────────────────
$monthly = db_fetch(
    "SELECT SUM(amount) AS total FROM payments
     WHERE status = 'paid' AND MONTH(paid_at) = MONTH(NOW()) AND YEAR(paid_at) = YEAR(NOW())"
);
$monthly_total = (float)($monthly['total'] ?? 0);

layout_start('Payments', 'payments');
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <h5 class="mb-0">Payment History</h5>
    <div class="d-flex gap-2">
        <a href="create.php" class="btn-tp-ghost btn-tp-sm">
            <i class="fa-solid fa-plus"></i> Manual Payment
        </a>
    </div>
</div>

<!-- KPI: Monthly total -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="tp-card text-center">
            <div style="font-size:.8rem;color:var(--gy);text-transform:uppercase;letter-spacing:.06em;">Revenue This Month</div>
            <div style="font-size:1.8rem;font-weight:700;color:#22c55e;"><?= fmt_money($monthly_total) ?></div>
        </div>
    </div>
</div>

<!-- Filters -->
<form method="GET" class="tp-card mb-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-3">
            <label class="form-label form-label-sm">Status</label>
            <select name="status" class="form-select form-select-sm">
                <option value="">All Statuses</option>
                <?php foreach (['pending','paid','failed','refunded'] as $s): ?>
                <option value="<?= e($s) ?>" <?= $filter_status === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label form-label-sm">From</label>
            <input type="date" name="date_from" class="form-control form-control-sm" value="<?= e($filter_from) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label form-label-sm">To</label>
            <input type="date" name="date_to" class="form-control form-control-sm" value="<?= e($filter_to) ?>">
        </div>
        <div class="col-md-3 d-flex gap-2">
            <button type="submit" class="btn-tp-primary btn-tp-sm">Filter</button>
            <a href="index.php" class="btn-tp-ghost btn-tp-sm">Reset</a>
        </div>
    </div>
</form>

<!-- Table -->
<div class="tp-card p-0">
    <div class="table-responsive">
        <table class="table tp-table mb-0">
            <thead>
                <tr>
                    <th>#</th>
                    <th>WO#</th>
                    <th>Customer</th>
                    <th>Invoice</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($payments)): ?>
                <tr><td colspan="9" class="text-center py-4" style="color:var(--gy);">No payments found.</td></tr>
            <?php else: ?>
            <?php foreach ($payments as $pay): ?>
                <tr>
                    <td><?= (int)$pay['id'] ?></td>
                    <td>
                        <?php if ($pay['wo_number']): ?>
                        <a href="<?= e(APP_URL) ?>/modules/work_orders/view.php?id=<?= (int)$pay['work_order_id'] ?>">
                            <?= e($pay['wo_number']) ?>
                        </a>
                        <?php else: ?>—<?php endif; ?>
                    </td>
                    <td><?= e($pay['customer_name'] ?? '—') ?></td>
                    <td><?= e($pay['invoice_number'] ?? '—') ?></td>
                    <td><?= fmt_money($pay['amount']) ?></td>
                    <td><?= e(ucfirst($pay['method'])) ?></td>
                    <td><?= payment_status_badge($pay['status']) ?></td>
                    <td><?= fmt_date($pay['created_at']) ?></td>
                    <td>
                        <a href="receipt.php?id=<?= (int)$pay['id'] ?>" class="btn-tp-ghost btn-tp-sm" title="Receipt">
                            <i class="fa-solid fa-receipt"></i>
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination -->
<?php if ($pager['pages'] > 1): ?>
<nav class="mt-3">
    <ul class="pagination pagination-sm justify-content-center">
        <?php for ($p = 1; $p <= $pager['pages']; $p++): ?>
        <li class="page-item <?= $p === $pager['page'] ? 'active' : '' ?>">
            <a class="page-link" href="?page=<?= $p ?>&status=<?= urlencode($filter_status) ?>&date_from=<?= urlencode($filter_from) ?>&date_to=<?= urlencode($filter_to) ?>">
                <?= $p ?>
            </a>
        </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>

<?php
layout_end();

function payment_status_badge(string $status): string
{
    $map = [
        'pending'  => ['Pending',  'warning'],
        'paid'     => ['Paid',     'success'],
        'failed'   => ['Failed',   'danger'],
        'refunded' => ['Refunded', 'secondary'],
    ];
    [$label, $color] = $map[$status] ?? [ucfirst($status), 'secondary'];
    return '<span class="badge bg-' . $color . '">' . e($label) . '</span>';
}
