<?php
/**
 * Scheduling – Monthly Calendar
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once TMPL_PATH . '/layout.php';
require_login();

// ── Parse month param (YYYY-MM, default current month) ───────────────────────
$month_param = trim($_GET['month'] ?? '');
if (!preg_match('/^\d{4}-\d{2}$/', $month_param)) {
    $month_param = date('Y-m');
}

[$year, $month] = array_map('intval', explode('-', $month_param));
$year  = max(2000, min(2099, $year));
$month = max(1, min(12, $month));
$month_param = sprintf('%04d-%02d', $year, $month);

// ── Calendar boundaries ───────────────────────────────────────────────────────
$first_day_ts  = mktime(0, 0, 0, $month, 1, $year);
$last_day      = (int)date('t', $first_day_ts);
$first_dow     = (int)date('w', $first_day_ts);   // 0=Sun … 6=Sat
$month_label   = date('F Y', $first_day_ts);
$today         = date('Y-m-d');

// ── Prev / next month navigation ─────────────────────────────────────────────
$prev_ts    = mktime(0, 0, 0, $month - 1, 1, $year);
$next_ts    = mktime(0, 0, 0, $month + 1, 1, $year);
$prev_month = date('Y-m', $prev_ts);
$next_month = date('Y-m', $next_ts);

// ── Fetch all WOs for the month (delivery_date or pickup_date falls in month) ─
$month_start = $month_param . '-01';
$month_end   = $month_param . '-' . str_pad((string)$last_day, 2, '0', STR_PAD_LEFT);

$month_wos = db_fetchall(
    "SELECT wo.id,
            wo.wo_number,
            wo.delivery_date,
            wo.pickup_date,
            wo.status,
            wo.cust_name
     FROM work_orders wo
     WHERE (
               (wo.delivery_date BETWEEN ? AND ?)
            OR (wo.pickup_date   BETWEEN ? AND ?)
           )
     ORDER BY wo.delivery_date ASC, wo.pickup_date ASC",
    [$month_start, $month_end, $month_start, $month_end]
);

// ── Index WOs by date for fast lookup ─────────────────────────────────────────
$deliveries_by_date = [];
$pickups_by_date    = [];
foreach ($month_wos as $wo) {
    if (!empty($wo['delivery_date'])) {
        $deliveries_by_date[$wo['delivery_date']][] = $wo;
    }
    if (!empty($wo['pickup_date'])) {
        $pickups_by_date[$wo['pickup_date']][] = $wo;
    }
}

// ── Upcoming Deliveries & Pickups (next 14 days) ──────────────────────────────
$upcoming_start = date('Y-m-d');
$upcoming_end   = date('Y-m-d', strtotime('+14 days'));

$upcoming_deliveries = db_fetchall(
    "SELECT wo.id, wo.wo_number, wo.cust_name, wo.service_address,
            wo.size, wo.delivery_date, wo.status
     FROM work_orders wo
     WHERE wo.delivery_date BETWEEN ? AND ?
       AND wo.status NOT IN ('completed','canceled','picked_up')
     ORDER BY wo.delivery_date ASC",
    [$upcoming_start, $upcoming_end]
);

$upcoming_pickups = db_fetchall(
    "SELECT wo.id, wo.wo_number, wo.cust_name, wo.service_address,
            wo.size, wo.pickup_date, wo.status
     FROM work_orders wo
     WHERE wo.pickup_date BETWEEN ? AND ?
       AND wo.status NOT IN ('completed','canceled','picked_up')
     ORDER BY wo.pickup_date ASC",
    [$upcoming_start, $upcoming_end]
);

layout_start('Scheduling', 'scheduling');
?>

<style>
/* ── Calendar grid ── */
.cal-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 2px;
    background: var(--bdr, #e5e7eb);
    border: 1px solid var(--bdr, #e5e7eb);
    border-radius: 8px;
    overflow: hidden;
}
.cal-header {
    background: var(--dk, #1f2937);
    color: #fff;
    text-align: center;
    padding: 8px 4px;
    font-size: .75rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .05em;
}
.cal-day {
    background: #fff;
    min-height: 90px;
    padding: 4px 5px;
    vertical-align: top;
    font-size: .8rem;
}
.cal-day.today {
    background: #f0fdf4;
}
.cal-day.empty {
    background: #f9fafb;
}
.cal-day-num {
    display: inline-block;
    font-weight: 600;
    font-size: .8rem;
    line-height: 1.6;
    min-width: 22px;
    text-align: center;
    border-radius: 50%;
    color: #374151;
}
.cal-day.today .cal-day-num {
    background: #16a34a;
    color: #fff;
}
.cal-day-num a {
    color: inherit;
    text-decoration: none;
}
.cal-ev {
    display: block;
    margin-top: 2px;
    padding: 2px 5px;
    border-radius: 4px;
    font-size: .68rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    text-decoration: none;
    font-weight: 500;
}
.cal-ev.del {
    background: #dcfce7;
    color: #15803d;
    border-left: 3px solid #16a34a;
}
.cal-ev.pck {
    background: #ede9fe;
    color: #6d28d9;
    border-left: 3px solid #7c3aed;
}
</style>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">
        <i class="fa-solid fa-calendar-days me-2"></i><?= e($month_label) ?>
    </h5>
    <div class="d-flex gap-2">
        <a href="?month=<?= e($prev_month) ?>" class="btn-tp-ghost btn-tp-sm">
            <i class="fa-solid fa-chevron-left"></i> Prev
        </a>
        <a href="?month=<?= e(date('Y-m')) ?>" class="btn-tp-ghost btn-tp-sm">Today</a>
        <a href="?month=<?= e($next_month) ?>" class="btn-tp-ghost btn-tp-sm">
            Next <i class="fa-solid fa-chevron-right"></i>
        </a>
    </div>
</div>

<!-- Legend -->
<div class="d-flex gap-3 mb-2" style="font-size:.75rem;">
    <span><span class="cal-ev del d-inline-block" style="width:60px;">Delivery</span></span>
    <span><span class="cal-ev pck d-inline-block" style="width:60px;">Pickup</span></span>
</div>

<!-- Monthly Calendar -->
<div class="cal-grid mb-4">

    <!-- Day-of-week headers -->
    <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dow): ?>
    <div class="cal-header"><?= $dow ?></div>
    <?php endforeach; ?>

    <!-- Empty cells before the 1st -->
    <?php for ($e_i = 0; $e_i < $first_dow; $e_i++): ?>
    <div class="cal-day empty"></div>
    <?php endfor; ?>

    <!-- Day cells -->
    <?php for ($day = 1; $day <= $last_day; $day++): ?>
    <?php
        $date_str  = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $is_today  = ($date_str === $today);
        $day_dels  = $deliveries_by_date[$date_str] ?? [];
        $day_pkups = $pickups_by_date[$date_str] ?? [];
    ?>
    <div class="cal-day <?= $is_today ? 'today' : '' ?>">
        <div class="cal-day-num">
            <a href="day.php?date=<?= e($date_str) ?>"><?= $day ?></a>
        </div>
        <?php foreach ($day_dels as $wo): ?>
        <a href="<?= e(APP_URL) ?>/modules/work_orders/view.php?id=<?= (int)$wo['id'] ?>"
           class="cal-ev del"
           title="Delivery: <?= e($wo['wo_number']) ?> — <?= e($wo['cust_name']) ?>">
            <i class="fa-solid fa-truck-arrow-right fa-xs"></i>
            <?= e($wo['wo_number']) ?> <?= e(mb_strimwidth($wo['cust_name'], 0, 12, '…')) ?>
        </a>
        <?php endforeach; ?>
        <?php foreach ($day_pkups as $wo): ?>
        <a href="<?= e(APP_URL) ?>/modules/work_orders/view.php?id=<?= (int)$wo['id'] ?>"
           class="cal-ev pck"
           title="Pickup: <?= e($wo['wo_number']) ?> — <?= e($wo['cust_name']) ?>">
            <i class="fa-solid fa-dumpster fa-xs"></i>
            <?= e($wo['wo_number']) ?> <?= e(mb_strimwidth($wo['cust_name'], 0, 12, '…')) ?>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endfor; ?>

    <!-- Trailing empty cells to complete final row -->
    <?php
    $total_cells = $first_dow + $last_day;
    $trailing    = (7 - ($total_cells % 7)) % 7;
    for ($t = 0; $t < $trailing; $t++): ?>
    <div class="cal-day empty"></div>
    <?php endfor; ?>

</div>

<!-- Upcoming section -->
<div class="row g-3">

    <!-- Upcoming Deliveries -->
    <div class="col-lg-6">
        <div class="tp-card">
            <h6 class="mb-3">
                <i class="fa-solid fa-truck-arrow-right text-success me-1"></i>
                Upcoming Deliveries <small class="text-muted">(next 14 days)</small>
            </h6>
            <?php if (empty($upcoming_deliveries)): ?>
                <p class="text-muted mb-0">No deliveries scheduled.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table tp-table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>WO#</th>
                            <th>Customer</th>
                            <th>Address</th>
                            <th>Size</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th></th>
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
                            <td><?= e($wo['cust_name']) ?></td>
                            <td><?= e($wo['service_address'] ?? '—') ?></td>
                            <td><?= e($wo['size'] ?? '—') ?></td>
                            <td><?= e(fmt_date($wo['delivery_date'])) ?></td>
                            <td><?= status_badge($wo['status']) ?></td>
                            <td>
                                <a href="day.php?date=<?= e($wo['delivery_date']) ?>"
                                   class="btn-tp-ghost btn-tp-sm">
                                    <i class="fa-solid fa-calendar-day"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Upcoming Pickups -->
    <div class="col-lg-6">
        <div class="tp-card">
            <h6 class="mb-3">
                <i class="fa-solid fa-dumpster" style="color:#7c3aed;" ></i>
                <span class="ms-1">Upcoming Pickups</span> <small class="text-muted">(next 14 days)</small>
            </h6>
            <?php if (empty($upcoming_pickups)): ?>
                <p class="text-muted mb-0">No pickups scheduled.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table tp-table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>WO#</th>
                            <th>Customer</th>
                            <th>Address</th>
                            <th>Size</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th></th>
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
                            <td><?= e($wo['cust_name']) ?></td>
                            <td><?= e($wo['service_address'] ?? '—') ?></td>
                            <td><?= e($wo['size'] ?? '—') ?></td>
                            <td><?= e(fmt_date($wo['pickup_date'])) ?></td>
                            <td><?= status_badge($wo['status']) ?></td>
                            <td>
                                <a href="day.php?date=<?= e($wo['pickup_date']) ?>"
                                   class="btn-tp-ghost btn-tp-sm">
                                    <i class="fa-solid fa-calendar-day"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php
layout_end();
