<?php
/**
 * Bookings – Create (Admin Manual Booking)
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once TMPL_PATH . '/layout.php';
require_login();
require_role('admin', 'office');

$errors = [];

// ── Load active dumpsters ─────────────────────────────────────────────────────
$units = db_fetchall(
    "SELECT id, unit_code, type, size, daily_rate
     FROM dumpsters
     WHERE active = 1 AND status != 'maintenance'
     ORDER BY size, unit_code"
);

// ── POST handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $customer_name    = trim($_POST['customer_name']    ?? '');
    $customer_phone   = trim($_POST['customer_phone']   ?? '');
    $customer_email   = trim($_POST['customer_email']   ?? '');
    $customer_address = trim($_POST['customer_address'] ?? '');
    $customer_city    = trim($_POST['customer_city']    ?? '');
    $dumpster_id      = (int)($_POST['dumpster_id']     ?? 0);
    $rental_start     = trim($_POST['rental_start']     ?? '');
    $rental_end       = trim($_POST['rental_end']       ?? '');
    $payment_method   = trim($_POST['payment_method']   ?? 'stripe');
    $notes            = trim($_POST['notes']            ?? '');

    // Required validation
    if ($customer_name === '') {
        $errors[] = 'Customer Name is required.';
    }
    if ($dumpster_id <= 0) {
        $errors[] = 'Please select a unit.';
    }
    if ($rental_start === '') {
        $errors[] = 'Start Date is required.';
    }
    if ($rental_end === '') {
        $errors[] = 'End Date is required.';
    }
    if (!in_array($payment_method, ['stripe', 'cash', 'check'], true)) {
        $errors[] = 'Invalid payment method.';
    }

    if (empty($errors) && $rental_start >= $rental_end) {
        $errors[] = 'End Date must be after Start Date.';
    }

    // Fetch unit
    $unit = null;
    if (empty($errors) && $dumpster_id > 0) {
        $unit = db_fetch(
            "SELECT id, unit_code, type, size, daily_rate, active, status
             FROM dumpsters WHERE id = ? LIMIT 1",
            [$dumpster_id]
        );
        if (!$unit) {
            $errors[] = 'Selected unit not found.';
        } elseif (!$unit['active'] || $unit['status'] === 'maintenance') {
            $errors[] = 'Selected unit is not available.';
        }
    }

    // Availability check
    if (empty($errors) && $unit) {
        $overlap = db_fetch(
            "SELECT COUNT(*) AS cnt FROM bookings
             WHERE dumpster_id = ?
               AND booking_status != 'canceled'
               AND rental_start <= ? AND rental_end >= ?",
            [$dumpster_id, $rental_end, $rental_start]
        );
        if ((int)($overlap['cnt'] ?? 0) > 0) {
            $errors[] = 'That unit is already booked during the selected dates.';
        }

        if (empty($errors)) {
            $block = db_fetch(
                "SELECT COUNT(*) AS cnt FROM inventory_blocks
                 WHERE dumpster_id = ?
                   AND block_start <= ? AND block_end >= ?",
                [$dumpster_id, $rental_end, $rental_start]
            );
            if ((int)($block['cnt'] ?? 0) > 0) {
                $errors[] = 'That unit has a scheduled block during the selected dates.';
            }
        }
    }

    if (empty($errors) && $unit) {
        $start_ts   = strtotime($rental_start);
        $end_ts     = strtotime($rental_end);
        $days       = max(1, (int)(($end_ts - $start_ts) / 86400));
        $daily_rate = (float)$unit['daily_rate'];
        $total      = round($daily_rate * $days, 2);

        // Generate booking number
        $booking_number = next_number('BK', 'bookings', 'booking_number');

        // Payment status based on method
        $pay_status_map = [
            'stripe' => 'pending',
            'cash'   => 'pending_cash',
            'check'  => 'pending_check',
        ];
        $payment_status = $pay_status_map[$payment_method] ?? 'pending';

        $new_id = db_insert('bookings', [
            'booking_number'   => $booking_number,
            'customer_name'    => $customer_name,
            'customer_phone'   => $customer_phone ?: null,
            'customer_email'   => $customer_email ?: null,
            'customer_address' => $customer_address ?: null,
            'customer_city'    => $customer_city ?: null,
            'dumpster_id'      => $dumpster_id,
            'unit_code'        => $unit['unit_code'],
            'unit_type'        => $unit['type'],
            'unit_size'        => $unit['size'],
            'rental_start'     => $rental_start,
            'rental_end'       => $rental_end,
            'rental_days'      => $days,
            'daily_rate'       => $daily_rate,
            'total_amount'     => $total,
            'payment_method'   => $payment_method,
            'payment_status'   => $payment_status,
            'booking_status'   => 'confirmed',
            'notes'            => $notes ?: null,
            'created_by'       => (int)($_SESSION['user_id'] ?? 0),
            'created_at'       => date('Y-m-d H:i:s'),
            'updated_at'       => date('Y-m-d H:i:s'),
        ]);

        log_activity('create', "Created booking $booking_number for $customer_name", 'booking', (int)$new_id);
        flash_success("Booking $booking_number created successfully.");
        redirect('view.php?id=' . (int)$new_id);
    }
}

// ── Pre-fill values on validation failure ─────────────────────────────────────
$f = [
    'customer_name'    => $_POST['customer_name']    ?? '',
    'customer_phone'   => $_POST['customer_phone']   ?? '',
    'customer_email'   => $_POST['customer_email']   ?? '',
    'customer_address' => $_POST['customer_address'] ?? '',
    'customer_city'    => $_POST['customer_city']    ?? '',
    'dumpster_id'      => (int)($_POST['dumpster_id'] ?? 0),
    'rental_start'     => $_POST['rental_start']     ?? '',
    'rental_end'       => $_POST['rental_end']       ?? '',
    'payment_method'   => $_POST['payment_method']   ?? 'stripe',
    'notes'            => $_POST['notes']            ?? '',
];

layout_start('New Booking', 'bookings');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">New Booking</h5>
    <a href="index.php" class="btn-tp-ghost btn-tp-sm">
        <i class="fa-solid fa-arrow-left"></i> Back to Bookings
    </a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="tp-card" style="max-width:760px;">
    <form method="POST" action="create.php" id="bookingForm">
        <?= csrf_field() ?>

        <h6 class="mb-3" style="font-weight:600;border-bottom:1px solid var(--st);padding-bottom:.5rem;">
            Customer Information
        </h6>

        <div class="row g-3 mb-4">
            <div class="col-md-6">
                <label class="form-label" for="customer_name">
                    Customer Name <span class="text-danger">*</span>
                </label>
                <input type="text" id="customer_name" name="customer_name" class="form-control"
                       value="<?= e($f['customer_name']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label" for="customer_phone">Phone</label>
                <input type="text" id="customer_phone" name="customer_phone" class="form-control"
                       value="<?= e($f['customer_phone']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="customer_email">Email</label>
                <input type="email" id="customer_email" name="customer_email" class="form-control"
                       value="<?= e($f['customer_email']) ?>">
            </div>
            <div class="col-md-6">
                <label class="form-label" for="customer_city">City</label>
                <input type="text" id="customer_city" name="customer_city" class="form-control"
                       value="<?= e($f['customer_city']) ?>">
            </div>
            <div class="col-12">
                <label class="form-label" for="customer_address">Address</label>
                <input type="text" id="customer_address" name="customer_address" class="form-control"
                       value="<?= e($f['customer_address']) ?>">
            </div>
        </div>

        <h6 class="mb-3" style="font-weight:600;border-bottom:1px solid var(--st);padding-bottom:.5rem;">
            Unit &amp; Dates
        </h6>

        <div class="row g-3 mb-4">
            <div class="col-12">
                <label class="form-label" for="dumpster_id">
                    Unit <span class="text-danger">*</span>
                </label>
                <select id="dumpster_id" name="dumpster_id" class="form-select" required
                        onchange="updateTotal()">
                    <option value="">— Select unit —</option>
                    <?php foreach ($units as $u): ?>
                    <option value="<?= (int)$u['id'] ?>"
                            data-rate="<?= e($u['daily_rate']) ?>"
                            <?= $f['dumpster_id'] === (int)$u['id'] ? 'selected' : '' ?>>
                        <?= e($u['unit_code']) ?> — <?= e($u['size']) ?>
                        (<?= e(ucfirst($u['type'])) ?>) — <?= e(fmt_money($u['daily_rate'])) ?>/day
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="rental_start">
                    Start Date <span class="text-danger">*</span>
                </label>
                <input type="date" id="rental_start" name="rental_start" class="form-control"
                       value="<?= e($f['rental_start']) ?>"
                       min="<?= date('Y-m-d') ?>" required
                       onchange="updateTotal()">
            </div>
            <div class="col-md-4">
                <label class="form-label" for="rental_end">
                    End Date <span class="text-danger">*</span>
                </label>
                <input type="date" id="rental_end" name="rental_end" class="form-control"
                       value="<?= e($f['rental_end']) ?>"
                       min="<?= date('Y-m-d', strtotime('+1 day')) ?>" required
                       onchange="updateTotal()">
            </div>
            <div class="col-md-4">
                <label class="form-label">Estimated Total</label>
                <div id="totalDisplay" class="form-control" style="background:var(--dk3);color:var(--or);font-weight:700;">
                    —
                </div>
            </div>
        </div>

        <h6 class="mb-3" style="font-weight:600;border-bottom:1px solid var(--st);padding-bottom:.5rem;">
            Payment &amp; Notes
        </h6>

        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label" for="payment_method">Payment Method</label>
                <select id="payment_method" name="payment_method" class="form-select">
                    <option value="stripe"  <?= $f['payment_method'] === 'stripe'  ? 'selected' : '' ?>>Stripe (Online)</option>
                    <option value="cash"    <?= $f['payment_method'] === 'cash'    ? 'selected' : '' ?>>Cash</option>
                    <option value="check"   <?= $f['payment_method'] === 'check'   ? 'selected' : '' ?>>Check</option>
                </select>
            </div>
            <div class="col-12">
                <label class="form-label" for="notes">Notes</label>
                <textarea id="notes" name="notes" class="form-control" rows="3"
                          placeholder="Internal notes about this booking…"><?= e($f['notes']) ?></textarea>
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn-tp-primary">
                    <i class="fa-solid fa-calendar-check"></i> Create Booking
                </button>
                <a href="index.php" class="btn-tp-ghost">Cancel</a>
            </div>
        </div>

    </form>
</div>

<script>
function updateTotal() {
    var sel   = document.getElementById('dumpster_id');
    var start = document.getElementById('rental_start').value;
    var end   = document.getElementById('rental_end').value;
    var disp  = document.getElementById('totalDisplay');

    if (!sel.value || !start || !end) {
        disp.textContent = '—';
        return;
    }

    var rate     = parseFloat(sel.options[sel.selectedIndex].dataset.rate) || 0;
    var startUTC = Date.UTC.apply(null, start.split('-').map(function(v,i){ return i===1?parseInt(v,10)-1:parseInt(v,10); }));
    var endUTC   = Date.UTC.apply(null, end.split('-').map(function(v,i){ return i===1?parseInt(v,10)-1:parseInt(v,10); }));
    var days     = Math.round((endUTC - startUTC) / 86400000);

    if (days <= 0) {
        disp.textContent = 'Invalid dates';
        return;
    }

    var total = rate * days;
    disp.textContent = days + ' day' + (days !== 1 ? 's' : '') + ' — $' + total.toFixed(2);
}
</script>

<?php
layout_end();
