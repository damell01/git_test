<?php
/**
 * Bookings – Update Payment (GET form + POST handler)
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once TMPL_PATH . '/layout.php';
require_login();
require_role('admin', 'office');

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    flash_error('Invalid booking ID.');
    redirect('index.php');
}

$booking = db_fetch('SELECT * FROM bookings WHERE id = ? LIMIT 1', [$id]);
if (!$booking) {
    flash_error('Booking not found.');
    redirect('index.php');
}

$allowed_payment_statuses = [
    'unpaid', 'pending', 'paid', 'refunded',
    'pending_cash', 'paid_cash', 'pending_check', 'paid_check',
];
$allowed_booking_statuses = ['pending', 'confirmed', 'paid', 'canceled', 'completed'];

// ── POST handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $payment_status = trim($_POST['payment_status'] ?? '');
    $booking_status = trim($_POST['booking_status'] ?? '');
    $payment_notes  = trim($_POST['payment_notes']  ?? '');

    $errors = [];
    if (!in_array($payment_status, $allowed_payment_statuses, true)) {
        $errors[] = 'Invalid payment status.';
    }
    if (!in_array($booking_status, $allowed_booking_statuses, true)) {
        $errors[] = 'Invalid booking status.';
    }

    if (empty($errors)) {
        $old_status = $booking['booking_status'];

        db_update('bookings', [
            'payment_status' => $payment_status,
            'booking_status' => $booking_status,
            'payment_notes'  => $payment_notes !== '' ? $payment_notes : null,
            'updated_at'     => date('Y-m-d H:i:s'),
        ], 'id', $id);

        // If booking is now canceled or completed, release the dumpster
        if (in_array($booking_status, ['canceled', 'completed'], true)
            && !in_array($old_status, ['canceled', 'completed'], true)
            && !empty($booking['dumpster_id'])
        ) {
            release_dumpster_if_free((int)$booking['dumpster_id'], $id);
        }

        // If booking was previously canceled/completed and is being re-activated, re-reserve
        if (in_array($old_status, ['canceled', 'completed'], true)
            && !in_array($booking_status, ['canceled', 'completed'], true)
            && !empty($booking['dumpster_id'])
        ) {
            db_update('dumpsters', [
                'status'     => 'reserved',
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id', (int)$booking['dumpster_id']);
        }

        log_activity(
            'update',
            "Updated payment for booking {$booking['booking_number']}: payment=$payment_status, status=$booking_status",
            'booking',
            $id
        );
        flash_success("Payment status updated for booking {$booking['booking_number']}.");
        redirect('view.php?id=' . $id);
    }
}

layout_start('Update Payment', 'bookings');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Update Payment — <span class="text-muted"><?= e($booking['booking_number']) ?></span></h5>
    <a href="view.php?id=<?= $id ?>" class="btn-tp-ghost btn-tp-sm">
        <i class="fa-solid fa-arrow-left"></i> Back
    </a>
</div>

<?php if (!empty($errors ?? [])): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="tp-card" style="max-width:480px;">
    <form method="POST" action="update_payment.php">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">

        <div class="row g-3">
            <div class="col-12">
                <div class="p-3 rounded mb-2" style="background:var(--dk3);">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted" style="font-size:.85rem;">Customer</span>
                        <span class="fw-semibold"><?= e($booking['customer_name']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-1">
                        <span class="text-muted" style="font-size:.85rem;">Total</span>
                        <span class="fw-bold" style="color:var(--or);"><?= e(fmt_money($booking['total_amount'])) ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span class="text-muted" style="font-size:.85rem;">Method</span>
                        <span><?= e(ucfirst($booking['payment_method'])) ?></span>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <label class="form-label" for="payment_status">Payment Status</label>
                <select id="payment_status" name="payment_status" class="form-select">
                    <?php
                    $ps_labels = [
                        'unpaid'        => 'Unpaid',
                        'pending'       => 'Pending',
                        'paid'          => 'Paid',
                        'refunded'      => 'Refunded',
                        'pending_cash'  => 'Cash (Pending)',
                        'paid_cash'     => 'Cash (Paid)',
                        'pending_check' => 'Check (Pending)',
                        'paid_check'    => 'Check (Paid)',
                    ];
                    foreach ($ps_labels as $val => $lbl):
                    ?>
                    <option value="<?= e($val) ?>"
                            <?= $booking['payment_status'] === $val ? 'selected' : '' ?>>
                        <?= e($lbl) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12">
                <label class="form-label" for="booking_status">Booking Status</label>
                <select id="booking_status" name="booking_status" class="form-select">
                    <?php
                    $bs_labels = [
                        'pending'   => 'Pending',
                        'confirmed' => 'Confirmed',
                        'paid'      => 'Paid',
                        'canceled'  => 'Canceled',
                        'completed' => 'Completed',
                    ];
                    foreach ($bs_labels as $val => $lbl):
                    ?>
                    <option value="<?= e($val) ?>"
                            <?= $booking['booking_status'] === $val ? 'selected' : '' ?>>
                        <?= e($lbl) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12">
                <label class="form-label" for="payment_notes">Payment Note <small class="text-muted">optional — for cash/check</small></label>
                <input type="text"
                       id="payment_notes"
                       name="payment_notes"
                       class="form-control"
                       placeholder="e.g. Paid at office, check #1042…"
                       value="<?= e($booking['payment_notes'] ?? '') ?>">
            </div>

            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn-tp-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Save
                </button>
                <a href="view.php?id=<?= $id ?>" class="btn-tp-ghost">Cancel</a>
            </div>
        </div>
    </form>
</div>

<?php
layout_end();
