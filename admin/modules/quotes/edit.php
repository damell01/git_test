<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once TMPL_PATH . '/layout.php';
require_login();
require_role('admin', 'office');
$pdo = get_db();

// ── Fetch quote ───────────────────────────────────────────────────────────────
$id    = (int)($_GET['id'] ?? 0);
$stmt  = $pdo->prepare('SELECT * FROM quotes WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$quote = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quote) {
    flash_error('Quote not found.');
    redirect('index.php');
}

$errors = [];
$old    = $quote; // default to existing data

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $old = [
        'cust_name'      => trim($_POST['cust_name']       ?? ''),
        'cust_phone'     => trim($_POST['cust_phone']      ?? ''),
        'cust_email'     => trim($_POST['cust_email']      ?? ''),
        'cust_address'   => trim($_POST['cust_address']    ?? ''),
        'service_address'=> trim($_POST['service_address'] ?? ''),
        'service_city'   => trim($_POST['service_city']    ?? ''),
        'size'           => trim($_POST['size']            ?? ''),
        'project_type'   => trim($_POST['project_type']    ?? ''),
        'rental_days'    => (int)($_POST['rental_days']    ?? 7),
        'rental_price'   => trim($_POST['rental_price']    ?? ''),
        'delivery_fee'   => trim($_POST['delivery_fee']    ?? '0'),
        'pickup_fee'     => trim($_POST['pickup_fee']      ?? '0'),
        'extra_fees'     => trim($_POST['extra_fees']      ?? '0'),
        'extra_fee_desc' => trim($_POST['extra_fee_desc']  ?? ''),
        'tax_rate'       => trim($_POST['tax_rate']        ?? '0'),
        'valid_until'    => trim($_POST['valid_until']     ?? ''),
        'notes'          => trim($_POST['notes']           ?? ''),
        'terms'          => trim($_POST['terms']           ?? ''),
    ];

    // Validation
    if ($old['cust_name'] === '') {
        $errors[] = 'Customer name is required.';
    }
    if ($old['rental_price'] === '' || !is_numeric($old['rental_price'])) {
        $errors[] = 'Rental price is required and must be a number.';
    }

    if (empty($errors)) {
        $rental_price = (float)$old['rental_price'];
        $delivery_fee = (float)$old['delivery_fee'];
        $pickup_fee   = (float)$old['pickup_fee'];
        $extra_fees   = (float)$old['extra_fees'];
        $tax_rate     = (float)$old['tax_rate'];

        $subtotal   = $rental_price + $delivery_fee + $pickup_fee + $extra_fees;
        $tax_amount = $subtotal * ($tax_rate / 100);
        $total      = $subtotal + $tax_amount;

        $update = $pdo->prepare(
            'UPDATE quotes SET
                cust_name       = ?,
                cust_phone      = ?,
                cust_email      = ?,
                cust_address    = ?,
                service_address = ?,
                service_city    = ?,
                size            = ?,
                project_type    = ?,
                rental_days     = ?,
                rental_price    = ?,
                delivery_fee    = ?,
                pickup_fee      = ?,
                extra_fees      = ?,
                extra_fee_desc  = ?,
                tax_rate        = ?,
                tax_amount      = ?,
                subtotal        = ?,
                total           = ?,
                valid_until     = ?,
                notes           = ?,
                terms           = ?,
                updated_at      = NOW()
             WHERE id = ?'
        );
        $update->execute([
            $old['cust_name'],
            $old['cust_phone'],
            $old['cust_email'],
            $old['cust_address'],
            $old['service_address'],
            $old['service_city'],
            $old['size'],
            $old['project_type'],
            (int)$old['rental_days'],
            $rental_price,
            $delivery_fee,
            $pickup_fee,
            $extra_fees,
            $old['extra_fee_desc'],
            $tax_rate,
            $tax_amount,
            $subtotal,
            $total,
            $old['valid_until'] ?: null,
            $old['notes'],
            $old['terms'],
            $id,
        ]);

        log_activity('update_quote', 'Updated quote ' . $quote['quote_number'], $id);
        flash_success('Quote ' . $quote['quote_number'] . ' updated successfully.');
        redirect('view.php?id=' . $id);
    }
}

