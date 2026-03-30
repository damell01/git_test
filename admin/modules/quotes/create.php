<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_login();
require_role('admin', 'office');

// ── Pre-fill from Lead ────────────────────────────────────────────────────────
$lead_id     = null;
$customer_id = null;
$prefill     = [
    'cust_name'      => '',
    'cust_email'     => '',
    'cust_phone'     => '',
    'cust_address'   => '',
    'service_address'=> '',
    'service_city'   => '',
];

if (!empty($_GET['lead_id'])) {
    $lead_id = (int)$_GET['lead_id'];
    $lead    = $pdo->prepare('SELECT * FROM leads WHERE id = ? LIMIT 1');
    $lead->execute([$lead_id]);
    $lead = $lead->fetch(PDO::FETCH_ASSOC);
    if ($lead) {
        $prefill['cust_name']    = $lead['name']    ?? '';
        $prefill['cust_email']   = $lead['email']   ?? '';
        $prefill['cust_phone']   = $lead['phone']   ?? '';
        $prefill['cust_address'] = $lead['address'] ?? '';
    } else {
        $lead_id = null;
    }
}

if (!empty($_GET['customer_id'])) {
    $customer_id = (int)$_GET['customer_id'];
    $cust_stmt   = $pdo->prepare('SELECT * FROM customers WHERE id = ? LIMIT 1');
    $cust_stmt->execute([$customer_id]);
    $cust = $cust_stmt->fetch(PDO::FETCH_ASSOC);
    if ($cust) {
        $prefill['cust_name']      = $cust['name']            ?? '';
        $prefill['cust_email']     = $cust['email']           ?? '';
        $prefill['cust_phone']     = $cust['phone']           ?? '';
        $prefill['cust_address']   = $cust['billing_address'] ?? '';
        $prefill['service_address']= $cust['service_address'] ?? '';
        $prefill['service_city']   = $cust['service_city']    ?? '';
    } else {
        $customer_id = null;
    }
}

