<?php
/**
 * Reports – Dashboard
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once TMPL_PATH . '/layout.php';
require_login();

// ── Date range filter ─────────────────────────────────────────────────────────
$date_from = trim($_GET['date_from'] ?? '');
$date_to   = trim($_GET['date_to']   ?? '');

// Default: first day of current month → today
if ($date_from === '' || !strtotime($date_from)) {
    $date_from = date('Y-m-01');
}
if ($date_to === '' || !strtotime($date_to)) {
    $date_to = date('Y-m-d');
}
$date_from = date('Y-m-d', strtotime($date_from));
$date_to   = date('Y-m-d', strtotime($date_to));

// ── Section 1: Leads by Status ────────────────────────────────────────────────
$lead_status_rows = db_fetchall(
    "SELECT status, COUNT(*) AS cnt
     FROM leads
     WHERE archived = 0
     GROUP BY status
     ORDER BY FIELD(status,'new','contacted','quoted','won','lost')"
);

// ── Section 2: Work Orders by Status ─────────────────────────────────────────
$wo_status_rows = db_fetchall(
    "SELECT status, COUNT(*) AS cnt
     FROM work_orders
     WHERE created_at BETWEEN ? AND ?
     GROUP BY status",
    [$date_from . ' 00:00:00', $date_to . ' 23:59:59']
);

// ── Section 3: Revenue Summary ────────────────────────────────────────────────
$revenue_row = db_fetch(
    "SELECT COUNT(*) AS wo_count,
            COALESCE(SUM(amount), 0) AS total
     FROM work_orders
     WHERE status = 'completed'
       AND updated_at BETWEEN ? AND ?",
    [$date_from . ' 00:00:00', $date_to . ' 23:59:59']
);

// ── Section 4: Upcoming Deliveries (next 7 days) ──────────────────────────────
$today         = date('Y-m-d');
$in_7_days     = date('Y-m-d', strtotime('+7 days'));

$upcoming_deliveries = db_fetchall(
    "SELECT wo.id, wo.wo_number, wo.customer_name, wo.service_address,
            wo.delivery_date, wo.size, wo.status
     FROM work_orders wo
     WHERE wo.delivery_date BETWEEN ? AND ?
       AND wo.status NOT IN ('completed','canceled','picked_up')
     ORDER BY wo.delivery_date ASC",
    [$today, $in_7_days]
);

// ── Section 5: Upcoming Pickups (next 7 days) ─────────────────────────────────
$upcoming_pickups = db_fetchall(
    "SELECT wo.id, wo.wo_number, wo.customer_name, wo.service_address,
            wo.pickup_date, wo.size, wo.status
     FROM work_orders wo
     WHERE wo.pickup_date BETWEEN ? AND ?
       AND wo.status NOT IN ('completed','canceled','picked_up')
     ORDER BY wo.pickup_date ASC",
    [$today, $in_7_days]
);

// ── Section 6: Overdue Pickups ────────────────────────────────────────────────
$overdue_pickups = db_fetchall(
    "SELECT wo.id, wo.wo_number, wo.customer_name, wo.service_address,
            wo.pickup_date, wo.size, wo.status,
            DATEDIFF(CURDATE(), wo.pickup_date) AS days_overdue
     FROM work_orders wo
     WHERE wo.pickup_date < CURDATE()
       AND wo.status NOT IN ('picked_up','completed','canceled')
     ORDER BY wo.pickup_date ASC"
);

// ── Section 7: WO Count by Month (last 6 months) ─────────────────────────────
$monthly_counts = db_fetchall(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
            COUNT(*) AS cnt
     FROM work_orders
     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
     GROUP BY month
     ORDER BY month ASC"
);
$max_count = 0;
foreach ($monthly_counts as $mc) {
    if ((int)$mc['cnt'] > $max_count) {
        $max_count = (int)$mc['cnt'];
    }
}

layout_start('Reports', 'reports');
?>

<style>
/* ── KPI cards ── */
.kpi-row {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 1.5rem;
}
.kpi-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    padding: 16px 20px;
    min-width: 140px;
    flex: 1 1 140px;
}
.kpi-card .kpi-value {
    font-size: 1.8rem;
    font-weight: 700;
    line-height: 1.1;
    color: #111827;
}
.kpi-card .kpi-label {
    font-size: .75rem;
    color: #6b7280;
    margin-top: 4px;
    text-transform: uppercase;
    letter-spacing: .05em;
}

