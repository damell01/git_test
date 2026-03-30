<?php
/**
 * Settings – Change Password
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once TMPL_PATH . '/layout.php';
require_login();

// ── Force mode: triggered when must_change_pw = 1 ────────────────────────────
$force_mode = isset($_GET['force']) && $_GET['force'] === '1';

// Also check the user's must_change_pw flag to set force mode automatically
$user = current_user();
if ($user && !empty($user['must_change_pw'])) {
    $force_mode = true;
}

$errors = [];

// ── POST handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $current_password  = $_POST['current_password']  ?? '';
    $new_password      = $_POST['new_password']       ?? '';
    $confirm_password  = $_POST['confirm_password']   ?? '';

    // In normal mode: verify current password
    if (!$force_mode) {
        if ($current_password === '') {
            $errors[] = 'Current password is required.';
        } elseif (!password_verify($current_password, $user['password'])) {
            $errors[] = 'Current password is incorrect.';
        }
    }

    // Validate new password
    if ($new_password === '') {
        $errors[] = 'New password is required.';
    } elseif (strlen($new_password) < 8) {
        $errors[] = 'New password must be at least 8 characters.';
    }

    if ($new_password !== $confirm_password) {
        $errors[] = 'New password and confirmation do not match.';
    }

    if (empty($errors)) {
        $hashed = password_hash($new_password, PASSWORD_BCRYPT);

        db_execute(
            'UPDATE users SET password = ?, must_change_pw = 0, updated_at = NOW() WHERE id = ?',
            [$hashed, (int)$user['id']]
        );

        log_activity('update', 'Changed own password', 'user', (int)$user['id']);
        flash_success('Password changed successfully.');
        redirect(APP_URL . '/dashboard.php');
    }
}

layout_start('Change Password', 'settings');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Change Password</h5>
    <?php if (!$force_mode): ?>
    <a href="<?= e(APP_URL) ?>/dashboard.php" class="btn-tp-ghost btn-tp-sm">
        <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
    </a>
    <?php endif; ?>
</div>

<?php if ($force_mode): ?>
<div class="alert alert-warning mb-3">
    <i class="fa-solid fa-lock me-1"></i>
    <strong>Password change required.</strong>
    You must set a new password before continuing.
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="tp-card" style="max-width:480px;">
    <form method="POST" action="change_password.php<?= $force_mode ? '?force=1' : '' ?>">
        <?= csrf_field() ?>

        <div class="row g-3">

            <!-- Current Password (skip in force mode) -->
            <?php if (!$force_mode): ?>
            <div class="col-12">
                <label class="form-label" for="current_password">
                    Current Password <span class="text-danger">*</span>
                </label>
                <input type="password"
                       id="current_password"
                       name="current_password"
                       class="form-control"
                       autocomplete="current-password"
                       required>
            </div>
            <?php endif; ?>

            <!-- New Password -->
            <div class="col-12">
                <label class="form-label" for="new_password">
                    New Password <span class="text-danger">*</span>
                </label>
                <input type="password"
                       id="new_password"
                       name="new_password"
                       class="form-control"
                       autocomplete="new-password"
                       required>
                <div class="form-text">Minimum 8 characters.</div>
            </div>

            <!-- Confirm New Password -->
            <div class="col-12">
                <label class="form-label" for="confirm_password">
                    Confirm New Password <span class="text-danger">*</span>
                </label>
                <input type="password"
                       id="confirm_password"
                       name="confirm_password"
                       class="form-control"
                       autocomplete="new-password"
                       required>
            </div>

            <!-- Submit -->
            <div class="col-12">
                <button type="submit" class="btn-tp-primary">
                    <i class="fa-solid fa-key"></i>
                    <?= $force_mode ? 'Set New Password' : 'Change Password' ?>
                </button>
            </div>

        </div>
    </form>
</div>

<?php
layout_end();
