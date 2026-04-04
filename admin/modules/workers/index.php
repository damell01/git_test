<?php
/**
 * Workers – List
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once TMPL_PATH . '/layout.php';
require_login();

// Active filter
$show_inactive = !empty($_GET['inactive']);

// Fetch workers with booking count (gracefully handles missing bookings table)
$has_bookings_table = true;
$workers = [];
try {
    $workers = db_fetchall(
        $show_inactive
            ? "SELECT w.*, COUNT(b.id) AS booking_count
               FROM workers w
               LEFT JOIN bookings b ON b.worker_id = w.id AND b.booking_status != 'canceled'
               GROUP BY w.id
               ORDER BY w.name ASC"
            : "SELECT w.*, COUNT(b.id) AS booking_count
               FROM workers w
               LEFT JOIN bookings b ON b.worker_id = w.id AND b.booking_status != 'canceled'
               WHERE w.active = 1
               GROUP BY w.id
               ORDER BY w.name ASC"
    );
} catch (\Throwable $e) {
    $has_bookings_table = false;
    $workers = db_fetchall(
        $show_inactive
            ? "SELECT *, 0 AS booking_count FROM workers ORDER BY name ASC"
            : "SELECT *, 0 AS booking_count FROM workers WHERE active = 1 ORDER BY name ASC"
    );
}

layout_start('Workers', 'workers');
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h5 class="mb-0">Workers &amp; Drivers</h5>
        <small class="text-muted">Manage your team and assign them to jobs</small>
    </div>
    <?php if (has_role('admin', 'office')): ?>
    <a href="create.php" class="btn-tp-primary btn-tp-sm">
        <i class="fa-solid fa-plus"></i> Add Worker
    </a>
    <?php endif; ?>
</div>

<!-- Active/Inactive toggle -->
<div class="mb-3">
    <?php if ($show_inactive): ?>
        <a href="index.php" class="tp-filter-tab active">Active</a>
        <a href="?inactive=1" class="tp-filter-tab">All</a>
    <?php else: ?>
        <a href="index.php" class="tp-filter-tab active">Active</a>
        <a href="?inactive=1" class="tp-filter-tab">All</a>
    <?php endif; ?>
</div>

<div class="tp-card">
    <?php if (empty($workers)): ?>
        <p class="text-muted p-3 mb-0">No workers found. <a href="create.php">Add your first worker.</a></p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table tp-table mb-0">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Active Jobs</th>
                    <th>Notes</th>
                    <?php if (has_role('admin', 'office')): ?>
                    <th class="text-end">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($workers as $w): ?>
                <tr>
                    <td><strong><?= e($w['name']) ?></strong></td>
                    <td>
                        <?php if (!empty($w['phone'])): ?>
                            <a href="tel:<?= e(preg_replace('/\D/', '', $w['phone'])) ?>"><?= e(fmt_phone($w['phone'])) ?></a>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($w['active']): ?>
                            <span class="tp-badge tp-badge-paid">Active</span>
                        <?php else: ?>
                            <span class="tp-badge" style="background:rgba(107,114,128,.15);color:#9ca3af;">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($has_bookings_table && (int)$w['booking_count'] > 0): ?>
                            <a href="<?= e(APP_URL) ?>/modules/bookings/index.php?worker_id=<?= (int)$w['id'] ?>">
                                <?= (int)$w['booking_count'] ?> booking<?= (int)$w['booking_count'] !== 1 ? 's' : '' ?>
                            </a>
                        <?php else: ?>
                            <span class="text-muted">0</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($w['notes'])): ?>
                            <span title="<?= e($w['notes']) ?>" style="cursor:help;">
                                <?= e(mb_strimwidth($w['notes'], 0, 50, '…')) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <?php if (has_role('admin', 'office')): ?>
                    <td class="text-end">
                        <a href="edit.php?id=<?= (int)$w['id'] ?>" class="btn-tp-ghost btn-tp-sm">
                            <i class="fa-solid fa-pencil"></i> Edit
                        </a>
                        <a href="delete.php?id=<?= (int)$w['id'] ?>"
                           class="btn-tp-ghost btn-tp-sm text-danger"
                           onclick="return confirm('Delete <?= e($w['name']) ?>? They will be unassigned from any bookings.')">
                            <i class="fa-solid fa-trash"></i> Delete
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

<?php
layout_end();
