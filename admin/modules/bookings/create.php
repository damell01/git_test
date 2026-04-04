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
    "SELECT id, unit_code, type, size, daily_rate, base_price, rental_days, extra_day_price
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
    $rental_start     = trim($_POST['rental_start']     ?? '');
    $rental_end       = trim($_POST['rental_end']       ?? '');
    $payment_method   = trim($_POST['payment_method']   ?? 'stripe');
    $worker_id        = (int)($_POST['worker_id']       ?? 0) ?: null;
    $notes            = trim($_POST['notes']            ?? '');

    // Support both single dumpster_id and multi dumpster_ids[]
    $selected_ids = [];
    if (!empty($_POST['dumpster_ids']) && is_array($_POST['dumpster_ids'])) {
        $selected_ids = array_map('intval', $_POST['dumpster_ids']);
        $selected_ids = array_filter($selected_ids, fn($v) => $v > 0);
        $selected_ids = array_values($selected_ids);
    } elseif (!empty($_POST['dumpster_id'])) {
        $single_id = (int)$_POST['dumpster_id'];
        if ($single_id > 0) {
            $selected_ids = [$single_id];
        }
    }

    // Required validation
    if ($customer_name === '') {
        $errors[] = 'Customer Name is required.';
    }
    if (empty($selected_ids)) {
        $errors[] = 'Please select at least one unit.';
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

    if (empty($errors) && $rental_start > $rental_end) {
        $errors[] = 'End Date must be on or after Start Date.';
    }

    // Validate each selected unit
    $validated_units = [];
    if (empty($errors)) {
        foreach ($selected_ids as $did) {
            $unit = db_fetch(
                "SELECT id, unit_code, type, size, daily_rate, base_price, rental_days, extra_day_price, active, status
                 FROM dumpsters WHERE id = ? LIMIT 1",
                [$did]
            );
            if (!$unit) {
                $errors[] = "Unit ID $did not found.";
                break;
            }
            if (!$unit['active'] || $unit['status'] === 'maintenance') {
                $errors[] = "Unit {$unit['unit_code']} is not available.";
                break;
            }
            // Overlap check
            $overlap = db_fetch(
                "SELECT COUNT(*) AS cnt FROM bookings
                 WHERE dumpster_id = ?
                   AND booking_status != 'canceled'
                   AND rental_start <= ? AND rental_end >= ?",
                [$did, $rental_end, $rental_start]
            );
            if ((int)($overlap['cnt'] ?? 0) > 0) {
                $errors[] = "Unit {$unit['unit_code']} is already booked during the selected dates.";
                break;
            }
            $block = db_fetch(
                "SELECT COUNT(*) AS cnt FROM inventory_blocks
                 WHERE dumpster_id = ?
                   AND block_start <= ? AND block_end >= ?",
                [$did, $rental_end, $rental_start]
            );
            if ((int)($block['cnt'] ?? 0) > 0) {
                $errors[] = "Unit {$unit['unit_code']} has a scheduled block during the selected dates.";
                break;
            }
            $validated_units[] = $unit;
        }
    }

    if (empty($errors) && !empty($validated_units)) {
        $d1   = new \DateTime($rental_start);
        $d2   = new \DateTime($rental_end);
        $days = max(1, (int)$d1->diff($d2)->days);

        $pay_status_map = [
            'stripe' => 'pending',
            'cash'   => 'pending_cash',
            'check'  => 'pending_check',
        ];
        $payment_status = $pay_status_map[$payment_method] ?? 'pending';

        $created_ids     = [];
        $created_numbers = [];

        foreach ($validated_units as $unit) {
            $daily_rate      = (float)$unit['daily_rate'];
            $base_price      = (float)($unit['base_price'] ?? 0);
            $incl_days       = max(1, (int)($unit['rental_days'] ?? 7));
            $extra_day_price = isset($unit['extra_day_price']) && $unit['extra_day_price'] !== null
                ? (float)$unit['extra_day_price'] : null;

            if ($base_price > 0) {
                $extra_days = max(0, $days - $incl_days);
                $total = round($base_price + ($extra_days * ($extra_day_price ?? 0)), 2);
            } else {
                $total = round($daily_rate * $days, 2);
            }
            $booking_number = next_number('BK', 'bookings', 'booking_number');

            $new_id = db_insert('bookings', [
                'booking_number'   => $booking_number,
                'customer_name'    => $customer_name,
                'customer_phone'   => $customer_phone ?: null,
                'customer_email'   => $customer_email ?: null,
                'customer_address' => $customer_address ?: null,
                'customer_city'    => $customer_city ?: null,
                'dumpster_id'      => (int)$unit['id'],
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
                'worker_id'        => $worker_id,
                'notes'            => $notes ?: null,
                'created_by'       => (int)($_SESSION['user_id'] ?? 0),
                'created_at'       => date('Y-m-d H:i:s'),
                'updated_at'       => date('Y-m-d H:i:s'),
            ]);

            log_activity('create', "Created booking $booking_number for $customer_name", 'booking', (int)$new_id);

            // Mark dumpster as reserved
            db_update('dumpsters', [
                'status'     => 'reserved',
                'updated_at' => date('Y-m-d H:i:s'),
            ], 'id', (int)$unit['id']);

            $created_ids[]     = (int)$new_id;
            $created_numbers[] = $booking_number;
        }

        $count = count($created_ids);
        if ($count === 1) {
            flash_success("Booking {$created_numbers[0]} created successfully.");
            redirect('view.php?id=' . $created_ids[0]);
        } else {
            flash_success("$count bookings created: " . implode(', ', $created_numbers));
            redirect('index.php');
        }
    }
}

