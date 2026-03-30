<?php
/**
 * Two-Factor Authentication – TOTP Setup
 * Trash Panda Roll-Offs
 *
 * Pure-PHP TOTP implementation (RFC 6238 / HMAC-SHA1, no library required).
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once TMPL_PATH . '/layout.php';
require_login();

$user = current_user();
if (!$user) {
    redirect(APP_URL . '/login.php');
}

$user_id = (int)$user['id'];
$errors  = [];

// ── TOTP Helper Functions ─────────────────────────────────────────────────────

/**
 * Generate a random Base32-encoded TOTP secret.
 */
function totp_generate_secret(int $length = 32): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret   = '';
    $bytes    = random_bytes($length);
    for ($i = 0; $i < $length; $i++) {
        $secret .= $alphabet[ord($bytes[$i]) & 31];
    }
    return $secret;
}

/**
 * Decode a Base32 string to binary.
 */
function base32_decode(string $input): string
{
    $input  = strtoupper($input);
    $chars  = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits   = '';
    $output = '';

    foreach (str_split($input) as $char) {
        $pos = strpos($chars, $char);
        if ($pos === false) {
            continue;
        }
        $bits .= str_pad(decbin($pos), 5, '0', STR_PAD_LEFT);
    }

    foreach (str_split($bits, 8) as $chunk) {
        if (strlen($chunk) === 8) {
            $output .= chr(bindec($chunk));
        }
    }

    return $output;
}

/**
 * Generate a 6-digit TOTP code for the given secret and time step.
 */
function totp_code(string $secret, int $timestamp = 0): string
{
    if ($timestamp === 0) {
        $timestamp = time();
    }

    $time_step = (int)floor($timestamp / 30);
    $time_bytes = pack('N*', 0) . pack('N*', $time_step);

    $key  = base32_decode($secret);
    $hmac = hash_hmac('sha1', $time_bytes, $key, true);

    $offset = ord($hmac[19]) & 0x0F;
    $code   = (
        ((ord($hmac[$offset])     & 0x7F) << 24) |
        ((ord($hmac[$offset + 1]) & 0xFF) << 16) |
        ((ord($hmac[$offset + 2]) & 0xFF) << 8)  |
        ((ord($hmac[$offset + 3]) & 0xFF))
    ) % 1000000;

    return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
}

/**
 * Validate a submitted TOTP code (allows ±1 window for clock drift).
 */
function totp_verify(string $secret, string $submitted_code): bool
{
    $submitted_code = preg_replace('/\D/', '', $submitted_code);
    $now = time();

    for ($i = -1; $i <= 1; $i++) {
        if (hash_equals(totp_code($secret, $now + $i * 30), $submitted_code)) {
            return true;
        }
    }

    return false;
}

// ── Existing 2FA record ───────────────────────────────────────────────────────
$existing = db_fetch('SELECT * FROM two_factor_secrets WHERE user_id = ? LIMIT 1', [$user_id]);

// ── Handle POST: Confirm setup ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'confirm_setup') {
    csrf_check();

    $secret    = trim($_POST['secret'] ?? '');
    $code      = preg_replace('/\D/', '', trim($_POST['code'] ?? ''));

    if (empty($secret) || empty($code)) {
        $errors[] = 'Secret and code are required.';
    } elseif (!totp_verify($secret, $code)) {
        $errors[] = 'Invalid code. Please check your authenticator app and try again.';
    } else {
        // Generate 8 backup codes
        $plain_codes  = [];
        $hashed_codes = [];
        for ($i = 0; $i < 8; $i++) {
            $code_plain     = strtoupper(bin2hex(random_bytes(4)));
            $plain_codes[]  = $code_plain;
            $hashed_codes[] = password_hash($code_plain, PASSWORD_BCRYPT);
        }
        $backup_json = json_encode($hashed_codes);

        if ($existing) {
            db_execute(
                'UPDATE two_factor_secrets SET secret = ?, enabled = 1, backup_codes = ?, updated_at = NOW() WHERE user_id = ?',
                [$secret, $backup_json, $user_id]
            );
        } else {
            db_insert('two_factor_secrets', [
                'user_id'      => $user_id,
                'secret'       => $secret,
                'enabled'      => 1,
                'backup_codes' => $backup_json,
                'created_at'   => date('Y-m-d H:i:s'),
                'updated_at'   => date('Y-m-d H:i:s'),
            ]);
        }

        log_activity('2fa_enabled', '2FA enabled by user', 'user', $user_id);

        // Store backup codes in session briefly so we can show them once
        $_SESSION['2fa_backup_codes'] = $plain_codes;
        redirect(APP_URL . '/modules/two_factor/setup.php?done=1');
    }
}

// ── Handle "done" state: show backup codes ────────────────────────────────────
$show_backup = false;
$backup_codes = [];
if (isset($_GET['done']) && !empty($_SESSION['2fa_backup_codes'])) {
    $show_backup  = true;
    $backup_codes = $_SESSION['2fa_backup_codes'];
    unset($_SESSION['2fa_backup_codes']);
}