// ── Default values ────────────────────────────────────────────────────────────
$default_tax_rate = get_setting('tax_rate', '8.00');
$default_terms    = get_setting('quote_terms', '');
$errors           = [];
$old              = array_merge($prefill, [
    'size'           => '',
    'project_type'   => '',
    'rental_days'    => '7',
    'rental_price'   => '',
    'delivery_fee'   => '0.00',
    'pickup_fee'     => '0.00',
    'extra_fees'     => '0.00',
    'extra_fee_desc' => '',
    'tax_rate'       => $default_tax_rate,
    'valid_until'    => date('Y-m-d', strtotime('+30 days')),
    'notes'          => '',
    'terms'          => $default_terms,
]);

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $old = [
        'cust_name'      => trim($_POST['cust_name']      ?? ''),
        'cust_phone'     => trim($_POST['cust_phone']     ?? ''),
        'cust_email'     => trim($_POST['cust_email']     ?? ''),
        'cust_address'   => trim($_POST['cust_address']   ?? ''),
        'service_address'=> trim($_POST['service_address']?? ''),
        'service_city'   => trim($_POST['service_city']   ?? ''),
        'size'           => trim($_POST['size']           ?? ''),
        'project_type'   => trim($_POST['project_type']   ?? ''),
        'rental_days'    => (int)($_POST['rental_days']   ?? 7),
        'rental_price'   => trim($_POST['rental_price']   ?? ''),
        'delivery_fee'   => trim($_POST['delivery_fee']   ?? '0'),
        'pickup_fee'     => trim($_POST['pickup_fee']     ?? '0'),
        'extra_fees'     => trim($_POST['extra_fees']     ?? '0'),
        'extra_fee_desc' => trim($_POST['extra_fee_desc'] ?? ''),
        'tax_rate'       => trim($_POST['tax_rate']       ?? $default_tax_rate),
        'valid_until'    => trim($_POST['valid_until']    ?? ''),
        'notes'          => trim($_POST['notes']          ?? ''),
        'terms'          => trim($_POST['terms']          ?? ''),
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

        $quote_number = next_number('Q', 'quotes', 'quote_number');

        $lead_id_val     = !empty($_POST['lead_id'])     ? (int)$_POST['lead_id']     : null;
        $customer_id_val = !empty($_POST['customer_id']) ? (int)$_POST['customer_id'] : null;

        $insert_id = db_insert('quotes', [
            'quote_number'   => $quote_number,
            'lead_id'        => $lead_id_val,
            'customer_id'    => $customer_id_val,
            'cust_name'      => $old['cust_name'],
            'cust_phone'     => $old['cust_phone'],
            'cust_email'     => $old['cust_email'],
            'cust_address'   => $old['cust_address'],
            'service_address'=> $old['service_address'],
            'service_city'   => $old['service_city'],
            'size'           => $old['size'],
            'project_type'   => $old['project_type'],
            'rental_days'    => (int)$old['rental_days'],
            'rental_price'   => $rental_price,
            'delivery_fee'   => $delivery_fee,
            'pickup_fee'     => $pickup_fee,
            'extra_fees'     => $extra_fees,
            'extra_fee_desc' => $old['extra_fee_desc'],
            'tax_rate'       => $tax_rate,
            'tax_amount'     => $tax_amount,
            'subtotal'       => $subtotal,
            'total'          => $total,
            'valid_until'    => $old['valid_until'] ?: null,
            'notes'          => $old['notes'],
            'terms'          => $old['terms'],
            'status'         => 'draft',
            'created_by'     => $_SESSION['user_id'],
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        log_activity('create_quote', 'Created quote ' . $quote_number, $insert_id);
        flash_success('Quote ' . $quote_number . ' created successfully.');
        redirect('view.php?id=' . $insert_id);
    }
}

$sizes         = dumpster_sizes();
$project_types = project_types();

layout_start('New Quote', 'quotes');
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">New Quote</h1>
    <a href="index.php" class="btn btn-outline-secondary">
        <i class="fas fa-arrow-left me-1"></i>Back to Quotes
    </a>
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
    <?php if ($lead_id): ?>
        <input type="hidden" name="lead_id" value="<?= (int)$lead_id ?>">
    <?php endif; ?>
    <?php if ($customer_id): ?>
        <input type="hidden" name="customer_id" value="<?= (int)$customer_id ?>">
    <?php endif; ?>

    <div class="row g-4">
        <!-- Left Column: Customer & Service Info -->
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
                                   value="<?= htmlspecialchars($old['cust_phone']) ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="cust_email" class="form-label">Email</label>
                            <input type="email" id="cust_email" name="cust_email" class="form-control"
                                   value="<?= htmlspecialchars($old['cust_email']) ?>">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label for="cust_address" class="form-label">Billing Address</label>
                        <input type="text" id="cust_address" name="cust_address" class="form-control"
                               value="<?= htmlspecialchars($old['cust_address']) ?>">
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
                               value="<?= htmlspecialchars($old['service_address']) ?>">
                    </div>
                    <div class="mb-3">
                        <label for="service_city" class="form-label">Service City</label>
                        <input type="text" id="service_city" name="service_city" class="form-control"
                               value="<?= htmlspecialchars($old['service_city']) ?>">
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label for="size" class="form-label">Dumpster Size</label>
                            <select id="size" name="size" class="form-select">
                                <option value="">— Select Size —</option>
                                <?php foreach ($sizes as $sz): ?>
                                    <option value="<?= htmlspecialchars($sz) ?>"
                                        <?= $old['size'] === $sz ? 'selected' : '' ?>>
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
                                        <?= $old['project_type'] === $pt ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($pt) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mt-3">
                        <label for="rental_days" class="form-label">Rental Days</label>
                        <input type="number" id="rental_days" name="rental_days" class="form-control"
                               min="1" value="<?= (int)$old['rental_days'] ?>">
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
                        <textarea id="notes" name="notes" class="form-control" rows="3"><?= htmlspecialchars($old['notes']) ?></textarea>
                    </div>
                    <div>
                        <label for="terms" class="form-label">Terms &amp; Conditions</label>
                        <textarea id="terms" name="terms" class="form-control" rows="5"><?= htmlspecialchars($old['terms']) ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Pricing & Summary -->
        <div class="col-lg-5">
            <!-- Pricing -->
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
                                   value="<?= htmlspecialchars($old['rental_price']) ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="delivery_fee" class="form-label">Delivery Fee</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" id="delivery_fee" name="delivery_fee"
                                   class="form-control calc-input" step="0.01" min="0"
                                   value="<?= htmlspecialchars($old['delivery_fee']) ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="pickup_fee" class="form-label">Pickup Fee</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" id="pickup_fee" name="pickup_fee"
                                   class="form-control calc-input" step="0.01" min="0"
                                   value="<?= htmlspecialchars($old['pickup_fee']) ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="extra_fees" class="form-label">Extra Fees</label>
                        <div class="input-group">
                            <span class="input-group-text">$</span>
                            <input type="number" id="extra_fees" name="extra_fees"
                                   class="form-control calc-input" step="0.01" min="0"
                                   value="<?= htmlspecialchars($old['extra_fees']) ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="extra_fee_desc" class="form-label">Extra Fee Description</label>
                        <input type="text" id="extra_fee_desc" name="extra_fee_desc" class="form-control"
                               value="<?= htmlspecialchars($old['extra_fee_desc']) ?>"
                               placeholder="e.g. Overweight charge">
                    </div>
                    <div class="mb-3">
                        <label for="tax_rate" class="form-label">Tax Rate (%)</label>
                        <div class="input-group">
                            <input type="number" id="tax_rate" name="tax_rate"
                                   class="form-control calc-input" step="0.01" min="0" max="100"
                                   value="<?= htmlspecialchars($old['tax_rate']) ?>">
                            <span class="input-group-text">%</span>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="valid_until" class="form-label">Valid Until</label>
                        <input type="date" id="valid_until" name="valid_until" class="form-control"
                               value="<?= htmlspecialchars($old['valid_until']) ?>">
                    </div>
                </div>
            </div>

            <!-- Live Quote Calculator -->
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
                    <i class="fas fa-save me-1"></i>Create Quote
                </button>
                <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
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