/* ── Bar chart ── */
.bar-chart {
    display: flex;
    align-items: flex-end;
    gap: 10px;
    height: 160px;
    padding: 0 4px;
}
.bar-col {
    display: flex;
    flex-direction: column;
    align-items: center;
    flex: 1;
}
.bar-count {
    font-size: .72rem;
    font-weight: 700;
    color: #374151;
    margin-bottom: 3px;
}
.bar-fill {
    background: #16a34a;
    border-radius: 4px 4px 0 0;
    width: 100%;
    min-height: 4px;
    transition: height .3s;
}
.bar-label {
    font-size: .68rem;
    color: #6b7280;
    margin-top: 5px;
    text-align: center;
}
</style>

<!-- Date range filter -->
<div class="tp-card mb-4">
    <form method="GET" action="index.php" class="row g-2 align-items-end">
        <div class="col-auto">
            <label class="form-label mb-1" for="date_from">From</label>
            <input type="date" id="date_from" name="date_from"
                   class="form-control form-control-sm"
                   value="<?= e($date_from) ?>">
        </div>
        <div class="col-auto">
            <label class="form-label mb-1" for="date_to">To</label>
            <input type="date" id="date_to" name="date_to"
                   class="form-control form-control-sm"
                   value="<?= e($date_to) ?>">
        </div>
        <div class="col-auto">
            <button type="submit" class="btn-tp-primary btn-tp-sm">
                <i class="fa-solid fa-magnifying-glass"></i> Filter
            </button>
            <a href="index.php" class="btn-tp-ghost btn-tp-sm ms-1">Reset</a>
        </div>
    </form>
</div>

<!-- ── Section 1: Leads by Status ────────────────────────────────────────── -->
<h6 class="section-heading mb-2">
    <i class="fa-solid fa-funnel me-1"></i> Leads by Status
</h6>
<div class="kpi-row mb-4">
    <?php if (empty($lead_status_rows)): ?>
        <p class="text-muted">No lead data available.</p>
    <?php else: ?>
        <?php foreach ($lead_status_rows as $row): ?>
        <div class="kpi-card">
            <div class="kpi-value"><?= (int)$row['cnt'] ?></div>
            <div class="kpi-label"><?= status_badge($row['status']) ?></div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ── Section 2: Work Orders by Status ──────────────────────────────────── -->
<h6 class="section-heading mb-2">
    <i class="fa-solid fa-clipboard-list me-1"></i> Work Orders by Status
    <small class="text-muted ms-1">(<?= e(fmt_date($date_from)) ?> – <?= e(fmt_date($date_to)) ?>)</small>
</h6>
<div class="kpi-row mb-4">
    <?php if (empty($wo_status_rows)): ?>
        <p class="text-muted">No work order data for this period.</p>
    <?php else: ?>
        <?php foreach ($wo_status_rows as $row): ?>
        <div class="kpi-card">
            <div class="kpi-value"><?= (int)$row['cnt'] ?></div>
            <div class="kpi-label"><?= status_badge($row['status']) ?></div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- ── Section 3: Revenue Summary ────────────────────────────────────────── -->
<h6 class="section-heading mb-2">
    <i class="fa-solid fa-dollar-sign me-1"></i> Revenue Summary
    <small class="text-muted ms-1">(<?= e(fmt_date($date_from)) ?> – <?= e(fmt_date($date_to)) ?>)</small>
</h6>
<div class="kpi-row mb-4">
    <div class="kpi-card" style="border-left:4px solid #16a34a;">
        <div class="kpi-value"><?= e(fmt_money($revenue_row['total'] ?? 0)) ?></div>
        <div class="kpi-label">Total Revenue</div>
    </div>
    <div class="kpi-card" style="border-left:4px solid #2563eb;">
        <div class="kpi-value"><?= (int)($revenue_row['wo_count'] ?? 0) ?></div>
        <div class="kpi-label">Completed Work Orders</div>
    </div>
</div>

<!-- ── Section 4: Upcoming Deliveries ────────────────────────────────────── -->
<h6 class="section-heading mb-2">
    <i class="fa-solid fa-truck-arrow-right text-success me-1"></i>
    Upcoming Deliveries <small class="text-muted">(next 7 days)</small>
