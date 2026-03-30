<?php
/**
 * Two-Factor Authentication – Verify
 * Trash Panda Roll-Offs
 *
 * Shown after successful password login when 2FA is enabled.
 */

require_once __DIR__ . '/../../config/config.php';
require_once INC_PATH . '/db.php';
require_once INC_PATH . '/helpers.php';
require_once INC_PATH . '/auth.php';
session_init();

// Must have a pending 2FA user
if (empty($_SESSION['2fa_pending_user_id'])) {
    redirect(APP_URL . '/login.php');
}

$pending_user_id = (int)$_SESSION['2fa_pending_user_id'];

// Load the 2FA record
$tfs = db_fetch(
    'SELECT * FROM two_factor_secrets WHERE user_id = ? AND enabled = 1 LIMIT 1',
    [$pending_user_id]
);

if (!$tfs) {
    // 2FA disabled since login started — proceed normally
    unset($_SESSION['2fa_pending_user_id']);
    $user = db_fetch('SELECT * FROM users WHERE id = ? AND active = 1 LIMIT 1', [$pending_user_id]);
    if ($user) {
        login_user($user);
    }
    redirect(APP_URL . '/dashboard.php');
}

// ── TOTP functions (same as in setup.php) ────────────────────────────────────
function base32_decode(string $input): string
{
    $input = strtoupper($input);
    $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits  = '';
    $output = '';
    foreach (str_split($input) as $char) {
        $pos = strpos($chars, $char);
        if ($pos === false) continue;
        $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }
    foreach (str_split($bits, 8) as $chunk) {
        if (strlen($chunk) === 8) $output .= chr(bindec($chunk));
    }
    return $output;
}

function totp_code(string $secret, int $timestamp = 0): string
{
    if ($timestamp === 0) $timestamp = time();
    $time_step  = (int)floor($timestamp / 30);
    $time_bytes = pack('N*', 0) . pack('N*', $time_step);
    $key        = base32_decode($secret);
    $hmac       = hash_hmac('sha1', $time_bytes, $key, true);
    $offset     = ord($hmac[19]) & 0x0F;
    $code       = (
        ((ord($hmac[$offset])     & 0x7F) << 24) |
        ((ord($hmac[$offset + 1]) & 0xFF) << 16) |
        ((ord($hmac[$offset + 2]) & 0xFF) << 8)  |
        ((ord($hmac[$offset + 3]) & 0xFF))
    ) % 1000000;
    return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
}

function totp_verify(string $secret, string $code): bool
{
    $code = preg_replace('/\D/', '', $code);
    $now  = time();
    for ($i = -1; $i <= 1; $i++) {
        if (hash_equals(totp_code($secret, $now + $i * 30), $code)) return true;
    }
    return false;
}

// ── Rate limiting for 2FA ─────────────────────────────────────────────────────
if (empty($_SESSION['2fa_attempts'])) {
    $_SESSION['2fa_attempts'] = 0;
}

$error = '';
$app_name = defined('APP_NAME') ? APP_NAME : 'Trash Panda Roll-Offs';
$asset_path = defined('ASSET_PATH') ? ASSET_PATH : '';

if ($_SESSION['2fa_attempts'] >= 5) {
    // Lock out — destroy the pending session
    unset($_SESSION['2fa_pending_user_id']);
    unset($_SESSION['2fa_attempts']);
    flash_error('Too many invalid 2FA attempts. Please log in again.');
    redirect(APP_URL . '/login.php');
}

