<?php
/**
 * Customer Portal – Pay Invoice via Stripe
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once INC_PATH . '/db.php';
require_once INC_PATH . '/helpers.php';
require_once INC_PATH . '/auth.php';
require_once INC_PATH . '/stripe.php';
require_once INC_PATH . '/mailer.php';

// Security headers
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');

session_name('tp_portal_session');
session_start();

// Session check
if (empty($_SESSION['portal_customer_id'])) {
    header('Location: ' . APP_URL . '/modules/portal/index.php');
    exit;
}

$cust_id    = (int)$_SESSION['portal_customer_id'];
$invoice_id = (int)($_GET['invoice_id'] ?? 0);
$company_name = get_setting('company_name', 'Trash Panda Roll-Offs');

$inv = db_fetch(
    'SELECT i.*, wo.wo_number, wo.service_address, wo.service_city, wo.size, wo.delivery_date
     FROM invoices i
     LEFT JOIN work_orders wo ON i.work_order_id = wo.id
     WHERE i.id = ? AND i.customer_id = ? LIMIT 1',
    [$invoice_id, $cust_id]
);

if (!$inv) {
    header('Location: ' . APP_URL . '/modules/portal/dashboard.php');
    exit;
}

$amount_due = max(0, (float)$inv['amount'] - (float)$inv['amount_paid']);

if ($amount_due <= 0) {
    header('Location: ' . APP_URL . '/modules/portal/dashboard.php');
    exit;
}

// Generate a portal-session CSRF token
if (empty($_SESSION['portal_csrf_token'])) {
    $_SESSION['portal_csrf_token'] = bin2hex(random_bytes(32));
}
$portal_csrf = $_SESSION['portal_csrf_token'];

// ── JSON: Create PaymentIntent ────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'create_intent') {
    header('Content-Type: application/json');

    $submitted_csrf = $_POST['portal_csrf'] ?? '';
    if (empty($submitted_csrf) || !hash_equals($_SESSION['portal_csrf_token'] ?? '', $submitted_csrf)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid security token']);
        exit;
    }

    $pi = stripe_create_payment_intent($amount_due, STRIPE_CURRENCY, [
        'invoice_id' => $inv['id'],
        'invoice_number' => $inv['invoice_number'],
        'customer_id' => $cust_id,
    ]);

    if (isset($pi['error'])) {
        echo json_encode(['error' => $pi['error']['message'] ?? 'Stripe error']);
        exit;
    }

    // Pre-create pending payment
    $customer = db_fetch('SELECT * FROM customers WHERE id = ? LIMIT 1', [$cust_id]);
    $pay_id = db_insert('payments', [
        'work_order_id'            => $inv['work_order_id'],
        'customer_id'              => $cust_id,
        'invoice_id'               => $inv['id'],
        'amount'                   => $amount_due,
        'method'                   => 'card',
        'stripe_payment_intent_id' => $pi['id'],
        'status'                   => 'pending',
        'created_at'               => date('Y-m-d H:i:s'),
    ]);

    echo json_encode([
        'clientSecret' => $pi['client_secret'],
        'payment_id'   => (int)$pay_id,
    ]);
    exit;
}

// ── JSON: Confirm Payment ─────────────────────────────────────────────────────
if (isset($_GET['action']) && $_GET['action'] === 'confirm') {
    header('Content-Type: application/json');

    $submitted_csrf = $_POST['portal_csrf'] ?? '';
    if (empty($submitted_csrf) || !hash_equals($_SESSION['portal_csrf_token'] ?? '', $submitted_csrf)) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid security token']);
        exit;
    }

    $pi_id  = trim($_POST['payment_intent_id'] ?? '');
    $pay_id = (int)($_POST['payment_id'] ?? 0);

    if (!$pi_id || !$pay_id) {
        echo json_encode(['error' => 'Missing data']);
        exit;
    }

    $pi = stripe_retrieve_payment_intent($pi_id);
    if (isset($pi['error']) || $pi['status'] !== 'succeeded') {
        db_update('payments', ['status' => 'failed'], 'id', $pay_id);
        echo json_encode(['error' => 'Payment not successful']);
        exit;
    }

    $charge_id = $pi['latest_charge'] ?? null;
    db_update('payments', [
        'status'           => 'paid',
        'stripe_charge_id' => $charge_id,
        'paid_at'          => date('Y-m-d H:i:s'),
    ], 'id', $pay_id);

    $new_paid   = (float)$inv['amount_paid'] + $amount_due;
    $new_status = $new_paid >= (float)$inv['amount'] ? 'paid' : 'partial';
    db_update('invoices', [
        'amount_paid' => $new_paid,
        'status'      => $new_status,
        'updated_at'  => date('Y-m-d H:i:s'),
    ], 'id', $inv['id']);

    send_receipt_email($pay_id);

    echo json_encode([
        'success'  => true,
        'redirect' => APP_URL . '/modules/portal/receipt.php?id=' . $pay_id,
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay Invoice | <?= htmlspecialchars($company_name, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600&family=Barlow+Condensed:wght@700&display=swap" rel="stylesheet">
    <style>
        body { background: #f3f4f6; font-family: 'Barlow', sans-serif; }
        .portal-nav { background: #1a1d27; color: #e5e7eb; padding: 14px 0; margin-bottom: 32px; }
        .portal-nav .brand { color: #f97316; font-family: 'Barlow Condensed', sans-serif;
                              font-size: 1.3rem; font-weight: 700; text-decoration: none; }
        .pay-card { background: #fff; border-radius: 10px; box-shadow: 0 2px 12px rgba(0,0,0,.08); padding: 28px; }
    </style>
</head>
<body>

<nav class="portal-nav">
    <div class="container d-flex justify-content-between align-items-center">
        <a class="brand" href="dashboard.php"><i class="fa-solid fa-dumpster me-1"></i><?= htmlspecialchars($company_name, ENT_QUOTES, 'UTF-8') ?></a>
        <a href="dashboard.php" style="color:#9ca3af;font-size:.85rem;text-decoration:none;">
            <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
        </a>
    </div>
</nav>

<div class="container pb-5" style="max-width:820px;">
    <h2 class="mb-4" style="font-family:'Barlow Condensed',sans-serif;font-weight:700;">
        Pay Invoice <?= htmlspecialchars($inv['invoice_number'], ENT_QUOTES, 'UTF-8') ?>
    </h2>

    <div class="row g-4">
        <!-- Summary -->
        <div class="col-md-5">
            <div class="pay-card">
                <h6 class="fw-bold mb-3">Invoice Summary</h6>
                <table class="table table-sm" style="font-size:.9rem;">
                    <tr><td class="text-muted">Invoice #</td><td><?= htmlspecialchars($inv['invoice_number'], ENT_QUOTES, 'UTF-8') ?></td></tr>
                    <tr><td class="text-muted">Work Order</td><td><?= htmlspecialchars($inv['wo_number'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td></tr>
                    <tr><td class="text-muted">Service Address</td>
                        <td><?= htmlspecialchars(($inv['service_address'] ?? '') . ($inv['service_city'] ? ', '.$inv['service_city'] : ''), ENT_QUOTES, 'UTF-8') ?></td></tr>
                    <?php if ($inv['size']): ?>
                    <tr><td class="text-muted">Size</td><td><?= htmlspecialchars($inv['size'], ENT_QUOTES, 'UTF-8') ?></td></tr>
                    <?php endif; ?>
                    <?php if ($inv['delivery_date']): ?>
                    <tr><td class="text-muted">Delivery</td><td><?= htmlspecialchars(date('M j, Y', strtotime($inv['delivery_date'])), ENT_QUOTES, 'UTF-8') ?></td></tr>
                    <?php endif; ?>
                </table>
                <hr>
                <div class="d-flex justify-content-between fw-bold">
                    <span>Amount Due</span>
                    <span style="color:#f97316;font-size:1.2rem;">$<?= number_format($amount_due, 2) ?></span>
                </div>
            </div>
        </div>

        <!-- Payment Form -->
        <div class="col-md-7">
            <div class="pay-card">
                <h6 class="fw-bold mb-3">Card Details</h6>

                <div id="pay-error" class="alert alert-danger d-none mb-3"></div>
                <div id="pay-success" class="alert alert-success d-none mb-3">
                    <i class="fa-solid fa-circle-check"></i> Payment successful! Redirecting…
                </div>

                <form id="pay-form">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Card Details</label>
                        <div id="card-element" style="border:1px solid #d1d5db;border-radius:6px;padding:12px;background:#fff;"></div>
                    </div>
                    <button type="submit" class="btn w-100 fw-bold" id="pay-btn"
                            style="background:#f97316;color:#fff;padding:.7rem;font-size:1rem;border:none;border-radius:6px;">
                        <i class="fa-solid fa-lock"></i>
                        <span id="pay-btn-text">Pay $<?= number_format($amount_due, 2) ?></span>
                        <span id="pay-spinner" class="d-none spinner-border spinner-border-sm ms-2"></span>
                    </button>
                </form>

                <p class="mt-2 text-center" style="font-size:.75rem;color:#9ca3af;">
                    <i class="fa-brands fa-stripe" style="color:#635BFF;font-size:1.1rem;"></i>
                    Secured by Stripe
                </p>
            </div>
        </div>
    </div>
</div>

<script src="https://js.stripe.com/v3/"></script>
<script>
(function () {
    const stripe   = Stripe('<?= htmlspecialchars(STRIPE_PUBLISHABLE_KEY, ENT_QUOTES, 'UTF-8') ?>');
    const elements = stripe.elements();
    const cardEl   = elements.create('card', {
        style: { base: { color: '#1f2937', fontFamily: '"Barlow", sans-serif', fontSize: '15px' } }
    });
    cardEl.mount('#card-element');

    const form      = document.getElementById('pay-form');
    const errDiv    = document.getElementById('pay-error');
    const successDiv = document.getElementById('pay-success');
    const btn       = document.getElementById('pay-btn');
    const btnText   = document.getElementById('pay-btn-text');
    const spinner   = document.getElementById('pay-spinner');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        errDiv.classList.add('d-none');
        btn.disabled = true;
        btnText.textContent = 'Processing…';
        spinner.classList.remove('d-none');

        // Create intent
        let piData;
        try {
            const r = await fetch('pay.php?action=create_intent&invoice_id=<?= $invoice_id ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'portal_csrf=' + encodeURIComponent('<?= htmlspecialchars($portal_csrf, ENT_QUOTES, 'UTF-8') ?>')
            });
            piData = await r.json();
        } catch {
            showErr('Network error. Please try again.');
            resetBtn();
            return;
        }

        if (piData.error) { showErr(piData.error); resetBtn(); return; }

        // Confirm with Stripe
        const { error, paymentIntent } = await stripe.confirmCardPayment(piData.clientSecret, {
            payment_method: { card: cardEl }
        });
        if (error) { showErr(error.message); resetBtn(); return; }

        // Confirm server-side
        const cr = await fetch('pay.php?action=confirm', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'payment_intent_id=' + encodeURIComponent(paymentIntent.id)
                + '&payment_id=' + piData.payment_id
                + '&portal_csrf=' + encodeURIComponent('<?= htmlspecialchars($portal_csrf, ENT_QUOTES, 'UTF-8') ?>')
        });
        const cd = await cr.json();
        if (cd.error) { showErr(cd.error); resetBtn(); return; }

        successDiv.classList.remove('d-none');
        setTimeout(() => { window.location.href = cd.redirect; }, 1500);
    });

    function showErr(msg) { errDiv.textContent = msg; errDiv.classList.remove('d-none'); }
    function resetBtn() {
        btn.disabled = false;
        btnText.textContent = 'Pay $<?= number_format($amount_due, 2) ?>';
        spinner.classList.add('d-none');
    }
})();
</script>

</body>
</html>