// ── Generate new secret for setup ────────────────────────────────────────────
$setup_secret = '';
if (!$show_backup && (!$existing || !$existing['enabled'])) {
    if (isset($_GET['secret'])) {
        $setup_secret = preg_replace('/[^A-Z2-7]/', '', strtoupper($_GET['secret']));
    }
    if (empty($setup_secret)) {
        $setup_secret = totp_generate_secret();
        header('Location: ' . APP_URL . '/modules/two_factor/setup.php?secret=' . urlencode($setup_secret));
        exit;
    }
}

$company_name = get_setting('company_name', 'Trash Panda Roll-Offs');
$issuer       = rawurlencode($company_name);
$account      = rawurlencode($user['email']);
$otpauth_url  = 'otpauth://totp/' . $issuer . ':' . $account . '?secret=' . $setup_secret . '&issuer=' . $issuer;
$qr_url       = 'https://chart.googleapis.com/chart?chs=200x200&chld=M|0&cht=qr&chl=' . rawurlencode($otpauth_url);

layout_start('Two-Factor Authentication Setup', 'settings');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Two-Factor Authentication</h5>
    <a href="<?= e(APP_URL) ?>/modules/settings/index.php" class="btn-tp-ghost btn-tp-sm">
        <i class="fa-solid fa-arrow-left"></i> Back to Settings
    </a>
</div>

<?php if ($existing && $existing['enabled'] && !$show_backup && empty($errors)): ?>
<!-- Already enabled -->
<div class="tp-card" style="max-width:540px;">
    <div class="alert alert-success mb-3">
        <i class="fa-solid fa-shield-halved me-2"></i>
        <strong>2FA is currently enabled</strong> on your account.
    </div>
    <p>To set up a new device or reset 2FA, use the form below.</p>
    <a href="<?= e(APP_URL) ?>/modules/two_factor/setup.php?reset=1" class="btn-tp-ghost btn-tp-sm">
        Reset 2FA
    </a>
    <form method="POST" action="<?= e(APP_URL) ?>/modules/settings/users.php" class="d-inline ms-2">
        <?= csrf_field() ?>
        <input type="hidden" name="action"  value="disable-2fa">
        <input type="hidden" name="user_id" value="<?= $user_id ?>">
        <button type="submit" class="btn-tp-ghost btn-tp-sm" style="border-color:#ef4444;color:#ef4444;">
            <i class="fa-solid fa-ban"></i> Disable 2FA
        </button>
    </form>
</div>

<?php elseif ($show_backup): ?>
<!-- Show backup codes once -->
<div class="tp-card" style="max-width:540px;">
    <div class="alert alert-success mb-3">
        <i class="fa-solid fa-circle-check me-2"></i>
        <strong>2FA enabled successfully!</strong>
    </div>

    <h6 class="fw-bold mb-2">Backup Codes <span style="color:#ef4444;">(save these now — shown only once)</span></h6>
    <p style="font-size:.88rem;color:var(--gy);">
        Each code can be used once to log in if you lose access to your authenticator app.
        Store them in a secure location.
    </p>

    <div style="background:#0f1117;border-radius:8px;padding:16px;font-family:monospace;font-size:1.1rem;">
        <?php foreach ($backup_codes as $bc): ?>
        <div style="letter-spacing:.15em;"><?= e($bc) ?></div>
        <?php endforeach; ?>
    </div>

    <a href="<?= e(APP_URL) ?>/dashboard.php" class="btn-tp-primary mt-3">
        <i class="fa-solid fa-check"></i> I've saved my backup codes
    </a>
</div>

<?php else: ?>
<!-- Setup flow -->
<div class="tp-card" style="max-width:580px;">

    <?php if (!empty($errors)): ?>
    <div class="alert alert-danger mb-3">
        <?php foreach ($errors as $err): ?>
        <div><?= e($err) ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <h6 class="fw-bold mb-3">Step 1 — Scan QR Code</h6>
    <p style="font-size:.9rem;">Open your authenticator app (Google Authenticator, Authy, etc.) and scan this QR code:</p>

    <div class="text-center mb-3">
        <img src="<?= e($qr_url) ?>" alt="QR Code" width="200" height="200" style="border:4px solid #fff;border-radius:8px;">
    </div>

    <p style="font-size:.82rem;color:var(--gy);">
        Or enter manually: <code style="color:#f97316;font-size:.85rem;"><?= e($setup_secret) ?></code>
    </p>

    <hr>

    <h6 class="fw-bold mb-3">Step 2 — Confirm Code</h6>
    <p style="font-size:.9rem;">Enter the 6-digit code from your app to confirm setup:</p>

    <form method="POST" action="setup.php?secret=<?= urlencode($setup_secret) ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="confirm_setup">
        <input type="hidden" name="secret" value="<?= e($setup_secret) ?>">

        <div class="mb-3">
            <input type="text" name="code" class="form-control form-control-lg text-center"
                   style="font-size:1.5rem;letter-spacing:.4em;max-width:200px;"
                   maxlength="6" pattern="[0-9]{6}" placeholder="000000"
                   autocomplete="one-time-code" autofocus required>
        </div>

        <button type="submit" class="btn-tp-primary">
            <i class="fa-solid fa-shield-halved"></i> Enable 2FA
        </button>
    </form>

</div>
<?php endif; ?>

<?php layout_end(); ?>