// ── Handle POST ───────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $submitted = trim($_POST['code'] ?? '');

    // Check TOTP first
    if (totp_verify($tfs['secret'], $submitted)) {
        unset($_SESSION['2fa_pending_user_id']);
        unset($_SESSION['2fa_attempts']);

        $user = db_fetch('SELECT * FROM users WHERE id = ? AND active = 1 LIMIT 1', [$pending_user_id]);
        if (!$user) {
            flash_error('User not found.');
            redirect(APP_URL . '/login.php');
        }

        login_user($user);
        $_SESSION['2fa_verified'] = true;

        if (!empty($user['must_change_pw'])) {
            redirect(APP_URL . '/modules/settings/change_password.php');
        }
        redirect(APP_URL . '/dashboard.php');
    }

    // Check backup codes
    $backup_hashes = json_decode($tfs['backup_codes'] ?? '[]', true);
    $backup_used   = false;

    if (is_array($backup_hashes)) {
        foreach ($backup_hashes as $idx => $hash) {
            if ($hash !== null && password_verify(strtoupper(preg_replace('/\W/', '', $submitted)), $hash)) {
                // Mark this backup code as used
                $backup_hashes[$idx] = null;
                db_execute(
                    'UPDATE two_factor_secrets SET backup_codes = ?, updated_at = NOW() WHERE user_id = ?',
                    [json_encode($backup_hashes), $pending_user_id]
                );
                $backup_used = true;
                break;
            }
        }
    }

    if ($backup_used) {
        unset($_SESSION['2fa_pending_user_id']);
        unset($_SESSION['2fa_attempts']);

        $user = db_fetch('SELECT * FROM users WHERE id = ? AND active = 1 LIMIT 1', [$pending_user_id]);
        if (!$user) {
            flash_error('User not found.');
            redirect(APP_URL . '/login.php');
        }

        login_user($user);
        $_SESSION['2fa_verified'] = true;
        flash_info('You used a backup code. Please set up 2FA again to generate new backup codes.');
        redirect(APP_URL . '/dashboard.php');
    }

    // Failed
    $_SESSION['2fa_attempts']++;
    $remaining = 5 - $_SESSION['2fa_attempts'];
    $error = 'Invalid code. ' . ($remaining > 0 ? $remaining . ' attempt' . ($remaining === 1 ? '' : 's') . ' remaining.' : 'Your session will be locked on next failure.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Two-Factor Authentication | <?= htmlspecialchars($app_name, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Barlow:wght@400;500;600&family=Barlow+Condensed:wght@700&display=swap" rel="stylesheet">
    <style>
        body { background: #0f1117; min-height: 100vh; display: flex; align-items: center;
               justify-content: center; font-family: 'Barlow', sans-serif; }
        .verify-card { background: #1a1d27; border: 1px solid #2a2d3e; border-radius: 12px;
                       padding: 2.5rem 2rem; width: 100%; max-width: 400px;
                       box-shadow: 0 8px 32px rgba(0,0,0,.45); }
        .verify-logo { font-family: 'Barlow Condensed', sans-serif; font-weight: 700;
                       font-size: 1.5rem; color: #f97316; text-align: center; margin-bottom: .25rem; }
        .verify-sub { text-align: center; color: #6b7280; font-size: .85rem; margin-bottom: 1.75rem; }
        .code-input { text-align: center; font-size: 2rem; letter-spacing: .5em;
                      background: #0f1117; border: 1px solid #2a2d3e; color: #e5e7eb;
                      border-radius: 8px; padding: .6rem .5rem; width: 100%; }
        .code-input:focus { outline: none; border-color: #f97316; box-shadow: 0 0 0 3px rgba(249,115,22,.15); }
        .btn-verify { width: 100%; background: #f97316; color: #fff; border: none; border-radius: 6px;
                      padding: .65rem; font-size: 1rem; font-weight: 600; cursor: pointer;
                      font-family: 'Barlow Condensed', sans-serif; }
        .btn-verify:hover { background: #ea6c0e; }
    </style>
</head>
<body>

<div class="verify-card">
    <div class="verify-logo">
        <i class="fa-solid fa-shield-halved" style="color:#f97316;"></i>
        <?= htmlspecialchars($app_name, ENT_QUOTES, 'UTF-8') ?>
    </div>
    <div class="verify-sub">Two-Factor Authentication — Enter your 6-digit code</div>

    <?php if ($error): ?>
    <div style="background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);color:#fca5a5;
                border-radius:6px;padding:.65rem .85rem;font-size:.875rem;margin-bottom:1.25rem;
                display:flex;align-items:center;gap:.5rem;">
        <i class="fa-solid fa-circle-exclamation"></i>
        <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
    </div>
    <?php endif; ?>

    <form method="POST" action="">
        <?= csrf_field() ?>

        <div class="mb-3">
            <input type="text" name="code" class="code-input"
                   maxlength="8" pattern="[0-9A-Fa-f]{6,8}"
                   placeholder="000000"
                   autocomplete="one-time-code"
                   inputmode="numeric"
                   autofocus required>
            <div style="font-size:.75rem;color:var(--gy,#6b7280);margin-top:.5rem;text-align:center;">
                Or enter a backup code
            </div>
        </div>

        <button type="submit" class="btn-verify">
            <i class="fa-solid fa-right-to-bracket"></i> Verify
        </button>
    </form>

    <div class="mt-3 text-center">
        <a href="<?= htmlspecialchars(APP_URL, ENT_QUOTES, 'UTF-8') ?>/login.php"
           style="font-size:.8rem;color:#6b7280;text-decoration:none;">
            ← Back to Login
        </a>
    </div>
</div>

</body>
</html>
