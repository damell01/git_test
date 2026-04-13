<?php
require_once __DIR__ . '/_bootstrap.php';

require_permission('branding.manage');

$tenantId = current_tenant_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $logoPath = trim((string) ($_POST['logo_path'] ?? ''));
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
            redirect($_SERVER['REQUEST_URI']);
        }

        $uploadDir = dirname(__DIR__, 3) . '/public/uploads/branding';
        if (!is_dir($uploadDir)) {
            @mkdir($uploadDir, 0775, true);
        }

        $filename = 'tenant-' . $tenantId . '-' . date('YmdHis') . '.' . $allowed[$mime];
        $destination = $uploadDir . '/' . $filename;
        if (!move_uploaded_file($_FILES['logo_file']['tmp_name'], $destination)) {
            flash_error('Unable to save uploaded logo.');
            redirect($_SERVER['REQUEST_URI']);
        }
        $logoPath = SITE_URL . '/public/uploads/branding/' . $filename;
    }

    db_execute(
        'UPDATE tenant_branding
         SET logo_path = ?, primary_color = ?, secondary_color = ?, accent_color = ?, marketing_headline = ?, booking_intro = ?, footer_text = ?
         WHERE tenant_id = ?',
        [
            $logoPath,
            trim((string) ($_POST['primary_color'] ?? '#2563eb')),
            trim((string) ($_POST['secondary_color'] ?? '#0f172a')),
            trim((string) ($_POST['accent_color'] ?? '#f59e0b')),
            trim((string) ($_POST['marketing_headline'] ?? '')),
            trim((string) ($_POST['booking_intro'] ?? '')),
            trim((string) ($_POST['footer_text'] ?? '')),
            $tenantId,
        ]
    );

    db_execute(
        'UPDATE tenants
         SET name = ?, business_email = ?, business_phone = ?, timezone = ?, address_line1 = ?, city = ?, state = ?, postal_code = ?, updated_at = NOW()
         WHERE id = ?',
        [
            trim((string) ($_POST['business_name'] ?? '')),
            trim((string) ($_POST['business_email'] ?? '')),
            trim((string) ($_POST['business_phone'] ?? '')),
            trim((string) ($_POST['timezone'] ?? APP_TIMEZONE)),
            trim((string) ($_POST['address_line1'] ?? '')),
            trim((string) ($_POST['city'] ?? '')),
            trim((string) ($_POST['state'] ?? '')),
            trim((string) ($_POST['postal_code'] ?? '')),
            $tenantId,
        ]
    );

    flash_success('Branding updated.');
    redirect($_SERVER['REQUEST_URI']);
}

$row = db_fetch('SELECT * FROM tenant_branding WHERE tenant_id = ?', [$tenantId]) ?: [];
$tenant = current_tenant() ?: [];

fieldora_layout_start('Branding', 'branding');
?>
<form method="post" enctype="multipart/form-data" class="stack">
    <?= csrf_field() ?>

    <section class="card stack">
        <div>
            <h3>Business identity</h3>
            <p class="muted">This powers your booking page contact info and future branded communications.</p>
        </div>
        <div class="form-grid">
            <label>
                <span>Business name</span>
                <input name="business_name" value="<?= e($tenant['name'] ?? '') ?>" required>
            </label>
            <label>
                <span>Business email</span>
                <input name="business_email" type="email" value="<?= e($tenant['business_email'] ?? '') ?>">
            </label>
            <label>
                <span>Business phone</span>
                <input name="business_phone" value="<?= e($tenant['business_phone'] ?? '') ?>">
            </label>
            <label>
                <span>Timezone</span>
                <input name="timezone" value="<?= e($tenant['timezone'] ?? APP_TIMEZONE) ?>">
            </label>
            <label>
                <span>Address</span>
                <input name="address_line1" value="<?= e($tenant['address_line1'] ?? '') ?>">
            </label>
            <label>
                <span>City</span>
                <input name="city" value="<?= e($tenant['city'] ?? '') ?>">
            </label>
            <label>
                <span>State</span>
                <input name="state" value="<?= e($tenant['state'] ?? '') ?>">
            </label>
            <label>
                <span>Postal code</span>
                <input name="postal_code" value="<?= e($tenant['postal_code'] ?? '') ?>">
            </label>
        </div>
    </section>

    <section class="card stack">
        <div>
            <h3>Visual brand</h3>
            <p class="muted">Set the logo, colors, and customer-facing copy used in the booking flow.</p>
        </div>
        <div class="form-grid">
            <label>
                <span>Logo URL</span>
                <input name="logo_path" placeholder="https://example.com/logo.png" value="<?= e($row['logo_path'] ?? '') ?>">
            </label>
            <label>
                <span>Upload logo</span>
                <input name="logo_file" type="file" accept=".png,.jpg,.jpeg,.webp,.gif,.svg">
            </label>
            <label>
                <span>Primary color</span>
                <input name="primary_color" placeholder="#2563eb" value="<?= e($row['primary_color'] ?? '#2563eb') ?>">
            </label>
            <label>
                <span>Secondary color</span>
                <input name="secondary_color" placeholder="#0f172a" value="<?= e($row['secondary_color'] ?? '#0f172a') ?>">
            </label>
            <label>
                <span>Accent color</span>
                <input name="accent_color" placeholder="#f59e0b" value="<?= e($row['accent_color'] ?? '#f59e0b') ?>">
            </label>
            <label>
                <span>Headline</span>
                <input name="marketing_headline" value="<?= e($row['marketing_headline'] ?? '') ?>" placeholder="Get booked and get paid online">
            </label>
            <label>
                <span>Booking intro</span>
                <textarea name="booking_intro" placeholder="Choose what you need, pick a day, and we will take it from there."><?= e($row['booking_intro'] ?? '') ?></textarea>
            </label>
            <label>
                <span>Footer text</span>
                <textarea name="footer_text" placeholder="Questions, special timing, or custom work? Call or text us and we will help you book the right option."><?= e($row['footer_text'] ?? '') ?></textarea>
            </label>
        </div>
        <?php if (!empty($row['logo_path'])): ?>
            <div>
                <p class="muted">Current logo</p>
                <img src="<?= e($row['logo_path']) ?>" alt="Tenant logo" style="max-height:72px;max-width:220px;">
            </div>
        <?php endif; ?>
    </section>

    <button class="primary-btn" type="submit">Save branding</button>
</form>
<?php fieldora_layout_end();
