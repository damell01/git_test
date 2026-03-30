<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_login();

// ── Filters ───────────────────────────────────────────────────────────────────
$valid_statuses = ['scheduled', 'delivered', 'active', 'pickup_requested', 'picked_up', 'completed', 'canceled'];
$status_filter  = (isset($_GET['status']) && in_array($_GET['status'], $valid_statuses))
                  ? $_GET['status'] : '';
$search            = trim($_GET['q'] ?? '');
$delivery_date_from = trim($_GET['delivery_date_from'] ?? '');
$delivery_date_to   = trim($_GET['delivery_date_to']   ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 25;
$offset   = ($page - 1) * $per_page;

// ── Build WHERE clause ────────────────────────────────────────────────────────
$where  = [];
$params = [];

if ($status_filter !== '') {
    $where[]  = 'wo.status = ?';
    $params[] = $status_filter;
}

if ($search !== '') {
    $where[]  = '(wo.wo_number LIKE ? OR wo.cust_name LIKE ? OR wo.service_address LIKE ?)';
    $sl       = '%' . $search . '%';
    $params[] = $sl;
    $params[] = $sl;
    $params[] = $sl;
}

if ($delivery_date_from !== '') {
    $where[]  = 'wo.delivery_date >= ?';
    $params[] = $delivery_date_from;
}

if ($delivery_date_to !== '') {
    $where[]  = 'wo.delivery_date <= ?';
    $params[] = $delivery_date_to;
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ── Count ─────────────────────────────────────────────────────────────────────
$count_stmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM work_orders wo
     LEFT JOIN dumpsters d ON wo.dumpster_id = d.id
     LEFT JOIN users u ON wo.assigned_driver = u.id
     $where_sql"
);
$count_stmt->execute($params);
$total       = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per_page));

// ── Fetch rows ────────────────────────────────────────────────────────────────
$sql = "SELECT wo.*,
               d.unit_code AS dumpster_code,
               d.size      AS dumpster_size,
               u.name      AS driver_name
        FROM work_orders wo
        LEFT JOIN dumpsters d ON wo.dumpster_id = d.id
        LEFT JOIN users u ON wo.assigned_driver = u.id
        $where_sql
        ORDER BY wo.created_at DESC
        LIMIT $per_page OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$work_orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Helpers ───────────────────────────────────────────────────────────────────
function wo_status_badge(string $status): string
{
    $map = [
        'scheduled'        => ['Scheduled',        'bg-primary'],
        'delivered'        => ['Delivered',         'bg-info text-dark'],
        'active'           => ['Active',            'bg-success'],
        'pickup_requested' => ['Pickup Requested',  'bg-warning text-dark'],
        'picked_up'        => ['Picked Up',         'bg-secondary'],
        'completed'        => ['Completed',         'bg-dark'],
        'canceled'         => ['Canceled',          'bg-danger'],
    ];
    [$label, $cls] = $map[$status] ?? [ucfirst(str_replace('_', ' ', $status)), 'bg-secondary'];
    return '<span class="badge ' . $cls . '">' . htmlspecialchars($label) . '</span>';
}

function wo_priority_badge(string $priority): string
{
    $map = [
        'normal' => ['Normal', 'bg-secondary'],
        'high'   => ['High',   'bg-warning text-dark'],
        'urgent' => ['Urgent', 'bg-danger'],
    ];
    [$label, $cls] = $map[$priority] ?? ['Normal', 'bg-secondary'];
    return '<span class="badge ' . $cls . '">' . htmlspecialchars($label) . '</span>';
}

function wo_qs(array $overrides = []): string
{
    global $status_filter, $search, $delivery_date_from, $delivery_date_to;
    $base = array_filter([
        'status'             => $status_filter,
        'q'                  => $search,
        'delivery_date_from' => $delivery_date_from,
        'delivery_date_to'   => $delivery_date_to,
    ]);
    return http_build_query(array_merge($base, $overrides));
}

$status_labels = [
    ''                 => 'All',
    'scheduled'        => 'Scheduled',
    'delivered'        => 'Delivered',
    'active'           => 'Active',
    'pickup_requested' => 'Pickup Requested',
    'picked_up'        => 'Picked Up',
    'completed'        => 'Completed',
    'canceled'         => 'Canceled',
];

layout_start('Work Orders', 'work_orders');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Work Orders</h1>
    <a href="create.php" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i>New Work Order
    </a>
</div>

<!-- Status Tabs -->
<ul class="nav nav-tabs mb-3 flex-nowrap overflow-auto">
    <?php foreach ($status_labels as $sv => $sl): ?>
    <li class="nav-item">
        <a class="nav-link text-nowrap <?= $status_filter === $sv ? 'active' : '' ?>"
           href="?<?= wo_qs(['status' => $sv, 'page' => 1]) ?>">
            <?= htmlspecialchars($sl) ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<!-- Search & Date Filters -->
