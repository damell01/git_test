<?php
require_once __DIR__ . '/_bootstrap.php';

require_permission('settings.manage');

$tenantId = current_tenant_id();

if (isset($_GET['remove_blackout'])) {
    $submittedToken = (string) ($_GET[CSRF_TOKEN_NAME] ?? '');
    if (!hash_equals(csrf_token(), $submittedToken)) {
        http_response_code(400);
        exit('Invalid security token.');
    }
    db_execute('DELETE FROM blackout_dates WHERE tenant_id = ? AND id = ?', [$tenantId, (int) $_GET['remove_blackout']]);
    flash_success('Blackout date removed.');
    redirect(APP_URL . '/modules/fieldora/settings.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    foreach ([
        'booking_approval_mode',
        'allow_instant_booking',
        'allow_full_payment',
        'require_deposit',
        'default_deposit_mode',
        'default_deposit_value',
        'max_bookings_per_day',
        'minimum_notice_hours',
        'show_public_prices',
        'currency',
        'booking_tagline',
        'booking_header_note',
        'booking_footer_note',
        'call_text_fallback',
        'twilio_sid',
        'twilio_token',
        'twilio_from',
        'smtp_host',
        'smtp_port',
        'smtp_username',
        'smtp_password',
        'smtp_encryption',
        'smtp_from_name',
        'smtp_from_email',
    ] as $key) {
        $value = trim((string) ($_POST[$key] ?? ''));
        if (in_array($key, ['smtp_password', 'twilio_token'], true) && $value === '') {
            $value = tenant_setting($key, '');
        }
        \TrashPanda\Fieldora\Services\TenantService::saveSetting($tenantId, $key, $value);
    }

    foreach ((array) ($_POST['working_hours'] ?? []) as $weekday => $hour) {
        db_execute(
            'UPDATE working_hours SET start_time = ?, end_time = ?, is_active = ? WHERE tenant_id = ? AND weekday = ?',
            [
                !empty($hour['start']) ? $hour['start'] . ':00' : null,
                !empty($hour['end']) ? $hour['end'] . ':00' : null,
                !empty($hour['active']) ? 1 : 0,
                $tenantId,
                (int) $weekday,
            ]
        );
    }

    $blackoutDate = trim((string) ($_POST['blackout_date'] ?? ''));
    if ($blackoutDate !== '') {
        db_execute(
            'INSERT INTO blackout_dates (tenant_id, blackout_date, reason) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE reason = VALUES(reason)',
            [$tenantId, $blackoutDate, trim((string) ($_POST['blackout_reason'] ?? ''))]
        );
    }

    flash_success('Settings saved.');
    redirect($_SERVER['REQUEST_URI']);
}

$approvalMode = tenant_setting('booking_approval_mode', 'request');
$allowInstant = tenant_setting('allow_instant_booking', '0');
$allowFullPayment = tenant_setting('allow_full_payment', '1');
$requireDeposit = tenant_setting('require_deposit', '1');
$depositMode = tenant_setting('default_deposit_mode', 'percent');
$showPublicPrices = tenant_setting('show_public_prices', '1');
$workingHours = db_fetchall('SELECT * FROM working_hours WHERE tenant_id = ? ORDER BY weekday ASC', [$tenantId]);
$blackoutDates = db_fetchall('SELECT * FROM blackout_dates WHERE tenant_id = ? ORDER BY blackout_date ASC LIMIT 50', [$tenantId]);
$dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
$currentTenant = current_tenant() ?: [];

