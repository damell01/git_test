<?php
/**
 * Customer Self-Service Portal – Login (Magic Link)
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/config/config.php';
require_once INC_PATH . '/db.php';
require_once INC_PATH . '/helpers.php';
require_once INC_PATH . '/auth.php';
require_once INC_PATH . '/mailer.php';

// Security headers
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');

session_name('tp_portal_session');
session_start();

// Already logged in as portal user?
if (!empty($_SESSION['portal_customer_id'])) {
    header('Location: ' . APP_URL . '/modules/portal/dashboard.php');
    exit;
}

$company_name = get_setting('company_name', 'Trash Panda Roll-Offs');
$message      = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim($_POST['email'] ?? ''));

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message      = 'Please enter a valid email address.';
        $message_type = 'danger';
    } else {
        $customer = db_fetch(
            'SELECT * FROM customers WHERE LOWER(email) = ? LIMIT 1',
            [$email]
        );

        if ($customer) {
            // Generate token
            $token      = bin2hex(random_bytes(48));
            $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));

            // Remove any existing tokens for this customer
            db_execute('DELETE FROM customer_portal_tokens WHERE customer_id = ?', [$customer['id']]);

            // Insert new token
            db_insert('customer_portal_tokens', [
                'customer_id' => $customer['id'],
                'token'       => $token,
                'expires_at'  => $expires_at,
                'created_at'  => date('Y-m-d H:i:s'),
            ]);

            $magic_url = APP_URL . '/modules/portal/dashboard.php?token=' . urlencode($token);

            // Try to send email
            $sent = send_portal_magic_link($customer['id'], $token);

            if ($sent) {
                $message      = 'A login link has been sent to your email address. It expires in 24 hours.';
                $message_type = 'success';
            } else {
                // Dev mode: show the link on screen
                $message      = 'Email could not be sent. Development mode link: <a href="' . htmlspecialchars($magic_url, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($magic_url, ENT_QUOTES, 'UTF-8') . '</a>';
                $message_type = 'warning';
            }
        } else {
            // Don't reveal if email exists
            $message      = 'If that email address is in our system, you will receive a login link shortly.';
            $message_type = 'info';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Portal | <?= htmlspecialchars($company_name, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600&family=Barlow+Condensed:wght@600;700&display=swap" rel="stylesheet">
    <style>
        body { background: #f3f4f6; min-height: 100vh; display: flex; align-items: center;
               justify-content: center; font-family: 'Barlow', sans-serif; }
        .portal-card { background: #fff; border-radius: 12px; padding: 2.5rem 2rem; width: 100%;
                       max-width: 420px; box-shadow: 0 8px 32px rgba(0,0,0,.1); }
        .portal-logo { font-family: 'Barlow Condensed', sans-serif; font-weight: 700;
                       font-size: 1.7rem; color: #f97316; text-align: center; margin-bottom: .25rem; }
        .portal-sub { text-align: center; color: #6b7280; font-size: .9rem; margin-bottom: 2rem; }
        .btn-orange { background: #f97316; color: #fff; border: none; border-radius: 6px;
                      padding: .65rem 1.5rem; font-weight: 600; width: 100%;
                      font-family: 'Barlow Condensed', sans-serif; letter-spacing: .04em; font-size: 1rem; }
        .btn-orange:hover { background: #ea6c0e; color: #fff; }
    </style>
</head>
<body>

<div class="portal-card">
    <div class="portal-logo">
        <i class="fa-solid fa-dumpster" style="color:#f97316;"></i>
        <?= htmlspecialchars($company_name, ENT_QUOTES, 'UTF-8') ?>
    </div>
    <div class="portal-sub">Customer Portal — Enter your email to receive a login link</div>

    <?php if ($message): ?>
    <div class="alert alert-<?= htmlspecialchars($message_type, ENT_QUOTES, 'UTF-8') ?> mb-3">
        <?= $message ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="mb-3">
            <label class="form-label fw-semibold" for="email">Email Address</label>
            <input type="email" id="email" name="email" class="form-control form-control-lg"
                   placeholder="you@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   required autofocus>
        </div>
        <button type="submit" class="btn btn-orange">
            <i class="fa-solid fa-paper-plane"></i> Send Login Link
        </button>
    </form>

    <div class="mt-3 text-center" style="font-size:.8rem;color:#9ca3af;">
        Need help? Contact us at <?= htmlspecialchars(get_setting('company_phone', ''), ENT_QUOTES, 'UTF-8') ?>
    </div>
</div>

</body>
</html>
