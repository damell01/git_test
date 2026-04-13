<?php
require_once __DIR__ . '/_bootstrap.php';

use TrashPanda\Fieldora\Services\ConnectService;
use TrashPanda\Fieldora\Services\PaymentService;
use TrashPanda\Fieldora\Services\TenantService;

require_permission('billing.manage');

$tenantId = current_tenant_id();
$status = ConnectService::status($tenantId);

if (isset($_GET['action'])) {
    try {
        if ($_GET['action'] === 'connect') {
            redirect(ConnectService::onboardingLink($tenantId));
        }
        if ($_GET['action'] === 'refresh' && $status && !empty($status['stripe_account_id'])) {
            $status = ConnectService::syncAccount($tenantId, (string) $status['stripe_account_id']);
            flash_success('Stripe account refreshed.');
            redirect(APP_URL . '/modules/fieldora/billing.php');
        }
        if ($_GET['action'] === 'dashboard') {
            $url = ConnectService::loginLink($tenantId);
            if ($url) {
                redirect($url);
            }
            flash_error('Stripe dashboard link is not available yet.');
            redirect(APP_URL . '/modules/fieldora/billing.php');
        }
        if ($_GET['action'] === 'disconnect') {
            ConnectService::disconnect($tenantId);
            flash_success('Stripe connection marked disconnected.');
            redirect(APP_URL . '/modules/fieldora/billing.php');
        }
    } catch (Throwable $e) {
        flash_error($e->getMessage());
        redirect(APP_URL . '/modules/fieldora/billing.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    foreach (['allow_full_payment', 'require_deposit', 'default_deposit_mode', 'default_deposit_value', 'invoice_payments_enabled', 'application_fee_percent'] as $key) {
        TenantService::saveSetting($tenantId, $key, trim((string) ($_POST[$key] ?? '')));
    }
    flash_success('Billing settings saved.');
    redirect($_SERVER['REQUEST_URI']);
}

$config = PaymentService::paymentConfig($tenantId);
fieldora_layout_start('Billing & Stripe', 'billing');
?>
<div class="grid two">
    <section class="card stack">
        <div>
            <h3>Stripe Connect</h3>
            <p class="muted">Each tenant connects their own Stripe account so booking and invoice payments settle to the business that earned them.</p>
        </div>
        <?php if ($status): ?>
            <p class="muted">Account: <?= e($status['stripe_account_id']) ?></p>
            <p><span class="tag"><?= e($status['account_status']) ?></span></p>
            <p class="muted">Charges enabled: <?= (int) $status['charges_enabled'] ?> - Payouts enabled: <?= (int) $status['payouts_enabled'] ?> - Details submitted: <?= (int) $status['details_submitted'] ?></p>
            <div class="topbar-actions">
                <a class="primary-btn" href="?action=connect">Reconnect onboarding</a>
                <a class="ghost-link" href="?action=refresh">Refresh status</a>
                <a class="ghost-link" href="?action=dashboard">Open Stripe dashboard</a>
                <a class="ghost-link" href="?action=disconnect">Disconnect</a>
            </div>
        <?php else: ?>
            <p class="muted">No Stripe account connected yet.</p>
            <a class="primary-btn" href="?action=connect">Connect Stripe</a>
        <?php endif; ?>
    </section>
    <section class="card stack">
        <div>
            <h3>Connect webhook</h3>
            <p class="muted">Configure this endpoint in your Stripe platform dashboard for account and payment lifecycle events.</p>
        </div>
        <p><code><?= e(SITE_URL) ?>/api/stripe-connect-webhook.php</code></p>
        <p class="muted">Use the platform webhook secret in <code>STRIPE_CONNECT_WEBHOOK_SECRET</code>.</p>
    </section>
</div>

<form method="post" class="stack" style="margin-top:20px;">
    <?= csrf_field() ?>
    <section class="card stack">
        <div>
            <h3>Billing rules</h3>
            <p class="muted">Set deposit defaults, invoice payment availability, and any platform fee retained before transfer.</p>
        </div>
        <div class="form-grid">
            <label>
                <span>Allow full payment</span>
                <select name="allow_full_payment">
                    <option value="1"<?= $config['allow_full_payment'] ? ' selected' : '' ?>>Yes</option>
                    <option value="0"<?= !$config['allow_full_payment'] ? ' selected' : '' ?>>No</option>
                </select>
            </label>
            <label>
                <span>Require deposit</span>
                <select name="require_deposit">
                    <option value="1"<?= $config['require_deposit'] ? ' selected' : '' ?>>Yes</option>
                    <option value="0"<?= !$config['require_deposit'] ? ' selected' : '' ?>>No</option>
                </select>
            </label>
            <label>
                <span>Default deposit mode</span>
                <select name="default_deposit_mode">
                    <option value="percent"<?= $config['default_deposit_mode'] === 'percent' ? ' selected' : '' ?>>Percent</option>
                    <option value="fixed"<?= $config['default_deposit_mode'] === 'fixed' ? ' selected' : '' ?>>Fixed</option>
                    <option value="none"<?= $config['default_deposit_mode'] === 'none' ? ' selected' : '' ?>>None</option>
                </select>
            </label>
            <label>
                <span>Default deposit value</span>
                <input name="default_deposit_value" type="number" step="0.01" value="<?= e((string) $config['default_deposit_value']) ?>" placeholder="25">
            </label>
            <label>
                <span>Invoice payments enabled</span>
                <select name="invoice_payments_enabled">
                    <option value="1"<?= $config['invoice_payments_enabled'] ? ' selected' : '' ?>>Yes</option>
                    <option value="0"<?= !$config['invoice_payments_enabled'] ? ' selected' : '' ?>>No</option>
                </select>
            </label>
            <label>
                <span>Platform fee percent</span>
                <input name="application_fee_percent" type="number" step="0.01" value="<?= e((string) $config['application_fee_percent']) ?>" placeholder="0">
            </label>
        </div>
    </section>
    <button class="primary-btn" type="submit">Save billing settings</button>
</form>
<?php fieldora_layout_end();
