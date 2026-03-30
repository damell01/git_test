<?php
/**
 * Payments – Stripe Charge Page
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once INC_PATH . '/stripe.php';
require_once INC_PATH . '/mailer.php';
require_once TMPL_PATH . '/layout.php';
require_login();

$invoice_id = (int)($_GET['invoice_id'] ?? 0);
$wo_id      = (int)($_GET['wo_id']      ?? 0);

// Load invoice or work order
$inv = null;
$wo  = null;

if ($invoice_id > 0) {
    $inv = db_fetch(
        'SELECT i.*, wo.wo_number, wo.cust_name, wo.cust_email, wo.service_address, wo.service_city, wo.size
         FROM invoices i
         LEFT JOIN work_orders wo ON i.work_order_id = wo.id
         WHERE i.id = ? LIMIT 1',
        [$invoice_id]
    );
    if ($inv) {
        $wo = db_fetch('SELECT * FROM work_orders WHERE id = ? LIMIT 1', [$inv['work_order_id']]);
    }
} elseif ($wo_id > 0) {
    $wo  = db_fetch('SELECT * FROM work_orders WHERE id = ? LIMIT 1', [$wo_id]);
    if ($wo) {
        $inv = db_fetch('SELECT * FROM invoices WHERE work_order_id = ? LIMIT 1', [$wo_id]);
    }
}

if (!$wo) {
    flash_error('Work order not found.');
    redirect(APP_URL . '/modules/payments/index.php');
}

$amount_due = $inv
    ? max(0, (float)$inv['amount'] - (float)$inv['amount_paid'])
    : (float)($wo['amount'] ?? 0);

// ── JSON endpoint: create PaymentIntent ──────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'create_intent') {
    header('Content-Type: application/json');

    // CSRF check for AJAX endpoints — token passed as POST field
    if (!csrf_verify()) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid security token']);
        exit;
    }

    $override_amount = (float)($_POST['amount'] ?? $amount_due);
    if ($override_amount <= 0) {
        echo json_encode(['error' => 'Invalid amount']);
        exit;
    }

    $metadata = [
        'wo_number'   => $wo['wo_number'],
        'customer'    => $wo['cust_name'],
        'invoice_id'  => $inv ? $inv['id'] : '',
        'wo_id'       => $wo['id'],
    ];

    $pi = stripe_create_payment_intent($override_amount, STRIPE_CURRENCY, $metadata);

    if (isset($pi['error'])) {
        echo json_encode(['error' => $pi['error']['message'] ?? 'Stripe error']);
        exit;
    }

    // Pre-create a pending payment record
    $cust_id = $wo['customer_id'] ?? null;
    $pay_id = db_insert('payments', [
        'work_order_id'            => $wo['id'],
        'customer_id'              => $cust_id,
        'invoice_id'               => $inv ? $inv['id'] : null,
        'amount'                   => $override_amount,
        'method'                   => 'card',
        'stripe_payment_intent_id' => $pi['id'],
        'status'                   => 'pending',
        'created_by'               => (int)($_SESSION['user_id'] ?? 0),
        'created_at'               => date('Y-m-d H:i:s'),
    ]);

    echo json_encode([
        'clientSecret' => $pi['client_secret'],
        'payment_id'   => (int)$pay_id,
    ]);
    exit;
}

// ── JSON endpoint: confirm payment success ────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'confirm_payment') {
    header('Content-Type: application/json');

    // CSRF check for AJAX endpoints
    if (!csrf_verify()) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid security token']);
        exit;
    }

    $pi_id     = trim($_POST['payment_intent_id'] ?? '');
    $pay_id    = (int)($_POST['payment_id'] ?? 0);

    if (!$pi_id || !$pay_id) {
        echo json_encode(['error' => 'Missing parameters']);
        exit;
    }

    $pi = stripe_retrieve_payment_intent($pi_id);

    if (isset($pi['error'])) {
        echo json_encode(['error' => 'Could not verify payment']);
        exit;
    }

    if ($pi['status'] === 'succeeded') {
        $charge_id = $pi['latest_charge'] ?? null;

        db_update('payments', [
            'status'           => 'paid',
            'stripe_charge_id' => $charge_id,
            'paid_at'          => date('Y-m-d H:i:s'),
        ], 'id', $pay_id);

        // Update invoice
        if ($inv) {
            $pay_row  = db_fetch('SELECT * FROM payments WHERE id = ? LIMIT 1', [$pay_id]);
            $new_paid = (float)$inv['amount_paid'] + (float)($pay_row['amount'] ?? 0);
            $new_status = $new_paid >= (float)$inv['amount'] ? 'paid' : 'partial';
            db_update('invoices', [
                'amount_paid' => $new_paid,
                'status'      => $new_status,
                'updated_at'  => date('Y-m-d H:i:s'),
            ], 'id', $inv['id']);
        }

        log_activity('payment', 'Card payment via Stripe for WO# ' . $wo['wo_number'], 'payment', $pay_id);

        // Send receipt
        send_receipt_email($pay_id);

        echo json_encode(['success' => true, 'redirect' => APP_URL . '/modules/payments/receipt.php?id=' . $pay_id]);
    } else {
        db_update('payments', ['status' => 'failed'], 'id', $pay_id);
        echo json_encode(['error' => 'Payment not completed. Status: ' . $pi['status']]);
    }
    exit;
}

layout_start('Charge Customer', 'payments');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Charge Customer via Stripe</h5>
    <a href="index.php" class="btn-tp-ghost btn-tp-sm"><i class="fa-solid fa-arrow-left"></i> Back</a>
</div>

<div class="row g-4">
    <!-- Left: Summary -->
    <div class="col-md-5">
        <div class="tp-card">
            <h6 class="mb-3" style="font-weight:600;">Order Summary</h6>
            <table class="table table-sm">
                <tr><td style="color:var(--gy)">Customer</td><td><?= e($wo['cust_name']) ?></td></tr>
                <tr><td style="color:var(--gy)">Work Order</td><td><?= e($wo['wo_number']) ?></td></tr>
                <?php if ($inv): ?>
                <tr><td style="color:var(--gy)">Invoice</td><td><?= e($inv['invoice_number']) ?></td></tr>
                <?php endif; ?>
                <tr><td style="color:var(--gy)">Service Address</td><td><?= e($wo['service_address'] . ($wo['service_city'] ? ', '.$wo['service_city'] : '')) ?></td></tr>
                <tr><td style="color:var(--gy)">Size</td><td><?= e($wo['size'] ?? '—') ?></td></tr>
            </table>
            <hr>
            <div class="d-flex justify-content-between align-items-center">
                <span style="font-size:.9rem;color:var(--gy);">Amount Due</span>
                <strong style="font-size:1.4rem;color:#f97316;"><?= fmt_money($amount_due) ?></strong>
            </div>
        </div>
    </div>

    <!-- Right: Card Input -->
    <div class="col-md-7">
        <div class="tp-card">
            <h6 class="mb-3" style="font-weight:600;">Payment Details</h6>

            <div id="stripe-error-msg" class="alert alert-danger d-none mb-3"></div>
            <div id="stripe-success-msg" class="alert alert-success d-none mb-3">
                <i class="fa-solid fa-circle-check"></i> Payment successful! Redirecting…
            </div>

            <form id="payment-form">
                <div class="mb-3">
                    <label class="form-label" for="charge-amount">Amount to Charge</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" id="charge-amount" class="form-control"
                               step="0.01" min="0.01"
                               value="<?= number_format($amount_due, 2, '.', '') ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label">Card Details</label>
                    <div id="card-element"
                         style="background:#0f1117;border:1px solid #2a2d3e;border-radius:6px;padding:12px;color:#e5e7eb;">
                    </div>
                </div>

                <button type="submit" class="btn-tp-primary w-100" id="charge-btn" style="font-size:1rem;padding:.75rem;">
                    <i class="fa-solid fa-lock"></i>
                    <span id="charge-btn-text">Charge <?= fmt_money($amount_due) ?></span>
                    <span id="charge-spinner" class="d-none spinner-border spinner-border-sm ms-2"></span>
                </button>
            </form>

            <p class="mt-2 text-center" style="font-size:.75rem;color:var(--gy);">
                <i class="fa-brands fa-stripe" style="color:#635BFF;font-size:1.1rem;"></i>
                Secured by Stripe. Card data never touches our servers.
            </p>
        </div>
    </div>
</div>

<!-- Stripe JS -->
<script src="https://js.stripe.com/v3/"></script>
<script>
(function () {
    const stripe    = Stripe('<?= e(STRIPE_PUBLISHABLE_KEY) ?>');
    const elements  = stripe.elements();
    const cardEl    = elements.create('card', {
        style: {
            base: { color: '#e5e7eb', fontFamily: '"Barlow", sans-serif', fontSize: '15px',
                    '::placeholder': { color: '#4b5563' } },
            invalid: { color: '#ef4444' }
        }
    });
    cardEl.mount('#card-element');

    const form       = document.getElementById('payment-form');
    const errMsg     = document.getElementById('stripe-error-msg');
    const successMsg = document.getElementById('stripe-success-msg');
    const chargeBtn  = document.getElementById('charge-btn');
    const btnText    = document.getElementById('charge-btn-text');
    const spinner    = document.getElementById('charge-spinner');
    const amountIn   = document.getElementById('charge-amount');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        errMsg.classList.add('d-none');
        chargeBtn.disabled = true;
        btnText.textContent = 'Processing…';
        spinner.classList.remove('d-none');

        const amount = parseFloat(amountIn.value);
        if (isNaN(amount) || amount <= 0) {
            showError('Please enter a valid amount.');
            resetBtn();
            return;
        }

        // Step 1: Create PaymentIntent
        let piData;
        try {
            const res = await fetch('charge.php?action=create_intent&invoice_id=<?= $invoice_id ?>&wo_id=<?= $wo['id'] ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'amount=' + encodeURIComponent(amount) + '&<?= CSRF_TOKEN_NAME ?>=' + encodeURIComponent('<?= csrf_token() ?>')
            });
            piData = await res.json();
        } catch (err) {
            showError('Network error. Please try again.');
            resetBtn();
            return;
        }

        if (piData.error) {
            showError(piData.error);
            resetBtn();
            return;
        }

        // Step 2: Confirm card payment via Stripe.js
        const { error, paymentIntent } = await stripe.confirmCardPayment(piData.clientSecret, {
            payment_method: { card: cardEl }
        });

        if (error) {
            showError(error.message || 'Payment failed. Please try again.');
            resetBtn();
            return;
        }

        // Step 3: Confirm server-side
        const confirmRes = await fetch('charge.php?action=confirm_payment', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'payment_intent_id=' + encodeURIComponent(paymentIntent.id)
                + '&payment_id=' + encodeURIComponent(piData.payment_id)
                + '&<?= CSRF_TOKEN_NAME ?>=' + encodeURIComponent('<?= csrf_token() ?>')
        });
        const confirmData = await confirmRes.json();

        if (confirmData.error) {
            showError(confirmData.error);
            resetBtn();
            return;
        }

        successMsg.classList.remove('d-none');
        setTimeout(() => { window.location.href = confirmData.redirect; }, 1500);
    });

    function showError(msg) {
        errMsg.textContent = msg;
        errMsg.classList.remove('d-none');
    }

    function resetBtn() {
        chargeBtn.disabled = false;
        btnText.textContent = 'Charge $' + parseFloat(amountIn.value).toFixed(2);
        spinner.classList.add('d-none');
    }
})();
</script>

<?php layout_end(); ?>
