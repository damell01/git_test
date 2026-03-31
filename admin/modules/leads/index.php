<?php
/**
 * Leads – Index / Listing
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once TMPL_PATH . '/layout.php';

require_login();

// ── Filters from GET ────────────────────────────────────────────────────────
$status_filter = trim($_GET['status'] ?? '');
$source_filter = trim($_GET['source'] ?? '');
$q             = trim($_GET['q']      ?? '');
$page_num      = max(1, (int)($_GET['page'] ?? 1));

$valid_statuses = ['new', 'contacted', 'quoted', 'won', 'lost'];

// ── Status badge counts (non-archived) ──────────────────────────────────────
$status_counts = [];
$count_rows = db_fetchall(
    "SELECT status, COUNT(*) AS cnt FROM leads WHERE archived = 0 GROUP BY status"
);
foreach ($count_rows as $cr) {
    $status_counts[$cr['status']] = (int)$cr['cnt'];
}
$total_all = array_sum($status_counts);

// ── Build base SQL ──────────────────────────────────────────────────────────
$where   = ['leads.archived = 0'];
$params  = [];

if ($status_filter !== '' && in_array($status_filter, $valid_statuses, true)) {
    $where[]  = 'leads.status = ?';
    $params[] = $status_filter;
}

if ($source_filter !== '') {
    $where[]  = 'leads.source = ?';
    $params[] = $source_filter;
}

if ($q !== '') {
    $like     = '%' . $q . '%';
    $where[]  = '(leads.name LIKE ? OR leads.phone LIKE ? OR leads.email LIKE ? OR leads.address LIKE ?)';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$where_sql = 'WHERE ' . implode(' AND ', $where);

// ── Count total for pagination ───────────────────────────────────────────────
$count_sql  = "SELECT COUNT(*) AS cnt FROM leads LEFT JOIN users ON leads.assigned_to = users.id $where_sql";
$count_row  = db_fetch($count_sql, $params);
$total      = (int)($count_row['cnt'] ?? 0);
$pag        = paginate($total, $page_num, 25);

// ── Fetch page of rows ──────────────────────────────────────────────────────
$data_sql = "SELECT leads.*, users.name AS assigned_name
             FROM leads
             LEFT JOIN users ON leads.assigned_to = users.id
             $where_sql
             ORDER BY leads.created_at DESC
             LIMIT {$pag['per_page']} OFFSET {$pag['offset']}";

$leads = db_fetchall($data_sql, $params);

// ── Helpers for building tab/filter query strings ───────────────────────────
function leads_query(array $overrides = []): string
{
    $base = [];
    if (!empty($_GET['source'])) {
        $base['source'] = $_GET['source'];
    }
    if (!empty($_GET['q'])) {
        $base['q'] = $_GET['q'];
    }
    $merged = array_merge($base, $overrides);
    unset($merged['page']); // reset pagination on filter change
    return $merged ? '?' . http_build_query($merged) : '?';
}

// ── Tab definitions ──────────────────────────────────────────────────────────
$tabs = [
    ''          => ['label' => 'All',       'count' => $total_all],
    'new'       => ['label' => 'New',       'count' => $status_counts['new']       ?? 0],
    'contacted' => ['label' => 'Contacted', 'count' => $status_counts['contacted'] ?? 0],
    'quoted'    => ['label' => 'Quoted',    'count' => $status_counts['quoted']    ?? 0],
    'won'       => ['label' => 'Won',       'count' => $status_counts['won']       ?? 0],
    'lost'      => ['label' => 'Lost',      'count' => $status_counts['lost']      ?? 0],
];

// ── Render ───────────────────────────────────────────────────────────────────
layout_start('Leads', 'leads');
?>

<!-- Page header -->
<div class="tp-page-header d-flex align-items-center justify-content-between mb-3">
    <h2 class="tp-page-title mb-0">Leads</h2>
    <?php if (has_role('admin', 'office')): ?>
    <a href="<?= APP_URL ?>/modules/leads/create.php" class="btn-tp-primary btn-tp-sm">
        <i class="fa-solid fa-plus"></i> New Lead
    </a>
    <?php endif; ?>
</div>

<!-- Filter bar -->
<div class="tp-filter-bar mb-3">

    <!-- Status tabs -->
    <div class="filter-tabs">
        <?php foreach ($tabs as $key => $tab): ?>
            <?php
            $is_active = ($status_filter === $key);
            $href      = APP_URL . '/modules/leads/index.php' . leads_query(['status' => $key]);
            ?>
            <a href="<?= e($href) ?>"
               class="filter-tab <?= $is_active ? 'active' : '' ?>">
                <?= e($tab['label']) ?>
                <span class="tab-count"><?= (int)$tab['count'] ?></span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Search + source filter -->
    <div class="d-flex gap-2 flex-wrap mt-2">
        <form method="get" action="<?= APP_URL ?>/modules/leads/index.php" class="d-flex gap-2 flex-wrap">
            <?php if ($status_filter !== ''): ?>
                <input type="hidden" name="status" value="<?= e($status_filter) ?>">
            <?php endif; ?>

            <input type="text"
                   name="q"
                   value="<?= e($q) ?>"
                   placeholder="Search name, phone, email, address…"
                   class="tp-search form-control form-control-sm"
                   style="min-width:220px;">

            <select name="source" class="form-select form-select-sm" style="min-width:160px;">
                <option value="">All Sources</option>
                <?php foreach (lead_sources() as $src): ?>
                    <option value="<?= e($src) ?>" <?= $source_filter === $src ? 'selected' : '' ?>>
                        <?= e($src) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn-tp-primary btn-tp-sm">
                <i class="fa-solid fa-magnifying-glass"></i> Filter
            </button>
            <?php if ($q !== '' || $source_filter !== ''): ?>
                <a href="<?= APP_URL ?>/modules/leads/index.php<?= $status_filter ? '?status=' . e($status_filter) : '' ?>"
                   class="btn-tp-ghost btn-tp-sm">
                    <i class="fa-solid fa-xmark"></i> Clear
                </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- Leads table -->
<div class="tp-card">
    <?php if (empty($leads)): ?>
        <div class="tp-empty-state py-5 text-center">
            <i class="fa-solid fa-funnel fa-2x mb-3" style="color:var(--gy);"></i>
            <p class="mb-0">No leads found<?= $q !== '' ? ' matching "' . e($q) . '"' : '' ?>.</p>
            <?php if (has_role('admin', 'office')): ?>
                <a href="<?= APP_URL ?>/modules/leads/create.php" class="btn-tp-primary btn-tp-sm mt-3">
                    <i class="fa-solid fa-plus"></i> Add First Lead
                </a>
            <?php endif; ?>
        </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="tp-table">
            <thead>
                <tr>
                    <th>Name / Email</th>
                    <th>Phone</th>
                    <th>Size</th>
                    <th>Project Type</th>
                    <th>Source</th>
                    <th>Status</th>
                    <th>Assigned</th>
                    <th>Date</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leads as $lead): ?>
                <tr>
                    <td>
                        <a href="<?= APP_URL ?>/modules/leads/view.php?id=<?= (int)$lead['id'] ?>"
                           class="fw-semibold text-decoration-none">
                            <?= e($lead['name']) ?>
                        </a>
                        <?php if (!empty($lead['email'])): ?>
                            <br><small class="text-muted"><?= e($lead['email']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= e(fmt_phone($lead['phone'])) ?></td>
                    <td><?= e($lead['size_needed'] ?? '—') ?></td>
                    <td><?= e($lead['project_type'] ?? '—') ?></td>
                    <td><?= e($lead['source'] ?? '—') ?></td>
                    <td><?= status_badge($lead['status'] ?? 'new') ?></td>
                    <td><?= e($lead['assigned_name'] ?? '—') ?></td>
                    <td>
                        <span title="<?= e(fmt_datetime($lead['created_at'])) ?>">
                            <?= e(days_ago($lead['created_at'])) ?>
                        </span>
                    </td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end">
                            <a href="<?= APP_URL ?>/modules/leads/view.php?id=<?= (int)$lead['id'] ?>"
                               class="btn-tp-ghost btn-tp-sm" title="View">
                                <i class="fa-solid fa-eye"></i> View
                            </a>
                            <?php if (has_role('admin', 'office')): ?>
                            <a href="<?= APP_URL ?>/modules/leads/edit.php?id=<?= (int)$lead['id'] ?>"
                               class="btn-tp-ghost btn-tp-sm" title="Edit">
                                <i class="fa-solid fa-pencil"></i> Edit
                            </a>
                            <a href="<?= APP_URL ?>/modules/leads/delete.php?id=<?= (int)$lead['id'] ?>"
                               class="btn-tp-ghost btn-tp-sm text-danger" title="Archive"
                               onclick="return confirm('Archive this lead?')">
                                <i class="fa-solid fa-box-archive"></i> Archive
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($pag['pages'] > 1): ?>
    <div class="tp-pagination d-flex align-items-center justify-content-between px-3 py-2 border-top">
        <small class="text-muted">
            Showing <?= ($pag['offset'] + 1) ?>–<?= min($pag['offset'] + $pag['per_page'], $total) ?>
            of <?= $total ?> leads
        </small>
        <div class="d-flex gap-1">
            <?php for ($p = 1; $p <= $pag['pages']; $p++):
                $q_args = array_filter(['status' => $status_filter, 'source' => $source_filter, 'q' => $q]);
                $q_args['page'] = $p;
                $href = APP_URL . '/modules/leads/index.php?' . http_build_query($q_args);
            ?>
                <a href="<?= e($href) ?>"
                   class="btn-tp-ghost btn-tp-sm <?= $p === $pag['page'] ? 'active' : '' ?>">
                    <?= $p ?>
                </a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<?php layout_end(); ?>
