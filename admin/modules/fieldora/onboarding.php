<?php

require_once __DIR__ . '/_bootstrap.php';

use TrashPanda\Fieldora\Services\ConnectService;
use TrashPanda\Fieldora\Services\TenantService;

$tenant = current_tenant();
$tenantId = current_tenant_id();
$progress = db_fetch('SELECT * FROM onboarding_progress WHERE tenant_id = ? LIMIT 1', [$tenantId]) ?: ['current_step' => 1, 'completed_steps_json' => '[]', 'last_completed_step' => 0];
$completedSteps = json_decode((string) ($progress['completed_steps_json'] ?? '[]'), true) ?: [];
$step = max(1, min(6, (int) ($_GET['step'] ?? $progress['current_step'] ?? 1)));
$paymentAccount = db_fetch('SELECT * FROM tenant_payment_accounts WHERE tenant_id = ? AND provider = ? LIMIT 1', [$tenantId, 'stripe']) ?: [];

$markStepComplete = static function (int $stepNumber) use ($tenantId, &$completedSteps): void {
    if (!in_array($stepNumber, $completedSteps, true)) {
        $completedSteps[] = $stepNumber;
        sort($completedSteps);
    }
    db_execute(
        'UPDATE onboarding_progress SET current_step = ?, completed_steps_json = ?, last_completed_step = ?, updated_at = NOW() WHERE tenant_id = ?',
        [min(6, $stepNumber + 1), json_encode(array_values($completedSteps), JSON_UNESCAPED_SLASHES), max($stepNumber, (int) max($completedSteps ?: [0])), $tenantId]
    );
};

