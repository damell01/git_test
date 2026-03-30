<?php
/**
 * Dashboard – Trash Panda Roll-Offs
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once TMPL_PATH . '/layout.php';

require_login();

// ── KPI Queries ──────────────────────────────────────────────────────────────

$leads_new = (int)(db_fetch(
    "SELECT COUNT(*) AS cnt FROM leads WHERE status IN ('new','contacted') AND archived = 0"
)['cnt'] ?? 0);

$wo_active = (int)(db_fetch(
    "SELECT COUNT(*) AS cnt FROM work_orders WHERE status IN ('scheduled','delivered','active','pickup_requested')"
)['cnt'] ?? 0);

$wo_today_deliveries = (int)(db_fetch(
    "SELECT COUNT(*) AS cnt FROM work_orders
     WHERE delivery_date = CURDATE() AND status NOT IN ('canceled','completed')"
)['cnt'] ?? 0);

$wo_today_pickups = (int)(db_fetch(
    "SELECT COUNT(*) AS cnt FROM work_orders
     WHERE pickup_date = CURDATE() AND status NOT IN ('canceled','completed')"
)['cnt'] ?? 0);

$revenue_row = db_fetch(
    "SELECT COALESCE(SUM(amount), 0) AS total FROM work_orders
     WHERE status = 'completed'
       AND MONTH(updated_at) = MONTH(NOW())
       AND YEAR(updated_at)  = YEAR(NOW())"
);
$revenue_month = (float)($revenue_row['total'] ?? 0);

// Revenue from work orders this month (amount field, not payments table)
$revenue_payments_month = $revenue_month;

$dumpsters_available = (int)(db_fetch(
    "SELECT COUNT(*) AS cnt FROM dumpsters WHERE status = 'available'"
)['cnt'] ?? 0);

// ── Recent Work Orders ───────────────────────────────────────────────────────
$recent_wo = db_fetchall(
    "SELECT wo.id, wo.wo_number, wo.cust_name, wo.status, wo.delivery_date, wo.amount,
            c.name AS customer_name
     FROM work_orders wo
     LEFT JOIN customers c ON c.id = wo.customer_id
     ORDER BY wo.created_at DESC
     LIMIT 8"
);

// ── Upcoming Deliveries (next 7 days) ────────────────────────────────────────
$upcoming_deliveries = db_fetchall(
    "SELECT wo.id, wo.wo_number, wo.cust_name, wo.service_address, wo.service_city,
            wo.delivery_date, wo.size, wo.status
     FROM work_orders wo
     WHERE wo.delivery_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
       AND wo.status NOT IN ('canceled','completed')
     ORDER BY wo.delivery_date ASC"
);

// ── Upcoming Pickups (next 7 days) ───────────────────────────────────────────
$upcoming_pickups = db_fetchall(
    "SELECT wo.id, wo.wo_number, wo.cust_name, wo.service_address, wo.service_city,
            wo.pickup_date, wo.size, wo.status
     FROM work_orders wo
     WHERE wo.pickup_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 7 DAY)
       AND wo.status NOT IN ('canceled','completed')
     ORDER BY wo.pickup_date ASC"
);

// ── Overdue Pickups ───────────────────────────────────────────────────────────
$overdue_pickups = db_fetchall(
    "SELECT wo.id, wo.wo_number, wo.cust_name, wo.service_address, wo.service_city,
            wo.pickup_date, wo.size, wo.status
     FROM work_orders wo
     WHERE wo.pickup_date < CURDATE()
       AND wo.status NOT IN ('picked_up','completed','canceled')
     ORDER BY wo.pickup_date ASC"
);

// ── Layout ───────────────────────────────────────────────────────────────────
layout_start('Dashboard', 'dashboard');
?>

<!-- Page Header -->
<div class="tp-page-header d-flex align-items-center justify-content-between mb-4">
    <div>
        <h1 class="tp-page-title">Dashboard</h1>
        <p class="tp-page-sub mb-0"><?= e(date('l, F j, Y')) ?></p>
    </div>
    <div class="d-flex gap-2 no-print">
        <a href="<?= e(APP_URL) ?>/modules/work_orders/create.php" class="btn-tp-primary btn-tp-sm">
            <i class="fa-solid fa-plus"></i> New Work Order
        </a>
        <a href="<?= e(APP_URL) ?>/modules/leads/create.php" class="btn-tp-ghost btn-tp-sm">
            <i class="fa-solid fa-plus"></i> New Lead
        </a>
    </div>
</div>

<!-- ── KPI Cards ──────────────────────────────────────────────────────────── -->
<div class="row g-3 mb-4">

    <!-- Open Leads -->
    <div class="col-6 col-md-4 col-xl-2">
        <a href="<?= e(APP_URL) ?>/modules/leads/index.php" class="tp-kpi-card tp-kpi-orange text-decoration-none d-block">
            <div class="kpi-icon"><i class="fa-solid fa-funnel"></i></div>
            <div class="kpi-value"><?= $leads_new ?></div>
            <div class="kpi-label">Open Leads</div>
        </a>
    </div>

    <!-- Active Work Orders -->
    <div class="col-6 col-md-4 col-xl-2">
        <a href="<?= e(APP_URL) ?>/modules/work_orders/index.php" class="tp-kpi-card tp-kpi-blue text-decoration-none d-block">
            <div class="kpi-icon"><i class="fa-solid fa-clipboard-list"></i></div>
            <div class="kpi-value"><?= $wo_active ?></div>
            <div class="kpi-label">Active WOs</div>
        </a>
    </div>

    <!-- Today's Deliveries -->
    <div class="col-6 col-md-4 col-xl-2">
        <a href="<?= e(APP_URL) ?>/modules/scheduling/index.php" class="tp-kpi-card tp-kpi-green text-decoration-none d-block">
            <div class="kpi-icon"><i class="fa-solid fa-truck"></i></div>
            <div class="kpi-value"><?= $wo_today_deliveries ?></div>
            <div class="kpi-label">Today Deliveries</div>
        </a>
    </div>

    <!-- Today's Pickups -->
    <div class="col-6 col-md-4 col-xl-2">
        <a href="<?= e(APP_URL) ?>/modules/scheduling/index.php" class="tp-kpi-card tp-kpi-amber text-decoration-none d-block">
            <div class="kpi-icon"><i class="fa-solid fa-truck-ramp-box"></i></div>
            <div class="kpi-value"><?= $wo_today_pickups ?></div>
            <div class="kpi-label">Today Pickups</div>
        </a>
    </div>

    <!-- Month Revenue (from work orders) -->
    <div class="col-6 col-md-4 col-xl-2">
        <a href="<?= e(APP_URL) ?>/modules/work_orders/index.php" class="tp-kpi-card tp-kpi-green text-decoration-none d-block">
            <div class="kpi-icon"><i class="fa-solid fa-dollar-sign"></i></div>
            <div class="kpi-value" style="font-size:1.1rem;"><?= fmt_money($revenue_payments_month) ?></div>
            <div class="kpi-label">Revenue This Month</div>
        </a>
    </div>

    <!-- Available Dumpsters -->
    <div class="col-6 col-md-4 col-xl-2">
        <a href="<?= e(APP_URL) ?>/modules/dumpsters/index.php" class="tp-kpi-card tp-kpi-gray text-decoration-none d-block">
            <div class="kpi-icon"><i class="fa-solid fa-dumpster"></i></div>
            <div class="kpi-value"><?= $dumpsters_available ?></div>
            <div class="kpi-label">Available Dumpsters</div>
        </a>
    </div>

    <!-- Overdue Pickups -->
    <?php
    $overdue_count_kpi = db_fetch(
        "SELECT COUNT(*) AS cnt FROM work_orders
         WHERE pickup_date < CURDATE() AND status NOT IN ('picked_up','completed','canceled')"
    );
    $overdue_count_num = (int)($overdue_count_kpi['cnt'] ?? 0);
    ?>
    <div class="col-6 col-md-4 col-xl-2">
        <a href="<?= e(APP_URL) ?>/modules/work_orders/index.php?status=overdue" class="tp-kpi-card text-decoration-none d-block"
           style="background:<?= $overdue_count_num > 0 ? 'rgba(239,68,68,.12);border-color:rgba(239,68,68,.4);' : '' ?>">
            <div class="kpi-icon"><i class="fa-solid fa-circle-exclamation" style="<?= $overdue_count_num > 0 ? 'color:#ef4444;' : '' ?>"></i></div>
            <div class="kpi-value" style="<?= $overdue_count_num > 0 ? 'color:#ef4444;' : '' ?>"><?= $overdue_count_num ?></div>
            <div class="kpi-label">Overdue Pickups</div>
        </a>
    </div>

</div><!-- /.row KPI cards -->

<!-- ── Two-column grid: Recent WOs + Quick Stats ─────────────────────────── -->
<div class="row g-4 mb-4">

    <!-- Recent Work Orders -->
    <div class="col-12 col-lg-7">
        <div class="tp-card h-100">
            <div class="tp-card-header d-flex align-items-center justify-content-between">
                <span><i class="fa-solid fa-clipboard-list me-2 text-muted"></i>Recent Work Orders</span>
                <a href="<?= e(APP_URL) ?>/modules/work_orders/index.php" class="btn-tp-ghost btn-tp-xs">
                    View All <i class="fa-solid fa-arrow-right ms-1"></i>
                </a>
            </div>
            <div class="tp-card-body p-0">
                <?php if (empty($recent_wo)): ?>
                    <p class="text-muted p-3 mb-0 text-center" style="font-size:.875rem;">
                        No work orders yet.
                    </p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="tp-table mb-0">
                        <thead>
                            <tr>
                                <th>WO #</th>
                                <th>Customer</th>
                                <th>Status</th>
                                <th>Delivery</th>
                                <th class="text-end">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recent_wo as $wo): ?>
                            <tr>
                                <td>
                                    <a href="<?= e(APP_URL) ?>/modules/work_orders/view.php?id=<?= (int)$wo['id'] ?>"
                                       class="tp-link fw-semibold">
                                        <?= e($wo['wo_number']) ?>
                                    </a>
                                </td>
                                <td><?= e($wo['customer_name'] ?: $wo['cust_name']) ?></td>
                                <td><?= status_badge($wo['status']) ?></td>
                                <td><?= $wo['delivery_date'] ? e(fmt_date($wo['delivery_date'])) : '<span class="text-muted">—</span>' ?></td>
                                <td class="text-end"><?= e(fmt_money($wo['amount'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="col-12 col-lg-5">
        <div class="tp-card h-100">
            <div class="tp-card-header">
                <i class="fa-solid fa-chart-simple me-2 text-muted"></i>Today's Schedule
            </div>
            <div class="tp-card-body">

                <div class="d-flex align-items-center justify-content-between p-3 mb-2"
                     style="background:#1a2235;border-radius:8px;">
                    <div class="d-flex align-items-center gap-3">
                        <span style="font-size:1.6rem;color:#22c55e;"><i class="fa-solid fa-truck"></i></span>
                        <div>
                            <div style="font-size:.8rem;color:#9ca3af;">Deliveries Today</div>
                            <div style="font-size:1.4rem;font-weight:700;color:#e5e7eb;"><?= $wo_today_deliveries ?></div>
                        </div>
                    </div>
                    <a href="<?= e(APP_URL) ?>/modules/scheduling/index.php" class="btn-tp-ghost btn-tp-xs">View</a>
                </div>

                <div class="d-flex align-items-center justify-content-between p-3 mb-3"
                     style="background:#1a2235;border-radius:8px;">
                    <div class="d-flex align-items-center gap-3">
                        <span style="font-size:1.6rem;color:#f59e0b;"><i class="fa-solid fa-truck-ramp-box"></i></span>
                        <div>
                            <div style="font-size:.8rem;color:#9ca3af;">Pickups Today</div>
                            <div style="font-size:1.4rem;font-weight:700;color:#e5e7eb;"><?= $wo_today_pickups ?></div>
                        </div>
                    </div>
                    <a href="<?= e(APP_URL) ?>/modules/scheduling/index.php" class="btn-tp-ghost btn-tp-xs">View</a>
                </div>

                <hr style="border-color:#2a2d3e;margin:1rem 0;">

                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span style="font-size:.85rem;color:#9ca3af;">Upcoming Deliveries (7 days)</span>
                    <span class="tp-badge badge-delivered"><?= count($upcoming_deliveries) ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span style="font-size:.85rem;color:#9ca3af;">Upcoming Pickups (7 days)</span>
                    <span class="tp-badge badge-pickup-requested"><?= count($upcoming_pickups) ?></span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span style="font-size:.85rem;color:#9ca3af;">Open Leads</span>
                    <span class="tp-badge badge-new"><?= $leads_new ?></span>
                </div>
                <?php if (count($overdue_pickups) > 0): ?>
                <div class="d-flex justify-content-between align-items-center">
                    <span style="font-size:.85rem;color:#f87171;">Overdue Pickups</span>
                    <span class="tp-badge badge-canceled"><?= count($overdue_pickups) ?></span>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

</div><!-- /.row two-col -->

<!-- ── Upcoming Deliveries Table ─────────────────────────────────────────── -->
<div class="tp-card mb-4">
    <div class="tp-card-header d-flex align-items-center justify-content-between">
        <span><i class="fa-solid fa-truck me-2" style="color:#22c55e;"></i>Upcoming Deliveries — Next 7 Days</span>
        <span class="tp-badge badge-delivered"><?= count($upcoming_deliveries) ?></span>
    </div>
    <div class="tp-card-body p-0">
        <?php if (empty($upcoming_deliveries)): ?>
            <p class="text-muted p-3 mb-0 text-center" style="font-size:.875rem;">
                No deliveries scheduled in the next 7 days.
            </p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="tp-table mb-0">
                <thead>
                    <tr>
                        <th>WO #</th>
                        <th>Customer</th>
                        <th>Service Address</th>
                        <th>Size</th>
                        <th>Delivery Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($upcoming_deliveries as $wo): ?>
                    <tr <?= $wo['delivery_date'] === date('Y-m-d') ? 'style="background:rgba(34,197,94,.06);"' : '' ?>>
                        <td>
                            <a href="<?= e(APP_URL) ?>/modules/work_orders/view.php?id=<?= (int)$wo['id'] ?>"
                               class="tp-link fw-semibold">
                                <?= e($wo['wo_number']) ?>
                            </a>
                        </td>
                        <td><?= e($wo['cust_name']) ?></td>
                        <td>
                            <?= e($wo['service_address']) ?>
                            <?php if ($wo['service_city']): ?>
                                <span class="text-muted">, <?= e($wo['service_city']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($wo['size'] ?: '—') ?></td>
                        <td>
                            <?php if ($wo['delivery_date'] === date('Y-m-d')): ?>
                                <span style="color:#22c55e;font-weight:600;">Today</span>
                            <?php else: ?>
                                <?= e(fmt_date($wo['delivery_date'])) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= status_badge($wo['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Upcoming Pickups Table ────────────────────────────────────────────── -->
<div class="tp-card mb-4">
    <div class="tp-card-header d-flex align-items-center justify-content-between">
        <span><i class="fa-solid fa-truck-ramp-box me-2" style="color:#f59e0b;"></i>Upcoming Pickups — Next 7 Days</span>
        <span class="tp-badge badge-pickup-requested"><?= count($upcoming_pickups) ?></span>
    </div>
    <div class="tp-card-body p-0">
        <?php if (empty($upcoming_pickups)): ?>
            <p class="text-muted p-3 mb-0 text-center" style="font-size:.875rem;">
                No pickups scheduled in the next 7 days.
            </p>
        <?php else: ?>
        <div class="table-responsive">
            <table class="tp-table mb-0">
                <thead>
                    <tr>
                        <th>WO #</th>
                        <th>Customer</th>
                        <th>Service Address</th>
                        <th>Size</th>
                        <th>Pickup Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($upcoming_pickups as $wo): ?>
                    <tr <?= $wo['pickup_date'] === date('Y-m-d') ? 'style="background:rgba(245,158,11,.06);"' : '' ?>>
                        <td>
                            <a href="<?= e(APP_URL) ?>/modules/work_orders/view.php?id=<?= (int)$wo['id'] ?>"
                               class="tp-link fw-semibold">
                                <?= e($wo['wo_number']) ?>
                            </a>
                        </td>
                        <td><?= e($wo['cust_name']) ?></td>
                        <td>
                            <?= e($wo['service_address']) ?>
                            <?php if ($wo['service_city']): ?>
                                <span class="text-muted">, <?= e($wo['service_city']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($wo['size'] ?: '—') ?></td>
                        <td>
                            <?php if ($wo['pickup_date'] === date('Y-m-d')): ?>
                                <span style="color:#f59e0b;font-weight:600;">Today</span>
                            <?php else: ?>
                                <?= e(fmt_date($wo['pickup_date'])) ?>
                            <?php endif; ?>
                        </td>
                        <td><?= status_badge($wo['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ── Overdue Pickups Table ─────────────────────────────────────────────── -->
<?php if (!empty($overdue_pickups)): ?>
<div class="tp-card mb-4" style="border-color:rgba(239,68,68,.35);">
    <div class="tp-card-header d-flex align-items-center justify-content-between"
         style="background:rgba(239,68,68,.08);border-bottom-color:rgba(239,68,68,.25);">
        <span style="color:#f87171;">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>Overdue Pickups
        </span>
        <span class="tp-badge badge-canceled"><?= count($overdue_pickups) ?></span>
    </div>
    <div class="tp-card-body p-0">
        <div class="table-responsive">
            <table class="tp-table mb-0">
                <thead>
                    <tr>
                        <th>WO #</th>
                        <th>Customer</th>
                        <th>Service Address</th>
                        <th>Size</th>
                        <th>Pickup Date</th>
                        <th>Days Overdue</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($overdue_pickups as $wo): ?>
                    <?php
                        $overdue_days = (int)floor((time() - strtotime($wo['pickup_date'])) / 86400);
                    ?>
                    <tr style="background:rgba(239,68,68,.04);">
                        <td>
                            <a href="<?= e(APP_URL) ?>/modules/work_orders/view.php?id=<?= (int)$wo['id'] ?>"
                               class="tp-link fw-semibold" style="color:#f87171;">
                                <?= e($wo['wo_number']) ?>
                            </a>
                        </td>
                        <td><?= e($wo['cust_name']) ?></td>
                        <td>
                            <?= e($wo['service_address']) ?>
                            <?php if ($wo['service_city']): ?>
                                <span class="text-muted">, <?= e($wo['service_city']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($wo['size'] ?: '—') ?></td>
                        <td style="color:#f87171;"><?= e(fmt_date($wo['pickup_date'])) ?></td>
                        <td>
                            <span style="color:#ef4444;font-weight:700;">
                                <?= $overdue_days ?> day<?= $overdue_days !== 1 ? 's' : '' ?>
                            </span>
                        </td>
                        <td><?= status_badge($wo['status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php layout_end(); ?>
