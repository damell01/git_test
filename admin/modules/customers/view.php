<?php
/**
 * Customers – View
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once TMPL_PATH . '/layout.php';

require_login();

// ── Fetch customer ───────────────────────────────────────────────────────────
$id   = (int)($_GET['id'] ?? 0);
$cust = $id ? db_fetch("SELECT * FROM customers WHERE id = ? LIMIT 1", [$id]) : false;

if (!$cust) {
    http_response_code(404);
    die('<h1>404 – Customer not found.</h1>');
}

// ── Fetch related data ───────────────────────────────────────────────────────
$work_orders = db_fetchall(
    "SELECT * FROM work_orders WHERE customer_id = ? ORDER BY created_at DESC",
    [$id]
);

$quotes = db_fetchall(
    "SELECT * FROM quotes WHERE customer_id = ? ORDER BY created_at DESC",
    [$id]
);

// ── Type badge helper ─────────────────────────────────────────────────────────
function cust_type_badge(string $type): string
{
    $map = [
        'residential' => 'badge-scheduled',
        'commercial'  => 'badge-quoted',
        'contractor'  => 'badge-active',
    ];
    $css   = $map[strtolower($type)] ?? 'badge-new';
    $label = ucfirst($type);
    return '<span class="tp-badge ' . $css . '">' . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . '</span>';
}

layout_start('Customer: ' . $cust['name'], 'customers');
?>

<!-- Page header -->
<div class="tp-page-header d-flex align-items-center justify-content-between mb-3">
    <div>
        <a href="<?= APP_URL ?>/modules/customers/index.php" class="text-muted small text-decoration-none">
            <i class="fa-solid fa-arrow-left"></i> Back to Customers
        </a>
        <h2 class="tp-page-title mb-0 mt-1">
            <?= e($cust['name']) ?>
            <?= cust_type_badge($cust['type'] ?? 'residential') ?>
        </h2>
        <?php if (!empty($cust['company'])): ?>
            <p class="text-muted mb-0"><?= e($cust['company']) ?></p>
        <?php endif; ?>
    </div>
</div>

<!-- 2-Column layout -->
<div class="row g-4">

    <!-- ====== LEFT COLUMN ====== -->
    <div class="col-lg-8">

        <!-- Customer Info Card -->
        <div class="tp-card mb-4">
            <div class="tp-card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0"><i class="fa-solid fa-circle-info me-2"></i>Customer Information</h5>
                <?php if (has_role('admin', 'office')): ?>
                <a href="<?= APP_URL ?>/modules/customers/edit.php?id=<?= (int)$cust['id'] ?>"
                   class="btn-tp-ghost btn-tp-sm">
                    <i class="fa-solid fa-pencil"></i> Edit
                </a>
                <?php endif; ?>
            </div>
            <div class="detail-grid mt-3">
                <?php if (!empty($cust['company'])): ?>
                <div class="detail-row">
                    <span class="detail-label">Company</span>
                    <span class="detail-value"><?= e($cust['company']) ?></span>
                </div>
                <?php endif; ?>
                <div class="detail-row">
                    <span class="detail-label">Phone</span>
                    <span class="detail-value">
                        <?= e(fmt_phone($cust['phone'] ?? '')) ?: '—' ?>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Email</span>
                    <span class="detail-value">
                        <?php if (!empty($cust['email'])): ?>
                            <a href="mailto:<?= e($cust['email']) ?>"><?= e($cust['email']) ?></a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Service Address</span>
                    <span class="detail-value">
                        <?php
                        $parts = array_filter([
                            $cust['address'] ?? '',
                            $cust['city']    ?? '',
                            $cust['state']   ?? '',
                            $cust['zip']     ?? '',
                        ]);
                        echo $parts ? e(implode(', ', $parts)) : '—';
                        ?>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Billing Address</span>
                    <span class="detail-value">
                        <?php
                        $bparts = array_filter([
                            $cust['billing_address'] ?? '',
                            $cust['billing_city']    ?? '',
                            $cust['billing_state']   ?? '',
                            $cust['billing_zip']     ?? '',
                        ]);
                        echo $bparts ? e(implode(', ', $bparts)) : '—';
                        ?>
                    </span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Type</span>
                    <span class="detail-value"><?= cust_type_badge($cust['type'] ?? 'residential') ?></span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Customer Since</span>
                    <span class="detail-value"><?= e(fmt_date($cust['created_at'])) ?></span>
                </div>
                <?php if (!empty($cust['notes'])): ?>
                <div class="detail-row">
                    <span class="detail-label">Notes</span>
                    <span class="detail-value"><?= nl2br(e($cust['notes'])) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Linked Work Orders -->
        <div class="tp-card mb-4">
            <div class="tp-card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0">
                    <i class="fa-solid fa-clipboard-list me-2"></i>Work Orders
                    <span class="tp-badge badge-active ms-2"><?= count($work_orders) ?></span>
                </h5>
                <?php if (has_role('admin', 'office')): ?>
                <a href="<?= APP_URL ?>/modules/work_orders/create.php?customer_id=<?= (int)$cust['id'] ?>"
                   class="btn-tp-ghost btn-tp-sm">
                    <i class="fa-solid fa-plus"></i> New Work Order
                </a>
                <?php endif; ?>
            </div>

            <?php if (empty($work_orders)): ?>
                <p class="text-muted mt-3 mb-0">No work orders found for this customer.</p>
            <?php else: ?>
            <div class="table-responsive mt-3">
                <table class="tp-table">
                    <thead>
                        <tr>
                            <th>WO #</th>
                            <th>Status</th>
                            <th>Size</th>
                            <th>Scheduled</th>
                            <th>Total</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($work_orders as $wo): ?>
                        <tr>
                            <td>
                                <a href="<?= APP_URL ?>/modules/work_orders/view.php?id=<?= (int)$wo['id'] ?>"
                                   class="fw-semibold text-decoration-none">
                                    <?= e($wo['wo_number'] ?? '#' . $wo['id']) ?>
                                </a>
                            </td>
                            <td><?= status_badge($wo['status'] ?? 'scheduled') ?></td>
                            <td><?= e($wo['size_needed'] ?? $wo['size'] ?? '—') ?></td>
                            <td><?= e(fmt_date($wo['delivery_date'] ?? null)) ?: '—' ?></td>
                            <td><?= e(fmt_money($wo['total'] ?? 0)) ?></td>
                            <td class="text-end">
                                <a href="<?= APP_URL ?>/modules/work_orders/view.php?id=<?= (int)$wo['id'] ?>"
                                   class="btn-tp-ghost btn-tp-sm">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Linked Quotes -->
        <div class="tp-card">
            <div class="tp-card-header d-flex align-items-center justify-content-between">
                <h5 class="mb-0">
                    <i class="fa-solid fa-file-invoice-dollar me-2"></i>Quotes
                    <span class="tp-badge badge-active ms-2"><?= count($quotes) ?></span>
                </h5>
                <?php if (has_role('admin', 'office')): ?>
                <a href="<?= APP_URL ?>/modules/quotes/create.php?customer_id=<?= (int)$cust['id'] ?>"
                   class="btn-tp-ghost btn-tp-sm">
                    <i class="fa-solid fa-plus"></i> New Quote
                </a>
                <?php endif; ?>
            </div>

            <?php if (empty($quotes)): ?>
                <p class="text-muted mt-3 mb-0">No quotes found for this customer.</p>
            <?php else: ?>
            <div class="table-responsive mt-3">
                <table class="tp-table">
                    <thead>
                        <tr>
                            <th>Quote #</th>
                            <th>Status</th>
                            <th>Amount</th>
                            <th>Date</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quotes as $quote): ?>
                        <tr>
                            <td>
                                <a href="<?= APP_URL ?>/modules/quotes/view.php?id=<?= (int)$quote['id'] ?>">
                                    <?= e($quote['quote_number'] ?? '#' . $quote['id']) ?>
                                </a>
                            </td>
                            <td><?= status_badge($quote['status'] ?? 'draft') ?></td>
                            <td><?= e(fmt_money($quote['total'] ?? 0)) ?></td>
                            <td><?= e(fmt_date($quote['created_at'])) ?></td>
                            <td class="text-end">
                                <a href="<?= APP_URL ?>/modules/quotes/view.php?id=<?= (int)$quote['id'] ?>"
                                   class="btn-tp-ghost btn-tp-sm">
                                    <i class="fa-solid fa-eye"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

    </div><!-- /col-lg-8 -->

    <!-- ====== RIGHT COLUMN ====== -->
    <div class="col-lg-4">

        <!-- Quick Actions Card -->
        <div class="tp-card">
            <div class="tp-card-header">
                <h5 class="mb-0"><i class="fa-solid fa-bolt me-2"></i>Quick Actions</h5>
            </div>
            <div class="qa-grid mt-3 d-flex flex-column gap-2">
                <?php if (has_role('admin', 'office')): ?>
                <a href="<?= APP_URL ?>/modules/work_orders/create.php?customer_id=<?= (int)$cust['id'] ?>"
                   class="btn-tp-primary w-100 justify-content-start">
                    <i class="fa-solid fa-clipboard-list"></i> New Work Order
                </a>
                <a href="<?= APP_URL ?>/modules/quotes/create.php?customer_id=<?= (int)$cust['id'] ?>"
                   class="btn-tp-ghost w-100 justify-content-start">
                    <i class="fa-solid fa-file-invoice-dollar"></i> New Quote
                </a>
                <a href="<?= APP_URL ?>/modules/customers/edit.php?id=<?= (int)$cust['id'] ?>"
                   class="btn-tp-ghost w-100 justify-content-start">
                    <i class="fa-solid fa-pencil"></i> Edit Customer
                </a>
                <a href="<?= APP_URL ?>/modules/customers/delete.php?id=<?= (int)$cust['id'] ?>"
                   class="btn-tp-ghost w-100 justify-content-start text-danger"
                   onclick="return confirm('Delete this customer? This action cannot be undone.')">
                    <i class="fa-solid fa-trash"></i> Delete Customer
                </a>
                <?php else: ?>
                <p class="text-muted small mb-0">No actions available for your role.</p>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /col-lg-4 -->

</div><!-- /row -->

<?php layout_end(); ?>