fieldora_layout_start('Settings', 'settings');
?>
<form method="post" class="stack">
    <?= csrf_field() ?>

    <section class="card stack">
        <div>
            <h3>Booking rules</h3>
            <p class="muted">Control how customers move through your booking page, approvals, availability, and upfront payment options.</p>
        </div>
        <div class="form-grid">
            <label>
                <span>Approval mode</span>
                <select name="booking_approval_mode">
                    <option value="request"<?= $approvalMode === 'request' ? ' selected' : '' ?>>Request and review</option>
                    <option value="instant"<?= $approvalMode === 'instant' ? ' selected' : '' ?>>Instant booking</option>
                </select>
            </label>
            <label>
                <span>Allow instant booking</span>
                <select name="allow_instant_booking">
                    <option value="1"<?= $allowInstant === '1' ? ' selected' : '' ?>>Yes</option>
                    <option value="0"<?= $allowInstant !== '1' ? ' selected' : '' ?>>No</option>
                </select>
            </label>
            <label>
                <span>Allow full payment</span>
                <select name="allow_full_payment">
                    <option value="1"<?= $allowFullPayment === '1' ? ' selected' : '' ?>>Yes</option>
                    <option value="0"<?= $allowFullPayment !== '1' ? ' selected' : '' ?>>No</option>
                </select>
            </label>
            <label>
                <span>Require deposit</span>
                <select name="require_deposit">
                    <option value="1"<?= $requireDeposit === '1' ? ' selected' : '' ?>>Yes</option>
                    <option value="0"<?= $requireDeposit !== '1' ? ' selected' : '' ?>>No</option>
                </select>
            </label>
            <label>
                <span>Default deposit mode</span>
                <select name="default_deposit_mode">
                    <option value="percent"<?= $depositMode === 'percent' ? ' selected' : '' ?>>Percent</option>
                    <option value="fixed"<?= $depositMode === 'fixed' ? ' selected' : '' ?>>Fixed amount</option>
                    <option value="none"<?= $depositMode === 'none' ? ' selected' : '' ?>>None</option>
                </select>
            </label>
            <label>
                <span>Default deposit value</span>
                <input name="default_deposit_value" type="number" step="0.01" value="<?= e(tenant_setting('default_deposit_value', '25')) ?>">
            </label>
            <label>
                <span>Minimum notice hours</span>
                <input name="minimum_notice_hours" type="number" min="0" value="<?= e(tenant_setting('minimum_notice_hours', '2')) ?>">
            </label>
            <label>
                <span>Max bookings per day</span>
                <input name="max_bookings_per_day" type="number" min="0" value="<?= e(tenant_setting('max_bookings_per_day', '0')) ?>">
            </label>
            <label>
                <span>Show public prices</span>
                <select name="show_public_prices">
                    <option value="1"<?= $showPublicPrices === '1' ? ' selected' : '' ?>>Yes</option>
                    <option value="0"<?= $showPublicPrices !== '1' ? ' selected' : '' ?>>No</option>
                </select>
            </label>
            <label>
                <span>Currency</span>
                <input name="currency" value="<?= e(tenant_setting('currency', 'usd')) ?>" placeholder="usd">
            </label>
        </div>
    </section>

    <section class="card stack">
        <div>
            <h3>Working hours</h3>
            <p class="muted">Bookings outside active days and hours are blocked on the public page.</p>
        </div>
        <div class="stack">
            <?php foreach ($workingHours as $hour): ?>
                <div class="form-grid">
                    <label>
                        <span><?= e($dayNames[(int) $hour['weekday']] ?? 'Day') ?></span>
                        <select name="working_hours[<?= (int) $hour['weekday'] ?>][active]">
                            <option value="1"<?= (int) $hour['is_active'] === 1 ? ' selected' : '' ?>>Open</option>
                            <option value="0"<?= (int) $hour['is_active'] !== 1 ? ' selected' : '' ?>>Closed</option>
                        </select>
                    </label>
                    <label>
                        <span>Start</span>
                        <input type="time" name="working_hours[<?= (int) $hour['weekday'] ?>][start]" value="<?= e(substr((string) $hour['start_time'], 0, 5)) ?>">
                    </label>
                    <label>
                        <span>End</span>
                        <input type="time" name="working_hours[<?= (int) $hour['weekday'] ?>][end]" value="<?= e(substr((string) $hour['end_time'], 0, 5)) ?>">
                    </label>
                </div>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="card stack">
        <div>
            <h3>Blackout dates</h3>
            <p class="muted">Use blackout dates for holidays, full-capacity days, or business closures.</p>
        </div>
        <div class="form-grid">
            <label>
                <span>Add blackout date</span>
                <input type="date" name="blackout_date">
            </label>
            <label>
                <span>Reason</span>
                <input name="blackout_reason" placeholder="Holiday, maintenance, no service area coverage">
            </label>
        </div>
        <?php if ($blackoutDates): ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Date</th><th>Reason</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($blackoutDates as $row): ?>
                        <tr>
                            <td><?= e($row['blackout_date']) ?></td>
                            <td><?= e((string) $row['reason']) ?></td>
                            <td><a class="ghost-link" href="<?= e(APP_URL) ?>/modules/fieldora/settings.php?remove_blackout=<?= (int) $row['id'] ?>&<?= e(CSRF_TOKEN_NAME) ?>=<?= e(csrf_token()) ?>">Remove</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

    <section class="card stack">
        <div>
            <h3>Booking page content</h3>
            <p class="muted">These settings drive customer-facing booking copy so the page stays backend-controlled.</p>
        </div>
        <div class="form-grid">
            <label>
                <span>Tagline</span>
                <input name="booking_tagline" value="<?= e(tenant_setting('booking_tagline', 'Book online in minutes')) ?>" placeholder="Short value proposition">
            </label>
            <label>
                <span>Header note</span>
                <input name="booking_header_note" value="<?= e(tenant_setting('booking_header_note', 'Choose what you need, pick a day, and we will take it from there.')) ?>" placeholder="Intro copy">
            </label>
            <label>
                <span>Footer note</span>
                <input name="booking_footer_note" value="<?= e(tenant_setting('booking_footer_note', 'Questions, special timing, or custom work? Call or text us and we will help you book the right option.')) ?>" placeholder="Footer support copy">
            </label>
            <label>
                <span>Call/text fallback</span>
                <input name="call_text_fallback" value="<?= e(tenant_setting('call_text_fallback', 'Prefer to book by phone? Call or text us and we will help.')) ?>" placeholder="Fallback message">
            </label>
        </div>
    </section>

    <section class="card stack">
        <div>
            <h3>Email delivery</h3>
            <p class="muted">Fieldora now expects SMTP for email delivery. Configure tenant-specific credentials here.</p>
        </div>
        <div class="form-grid">
            <label>
                <span>SMTP host</span>
                <input name="smtp_host" value="<?= e(tenant_setting('smtp_host', '')) ?>" placeholder="smtp.mailgun.org">
            </label>
            <label>
                <span>SMTP port</span>
                <input name="smtp_port" value="<?= e(tenant_setting('smtp_port', '587')) ?>" placeholder="587">
            </label>
            <label>
                <span>SMTP username</span>
                <input name="smtp_username" value="<?= e(tenant_setting('smtp_username', '')) ?>">
            </label>
            <label>
                <span>SMTP password</span>
                <input name="smtp_password" type="password" placeholder="<?= tenant_setting('smtp_password', '') !== '' ? 'Stored value kept if left blank' : 'Enter SMTP password' ?>">
            </label>
            <label>
                <span>Encryption</span>
                <select name="smtp_encryption">
                    <?php $smtpEncryption = tenant_setting('smtp_encryption', 'tls'); ?>
                    <option value="tls"<?= $smtpEncryption === 'tls' ? ' selected' : '' ?>>TLS</option>
                    <option value="ssl"<?= $smtpEncryption === 'ssl' ? ' selected' : '' ?>>SSL</option>
                    <option value="none"<?= $smtpEncryption === 'none' ? ' selected' : '' ?>>None</option>
                </select>
            </label>
            <label>
                <span>From name</span>
                <input name="smtp_from_name" value="<?= e(tenant_setting('smtp_from_name', $currentTenant['name'] ?? APP_NAME)) ?>">
            </label>
            <label>
                <span>From email</span>
                <input name="smtp_from_email" type="email" value="<?= e(tenant_setting('smtp_from_email', $currentTenant['business_email'] ?? '')) ?>">
            </label>
        </div>
    </section>

    <section class="card stack">
        <div>
            <h3>SMS provider</h3>
            <p class="muted">Growth and Pro tenants can plug in Twilio for one-way booking, invoice, and payment notifications.</p>
        </div>
        <div class="form-grid">
            <label>
                <span>Twilio SID</span>
                <input name="twilio_sid" value="<?= e(tenant_setting('twilio_sid', '')) ?>">
            </label>
            <label>
                <span>Twilio token</span>
                <input name="twilio_token" type="password" placeholder="<?= tenant_setting('twilio_token', '') !== '' ? 'Stored value kept if left blank' : 'Enter Twilio token' ?>">
            </label>
            <label>
                <span>Twilio from number</span>
                <input name="twilio_from" value="<?= e(tenant_setting('twilio_from', '')) ?>" placeholder="+15551234567">
            </label>
        </div>
    </section>

    <section class="card">
        <h3>Payments are managed in Billing</h3>
        <p class="muted">Stripe is platform-managed through Stripe Connect. Use Billing to connect the tenant account, review status, and control invoice payment availability.</p>
        <a class="ghost-link" href="<?= e(APP_URL) ?>/modules/fieldora/billing.php">Open billing settings</a>
    </section>

    <button class="primary-btn" type="submit">Save settings</button>
</form>
<?php fieldora_layout_end();