$sizes         = dumpster_sizes();
$project_types = project_types();

layout_start('Edit Quote: ' . $quote['quote_number'], 'quotes');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Edit Quote: <span class="text-primary"><?= htmlspecialchars($quote['quote_number']) ?></span></h1>
    <div>
        <a href="view.php?id=<?= $id ?>" class="btn btn-outline-secondary me-2">
            <i class="fas fa-eye me-1"></i>View Quote
        </a>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left me-1"></i>Back
        </a>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <strong>Please fix the following errors:</strong>
    <ul class="mb-0 mt-1">
        <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form method="post" action="">
    <?= csrf_field() ?>

    <div class="row g-4">
        <!-- Left Column -->
        <div class="col-lg-7">
            <!-- Customer Information -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-user me-2"></i>Customer Information</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="cust_name" class="form-label">Customer Name <span class="text-danger">*</span></label>
                        <input type="text" id="cust_name" name="cust_name" class="form-control"
                               value="<?= htmlspecialchars($old['cust_name']) ?>" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="cust_phone" class="form-label">Phone</label>
                            <input type="text" id="cust_phone" name="cust_phone" class="form-control"
                                   value="<?= htmlspecialchars($old['cust_phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="cust_email" class="form-label">Email</label>
                            <input type="email" id="cust_email" name="cust_email" class="form-control"
                                   value="<?= htmlspecialchars($old['cust_email'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label for="cust_address" class="form-label">Billing Address</label>
                        <input type="text" id="cust_address" name="cust_address" class="form-control"
                               value="<?= htmlspecialchars($old['cust_address'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Service Details -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-map-marker-alt me-2"></i>Service Details</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="service_address" class="form-label">Service Address</label>
                        <input type="text" id="service_address" name="service_address" class="form-control"
                               value="<?= htmlspecialchars($old['service_address'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="service_city" class="form-label">Service City</label>
                        <input type="text" id="service_city" name="service_city" class="form-control"
                               value="<?= htmlspecialchars($old['service_city'] ?? '') ?>">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="size" class="form-label">Dumpster Size</label>
                            <select id="size" name="size" class="form-select">
                                <option value="">— Select Size —</option>
                                <?php foreach ($sizes as $sz): ?>
                                    <option value="<?= htmlspecialchars($sz) ?>"
                                        <?= ($old['size'] ?? '') === $sz ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($sz) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="project_type" class="form-label">Project Type</label>
                            <select id="project_type" name="project_type" class="form-select">
                                <option value="">— Select Type —</option>
                                <?php foreach ($project_types as $pt): ?>
                                    <option value="<?= htmlspecialchars($pt) ?>"
                                        <?= ($old['project_type'] ?? '') === $pt ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($pt) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label for="rental_days" class="form-label">Rental Days</label>
                        <input type="number" id="rental_days" name="rental_days" class="form-control"
                               min="1" value="<?= (int)($old['rental_days'] ?? 7) ?>">
                    </div>
                </div>
            </div>

            <!-- Notes & Terms -->
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-sticky-note me-2"></i>Notes &amp; Terms</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea id="notes" name="notes" class="form-control" rows="3"><?= htmlspecialchars($old['notes'] ?? '') ?></textarea>
                    </div>
                    <div>
                        <label for="terms" class="form-label">Terms &amp; Conditions</label>
                        <textarea id="terms" name="terms" class="form-control" rows="5"><?= htmlspecialchars($old['terms'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Pricing -->
        <div class="col-lg-5">
            <div class="card shadow-sm mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-dollar-sign me-2"></i>Pricing</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label for="rental_price" class="form-label">Rental Price <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" id="rental_price" name="rental_price"
                                   class="form-control calc-input" step="0.01" min="0"
                                   value="<?= htmlspecialchars($old['rental_price'] ?? '') ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="delivery_fee" class="form-label">Delivery Fee</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" id="delivery_fee" name="delivery_fee"
                                   class="form-control calc-input" step="0.01" min="0"
                                   value="<?= htmlspecialchars($old['delivery_fee'] ?? '0') ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="pickup_fee" class="form-label">Pickup Fee</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" id="pickup_fee" name="pickup_fee"
                                   class="form-control calc-input" step="0.01" min="0"
                                   value="<?= htmlspecialchars($old['pickup_fee'] ?? '0') ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="extra_fees" class="form-label">Extra Fees</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" id="extra_fees" name="extra_fees"
                                   class="form-control calc-input" step="0.01" min="0"
                                   value="<?= htmlspecialchars($old['extra_fees'] ?? '0') ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="extra_fee_desc" class="form-label">Extra Fee Description</label>
                        <input type="text" id="extra_fee_desc" name="extra_fee_desc" class="form-control"
                               value="<?= htmlspecialchars($old['extra_fee_desc'] ?? '') ?>">
                    </div>
                    <div class="mb-3">
                        <label for="tax_rate" class="form-label">Tax Rate (%)</label>
                        <div class="input-group">
                            <input type="number" id="tax_rate" name="tax_rate"
                                   class="form-control calc-input" step="0.01" min="0" max="100"
                                   value="<?= htmlspecialchars($old['tax_rate'] ?? '0') ?>">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="valid_until" class="form-label">Valid Until</label>
                        <input type="date" id="valid_until" name="valid_until" class="form-control"
                               value="<?= htmlspecialchars($old['valid_until'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Live Calculator -->
            <div class="card shadow-sm mb-4 border-primary">
                <div class="card-header bg-primary text-white">
                    <h5 class="card-title mb-0"><i class="fas fa-calculator me-2"></i>Quote Summary</h5>
                </div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr>
                                <td class="text-muted">Subtotal</td>
                                <td class="text-end fw-semibold" id="calc-subtotal">$0.00</td>
                            </tr>
                            <tr>
                                <td class="text-muted">Tax</td>
                                <td class="text-end fw-semibold" id="calc-tax">$0.00</td>
                            </tr>
                            <tr class="table-primary">
                                <td class="fw-bold">Total</td>
                                <td class="text-end fw-bold fs-5" id="calc-total">$0.00</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-save me-1"></i>Save Changes
                </button>
                <a href="view.php?id=<?= $id ?>" class="btn btn-outline-secondary">Cancel</a>
            </div>
        </div>
    </div>
</form>

<script>
(function () {
    'use strict';

    function formatMoney(n) {
        return '$' + parseFloat(n || 0).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }

    function recalculate() {
        var rental   = parseFloat(document.getElementById('rental_price').value)  || 0;
        var delivery = parseFloat(document.getElementById('delivery_fee').value)   || 0;
        var pickup   = parseFloat(document.getElementById('pickup_fee').value)     || 0;
        var extra    = parseFloat(document.getElementById('extra_fees').value)     || 0;
        var taxRate  = parseFloat(document.getElementById('tax_rate').value)       || 0;

        var subtotal  = rental + delivery + pickup + extra;
        var taxAmount = subtotal * (taxRate / 100);
        var total     = subtotal + taxAmount;

        document.getElementById('calc-subtotal').textContent = formatMoney(subtotal);
        document.getElementById('calc-tax').textContent      = formatMoney(taxAmount);
        document.getElementById('calc-total').textContent    = formatMoney(total);
    }

    document.querySelectorAll('.calc-input').forEach(function (el) {
        el.addEventListener('input', recalculate);
    });

    recalculate();
})();
</script>

<?php layout_end(); ?>
