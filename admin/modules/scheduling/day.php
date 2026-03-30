<?php
/**
 * Scheduling – Day View
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once TMPL_PATH . '/layout.php';
require_login();

// ── Parse date param (YYYY-MM-DD, default today) ──────────────────────────────
$date = trim($_GET['date'] ?? '');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
    $date = date('Y-m-d');
}
// Normalise to canonical form
$date = date('Y-m-d', strtotime($date));

// ── Back-to-calendar month ────────────────────────────────────────────────────
$cal_month = date('Y-m', strtotime($date));

// ── Fetch all WOs with delivery_date=? OR pickup_date=? ──────────────────────
$wos = db_fetchall(
    "SELECT wo.*,
            d.unit_code  AS dumpster_unit_code,
            d.size       AS dumpster_size,
            u.name       AS driver_name
     FROM work_orders wo
     LEFT JOIN dumpsters d ON wo.dumpster_id = d.id
     LEFT JOIN users u     ON wo.assigned_driver = u.id
     WHERE (wo.delivery_date = ? OR wo.pickup_date = ?)
       AND wo.status NOT IN ('completed','canceled')
     ORDER BY wo.delivery_date ASC, wo.pickup_date ASC",
    [$date, $date]
);

// ── Split into deliveries / pickups ───────────────────────────────────────────
$deliveries = [];
$pickups    = [];
foreach ($wos as $wo) {
    if ($wo['delivery_date'] === $date) {
        $deliveries[] = $wo;
    }
    if ($wo['pickup_date'] === $date) {
        $pickups[] = $wo;
    }
}

// ── All valid statuses for quick status update ────────────────────────────────
$quick_statuses = [
    'scheduled'        => 'Scheduled',
    'delivered'        => 'Delivered',
    'active'           => 'Active',
    'pickup_requested' => 'Pickup Req.',
    'picked_up'        => 'Picked Up',
    'completed'        => 'Completed',
    'canceled'         => 'Canceled',
];

layout_start('Schedule: ' . fmt_date($date), 'scheduling');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">
        <i class="fa-solid fa-calendar-day me-2"></i>
        <?= e(fmt_date($date, 'l, F j, Y')) ?>
    </h5>
    <a href="index.php?month=<?= e($cal_month) ?>" class="btn-tp-ghost btn-tp-sm">
        <i class="fa-solid fa-arrow-left"></i> Back to Calendar
    </a>
</div>

<!-- ── Deliveries ─────────────────────────────────────────────────────────── -->
<div class="tp-card mb-4">
    <h6 class="mb-3">
        <i class="fa-solid fa-truck-arrow-right text-success me-1"></i>
        Deliveries on <?= e(fmt_date($date)) ?>
        <span class="badge bg-secondary ms-1"><?= count($deliveries) ?></span>
    </h6>

    <?php if (empty($deliveries)): ?>
        <p class="text-muted mb-0">No deliveries scheduled for this date.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table tp-table mb-0">
            <thead>
                <tr>
                    <th>WO#</th>
                    <th>Customer</th>
                    <th>Service Address</th>
                    <th>Size</th>
                    <th>Unit Code</th>
                    <th>Driver</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($deliveries as $wo): ?>
                <tr>
                    <td>
                        <a href="<?= e(APP_URL) ?>/modules/work_orders/view.php?id=<?= (int)$wo['id'] ?>">
                            <?= e($wo['wo_number']) ?>
                        </a>
                    </td>
                    <td><?= e($wo['customer_name']) ?></td>
                    <td><?= e($wo['service_address'] ?? '—') ?></td>
                    <td><?= e($wo['size'] ?? '—') ?></td>
                    <td>
                        <?php if (!empty($wo['dumpster_unit_code'])): ?>
                            <?= e($wo['dumpster_unit_code']) ?>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($wo['driver_name'])): ?>
                            <?= e($wo['driver_name']) ?>
                        <?php else: ?>
                            <span class="text-muted">Unassigned</span>
                        <?php endif; ?>
                    </td>
                    <td><?= status_badge($wo['status']) ?></td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end align-items-center flex-wrap">
                            <a href="<?= e(APP_URL) ?>/modules/work_orders/view.php?id=<?= (int)$wo['id'] ?>"
                               class="btn-tp-ghost btn-tp-sm">
                                <i class="fa-solid fa-eye"></i> View
                            </a>
                            <?php if (has_role('admin', 'office', 'dispatcher')): ?>
                            <form method="POST"
                                  action="<?= e(APP_URL) ?>/modules/work_orders/update_status.php"
                                  class="d-flex gap-1 align-items-center">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$wo['id'] ?>">
                                <input type="hidden" name="redirect" value="<?= e(APP_URL) ?>/modules/scheduling/day.php?date=<?= e($date) ?>">
                                <select name="status" class="form-select form-select-sm" style="font-size:.75rem;width:auto;">
                                    <?php foreach ($quick_statuses as $sv => $sl): ?>
                                    <option value="<?= e($sv) ?>" <?= $wo['status'] === $sv ? 'selected' : '' ?>>
                                        <?= e($sl) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn-tp-primary btn-tp-sm">
                                    <i class="fa-solid fa-check"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ── Pickups ────────────────────────────────────────────────────────────── -->
<div class="tp-card mb-3">
    <h6 class="mb-3">
        <i class="fa-solid fa-dumpster me-1" style="color:#7c3aed;"></i>
        Pickups on <?= e(fmt_date($date)) ?>
        <span class="badge bg-secondary ms-1"><?= count($pickups) ?></span>
    </h6>

    <?php if (empty($pickups)): ?>
        <p class="text-muted mb-0">No pickups scheduled for this date.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table tp-table mb-0">
            <thead>
                <tr>
                    <th>WO#</th>
                    <th>Customer</th>
                    <th>Service Address</th>
                    <th>Size</th>
                    <th>Unit Code</th>
                    <th>Driver</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($pickups as $wo): ?>
                <tr>
                    <td>
                        <a href="<?= e(APP_URL) ?>/modules/work_orders/view.php?id=<?= (int)$wo['id'] ?>">
                            <?= e($wo['wo_number']) ?>
                        </a>
                    </td>
                    <td><?= e($wo['customer_name']) ?></td>
                    <td><?= e($wo['service_address'] ?? '—') ?></td>
                    <td><?= e($wo['size'] ?? '—') ?></td>
                    <td>
                        <?php if (!empty($wo['dumpster_unit_code'])): ?>
                            <?= e($wo['dumpster_unit_code']) ?>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($wo['driver_name'])): ?>
                            <?= e($wo['driver_name']) ?>
                        <?php else: ?>
                            <span class="text-muted">Unassigned</span>
                        <?php endif; ?>
                    </td>
                    <td><?= status_badge($wo['status']) ?></td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end align-items-center flex-wrap">
                            <a href="<?= e(APP_URL) ?>/modules/work_orders/view.php?id=<?= (int)$wo['id'] ?>"
                               class="btn-tp-ghost btn-tp-sm">
                                <i class="fa-solid fa-eye"></i> View
                            </a>
                            <?php if (has_role('admin', 'office', 'dispatcher')): ?>
                            <form method="POST"
                                  action="<?= e(APP_URL) ?>/modules/work_orders/update_status.php"
                                  class="d-flex gap-1 align-items-center">
                                <?= csrf_field() ?>
                                <input type="hidden" name="id" value="<?= (int)$wo['id'] ?>">
                                <input type="hidden" name="redirect" value="<?= e(APP_URL) ?>/modules/scheduling/day.php?date=<?= e($date) ?>">
                                <select name="status" class="form-select form-select-sm" style="font-size:.75rem;width:auto;">
                                    <?php foreach ($quick_statuses as $sv => $sl): ?>
                                    <option value="<?= e($sv) ?>" <?= $wo['status'] === $sv ? 'selected' : '' ?>>
                                        <?= e($sl) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <button type="submit" class="btn-tp-primary btn-tp-sm">
                                    <i class="fa-solid fa-check"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<div class="text-end">
    <a href="index.php?month=<?= e($cal_month) ?>" class="btn-tp-ghost btn-tp-sm">
        <i class="fa-solid fa-arrow-left"></i> Back to Calendar
    </a>
</div>

<?php
layout_end();