<form method="get" class="mb-3" action="">
    <?php if ($status_filter): ?>
        <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
    <?php endif; ?>
    <div class="row g-2 align-items-end">
        <div class="col-md-4">
            <label class="form-label small text-muted mb-1">Search</label>
            <div class="input-group">
                <input type="text" name="q" class="form-control"
                       placeholder="WO#, customer, address…"
                       value="<?= htmlspecialchars($search) ?>">
                <?php if ($search): ?>
                <a href="?<?= wo_qs(['q' => '', 'page' => 1]) ?>" class="btn btn-outline-danger" title="Clear">
                    <i class="fas fa-times"></i>
                </a>
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-3">
            <label class="form-label small text-muted mb-1">Delivery From</label>
            <input type="date" name="delivery_date_from" class="form-control"
                   value="<?= htmlspecialchars($delivery_date_from) ?>">
        </div>
        <div class="col-md-3">
            <label class="form-label small text-muted mb-1">Delivery To</label>
            <input type="date" name="delivery_date_to" class="form-control"
                   value="<?= htmlspecialchars($delivery_date_to) ?>">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-outline-secondary w-100">
                <i class="fas fa-filter me-1"></i>Filter
            </button>
        </div>
    </div>
</form>

<!-- Work Orders Table -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>WO #</th>
                        <th>Customer</th>
                        <th>Service Address</th>
                        <th>Size</th>
                        <th>Dumpster</th>
                        <th>Delivery</th>
                        <th>Pickup</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($work_orders)): ?>
                    <tr>
                        <td colspan="10" class="text-center text-muted py-5">
                            <i class="fas fa-truck fa-2x mb-2 d-block"></i>
                            No work orders found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($work_orders as $wo): ?>
                    <tr>
                        <td>
                            <a href="view.php?id=<?= (int)$wo['id'] ?>" class="fw-semibold text-decoration-none">
                                <?= htmlspecialchars($wo['wo_number']) ?>
                            </a>
                        </td>
                        <td>
                            <?= htmlspecialchars($wo['cust_name']) ?>
                            <?php if (!empty($wo['cust_phone'])): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($wo['cust_phone']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= htmlspecialchars($wo['service_address'] ?? '—') ?>
                            <?php if (!empty($wo['service_city'])): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($wo['service_city']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($wo['size'] ?? '—') ?></td>
                        <td>
                            <?php if (!empty($wo['dumpster_code'])): ?>
                                <span class="badge bg-light text-dark border"><?= htmlspecialchars($wo['dumpster_code']) ?></span>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= !empty($wo['delivery_date'])
                                ? date('m/d/Y', strtotime($wo['delivery_date']))
                                : '<span class="text-muted">—</span>' ?>
                        </td>
                        <td>
                            <?= !empty($wo['pickup_date'])
                                ? date('m/d/Y', strtotime($wo['pickup_date']))
                                : '<span class="text-muted">—</span>' ?>
                        </td>
                        <td><?= wo_status_badge($wo['status']) ?></td>
                        <td><?= wo_priority_badge($wo['priority'] ?? 'normal') ?></td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="view.php?id=<?= (int)$wo['id'] ?>"
                                   class="btn btn-outline-secondary" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit.php?id=<?= (int)$wo['id'] ?>"
                                   class="btn btn-outline-primary" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Pagination -->
<?php if ($total_pages > 1): ?>
<nav aria-label="Work orders pagination" class="mt-3 d-flex justify-content-between align-items-center">
    <p class="text-muted small mb-0">
        Showing <?= number_format(min($offset + 1, $total)) ?>–<?= number_format(min($offset + $per_page, $total)) ?>
        of <?= number_format($total) ?> work orders
    </p>
    <ul class="pagination pagination-sm mb-0">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= wo_qs(['page' => $page - 1]) ?>">‹ Prev</a>
        </li>
        <?php
        $start = max(1, $page - 2);
        $end   = min($total_pages, $page + 2);
        if ($start > 1): ?>
            <li class="page-item disabled"><span class="page-link">…</span></li>
        <?php endif;
        for ($p = $start; $p <= $end; $p++): ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                <a class="page-link" href="?<?= wo_qs(['page' => $p]) ?>"><?= $p ?></a>
            </li>
        <?php endfor;
        if ($end < $total_pages): ?>
            <li class="page-item disabled"><span class="page-link">…</span></li>
        <?php endif; ?>
        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= wo_qs(['page' => $page + 1]) ?>">Next ›</a>
        </li>
    </ul>
</nav>
<?php else: ?>
    <?php if ($total > 0): ?>
    <p class="text-muted small mt-2">Showing all <?= number_format($total) ?> work order<?= $total !== 1 ? 's' : '' ?>.</p>
    <?php endif; ?>
<?php endif; ?>

<?php layout_end(); ?>
