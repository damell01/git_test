<?php
/**
 * Booking Success Page — Trash Panda Roll-Offs
 */

$_admin_root = dirname(__DIR__) . '/admin';
require_once $_admin_root . '/config/config.php';
require_once INC_PATH . '/db.php';
require_once INC_PATH . '/helpers.php';

$id    = (int)($_GET['id']    ?? 0);
$token = trim($_GET['token']  ?? '');

if ($id <= 0 || $token === '') {
    header('Location: /');
    exit;
}

// Verify token
$expected = hash_hmac('sha256', (string)$id, get_setting('stripe_secret_key', 'booking-token-secret'));
if (!hash_equals($expected, $token)) {
    header('Location: /');
    exit;
}

$booking = db_fetch('SELECT * FROM bookings WHERE id = ? LIMIT 1', [$id]);
if (!$booking) {
    header('Location: /');
    exit;
}

$company_name = get_setting('company_name', 'Trash Panda Roll-Offs');
$company_phone = get_setting('company_phone', '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmed — <?= htmlspecialchars($company_name, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Black+Han+Sans&family=Barlow+Condensed:wght@400;600;700;800&family=Barlow:wght@300;400;500;600&display=swap">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="/shared.css">
    <!-- PWA -->
    <link rel="manifest" href="/manifest.json"/>
    <meta name="theme-color" content="#f97316"/>
    <meta name="mobile-web-app-capable" content="yes"/>
    <meta name="apple-mobile-web-app-capable" content="yes"/>
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"/>
    <meta name="apple-mobile-web-app-title" content="Trash Panda"/>
    <link rel="apple-touch-icon" href="/assets/icon-192.png"/>
    <style>
        body { background: var(--black); color: var(--white); font-family: var(--font-body); }
        .book-nav {
            background: var(--dark);
            border-bottom: 1px solid var(--steel);
            padding: .8rem 1rem;
            display: flex;
            align-items: center;
        }
        .book-nav-brand { font-family: var(--font-display); font-size: 1.1rem; color: var(--white); text-decoration: none; }
        .book-nav-brand span { color: var(--orange); }
        .success-container { max-width: 620px; margin: 4rem auto; padding: 0 1rem; text-align: center; }
        .success-icon {
            width: 90px; height: 90px;
            background: rgba(34,197,94,.15);
            border: 2px solid rgba(34,197,94,.4);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2.5rem;
            color: #22c55e;
        }
        .success-title {
            font-family: var(--font-display);
            font-size: 2.2rem;
            color: var(--white);
            margin-bottom: .5rem;
        }
        .success-title span { color: var(--orange); }
        .booking-card {
            background: var(--dark2);
            border: 1px solid var(--steel);
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 2rem;
            text-align: left;
        }
        .booking-card h3 {
            font-family: var(--font-cond);
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--white);
            border-bottom: 1px solid var(--steel);
            padding-bottom: .6rem;
            margin-bottom: 1rem;
        }
        .detail-row { display: flex; justify-content: space-between; padding: .4rem 0; font-size: .9rem; border-bottom: 1px solid var(--steel2); }
        .detail-row:last-child { border-bottom: none; }
        .detail-label { color: var(--gray); }
        .detail-value { color: var(--white); font-weight: 500; text-align: right; }
        .total-row .detail-value { color: var(--orange); font-weight: 700; font-size: 1.1rem; }
        .booking-number-display {
            display: inline-block;
            background: rgba(249,115,22,.15);
            border: 1px solid rgba(249,115,22,.35);
            border-radius: 6px;
            padding: .4rem 1rem;
            font-family: var(--font-cond);
            font-size: 1.4rem;
            font-weight: 700;
            color: var(--orange);
            letter-spacing: .05em;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>

<nav class="book-nav">
    <a href="/" class="book-nav-brand">TRASH PANDA <span>ROLL-OFFS</span></a>
</nav>

<div class="success-container">
    <div class="success-icon">
        <i class="fas fa-check"></i>
    </div>

    <div class="success-title">BOOKING <span>CONFIRMED!</span></div>
    <p style="color:var(--gray-light);margin-bottom:1rem;">
        Thank you, <?= htmlspecialchars($booking['customer_name'], ENT_QUOTES, 'UTF-8') ?>!
        Your dumpster rental has been booked.
    </p>

    <div class="booking-number-display">
        <?= htmlspecialchars($booking['booking_number'], ENT_QUOTES, 'UTF-8') ?>
    </div>

    <div class="booking-card">
        <h3><i class="fas fa-receipt" style="color:var(--orange);margin-right:.4rem;"></i> Booking Details</h3>

        <div class="detail-row">
            <span class="detail-label">Booking #</span>
            <span class="detail-value"><?= htmlspecialchars($booking['booking_number'], ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Unit</span>
            <span class="detail-value">
                <?= htmlspecialchars($booking['unit_code'] ?? '—', ENT_QUOTES, 'UTF-8') ?>
                <?php if ($booking['unit_size']): ?>
                — <?= htmlspecialchars($booking['unit_size'], ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
            </span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Start Date</span>
            <span class="detail-value"><?= htmlspecialchars(fmt_date($booking['rental_start']), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">End Date</span>
            <span class="detail-value"><?= htmlspecialchars(fmt_date($booking['rental_end']), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Duration</span>
            <span class="detail-value"><?= (int)$booking['rental_days'] ?> day<?= (int)$booking['rental_days'] !== 1 ? 's' : '' ?></span>
        </div>
        <div class="detail-row">
            <span class="detail-label">Payment Method</span>
            <span class="detail-value"><?= htmlspecialchars(ucfirst($booking['payment_method']), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <?php if ($booking['customer_address'] || $booking['customer_city']): ?>
        <div class="detail-row">
            <span class="detail-label">Drop-off Address</span>
            <span class="detail-value">
                <?= htmlspecialchars(trim(($booking['customer_address'] ?? '') . ', ' . ($booking['customer_city'] ?? ''), ', '), ENT_QUOTES, 'UTF-8') ?>
            </span>
        </div>
        <?php endif; ?>
        <div class="detail-row total-row">
            <span class="detail-label" style="font-weight:600;">Total</span>
            <span class="detail-value"><?= htmlspecialchars(fmt_money($booking['total_amount']), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
    </div>

    <?php if ($booking['payment_method'] !== 'stripe'): ?>
    <div style="background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.3);border-radius:8px;padding:1rem;margin-top:1.25rem;font-size:.9rem;color:var(--gray-light);text-align:left;">
        <i class="fas fa-info-circle" style="color:var(--orange);"></i>
        <strong style="color:var(--white);">Payment Note:</strong>
        <?php if ($booking['payment_method'] === 'cash'): ?>
            Please have cash payment ready at time of delivery.
        <?php else: ?>
            Please have your check made out to
            <strong style="color:var(--white);"><?= htmlspecialchars($company_name, ENT_QUOTES, 'UTF-8') ?></strong>
            ready at time of delivery.
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php if ($company_phone): ?>
    <p style="color:var(--gray);font-size:.85rem;margin-top:1.5rem;">
        Questions? Call us at
        <a href="tel:<?= htmlspecialchars(preg_replace('/\D/', '', $company_phone), ENT_QUOTES, 'UTF-8') ?>"
           style="color:var(--orange);">
            <?= htmlspecialchars(fmt_phone($company_phone), ENT_QUOTES, 'UTF-8') ?>
        </a>
    </p>
    <?php endif; ?>

    <div class="d-flex gap-3 justify-content-center mt-4">
        <a href="/" class="btn-panda-outline"><i class="fas fa-home"></i> Back to Home</a>
        <a href="/book.php" class="btn-ghost">Book Another</a>
    </div>
</div>

    <script>if('serviceWorker'in navigator)navigator.serviceWorker.register('/sw.js').catch(()=>{});</script>
</body>
</html>
