<?php
/**
 * Dumpsters – Inventory Listing
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once TMPL_PATH . '/layout.php';
require_login();

// ── Status filter ─────────────────────────────────────────────────────────────
$status_filter = trim($_GET['status'] ?? '');
$valid_statuses = ['available', 'reserved', 'in_use', 'maintenance'];

$where  = ['1=1'];
$params = [];

if ($status_filter !== '' && in_array($status_filter, $valid_statuses, true)) {
    $where[]  = 'd.status = ?';
    $params[] = $status_filter;
}

$where_sql = 'WHERE ' . implode(' AND ', $where);

// ── Fetch dumpsters with active work order joined ─────────────────────────────
$dumpsters = db_fetchall(
    "SELECT d.*,
            wo.id        AS wo_id,
            wo.wo_number AS wo_number
     FROM dumpsters d
     LEFT JOIN work_orders wo
           ON  wo.dumpster_id = d.id
           AND wo.status NOT IN ('completed','canceled','picked_up')
     $where_sql
     ORDER BY d.unit_code ASC",
    $params
);

// ── Tab counts ────────────────────────────────────────────────────────────────
$status_counts = [];
$count_rows = db_fetchall("SELECT status, COUNT(*) AS cnt FROM dumpsters GROUP BY status");
foreach ($count_rows as $cr) {
    $status_counts[$cr['status']] = (int)$cr['cnt'];
}
$total_all = array_sum($status_counts);

$tabs = [
    ''            => ['label' => 'All',         'count' => $total_all],
    'available'   => ['label' => 'Available',   'count' => $status_counts['available']   ?? 0],
    'reserved'    => ['label' => 'Reserved',    'count' => $status_counts['reserved']    ?? 0],
    'in_use'      => ['label' => 'In Use',      'count' => $status_counts['in_use']      ?? 0],
    'maintenance' => ['label' => 'Maintenance', 'count' => $status_counts['maintenance'] ?? 0],
];

// ── Layout ────────────────────────────────────────────────────────────────────
layout_start('Inventory', 'inventory');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Dumpster Inventory</h5>
    <?php if (has_role('admin', 'office')): ?>
    <a href="create.php" class="btn-tp-primary btn-tp-sm">
        <i class="fa-solid fa-plus"></i> Add Dumpster
    </a>
    <?php endif; ?>
</div>

<!-- Status filter tabs -->
<ul class="nav nav-tabs mb-3">
    <?php foreach ($tabs as $key => $tab): ?>
    <li class="nav-item">
        <a class="nav-link <?= $status_filter === $key ? 'active' : '' ?>"
           href="?status=<?= urlencode($key) ?>">
            <?= e($tab['label']) ?>
            <span class="badge bg-secondary ms-1"><?= $tab['count'] ?></span>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<!-- Dumpsters table -->
<div class="tp-card">
    <?php if (empty($dumpsters)): ?>
        <p class="text-muted p-3 mb-0">No dumpsters found.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table tp-table mb-0">
            <thead>
                <tr>
                    <th>Unit Code</th>
                    <th>Size</th>
                    <th>Status</th>
                    <th>Condition</th>
                    <th>Current WO#</th>
                    <th>Notes</th>
                    <?php if (has_role('admin', 'office')): ?>
                    <th class="text-end">Actions</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($dumpsters as $d): ?>
                <tr>
                    <td><strong><?= e($d['unit_code']) ?></strong></td>
                    <td><?= e($d['size']) ?></td>
                    <td><?= status_badge($d['status']) ?></td>
                    <td><?= e(ucfirst($d['condition'] ?? '')) ?></td>
                    <td>
                        <?php if (!empty($d['wo_id'])): ?>
                            <a href="<?= e(APP_URL) ?>/modules/work_orders/view.php?id=<?= (int)$d['wo_id'] ?>">
                                <?= e($d['wo_number']) ?>
                            </a>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($d['notes'])): ?>
                            <span title="<?= e($d['notes']) ?>"
                                  style="cursor:help;">
                                <?= e(mb_strimwidth($d['notes'], 0, 40, '…')) ?>
                            </span>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                    <?php if (has_role('admin', 'office')): ?>
                    <td class="text-end">
                        <a href="edit.php?id=<?= (int)$d['id'] ?>"
                           class="btn-tp-ghost btn-tp-sm">
                            <i class="fa-solid fa-pencil"></i> Edit
                        </a>
                        <a href="delete.php?id=<?= (int)$d['id'] ?>"
                           class="btn-tp-ghost btn-tp-sm text-danger"
                           onclick="return confirm('Delete dumpster <?= e($d['unit_code']) ?>?')">
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
