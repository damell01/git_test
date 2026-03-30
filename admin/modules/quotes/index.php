<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_login();

// ── Filters ──────────────────────────────────────────────────────────────────
$valid_statuses = ['draft', 'sent', 'approved', 'rejected'];
$status_filter  = (isset($_GET['status']) && in_array($_GET['status'], $valid_statuses))
                  ? $_GET['status'] : '';
$search   = trim($_GET['q'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 25;
$offset   = ($page - 1) * $per_page;

// ── Build WHERE clause ────────────────────────────────────────────────────────
$where  = [];
$params = [];

if ($status_filter !== '') {
    $where[]  = 'quotes.status = ?';
    $params[] = $status_filter;
}

if ($search !== '') {
    $where[]  = '(quotes.cust_name LIKE ? OR quotes.quote_number LIKE ? OR quotes.cust_email LIKE ?)';
    $sl       = '%' . $search . '%';
    $params[] = $sl;
    $params[] = $sl;
    $params[] = $sl;
}

$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// ── Database connection ───────────────────────────────────────────────────────
$pdo = get_db();

// ── Count ─────────────────────────────────────────────────────────────────────
$count_stmt = $pdo->prepare(
    "SELECT COUNT(*) FROM quotes
     LEFT JOIN users ON quotes.created_by = users.id
     $where_sql"
);
$count_stmt->execute($params);
$total       = (int)$count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total / $per_page));

// ── Fetch rows ────────────────────────────────────────────────────────────────
$sql = "SELECT quotes.*, users.name AS created_by_name
        FROM quotes
        LEFT JOIN users ON quotes.created_by = users.id
        $where_sql
        ORDER BY quotes.created_at DESC
        LIMIT $per_page OFFSET $offset";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$quotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ── Helpers ───────────────────────────────────────────────────────────────────
function quote_status_badge(string $status): string
{
    $map = [
        'draft'    => ['Draft',    'bg-secondary'],
        'sent'     => ['Sent',     'bg-info text-dark'],
        'approved' => ['Approved', 'bg-success'],
        'rejected' => ['Rejected', 'bg-danger'],
    ];
    [$label, $cls] = $map[$status] ?? [ucfirst($status), 'bg-secondary'];
    return '<span class="badge ' . $cls . '">' . htmlspecialchars($label) . '</span>';
}

// ── Build pagination query string ─────────────────────────────────────────────
function quote_qs(array $overrides = []): string
{
    global $status_filter, $search;
    $base = array_filter([
        'status' => $status_filter,
        'q'      => $search,
    ]);
    return http_build_query(array_merge($base, $overrides));
}

layout_start('Quotes', 'quotes');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Quotes</h1>
    <a href="create.php" class="btn btn-primary">
        <i class="fas fa-plus me-1"></i>New Quote
    </a>
</div>

<!-- Status Tabs -->
<ul class="nav nav-tabs mb-3">
    <li class="nav-item">
        <a class="nav-link <?= $status_filter === '' ? 'active' : '' ?>"
           href="?<?= quote_qs(['status' => '', 'page' => 1]) ?>">All
            <span class="badge bg-secondary ms-1"><?= $total ?></span>
        </a>
    </li>
    <?php foreach ($valid_statuses as $s): ?>
    <li class="nav-item">
        <a class="nav-link <?= $status_filter === $s ? 'active' : '' ?>"
           href="?<?= quote_qs(['status' => $s, 'page' => 1]) ?>">
            <?= ucfirst($s) ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<!-- Search Bar -->
<form method="get" class="mb-3" action="">
    <?php if ($status_filter): ?>
        <input type="hidden" name="status" value="<?= htmlspecialchars($status_filter) ?>">
    <?php endif; ?>
    <div class="input-group" style="max-width:420px;">
        <input type="text" name="q" class="form-control"
               placeholder="Search by name, quote #, or email…"
               value="<?= htmlspecialchars($search) ?>">
        <button type="submit" class="btn btn-outline-secondary">
            <i class="fas fa-search"></i>
        </button>
        <?php if ($search): ?>
        <a href="?<?= quote_qs(['q' => '', 'page' => 1]) ?>" class="btn btn-outline-danger" title="Clear search">
            <i class="fas fa-times"></i>
        </a>
        <?php endif; ?>
    </div>
