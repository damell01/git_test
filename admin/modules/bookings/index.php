<?php
/**
 * Bookings – List
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once TMPL_PATH . '/layout.php';
require_login();

// ── Filters ───────────────────────────────────────────────────────────────────
$filter  = trim($_GET['filter'] ?? 'all');
$page    = max(1, (int)($_GET['page'] ?? 1));
$per_page = 25;

$allowed_filters = ['all', 'pending', 'confirmed', 'paid', 'canceled', 'upcoming'];
if (!in_array($filter, $allowed_filters, true)) {
    $filter = 'all';
}

// ── Build WHERE clause ────────────────────────────────────────────────────────
$where  = '1=1';
$params = [];

switch ($filter) {
    case 'pending':
        $where  = 'b.booking_status = ?';
        $params = ['pending'];
        break;
    case 'confirmed':
        $where  = 'b.booking_status = ?';
        $params = ['confirmed'];
        break;
    case 'paid':
        $where  = "b.booking_status = 'paid' OR b.payment_status = 'paid'";
        break;
    case 'canceled':
        $where  = 'b.booking_status = ?';
        $params = ['canceled'];
        break;
    case 'upcoming':
        $where  = "b.rental_start >= CURDATE() AND b.booking_status NOT IN ('canceled','completed')";
        break;
}

// ── Count ─────────────────────────────────────────────────────────────────────
$total_row = db_fetch(
    "SELECT COUNT(*) AS cnt FROM bookings b WHERE $where",
    $params
);
$total = (int)($total_row['cnt'] ?? 0);
$pager = paginate($total, $page, $per_page);

// ── Fetch rows ────────────────────────────────────────────────────────────────
$bookings = db_fetchall(
    "SELECT b.id, b.booking_number, b.customer_name, b.customer_email,
            b.unit_code, b.unit_type, b.unit_size,
            b.rental_start, b.rental_end, b.rental_days,
            b.total_amount, b.payment_method, b.payment_status, b.booking_status,
            b.stripe_payment_id, b.stripe_session_id,
            b.created_at
     FROM bookings b
     WHERE $where
     ORDER BY b.created_at DESC
     LIMIT ? OFFSET ?",
    array_merge($params, [$pager['per_page'], $pager['offset']])
);

layout_start('Bookings', 'bookings');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Bookings</h5>
    <a href="create.php" class="btn-tp-primary btn-tp-sm">
        <i class="fa-solid fa-plus"></i> New Booking
    </a>
</div>

<!-- Filter Tabs -->
<div class="tp-filter-tabs mb-3">
    <?php
    $tabs = [
        'all'       => 'All',
        'pending'   => 'Pending',
        'confirmed' => 'Confirmed',
        'paid'      => 'Paid',
        'upcoming'  => 'Upcoming',
        'canceled'  => 'Canceled',
    ];
    foreach ($tabs as $key => $label):
        $active_class = $filter === $key ? ' active' : '';
    ?>
    <a href="?filter=<?= e($key) ?>" class="tp-filter-tab<?= $active_class ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
</div>

<div class="tp-card p-0">
    <?php if (empty($bookings)): ?>
        <p class="text-muted p-4 mb-0 text-center">No bookings found.</p>
    <?php else: ?>
    <div class="table-responsive">
        <table class="tp-table">
            <thead>
                <tr>
                    <th>Booking #</th>
                    <th>Customer</th>
                    <th>Unit</th>
                    <th>Dates</th>
                    <th class="text-center">Days</th>
                    <th class="text-end">Total</th>
                    <th>Method</th>
                    <th>Payment</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($bookings as $b): ?>
            <?php
                $stripeLink = stripe_dashboard_url($b['stripe_payment_id'] ?? '');
                if ($stripeLink === null) {
                    $stripeLink = stripe_dashboard_url($b['stripe_session_id'] ?? '');
                }
            ?>
            <tr>
                <td>
                    <a href="view.php?id=<?= (int)$b['id'] ?>" class="fw-semibold text-decoration-none">
                        <?= e($b['booking_number']) ?>
                    </a>
                </td>
                <td>
                    <div><?= e($b['customer_name']) ?></div>
                    <?php if ($b['customer_email']): ?>
                    <div style="font-size:.8rem;color:var(--gy);"><?= e($b['customer_email']) ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($b['unit_code']): ?>
                    <div class="fw-semibold"><?= e($b['unit_code']) ?></div>
                    <div style="font-size:.8rem;color:var(--gy);">
                        <?= e($b['unit_size'] ?? '') ?>
                        <?php if ($b['unit_type']): ?> · <?= e(ucfirst($b['unit_type'])) ?><?php endif; ?>
                    </div>
                    <?php else: ?>
                    <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <div><?= e(fmt_date($b['rental_start'])) ?></div>
                    <div style="font-size:.8rem;color:var(--gy);">→ <?= e(fmt_date($b['rental_end'])) ?></div>
                </td>
                <td class="text-center"><?= (int)$b['rental_days'] ?></td>
                <td class="text-end fw-semibold"><?= e(fmt_money($b['total_amount'])) ?></td>
                <td><?= e(ucfirst($b['payment_method'])) ?></td>
                <td>
                    <?= payment_badge($b['payment_status']) ?>
                    <?php if (($b['payment_method'] ?? '') === 'stripe' && !empty($stripeLink)): ?>
                    <div style="margin-top:.35rem;">
                        <a href="<?= e($stripeLink) ?>" target="_blank" rel="noopener noreferrer"
                           class="btn-tp-ghost btn-tp-xs" title="Open this payment in Stripe Dashboard">
                            <i class="fa-brands fa-stripe"></i> Stripe
                        </a>
                    </div>
                    <?php endif; ?>
                </td>
                <td><?= status_badge($b['booking_status']) ?></td>
                <td class="text-end">
                    <a href="view.php?id=<?= (int)$b['id'] ?>" class="btn-tp-ghost btn-tp-xs">View</a>
                    <a href="edit.php?id=<?= (int)$b['id'] ?>" class="btn-tp-ghost btn-tp-xs">Edit</a>
                    <?php if (has_role('admin')): ?>
                    <a href="delete.php?id=<?= (int)$b['id'] ?>" class="btn-tp-ghost btn-tp-xs text-danger"
                       onclick="return confirm('Permanently delete booking <?= e($b['booking_number']) ?>? This cannot be undone.')">Delete</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pager['pages'] > 1): ?>
    <div class="d-flex justify-content-between align-items-center p-3" style="border-top:1px solid var(--st);">
        <small class="text-muted">
            Showing <?= $pager['offset'] + 1 ?>–<?= min($pager['offset'] + $pager['per_page'], $total) ?> of <?= $total ?>
        </small>
        <div class="d-flex gap-1">
            <?php if ($pager['page'] > 1): ?>
            <a href="?filter=<?= e($filter) ?>&page=<?= $pager['page'] - 1 ?>" class="btn-tp-ghost btn-tp-xs">
                <i class="fa-solid fa-chevron-left"></i>
            </a>
            <?php endif; ?>
            <?php for ($p = max(1, $pager['page'] - 2); $p <= min($pager['pages'], $pager['page'] + 2); $p++): ?>
            <a href="?filter=<?= e($filter) ?>&page=<?= $p ?>"
               class="btn-tp-ghost btn-tp-xs<?= $p === $pager['page'] ? ' active' : '' ?>">
                <?= $p ?>
            </a>
            <?php endfor; ?>
            <?php if ($pager['page'] < $pager['pages']): ?>
            <a href="?filter=<?= e($filter) ?>&page=<?= $pager['page'] + 1 ?>" class="btn-tp-ghost btn-tp-xs">
                <i class="fa-solid fa-chevron-right"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<?php
layout_end();
