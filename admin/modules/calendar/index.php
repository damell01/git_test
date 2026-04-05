<?php
/**
 * Calendar – Dumpster Availability & Bookings
 * Shows rental periods, booked date ranges by unit, and blocked dates.
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

$month_start = $month_param . '-01';
$month_end   = $month_param . '-' . str_pad((string)$last_day, 2, '0', STR_PAD_LEFT);

// ── Fetch all confirmed/active bookings that overlap this month ───────────────
$month_bookings = [];
try {
    $month_bookings = db_fetchall(
        "SELECT b.id, b.booking_number, b.customer_name, b.unit_code,
                b.rental_start, b.rental_end, b.booking_status, b.payment_method,
                b.payment_status
         FROM bookings b
         WHERE b.booking_status NOT IN ('canceled')
           AND b.rental_start <= ? AND b.rental_end >= ?
         ORDER BY b.rental_start ASC",
        [$month_end, $month_start]
    );
} catch (\Throwable $e) {
    // bookings table may not exist
}

// ── Fetch inventory blocks that overlap this month ────────────────────────────
$month_blocks = [];
try {
    $month_blocks = db_fetchall(
        "SELECT ib.id, ib.dumpster_id, ib.block_start, ib.block_end, ib.reason,
                d.unit_code, d.size
         FROM inventory_blocks ib
         JOIN dumpsters d ON d.id = ib.dumpster_id
         WHERE ib.block_start <= ? AND ib.block_end >= ?
         ORDER BY ib.block_start ASC",
        [$month_end, $month_start]
    );
} catch (\Throwable $e) {
    // inventory_blocks table may not exist
}

// ── Fetch all active dumpster units ──────────────────────────────────────────
$all_units = db_fetchall(
    "SELECT id, unit_code, size, type FROM dumpsters WHERE active = 1 ORDER BY unit_code ASC"
);

// ── Index bookings by date ────────────────────────────────────────────────────
$bookings_by_date = [];
foreach ($month_bookings as $bk) {
    $start = max($bk['rental_start'], $month_start);
    $end   = min($bk['rental_end'],   $month_end);
    $cur   = new \DateTime($start);
    $eDate = new \DateTime($end);
    while ($cur <= $eDate) {
        $bookings_by_date[$cur->format('Y-m-d')][] = $bk;
        $cur->modify('+1 day');
    }
}

// ── Index blocks by date ──────────────────────────────────────────────────────
$blocks_by_date = [];
foreach ($month_blocks as $blk) {
    $start = max($blk['block_start'], $month_start);
    $end   = min($blk['block_end'],   $month_end);
    $cur   = new \DateTime($start);
    $eDate = new \DateTime($end);
    while ($cur <= $eDate) {
        $blocks_by_date[$cur->format('Y-m-d')][] = $blk;
        $cur->modify('+1 day');
    }
}

// ── Unit availability by date ─────────────────────────────────────────────────
// Build a per-unit summary for a quick availability view
$unit_count        = count($all_units);
$unit_code_map     = [];
foreach ($all_units as $u) {
    $unit_code_map[$u['unit_code']] = $u;
}

// ── Upcoming blocked ranges (next 60 days) ────────────────────────────────────
$upcoming_end   = date('Y-m-d', strtotime('+60 days'));
$upcoming_blocks = [];
try {
    $upcoming_blocks = db_fetchall(
        "SELECT ib.id, ib.dumpster_id, ib.block_start, ib.block_end, ib.reason,
                d.unit_code, d.size
         FROM inventory_blocks ib
         JOIN dumpsters d ON d.id = ib.dumpster_id
         WHERE ib.block_end >= ?
         ORDER BY ib.block_start ASC
         LIMIT 50",
        [$today]
    );
} catch (\Throwable $e) {
    // ignore
}

layout_start('Calendar', 'calendar');
?>

<!-- Navigation header -->
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
        <?php if (has_role('admin', 'office')): ?>
        <a href="<?= e(APP_URL) ?>/modules/bookings/block.php" class="btn-tp-primary btn-tp-sm">
            <i class="fa-solid fa-lock"></i> Block Dates
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Legend -->
<div class="d-flex gap-3 mb-3 flex-wrap" style="font-size:.75rem;">
    <span><span class="cal-ev bk d-inline-block" style="width:90px;">Booking</span></span>
    <span><span class="cal-ev bk-completed d-inline-block" style="width:90px;">Completed</span></span>
    <span><span class="cal-ev blk d-inline-block" style="width:90px;">Blocked</span></span>
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
        $date_str = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $is_today = ($date_str === $today);
        $day_bks  = $bookings_by_date[$date_str] ?? [];
        $day_blks = $blocks_by_date[$date_str] ?? [];
        $cell_class = 'cal-day';
        if ($is_today)         $cell_class .= ' today';
        if (!empty($day_blks)) $cell_class .= ' has-blocks';
    ?>
    <div class="<?= $cell_class ?>">
        <div class="cal-day-num"><?= $day ?></div>
        <?php foreach ($day_bks as $bk):
            if ($bk['booking_status'] === 'completed') {
                $bk_css = 'bk-completed';
            } else {
                // pending, confirmed, paid, etc. all use the standard blue booking style
                $bk_css = 'bk';
            }
        ?>
        <a href="<?= e(APP_URL) ?>/modules/bookings/view.php?id=<?= (int)$bk['id'] ?>"
           class="cal-ev <?= $bk_css ?>"
           title="Booking: <?= e($bk['booking_number']) ?> — <?= e($bk['customer_name']) ?> | <?= e($bk['unit_code']) ?>">
            <i class="fa-solid fa-calendar-check fa-xs"></i>
            <?= e($bk['unit_code'] ?: $bk['booking_number']) ?> <?= e(mb_strimwidth($bk['customer_name'], 0, 10, '…')) ?>
        </a>
        <?php endforeach; ?>
        <?php foreach ($day_blks as $blk): ?>
        <a href="<?= e(APP_URL) ?>/modules/dumpsters/index.php"
           class="cal-ev blk"
           title="Blocked: <?= e($blk['unit_code']) ?> — <?= e($blk['reason'] ?: 'Maintenance block') ?>">
            <i class="fa-solid fa-lock fa-xs"></i>
            <?= e($blk['unit_code']) ?> <?= e(mb_strimwidth($blk['reason'] ?: 'Blocked', 0, 10, '…')) ?>
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

<!-- Unit Availability Summary (today) -->
<?php if (!empty($all_units)): ?>
<div class="tp-card mb-4">
    <h6 class="mb-3">
        <i class="fa-solid fa-dumpster me-1"></i> Unit Availability Today
        <small class="text-muted">(<?= e(date('M j, Y')) ?>)</small>
    </h6>
    <div class="d-flex flex-wrap gap-1">
    <?php foreach ($all_units as $u):
        $unit_code = $u['unit_code'];
        $is_booked  = false;
        $is_blocked = false;
        foreach (($bookings_by_date[$today] ?? []) as $bk) {
            if ($bk['unit_code'] === $unit_code) { $is_booked = true; break; }
        }
        foreach (($blocks_by_date[$today] ?? []) as $blk) {
            if ($blk['unit_code'] === $unit_code) { $is_blocked = true; break; }
        }
        if ($is_blocked) {
            $chip_class = 'blocked';
            $chip_icon  = 'fa-lock';
            $chip_title = 'Blocked';
        } elseif ($is_booked) {
            $chip_class = 'booked';
            $chip_icon  = 'fa-calendar-check';
            $chip_title = 'Booked';
        } else {
            $chip_class = 'available';
            $chip_icon  = 'fa-check';
            $chip_title = 'Available';
        }
    ?>
    <span class="unit-avail-chip <?= $chip_class ?>"
          title="<?= e($u['size']) ?> — <?= $chip_title ?>">
        <i class="fa-solid <?= $chip_icon ?> fa-xs me-1"></i>
        <?= e($unit_code) ?>
    </span>
    <?php endforeach; ?>
    </div>
    <div class="mt-2" style="font-size:.75rem;color:#6b7280;">
        <span class="me-3"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#dcfce7;margin-right:4px;"></span>Available</span>
        <span class="me-3"><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#dbeafe;margin-right:4px;"></span>Booked</span>
        <span><span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:#fee2e2;margin-right:4px;"></span>Blocked</span>
    </div>
</div>
<?php endif; ?>

<div class="row g-4">

    <!-- Active Bookings This Month -->
    <div class="col-lg-6">
        <div class="tp-card">
            <h6 class="mb-3">
                <i class="fa-solid fa-calendar-check text-primary me-1"></i>
                Bookings in <?= e($month_label) ?>
                <span class="badge bg-secondary ms-1"><?= count($month_bookings) ?></span>
            </h6>
            <?php if (empty($month_bookings)): ?>
                <p class="text-muted mb-0">No bookings this month.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table tp-table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Booking #</th>
                            <th>Customer</th>
                            <th>Unit</th>
                            <th>Dates</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($month_bookings as $bk): ?>
                        <tr>
                            <td>
                                <a href="<?= e(APP_URL) ?>/modules/bookings/view.php?id=<?= (int)$bk['id'] ?>">
                                    <?= e($bk['booking_number']) ?>
                                </a>
                            </td>
                            <td><?= e($bk['customer_name']) ?></td>
                            <td><?= e($bk['unit_code'] ?: '—') ?></td>
                            <td style="font-size:.8rem;">
                                <?= e(fmt_date($bk['rental_start'])) ?><br>
                                <span class="text-muted">→ <?= e(fmt_date($bk['rental_end'])) ?></span>
                            </td>
                            <td><?= status_badge($bk['booking_status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Inventory Blocks -->
    <div class="col-lg-6">
        <div class="tp-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0">
                    <i class="fa-solid fa-lock text-danger me-1"></i>
                    Inventory Blocks (Active &amp; Upcoming)
                    <span class="badge bg-secondary ms-1"><?= count($upcoming_blocks) ?></span>
                </h6>
                <?php if (has_role('admin', 'office')): ?>
                <a href="<?= e(APP_URL) ?>/modules/bookings/block.php" class="btn-tp-primary btn-tp-sm">
                    <i class="fa-solid fa-plus"></i> Add Block
                </a>
                <?php endif; ?>
            </div>
            <?php if (empty($upcoming_blocks)): ?>
                <p class="text-muted mb-0">No active or upcoming inventory blocks.</p>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table tp-table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Unit</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Reason</th>
                            <?php if (has_role('admin', 'office')): ?>
                            <th></th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($upcoming_blocks as $blk): ?>
                        <tr style="<?= $blk['block_start'] <= $today && $blk['block_end'] >= $today ? 'background:#fef2f2;' : '' ?>">
                            <td><strong><?= e($blk['unit_code']) ?></strong></td>
                            <td><?= e(fmt_date($blk['block_start'])) ?></td>
                            <td><?= e(fmt_date($blk['block_end'])) ?></td>
                            <td class="text-muted"><?= e($blk['reason'] ?: '—') ?></td>
                            <?php if (has_role('admin', 'office')): ?>
                            <td>
                                <a href="<?= e(APP_URL) ?>/modules/bookings/unblock.php?id=<?= (int)$blk['id'] ?>"
                                   class="btn-tp-ghost btn-tp-xs text-danger"
                                   onclick="return confirm('Remove this inventory block?')">
                                    <i class="fa-solid fa-trash"></i>
                                </a>
                            </td>
                            <?php endif; ?>
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