// ── Pre-fill values on validation failure ─────────────────────────────────────
$f = [
    'customer_name'    => $_POST['customer_name']    ?? '',
    'customer_phone'   => $_POST['customer_phone']   ?? '',
    'customer_email'   => $_POST['customer_email']   ?? '',
    'customer_address' => $_POST['customer_address'] ?? '',
    'customer_city'    => $_POST['customer_city']    ?? '',
    'selected_ids'     => array_map('intval', (array)($_POST['dumpster_ids'] ?? [])),
    'rental_start'     => $_POST['rental_start']     ?? '',
    'rental_end'       => $_POST['rental_end']       ?? '',
    'payment_method'   => $_POST['payment_method']   ?? 'stripe',
    'worker_id'        => (int)($_POST['worker_id']  ?? 0),
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

<div class="tp-card" style="max-width:880px;">
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
            Units &amp; Dates
            <small style="font-size:.75rem;color:var(--gl);font-weight:400;text-transform:none;letter-spacing:0;">
                (select one or more)
            </small>
        </h6>

        <div class="row g-3 mb-4">
            <!-- Unit selection checkboxes -->
            <div class="col-12">
                <label class="form-label">
                    Unit(s) <span class="text-danger">*</span>
                </label>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:8px;">
                    <?php foreach ($units as $u): ?>
                    <?php $checked = in_array((int)$u['id'], $f['selected_ids'], true); ?>
                    <label class="unit-checkbox-card<?= $checked ? ' selected' : '' ?>"
                           data-id="<?= (int)$u['id'] ?>"
                           data-rate="<?= e($u['daily_rate']) ?>"
                           data-base="<?= e($u['base_price'] ?? 0) ?>"
                           data-incl="<?= (int)($u['rental_days'] ?? 7) ?>"
                           data-extra="<?= e($u['extra_day_price'] ?? '') ?>">
                        <input type="checkbox" name="dumpster_ids[]"
                               value="<?= (int)$u['id'] ?>"
                               <?= $checked ? 'checked' : '' ?>
                               onchange="updateTotal(); toggleCardStyle(this);"
                               style="margin-right:6px;">
                        <strong><?= e($u['unit_code']) ?></strong>
                        — <?= e($u['size']) ?>
                        <span style="font-size:.78rem;color:var(--gl);display:block;margin-left:20px;">
                            <?php if ((float)($u['base_price'] ?? 0) > 0): ?>
                                <?= e(fmt_money($u['base_price'])) ?> / <?= (int)($u['rental_days'] ?? 7) ?> days
                            <?php else: ?>
                                <?= e(fmt_money($u['daily_rate'])) ?>/day
                            <?php endif; ?>
                            <?php if ($u['status'] !== 'available'): ?>
                                · <span style="color:var(--am);"><?= e(ucfirst($u['status'])) ?></span>
                            <?php endif; ?>
                        </span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <div class="mt-2">
                    <button type="button" class="btn-tp-ghost btn-tp-xs" onclick="selectAllUnits(true)">Select All</button>
                    <button type="button" class="btn-tp-ghost btn-tp-xs ms-1" onclick="selectAllUnits(false)">Clear All</button>
                </div>
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

<style>
.unit-checkbox-card {
    display: block;
    padding: 10px 12px;
    background: var(--dk2);
    border: 1px solid var(--st2);
    border-radius: 8px;
    cursor: pointer;
    transition: border-color .2s, background .2s;
    font-size: .88rem;
    color: var(--wh);
}
.unit-checkbox-card:hover {
    border-color: var(--or);
}
.unit-checkbox-card.selected {
    border-color: var(--or);
    background: rgba(249,115,22,.1);
}
</style>

<script>
function calcDays() {
    var start = document.getElementById('rental_start').value;
    var end   = document.getElementById('rental_end').value;
    if (!start || !end) return 0;
    var s = Date.UTC.apply(null, start.split('-').map(function(v,i){ return i===1?parseInt(v,10)-1:parseInt(v,10); }));
    var e2 = Date.UTC.apply(null, end.split('-').map(function(v,i){ return i===1?parseInt(v,10)-1:parseInt(v,10); }));
    return Math.max(0, Math.round((e2 - s) / 86400000));
}

function updateTotal() {
    var disp  = document.getElementById('totalDisplay');
    var days  = calcDays();
    if (days <= 0) { disp.textContent = 'Invalid dates'; return; }

    var boxes = document.querySelectorAll('input[name="dumpster_ids[]"]:checked');
    if (boxes.length === 0) { disp.textContent = '—'; return; }

    var totalAmt = 0;
    boxes.forEach(function(cb) {
        var card  = cb.closest('.unit-checkbox-card');
        if (!card) return;
        var base  = parseFloat(card.dataset.base)  || 0;
        var incl  = parseInt(card.dataset.incl, 10) || 7;
        var extra = card.dataset.extra !== '' ? parseFloat(card.dataset.extra) : 0;
        var rate  = parseFloat(card.dataset.rate)  || 0;
        if (base > 0) {
            var extraDays = Math.max(0, days - incl);
            totalAmt += base + (extraDays * extra);
        } else {
            totalAmt += rate * days;
        }
    });

    var unitWord = boxes.length > 1 ? ' (' + boxes.length + ' units)' : '';
    disp.textContent = days + ' day' + (days !== 1 ? 's' : '') + unitWord + ' — $' + totalAmt.toFixed(2);
}

function toggleCardStyle(cb) {
    var card = cb.closest('.unit-checkbox-card');
    if (card) card.classList.toggle('selected', cb.checked);
}

function selectAllUnits(selectAll) {
    document.querySelectorAll('input[name="dumpster_ids[]"]').forEach(function(cb) {
        cb.checked = selectAll;
        toggleCardStyle(cb);
    });
    updateTotal();
}
</script>

<?php
layout_end();