</form>

<!-- Quotes Table -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Quote #</th>
                        <th>Customer Name</th>
                        <th>Size</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Valid Until</th>
                        <th>Created By</th>
                        <th>Date</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($quotes)): ?>
                    <tr>
                        <td colspan="9" class="text-center text-muted py-5">
                            <i class="fas fa-file-invoice fa-2x mb-2 d-block"></i>
                            No quotes found.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($quotes as $q): ?>
                    <tr>
                        <td>
                            <a href="view.php?id=<?= (int)$q['id'] ?>" class="fw-semibold text-decoration-none">
                                <?= htmlspecialchars($q['quote_number']) ?>
                            </a>
                        </td>
                        <td>
                            <?= htmlspecialchars($q['cust_name']) ?>
                            <?php if (!empty($q['cust_phone'])): ?>
                                <br><small class="text-muted"><?= htmlspecialchars($q['cust_phone']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($q['size'] ?? '—') ?></td>
                        <td class="fw-semibold">$<?= number_format((float)($q['total'] ?? 0), 2) ?></td>
                        <td><?= quote_status_badge($q['status']) ?></td>
                        <td>
                            <?= !empty($q['valid_until'])
                                ? date('m/d/Y', strtotime($q['valid_until']))
                                : '<span class="text-muted">—</span>' ?>
                        </td>
                        <td><?= htmlspecialchars($q['created_by_name'] ?? '—') ?></td>
                        <td><?= date('m/d/Y', strtotime($q['created_at'])) ?></td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a href="view.php?id=<?= (int)$q['id'] ?>"
                                   class="btn btn-outline-secondary" title="View">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit.php?id=<?= (int)$q['id'] ?>"
                                   class="btn btn-outline-primary" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <?php if ($q['status'] === 'approved' && empty($q['converted_to'])): ?>
                                <a href="convert.php?quote_id=<?= (int)$q['id'] ?>"
                                   class="btn btn-outline-success" title="Convert to Work Order">
                                    <i class="fas fa-exchange-alt"></i>
                                </a>
                                <?php endif; ?>
                                <a href="delete.php?id=<?= (int)$q['id'] ?>"
                                   class="btn btn-outline-danger" title="Delete"
                                   onclick="return confirm('Permanently delete quote <?= htmlspecialchars($q['quote_number'], ENT_QUOTES) ?>?')">
                                    <i class="fas fa-trash"></i>
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
<nav aria-label="Quotes pagination" class="mt-3 d-flex justify-content-between align-items-center">
    <p class="text-muted small mb-0">
        Showing <?= number_format(min($offset + 1, $total)) ?>–<?= number_format(min($offset + $per_page, $total)) ?>
        of <?= number_format($total) ?> quotes
    </p>
    <ul class="pagination pagination-sm mb-0">
        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= quote_qs(['page' => $page - 1]) ?>">‹ Prev</a>
        </li>
        <?php
        $start = max(1, $page - 2);
        $end   = min($total_pages, $page + 2);
        if ($start > 1): ?>
            <li class="page-item disabled"><span class="page-link">…</span></li>
        <?php endif;
        for ($p = $start; $p <= $end; $p++): ?>
            <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                <a class="page-link" href="?<?= quote_qs(['page' => $p]) ?>"><?= $p ?></a>
            </li>
        <?php endfor;
        if ($end < $total_pages): ?>
            <li class="page-item disabled"><span class="page-link">…</span></li>
        <?php endif; ?>
        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
            <a class="page-link" href="?<?= quote_qs(['page' => $page + 1]) ?>">Next ›</a>
        </li>
    </ul>
</nav>
<?php else: ?>
    <?php if ($total > 0): ?>
    <p class="text-muted small mt-2">Showing all <?= number_format($total) ?> quote<?= $total !== 1 ? 's' : '' ?>.</p>
    <?php endif; ?>
<?php endif; ?>

<?php layout_end(); ?>
