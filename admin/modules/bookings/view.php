<?php
/**
 * Bookings – View
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once TMPL_PATH . '/layout.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    flash_error('Invalid booking ID.');
    redirect('index.php');
}

$booking = db_fetch('SELECT * FROM bookings WHERE id = ? LIMIT 1', [$id]);
if (!$booking) {
    flash_error('Booking not found.');
    redirect('index.php');
}

$stripe_payment_url = stripe_dashboard_url($booking['stripe_payment_id'] ?? '');
$stripe_session_url = stripe_dashboard_url($booking['stripe_session_id'] ?? '');

layout_start('Booking Detail', 'bookings');
?>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h5 class="mb-0">Booking <?= e($booking['booking_number']) ?></h5>
        <small class="text-muted">Created <?= e(fmt_datetime($booking['created_at'])) ?></small>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="index.php" class="btn-tp-ghost btn-tp-sm">
            <i class="fa-solid fa-arrow-left"></i> Back
        </a>
        <a href="edit.php?id=<?= $id ?>" class="btn-tp-ghost btn-tp-sm">
            <i class="fa-solid fa-pen"></i> Edit
        </a>
        <a href="update_payment.php?id=<?= $id ?>" class="btn-tp-ghost btn-tp-sm">
            <i class="fa-solid fa-credit-card"></i> Update Payment
        </a>
        <?php if ($booking['booking_status'] !== 'canceled'): ?>
        <form method="POST" action="cancel.php" class="d-inline"
              onsubmit="return confirm('Cancel this booking?');">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= $id ?>">
            <button type="submit" class="btn-tp-danger btn-tp-sm">
                <i class="fa-solid fa-ban"></i> Cancel Booking
            </button>
        </form>
        <?php endif; ?>
        <?php if (has_role('admin')): ?>
        <a href="delete.php?id=<?= $id ?>"
           class="btn-tp-danger btn-tp-sm"
           onclick="return confirm('Permanently delete booking <?= e($booking['booking_number']) ?>? This cannot be undone.')">
            <i class="fa-solid fa-trash"></i> Delete
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-4">

    <!-- Left column: Customer + Unit -->
    <div class="col-12 col-lg-7">

        <!-- Customer Info -->
        <div class="tp-card mb-4">
            <div class="tp-card-header">
                <i class="fa-solid fa-user me-2 text-muted"></i> Customer Information
            </div>
            <div class="tp-card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="text-muted" style="font-size:.8rem;">Name</div>
                        <div class="fw-semibold"><?= e($booking['customer_name']) ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted" style="font-size:.8rem;">Phone</div>
                        <div><?= $booking['customer_phone'] ? e(fmt_phone($booking['customer_phone'])) : '<span class="text-muted">—</span>' ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted" style="font-size:.8rem;">Email</div>
                        <div><?= $booking['customer_email'] ? '<a href="mailto:' . e($booking['customer_email']) . '">' . e($booking['customer_email']) . '</a>' : '<span class="text-muted">—</span>' ?></div>
                    </div>
                    <div class="col-md-6">
                        <div class="text-muted" style="font-size:.8rem;">Address</div>
                        <div>
                            <?php if ($booking['customer_address'] || $booking['customer_city']): ?>
                                <?= e($booking['customer_address'] ?? '') ?>
                                <?php if ($booking['customer_city']): ?>, <?= e($booking['customer_city']) ?><?php endif; ?>
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Unit Info -->
        <div class="tp-card mb-4">
            <div class="tp-card-header">
                <i class="fa-solid fa-dumpster me-2 text-muted"></i> Unit Information
            </div>
            <div class="tp-card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="text-muted" style="font-size:.8rem;">Unit Code</div>
                        <div class="fw-semibold"><?= $booking['unit_code'] ? e($booking['unit_code']) : '<span class="text-muted">—</span>' ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted" style="font-size:.8rem;">Type</div>
                        <div><?= $booking['unit_type'] ? e(ucfirst($booking['unit_type'])) : '<span class="text-muted">—</span>' ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted" style="font-size:.8rem;">Size</div>
                        <div><?= $booking['unit_size'] ? e($booking['unit_size']) : '<span class="text-muted">—</span>' ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dates -->
        <div class="tp-card mb-4">
            <div class="tp-card-header">
                <i class="fa-solid fa-calendar me-2 text-muted"></i> Rental Period
            </div>
            <div class="tp-card-body">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="text-muted" style="font-size:.8rem;">Start Date</div>
                        <div class="fw-semibold"><?= e(fmt_date($booking['rental_start'])) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted" style="font-size:.8rem;">End Date</div>
                        <div class="fw-semibold"><?= e(fmt_date($booking['rental_end'])) ?></div>
                    </div>
                    <div class="col-md-4">
                        <div class="text-muted" style="font-size:.8rem;">Days</div>
                        <div class="fw-semibold"><?= (int)$booking['rental_days'] ?></div>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($booking['notes']): ?>
        <div class="tp-card">
            <div class="tp-card-header">
                <i class="fa-solid fa-note-sticky me-2 text-muted"></i> Notes
            </div>
            <div class="tp-card-body">
                <p class="mb-0" style="white-space:pre-wrap;"><?= e($booking['notes']) ?></p>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <!-- Right column: Financial + Status -->
    <div class="col-12 col-lg-5">

        <!-- Status -->
        <div class="tp-card mb-4">
            <div class="tp-card-header">
                <i class="fa-solid fa-circle-info me-2 text-muted"></i> Status
            </div>
            <div class="tp-card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="text-muted" style="font-size:.8rem;">Booking Status</div>
                        <div class="mt-1"><?= status_badge($booking['booking_status']) ?></div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted" style="font-size:.8rem;">Payment Status</div>
                        <div class="mt-1"><?= payment_badge($booking['payment_status']) ?></div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted" style="font-size:.8rem;">Payment Method</div>
                        <div><?= e(ucfirst($booking['payment_method'])) ?></div>
                    </div>
                    <div class="col-6">
                        <div class="text-muted" style="font-size:.8rem;">Updated</div>
                        <div><?= e(fmt_datetime($booking['updated_at'])) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial -->
        <div class="tp-card mb-4">
            <div class="tp-card-header">
                <i class="fa-solid fa-dollar-sign me-2 text-muted"></i> Financial
            </div>
            <div class="tp-card-body">
                <table class="w-100" style="font-size:.9rem;">
                    <tr>
                        <td class="text-muted py-1">Daily Rate</td>
                        <td class="text-end fw-semibold"><?= e(fmt_money($booking['daily_rate'])) ?></td>
                    </tr>
                    <tr>
                        <td class="text-muted py-1">Days</td>
                        <td class="text-end fw-semibold"><?= (int)$booking['rental_days'] ?></td>
                    </tr>
                    <tr style="border-top:1px solid var(--st);">
                        <td class="py-1 fw-bold">Total</td>
                        <td class="text-end fw-bold" style="color:var(--or);font-size:1.1rem;">
                            <?= e(fmt_money($booking['total_amount'])) ?>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Stripe Info -->
        <?php if ($booking['stripe_session_id'] || $booking['stripe_payment_id']): ?>
        <div class="tp-card">
            <div class="tp-card-header">
                <i class="fa-brands fa-stripe me-2 text-muted"></i> Stripe
            </div>
            <div class="tp-card-body">
                <?php if ($booking['stripe_session_id']): ?>
                <div class="mb-2">
                    <div class="text-muted" style="font-size:.8rem;">Session ID</div>
                    <div style="font-size:.8rem;word-break:break-all;"><?= e($booking['stripe_session_id']) ?></div>
                    <?php if ($stripe_session_url): ?>
                    <a href="<?= e($stripe_session_url) ?>" target="_blank" rel="noopener noreferrer"
                       class="btn-tp-ghost btn-tp-xs mt-2">
                        <i class="fa-brands fa-stripe"></i> Open Session in Stripe
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
                <?php if ($booking['stripe_payment_id']): ?>
                <div>
                    <div class="text-muted" style="font-size:.8rem;">Payment ID</div>
                    <div style="font-size:.8rem;word-break:break-all;"><?= e($booking['stripe_payment_id']) ?></div>
                    <?php if ($stripe_payment_url): ?>
                    <a href="<?= e($stripe_payment_url) ?>" target="_blank" rel="noopener noreferrer"
                       class="btn-tp-ghost btn-tp-xs mt-2">
                        <i class="fa-brands fa-stripe"></i> Open Payment in Stripe
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>
</div>

<?php
layout_end();