if (isset($_GET['connect_stripe'])) {
    try {
        redirect(ConnectService::onboardingLink($tenantId));
    } catch (Throwable $e) {
        flash_error($e->getMessage());
        redirect(APP_URL . '/modules/fieldora/onboarding.php?step=4');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $postedStep = (int) ($_POST['step'] ?? $step);

    if ($postedStep === 1) {
        $businessEmail = trim((string) ($_POST['business_email'] ?? ''));
        if ($businessEmail === '') {
            flash_error('Business email is required.');
            redirect(APP_URL . '/modules/fieldora/onboarding.php?step=1');
        }

        db_execute(
            'UPDATE tenants SET name = ?, business_email = ?, business_phone = ?, timezone = ?, address_line1 = ?, city = ?, state = ?, postal_code = ?, updated_at = NOW() WHERE id = ?',
            [
                trim((string) ($_POST['business_name'] ?? $tenant['name'] ?? '')),
                $businessEmail,
                trim((string) ($_POST['business_phone'] ?? '')),
                trim((string) ($_POST['timezone'] ?? APP_TIMEZONE)),
                trim((string) ($_POST['address_line1'] ?? '')),
                trim((string) ($_POST['city'] ?? '')),
                trim((string) ($_POST['state'] ?? '')),
                trim((string) ($_POST['postal_code'] ?? '')),
                $tenantId,
            ]
        );
        $markStepComplete(1);
        flash_success('Business info saved.');
        redirect(APP_URL . '/modules/fieldora/onboarding.php?step=2');
    }

    if ($postedStep === 2) {
        $logoPath = trim((string) ($_POST['logo_path'] ?? tenant_brand_value('logo_path', '')));
        if (!empty($_FILES['logo_file']['tmp_name']) && is_uploaded_file($_FILES['logo_file']['tmp_name'])) {
            $mime = mime_content_type($_FILES['logo_file']['tmp_name']) ?: '';
            $allowed = [
                'image/png' => 'png',
                'image/jpeg' => 'jpg',
                'image/webp' => 'webp',
                'image/gif' => 'gif',
                'image/svg+xml' => 'svg',
            ];

            if (!isset($allowed[$mime])) {
                flash_error('Logo upload must be PNG, JPG, WEBP, GIF, or SVG.');
                redirect(APP_URL . '/modules/fieldora/onboarding.php?step=2');
            }

            $uploadDir = dirname(__DIR__, 3) . '/public/uploads/branding';
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0775, true);
            }

            $filename = 'tenant-' . $tenantId . '-' . date('YmdHis') . '.' . $allowed[$mime];
            $destination = $uploadDir . '/' . $filename;
            if (!move_uploaded_file($_FILES['logo_file']['tmp_name'], $destination)) {
                flash_error('Unable to save uploaded logo.');
                redirect(APP_URL . '/modules/fieldora/onboarding.php?step=2');
            }
            $logoPath = SITE_URL . '/public/uploads/branding/' . $filename;
        }

        db_execute(
            'UPDATE tenant_branding SET logo_path = ?, primary_color = ?, secondary_color = ?, accent_color = ?, marketing_headline = ?, booking_intro = ?, footer_text = ? WHERE tenant_id = ?',
            [
                $logoPath,
                trim((string) ($_POST['primary_color'] ?? '#2563eb')),
                trim((string) ($_POST['secondary_color'] ?? '#0f172a')),
                trim((string) ($_POST['accent_color'] ?? '#f59e0b')),
                trim((string) ($_POST['marketing_headline'] ?? 'Get booked and get paid online.')),
                trim((string) ($_POST['booking_intro'] ?? '')),
                trim((string) ($_POST['footer_text'] ?? 'Powered by Fieldora')),
                $tenantId,
            ]
        );
        $markStepComplete(2);
        flash_success('Branding saved.');
        redirect(APP_URL . '/modules/fieldora/onboarding.php?step=3');
    }

    if ($postedStep === 3) {
        $name = trim((string) ($_POST['service_name'] ?? ''));
        $price = (float) ($_POST['service_price'] ?? 0);
        if ($name === '' || $price <= 0) {
            flash_error('Add at least one service with a valid price.');
            redirect(APP_URL . '/modules/fieldora/onboarding.php?step=3');
        }

        db_insert('services', [
            'tenant_id' => $tenantId,
            'name' => $name,
            'description' => trim((string) ($_POST['description'] ?? '')),
            'price' => $price,
            'duration_minutes' => (int) ($_POST['duration_minutes'] ?? 0) ?: null,
            'deposit_mode' => trim((string) ($_POST['deposit_mode'] ?? 'none')),
            'deposit_value' => (float) ($_POST['deposit_value'] ?? 0),
            'is_active' => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $markStepComplete(3);
        flash_success('Your first service was added.');
        redirect(APP_URL . '/modules/fieldora/onboarding.php?step=4');
    }

    if ($postedStep === 4) {
        if (($paymentAccount['account_status'] ?? '') !== 'connected' && (string) ($_POST['continue_without_payments'] ?? '') !== '1') {
            flash_error('Connect Stripe or choose to continue without online payments for now.');
            redirect(APP_URL . '/modules/fieldora/onboarding.php?step=4');
        }
        $markStepComplete(4);
        flash_success('Payment step completed.');
        redirect(APP_URL . '/modules/fieldora/onboarding.php?step=5');
    }

    if ($postedStep === 5) {
        $notificationKeys = ['smtp_host', 'smtp_port', 'smtp_username', 'smtp_encryption', 'smtp_from_name', 'smtp_from_email', 'twilio_sid', 'twilio_from'];
        foreach ($notificationKeys as $key) {
            TenantService::saveSetting($tenantId, $key, trim((string) ($_POST[$key] ?? '')));
        }

        foreach (['smtp_password', 'twilio_token'] as $secretKey) {
            $value = trim((string) ($_POST[$secretKey] ?? ''));
            if ($value !== '') {
                TenantService::saveSetting($tenantId, $secretKey, $value);
            }
        }

        if (tenant_setting('smtp_host', '') !== '' && !filter_var(tenant_setting('smtp_from_email', ''), FILTER_VALIDATE_EMAIL)) {
            flash_error('Add a valid From email address for SMTP notifications.');
            redirect(APP_URL . '/modules/fieldora/onboarding.php?step=5');
        }
        db_execute('UPDATE tenants SET onboarding_completed_at = NOW(), updated_at = NOW() WHERE id = ?', [$tenantId]);
        $markStepComplete(5);
        flash_success('Onboarding completed. Your workspace is ready for testing.');
        redirect(APP_URL . '/modules/fieldora/onboarding.php?step=6');
    }
}

$steps = [
    1 => ['title' => 'Business info', 'description' => 'Tell Fieldora how to represent your business.'],
    2 => ['title' => 'Branding', 'description' => 'Set your colors and customer-facing message.'],
    3 => ['title' => 'Services', 'description' => 'Add the first service customers can book.'],
    4 => ['title' => 'Payments', 'description' => 'Connect Stripe or continue with manual payments.'],
    5 => ['title' => 'Notifications', 'description' => 'Set up email and optional SMS delivery.'],
];

fieldora_layout_start('Onboarding', 'onboarding');
?>
<section class="card stack">
    <div>
        <h3>Step <?= $step <= 5 ? $step : 5 ?> of 5</h3>
        <p class="muted"><?= e($steps[min($step, 5)]['title'] ?? 'Finish') ?> - <?= e($steps[min($step, 5)]['description'] ?? 'Complete setup') ?></p>
    </div>
    <div class="grid">
        <?php foreach ($steps as $number => $meta): ?>
            <div class="service-option"><span><strong><?= in_array($number, $completedSteps, true) ? '[x]' : ($step === $number ? '[>]' : '[ ]') ?> <?= e($meta['title']) ?></strong><small><?= e($meta['description']) ?></small></span></div>
        <?php endforeach; ?>
    </div>
</section>

<?php if ($step === 1): ?>
    <form method="post" class="card form-grid" style="margin-top:20px;">
        <?= csrf_field() ?><input type="hidden" name="step" value="1">
        <label><span>Business name</span><input name="business_name" value="<?= e($tenant['name'] ?? '') ?>" required></label>
        <label><span>Business email</span><input name="business_email" type="email" value="<?= e($tenant['business_email'] ?? '') ?>" required></label>
        <label><span>Business phone</span><input name="business_phone" value="<?= e($tenant['business_phone'] ?? '') ?>"></label>
        <label><span>Timezone</span><input name="timezone" value="<?= e($tenant['timezone'] ?? APP_TIMEZONE) ?>"></label>
        <label><span>Address</span><input name="address_line1" value="<?= e($tenant['address_line1'] ?? '') ?>"></label>
        <label><span>City</span><input name="city" value="<?= e($tenant['city'] ?? '') ?>"></label>
        <label><span>State</span><input name="state" value="<?= e($tenant['state'] ?? '') ?>"></label>
        <label><span>Postal code</span><input name="postal_code" value="<?= e($tenant['postal_code'] ?? '') ?>"></label>
        <button class="primary-btn" type="submit">Save and continue</button>
    </form>
<?php elseif ($step === 2): ?>
    <form method="post" enctype="multipart/form-data" class="card form-grid" style="margin-top:20px;">
        <?= csrf_field() ?><input type="hidden" name="step" value="2">
        <label><span>Logo URL</span><input name="logo_path" value="<?= e(tenant_brand_value('logo_path', '')) ?>" placeholder="https://example.com/logo.png"></label>
        <label><span>Upload logo</span><input name="logo_file" type="file" accept=".png,.jpg,.jpeg,.webp,.gif,.svg"></label>
        <label><span>Primary color</span><input name="primary_color" value="<?= e(tenant_brand_value('primary_color', '#2563eb')) ?>"></label>
        <label><span>Secondary color</span><input name="secondary_color" value="<?= e(tenant_brand_value('secondary_color', '#0f172a')) ?>"></label>
        <label><span>Accent color</span><input name="accent_color" value="<?= e(tenant_brand_value('accent_color', '#f59e0b')) ?>"></label>
        <label><span>Headline</span><input name="marketing_headline" value="<?= e(tenant_brand_value('marketing_headline', 'Get booked and get paid online.')) ?>"></label>
        <label><span>Booking intro</span><textarea name="booking_intro"><?= e(tenant_brand_value('booking_intro', '')) ?></textarea></label>
        <label><span>Footer text</span><textarea name="footer_text"><?= e(tenant_brand_value('footer_text', 'Powered by Fieldora')) ?></textarea></label>
        <?php if (tenant_brand_value('logo_path', '') !== ''): ?><div class="service-option"><span><strong>Current logo</strong><small><?= e(tenant_brand_value('logo_path', '')) ?></small></span></div><?php endif; ?>
        <button class="primary-btn" type="submit">Save and continue</button>
    </form>
<?php elseif ($step === 3): ?>
    <section class="card" style="margin-top:20px;">
        <h3>Add your first service</h3>
        <p class="muted">A simple first service is enough to test booking end to end. You can refine pricing and deposits later.</p>
    </section>
    <form method="post" class="card form-grid" style="margin-top:20px;">
        <?= csrf_field() ?><input type="hidden" name="step" value="3">
        <label><span>Service name</span><input name="service_name" placeholder="Standard service" required></label>
        <label><span>Price</span><input name="service_price" type="number" step="0.01" placeholder="149.00" required></label>
        <label><span>Duration minutes</span><input name="duration_minutes" type="number" placeholder="60"></label>
        <label><span>Deposit mode</span><select name="deposit_mode"><option value="none">No deposit</option><option value="percent">Percent</option><option value="fixed">Fixed</option></select></label>
        <label><span>Deposit value</span><input name="deposit_value" type="number" step="0.01" placeholder="25"></label>
        <label><span>Description</span><textarea name="description" placeholder="What this service includes"></textarea></label>
        <button class="primary-btn" type="submit">Save and continue</button>
    </form>
<?php elseif ($step === 4): ?>
    <section class="card stack" style="margin-top:20px;">
        <h3>Connect payments</h3>
        <p class="muted">Fieldora uses Stripe Connect so each business receives its own payments. If you are just testing workflows, you can continue without online payments and use manual payment entry first.</p>
        <p>Status: <span class="tag"><?= e((string) ($paymentAccount['account_status'] ?? 'not_connected')) ?></span></p>
        <div class="topbar-actions">
            <a class="primary-btn" href="<?= e(APP_URL) ?>/modules/fieldora/onboarding.php?step=4&connect_stripe=1">Connect Stripe</a>
            <a class="ghost-link" href="<?= e(APP_URL) ?>/modules/fieldora/billing.php">Open billing page</a>
        </div>
    </section>
    <form method="post" class="card form-grid" style="margin-top:20px;">
        <?= csrf_field() ?><input type="hidden" name="step" value="4">
        <label><span>Continue without online payments</span><select name="continue_without_payments"><option value="0">No, I want Stripe first</option><option value="1">Yes, manual payments for now</option></select></label>
        <button class="primary-btn" type="submit">Save and continue</button>
    </form>
<?php elseif ($step === 5): ?>
    <section class="card" style="margin-top:20px;">
        <h3>Set up notifications</h3>
        <p class="muted">Email is the most important first step. Add SMTP now so password resets, booking confirmations, invoice sends, and payment confirmations deliver correctly.</p>
        <p class="muted">You can leave Twilio blank for now if you only want email during testing.</p>
    </section>
    <form method="post" class="card form-grid" style="margin-top:20px;">
        <?= csrf_field() ?><input type="hidden" name="step" value="5">
        <label><span>SMTP host</span><input name="smtp_host" value="<?= e(tenant_setting('smtp_host', '')) ?>"></label>
        <label><span>SMTP port</span><input name="smtp_port" value="<?= e(tenant_setting('smtp_port', '587')) ?>"></label>
        <label><span>SMTP username</span><input name="smtp_username" value="<?= e(tenant_setting('smtp_username', '')) ?>"></label>
        <label><span>SMTP password</span><input name="smtp_password" type="password" placeholder="<?= tenant_setting('smtp_password', '') !== '' ? 'Stored value kept if left blank' : 'Enter SMTP password' ?>"></label>
        <label><span>Encryption</span><select name="smtp_encryption"><?php $enc = tenant_setting('smtp_encryption', 'tls'); ?><option value="tls"<?= $enc === 'tls' ? ' selected' : '' ?>>TLS</option><option value="ssl"<?= $enc === 'ssl' ? ' selected' : '' ?>>SSL</option><option value="none"<?= $enc === 'none' ? ' selected' : '' ?>>None</option></select></label>
        <label><span>From name</span><input name="smtp_from_name" value="<?= e(tenant_setting('smtp_from_name', $tenant['name'] ?? APP_NAME)) ?>"></label>
        <label><span>From email</span><input name="smtp_from_email" value="<?= e(tenant_setting('smtp_from_email', $tenant['business_email'] ?? '')) ?>"></label>
        <label><span>Twilio SID</span><input name="twilio_sid" value="<?= e(tenant_setting('twilio_sid', '')) ?>"></label>
        <label><span>Twilio token</span><input name="twilio_token" type="password" placeholder="<?= tenant_setting('twilio_token', '') !== '' ? 'Stored value kept if left blank' : 'Enter Twilio token' ?>"></label>
        <label><span>Twilio from</span><input name="twilio_from" value="<?= e(tenant_setting('twilio_from', '')) ?>"></label>
        <button class="primary-btn" type="submit">Finish onboarding</button>
    </form>
<?php else: ?>
    <section class="card stack" style="margin-top:20px;">
        <h3>Workspace ready</h3>
        <p class="muted">Your setup is complete enough for internal testing. Here are the next best actions.</p>
        <p><strong>Booking link:</strong> <a href="<?= e(tenant_booking_url()) ?>" target="_blank" rel="noopener"><?= e(tenant_booking_url()) ?></a></p>
        <div class="stack">
            <a class="service-option" href="<?= e(APP_URL) ?>/modules/fieldora/share.php"><span><strong>Share your booking page</strong><small>Copy the link, QR code, or embed button.</small></span></a>
            <a class="service-option" href="<?= e(APP_URL) ?>/dashboard.php"><span><strong>Use the Getting Started checklist</strong><small>Track the last setup tasks from your dashboard.</small></span></a>
            <a class="service-option" href="<?= e(APP_URL) ?>/modules/fieldora/help.php"><span><strong>Open in-app help</strong><small>Read setup articles by role and topic.</small></span></a>
        </div>
        <div class="stack">
            <label class="service-option"><span><strong>[ ] Test a live booking</strong><small>Open your booking page and submit one booking from a separate browser session.</small></span></label>
            <label class="service-option"><span><strong>[ ] Confirm notification delivery</strong><small>Run cron once and make sure booking and invoice emails arrive in your inbox.</small></span></label>
            <label class="service-option"><span><strong>[ ] Connect one integration</strong><small>Send a webhook test to webhook.site, Zapier, n8n, or Make.</small></span></label>
        </div>
    </section>
<?php endif; ?>
<?php fieldora_layout_end();