</h6>
<div class="tp-card mb-4">
    <?php if (empty($upcoming_deliveries)): ?>
        <p class="text-muted mb-0">No deliveries in the next 7 days.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table tp-table table-sm mb-0">
            <thead>
                <tr>
                    <th>WO#</th>
                    <th>Customer</th>
                    <th>Address</th>
                    <th>Delivery Date</th>
                    <th>Size</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($upcoming_deliveries as $wo): ?>
                <tr>
                    <td>
                        <a href="<?= e(APP_URL) ?>/modules/work_orders/view.php?id=<?= (int)$wo['id'] ?>">
                            <?= e($wo['wo_number']) ?>
                        </a>
                    </td>
                    <td><?= e($wo['customer_name']) ?></td>
                    <td><?= e($wo['service_address'] ?? '—') ?></td>
                    <td><?= e(fmt_date($wo['delivery_date'])) ?></td>
                    <td><?= e($wo['size'] ?? '—') ?></td>
                    <td><?= status_badge($wo['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ── Section 5: Upcoming Pickups ───────────────────────────────────────── -->
<h6 class="section-heading mb-2">
    <i class="fa-solid fa-dumpster me-1" style="color:#7c3aed;"></i>
    Upcoming Pickups <small class="text-muted">(next 7 days)</small>
</h6>
<div class="tp-card mb-4">
    <?php if (empty($upcoming_pickups)): ?>
        <p class="text-muted mb-0">No pickups in the next 7 days.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table tp-table table-sm mb-0">
            <thead>
                <tr>
                    <th>WO#</th>
                    <th>Customer</th>
                    <th>Address</th>
                    <th>Pickup Date</th>
                    <th>Size</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($upcoming_pickups as $wo): ?>
                <tr>
                    <td>
                        <a href="<?= e(APP_URL) ?>/modules/work_orders/view.php?id=<?= (int)$wo['id'] ?>">
                            <?= e($wo['wo_number']) ?>
                        </a>
                    </td>
                    <td><?= e($wo['customer_name']) ?></td>
                    <td><?= e($wo['service_address'] ?? '—') ?></td>
                    <td><?= e(fmt_date($wo['pickup_date'])) ?></td>
                    <td><?= e($wo['size'] ?? '—') ?></td>
                    <td><?= status_badge($wo['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ── Section 6: Overdue Pickups ────────────────────────────────────────── -->
<h6 class="section-heading mb-2">
    <i class="fa-solid fa-triangle-exclamation text-danger me-1"></i>
    Overdue Pickups
</h6>
<div class="tp-card mb-4">
    <?php if (empty($overdue_pickups)): ?>
        <p class="text-muted mb-0">No overdue pickups. </p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table tp-table table-sm mb-0">
            <thead>
                <tr>
                    <th>WO#</th>
                    <th>Customer</th>
                    <th>Address</th>
                    <th>Pickup Date</th>
                    <th>Days Overdue</th>
                    <th>Size</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($overdue_pickups as $wo): ?>
                <tr style="background:#fef2f2;">
                    <td>
                        <a href="<?= e(APP_URL) ?>/modules/work_orders/view.php?id=<?= (int)$wo['id'] ?>">
                            <?= e($wo['wo_number']) ?>
                        </a>
                    </td>
                    <td><?= e($wo['customer_name']) ?></td>
                    <td><?= e($wo['service_address'] ?? '—') ?></td>
                    <td><?= e(fmt_date($wo['pickup_date'])) ?></td>
                    <td>
                        <span class="text-danger fw-semibold">
                            <?= (int)$wo['days_overdue'] ?> day<?= (int)$wo['days_overdue'] !== 1 ? 's' : '' ?>
                        </span>
                    </td>
                    <td><?= e($wo['size'] ?? '—') ?></td>
                    <td><?= status_badge($wo['status']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ── Section 7: WO Count by Month ──────────────────────────────────────── -->
<h6 class="section-heading mb-2">
    <i class="fa-solid fa-chart-bar me-1"></i>
    Work Orders by Month <small class="text-muted">(last 6 months)</small>
</h6>
<div class="tp-card mb-4">
    <?php if (empty($monthly_counts)): ?>
        <p class="text-muted mb-0">No data available.</p>
    <?php else: ?>
    <div class="bar-chart">
        <?php foreach ($monthly_counts as $mc):
            $pct    = $max_count > 0 ? round(((int)$mc['cnt'] / $max_count) * 140) : 4;
            $pct    = max($pct, 4);
            $label  = date('M \'y', strtotime($mc['month'] . '-01'));
        ?>
        <div class="bar-col">
            <div class="bar-count"><?= (int)$mc['cnt'] ?></div>
            <div class="bar-fill" style="height:<?= $pct ?>px;"></div>
            <div class="bar-label"><?= e($label) ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php
layout_end();
