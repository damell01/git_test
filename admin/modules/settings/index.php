<?php
/**
 * Settings – Company & System Settings
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once TMPL_PATH . '/layout.php';
require_login();
require_role('admin', 'office');

// ── POST handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $action = trim($_POST['action'] ?? 'save_settings');

    if ($action === 'test_email') {
        require_once INC_PATH . '/mailer.php';
        $to = get_setting('company_email', '');
        if (empty($to)) {
            flash_error('No company email set. Please save settings first.');
        } else {
            $html = email_template(
                'Test Email',
                '<p>This is a test email from your Trash Panda Roll-Offs Manager.</p><p>If you received this, email sending is working correctly.</p>'
            );
            $result = send_email($to, 'Test Email from ' . get_setting('company_name', 'Trash Panda Roll-Offs'), $html);
            if ($result) {
                flash_success('Test email sent to ' . $to . '.');
            } else {
                flash_error('Failed to send test email. Check your PHP mail() configuration.');
            }
        }
        redirect('index.php');
    }

    $fields = [
        'company_name',
        'company_phone',
        'company_email',
        'company_address',
        'tax_rate',
        'quote_terms',
        'wo_footer',
        'logo_path',
        'email_from_name',
        'email_from_email',
        'notification_emails',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'smtp_encryption',
    ];

    foreach ($fields as $key) {
        // Don't overwrite the SMTP password if the field was left blank
        if ($key === 'smtp_password' && trim($_POST[$key] ?? '') === '') {
            continue;
        }
        $value = trim($_POST[$key] ?? '');
        set_setting($key, $value);
    }

    log_activity('update', 'Updated system settings', 'settings', 0);
    flash_success('Settings saved successfully.');
    redirect('index.php');
}

layout_start('Settings', 'settings');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">System Settings</h5>
</div>

<div class="tp-card" style="max-width:780px;">
    <form method="POST" action="index.php">
        <?= csrf_field() ?>

        <h6 class="mb-3" style="font-weight:600;border-bottom:1px solid #e5e7eb;padding-bottom:.5rem;">
            Company Information
        </h6>

        <div class="row g-3 mb-4">

            <!-- Company Name -->
            <div class="col-md-6">
                <label class="form-label" for="company_name">Company Name</label>
                <input type="text"
                       id="company_name"
                       name="company_name"
                       class="form-control"
                       value="<?= e(get_setting('company_name', 'Trash Panda Roll-Offs')) ?>">
            </div>

            <!-- Company Phone -->
            <div class="col-md-6">
                <label class="form-label" for="company_phone">Company Phone</label>
                <input type="text"
                       id="company_phone"
                       name="company_phone"
                       class="form-control"
                       value="<?= e(get_setting('company_phone')) ?>">
            </div>

            <!-- Company Email -->
            <div class="col-md-6">
                <label class="form-label" for="company_email">Company Email</label>
                <input type="email"
                       id="company_email"
                       name="company_email"
                       class="form-control"
                       value="<?= e(get_setting('company_email')) ?>">
            </div>

            <!-- Company Address -->
            <div class="col-md-6">
                <label class="form-label" for="company_address">Company Address</label>
                <input type="text"
                       id="company_address"
                       name="company_address"
                       class="form-control"
                       value="<?= e(get_setting('company_address')) ?>">
            </div>

        </div>

        <h6 class="mb-3" style="font-weight:600;border-bottom:1px solid #e5e7eb;padding-bottom:.5rem;">
            Financial
        </h6>

        <div class="row g-3 mb-4">

            <!-- Tax Rate -->
            <div class="col-md-4">
                <label class="form-label" for="tax_rate">Tax Rate (%)</label>
                <input type="number"
                       id="tax_rate"
                       name="tax_rate"
                       class="form-control"
                       step="0.01"
                       min="0"
                       max="100"
                       value="<?= e(get_setting('tax_rate', '0.00')) ?>">
                <div class="form-text">Enter as a percentage, e.g. 8.5 for 8.5%</div>
            </div>

        </div>

        <h6 class="mb-3" style="font-weight:600;border-bottom:1px solid #e5e7eb;padding-bottom:.5rem;">
            Documents
        </h6>

        <div class="row g-3 mb-4">

            <!-- Quote Terms -->
            <div class="col-12">
                <label class="form-label" for="quote_terms">Quote Terms &amp; Conditions</label>
                <textarea id="quote_terms"
                          name="quote_terms"
                          class="form-control"
                          rows="4"
                          placeholder="Terms and conditions to display on quotes…"><?= e(get_setting('quote_terms')) ?></textarea>
            </div>

            <!-- Work Order Footer -->
            <div class="col-12">
                <label class="form-label" for="wo_footer">Work Order Footer</label>
                <textarea id="wo_footer"
                          name="wo_footer"
                          class="form-control"
                          rows="4"
                          placeholder="Footer text to appear on printed work orders…"><?= e(get_setting('wo_footer')) ?></textarea>
            </div>

        </div>

        <h6 class="mb-3" style="font-weight:600;border-bottom:1px solid #e5e7eb;padding-bottom:.5rem;">
            Branding
        </h6>

        <div class="row g-3 mb-4">

            <!-- Logo Path -->
            <div class="col-12">
                <label class="form-label" for="logo_path">Logo Path</label>
                <input type="text"
                       id="logo_path"
                       name="logo_path"
                       class="form-control"
                       value="<?= e(get_setting('logo_path')) ?>"
                       placeholder="/admin/assets/img/logo.png">
                <div class="form-text">
                    Enter the server path or URL to the logo image.
                    Upload the file manually via FTP/SFTP to your server's assets directory.
                </div>
            </div>

        </div>

        <!-- Submit -->
        <div class="d-flex gap-2">
            <button type="submit" class="btn-tp-primary">
                <i class="fa-solid fa-floppy-disk"></i> Save Settings
            </button>
        </div>

    </form>
</div>

<!-- ── Email Configuration ──────────────────────────────────────────────── -->
<div class="tp-card mt-4" style="max-width:780px;">
    <h6 class="mb-3" style="font-weight:600;border-bottom:1px solid #e5e7eb;padding-bottom:.5rem;">
        <i class="fa-solid fa-envelope" style="color:#f97316;"></i> Email Configuration
    </h6>

    <form method="POST" action="index.php">
        <?= csrf_field() ?>

        <div class="row g-3 mb-4">

            <div class="col-md-6">
                <label class="form-label" for="email_from_name">From Name</label>
                <input type="text" id="email_from_name" name="email_from_name" class="form-control"
                       value="<?= e(get_setting('email_from_name', get_setting('company_name', 'Trash Panda Roll-Offs'))) ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label" for="email_from_email">From Email</label>
                <input type="email" id="email_from_email" name="email_from_email" class="form-control"
                       value="<?= e(get_setting('email_from_email', get_setting('company_email', ''))) ?>">
            </div>

            <div class="col-12">
                <label class="form-label" for="notification_emails">
                    Notification Email(s)
                    <span style="font-weight:400;color:var(--gy);font-size:.8rem;"> — receive alerts for new contact-form leads</span>
                </label>
                <input type="text" id="notification_emails" name="notification_emails" class="form-control"
                       placeholder="admin@example.com, manager@example.com"
                       value="<?= e(get_setting('notification_emails', get_setting('company_email', ''))) ?>">
                <div class="form-text" style="color:var(--gy);">Comma-separated. These addresses get an email whenever the public contact form is submitted.</div>
            </div>

        </div>

        <h6 class="mb-3 mt-2" style="font-weight:600;border-bottom:1px solid var(--st);padding-bottom:.5rem;">
            <i class="fa-solid fa-server" style="color:var(--or);"></i> SMTP Settings
            <span style="font-weight:400;font-size:.8rem;color:var(--gy);"> — leave Host blank to use PHP mail()</span>
        </h6>

        <div class="row g-3 mb-4">

            <div class="col-md-8">
                <label class="form-label" for="smtp_host">SMTP Host</label>
                <input type="text" id="smtp_host" name="smtp_host" class="form-control"
                       placeholder="smtp.mailgun.org / smtp.sendgrid.net / smtp.gmail.com"
                       value="<?= e(get_setting('smtp_host', '')) ?>">
            </div>

            <div class="col-md-4">
                <label class="form-label" for="smtp_port">SMTP Port</label>
                <input type="number" id="smtp_port" name="smtp_port" class="form-control"
                       placeholder="587"
                       value="<?= e(get_setting('smtp_port', '587')) ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label" for="smtp_username">SMTP Username</label>
                <input type="text" id="smtp_username" name="smtp_username" class="form-control"
                       value="<?= e(get_setting('smtp_username', '')) ?>">
            </div>

            <div class="col-md-6">
                <label class="form-label" for="smtp_password">SMTP Password</label>
                <input type="password" id="smtp_password" name="smtp_password" class="form-control"
                       placeholder="<?= get_setting('smtp_password') ? '••••••••' : 'Enter password' ?>"
                       value="">
                <div class="form-text" style="color:var(--gy);">Leave blank to keep existing password.</div>
            </div>

            <div class="col-md-4">
                <label class="form-label" for="smtp_encryption">Encryption</label>
                <select id="smtp_encryption" name="smtp_encryption" class="form-select">
                    <?php
                    $enc_cur = get_setting('smtp_encryption', 'tls');
                    foreach (['tls' => 'TLS (STARTTLS — port 587)', 'ssl' => 'SSL (port 465)', 'none' => 'None (not recommended)'] as $val => $lbl): ?>
                    <option value="<?= e($val) ?>" <?= $enc_cur === $val ? 'selected' : '' ?>><?= e($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-12">
                <div class="alert alert-info mb-0" style="font-size:.85rem;">
                    <i class="fa-solid fa-circle-info me-2"></i>
                    <strong>PHPMailer:</strong> Install PHPMailer via Composer for reliable SMTP delivery.
                    Run <code>composer install</code> inside the <code>admin/</code> folder.
                    Without it, the system falls back to PHP <code>mail()</code>.
                </div>
            </div>

        </div>

        <div class="d-flex gap-2">
            <button type="submit" class="btn-tp-primary">
                <i class="fa-solid fa-floppy-disk"></i> Save Email Settings
            </button>

            <form method="POST" action="index.php" class="d-inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="test_email">
                <button type="submit" class="btn-tp-ghost btn-tp-sm">
                    <i class="fa-solid fa-paper-plane"></i> Send Test Email
                </button>
            </form>
        </div>

    </form>
</div>

<!-- ── 2FA Setup Link ────────────────────────────────────────────────────── -->
<div class="tp-card mt-4" style="max-width:780px;">
    <h6 class="mb-2" style="font-weight:600;">
        <i class="fa-solid fa-shield-halved" style="color:#f97316;"></i> Two-Factor Authentication
    </h6>
    <p style="font-size:.9rem;color:var(--gy);">
        Enable 2FA on your account using an authenticator app (Google Authenticator, Authy, etc.)
    </p>
    <a href="<?= e(APP_URL) ?>/modules/two_factor/setup.php" class="btn-tp-ghost btn-tp-sm">
        <i class="fa-solid fa-qrcode"></i> Manage 2FA
    </a>
</div>

<script>
function toggleField(id) {
    var inp  = document.getElementById(id);
    var icon = document.getElementById(id + '-icon');
    if (inp.type === 'password') {
        inp.type = 'text';
        icon.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        inp.type = 'password';
        icon.classList.replace('fa-eye-slash', 'fa-eye');
    }
}
</script>

<?php
layout_end();
