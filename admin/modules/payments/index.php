<?php
/**
 * Payments – Overview
 * Shows Stripe balance, recent payouts, and charge history – all in-app.
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once TMPL_PATH . '/layout.php';
require_once INC_PATH . '/stripe.php';
require_login();
require_role('admin', 'office');

// ── Local revenue totals from the database ────────────────────────────────────
$month_start = date('Y-m-01');
$today       = date('Y-m-d');

// Bookings revenue (paid via any method)
$booking_rev = db_fetch(
    "SELECT COALESCE(SUM(total_amount),0) AS total, COUNT(*) AS cnt
     FROM bookings
     WHERE payment_status IN ('paid','paid_cash','paid_check')
       AND booking_status != 'canceled'
       AND updated_at >= ?",
    [$month_start . ' 00:00:00']
) ?: ['total' => 0, 'cnt' => 0];

// Invoice revenue
$invoice_rev = db_fetch(
    "SELECT COALESCE(SUM(total),0) AS total, COUNT(*) AS cnt
     FROM invoices
     WHERE status = 'paid'
       AND updated_at >= ?",
    [$month_start . ' 00:00:00']
) ?: ['total' => 0, 'cnt' => 0];

// Work order revenue
$wo_rev = db_fetch(
    "SELECT COALESCE(SUM(amount),0) AS total, COUNT(*) AS cnt
     FROM work_orders
     WHERE status = 'completed'
       AND updated_at >= ?",
    [$month_start . ' 00:00:00']
) ?: ['total' => 0, 'cnt' => 0];

$total_local_revenue = (float)$booking_rev['total']
                     + (float)$invoice_rev['total']
                     + (float)$wo_rev['total'];

// Recent paid bookings
$recent_bookings = db_fetchall(
    "SELECT b.id, b.booking_number, b.customer_name, b.total_amount,
            b.payment_method, b.payment_status, b.stripe_payment_id,
            b.stripe_session_id, b.updated_at
     FROM bookings b
     WHERE b.payment_status IN ('paid','paid_cash','paid_check','refunded')
       AND b.booking_status != 'canceled'
     ORDER BY b.updated_at DESC
     LIMIT 25"
) ?: [];

// ── Stripe live data ──────────────────────────────────────────────────────────
$stripe_balance    = null;
$stripe_payouts    = [];
$stripe_charges    = [];
$stripe_error      = null;
$stripe_configured = trim(get_setting('stripe_secret_key', '')) !== '';

if ($stripe_configured) {
    try {
        $stripe_balance = stripe_get_balance();

        $payout_list  = stripe_list_payouts(20);
        $stripe_payouts = $payout_list->data ?? [];

        $charge_list  = stripe_list_charges(50, strtotime($month_start));
        $stripe_charges = $charge_list->data ?? [];

    } catch (\Throwable $e) {
        $stripe_error = $e->getMessage();
    }
}

// Compute Stripe month revenue from charges
$stripe_month_revenue = 0.0;
$stripe_failed_count  = 0;
foreach ($stripe_charges as $ch) {
    if ($ch->status === 'succeeded' && !$ch->refunded) {
        $stripe_month_revenue += $ch->amount / 100;
    } elseif ($ch->status === 'failed') {
        $stripe_failed_count++;
    }
}

$currency_symbol = '$';

layout_start('Payments', 'payments');
?>

<style>
.kpi-row { display:flex; flex-wrap:wrap; gap:12px; margin-bottom:1.5rem; }
.kpi-card {
    background:#fff; border:1px solid var(--st); border-radius:8px;
    padding:16px 20px; flex:1; min-width:160px;
}
.kpi-card .kpi-label { font-size:.75rem; text-transform:uppercase; letter-spacing:.04em; color:#888; margin-bottom:4px; }
.kpi-card .kpi-value { font-size:1.6rem; font-weight:700; color:#222; line-height:1; }
.kpi-card .kpi-sub   { font-size:.75rem; color:#aaa; margin-top:4px; }
.charge-status-succeeded { color:#28a745; }
.charge-status-failed    { color:#dc3545; }
.charge-status-refunded  { color:#fd7e14; }
.charge-status-pending   { color:#6c757d; }
</style>

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h5 class="mb-0">Payments &amp; Revenue</h5>
        <small class="text-muted">Month to date: <?= e(date('F Y')) ?></small>
    </div>
    <?php if ($stripe_configured): ?>
    <a href="<?= e('https://dashboard.stripe.com' . (stripe_is_test_mode() ? '/test' : '') . '/payouts') ?>"
       target="_blank" rel="noopener noreferrer" class="btn-tp-ghost btn-tp-sm">
        <i class="fa-brands fa-stripe"></i> Open Stripe Dashboard
    </a>
    <?php endif; ?>
</div>

<?php render_flash(); ?>

<?php if ($stripe_error): ?>
<div class="alert alert-warning" style="font-size:.88rem;">
    <i class="fa-solid fa-triangle-exclamation me-1"></i>
    Stripe data unavailable: <?= e($stripe_error) ?>
    — Showing local database totals only.
</div>
<?php endif; ?>

<!-- ── KPI Row ── -->
<div class="kpi-row mb-4">

    <div class="kpi-card">
        <div class="kpi-label">Total Revenue (Month)</div>
        <div class="kpi-value" style="color:var(--or,#f60);"><?= e(fmt_money($total_local_revenue)) ?></div>
        <div class="kpi-sub">All sources — bookings, invoices, WOs</div>
    </div>

    <div class="kpi-card">
        <div class="kpi-label">Booking Revenue</div>
        <div class="kpi-value"><?= e(fmt_money($booking_rev['total'])) ?></div>
        <div class="kpi-sub"><?= (int)$booking_rev['cnt'] ?> paid booking<?= (int)$booking_rev['cnt'] !== 1 ? 's' : '' ?></div>
    </div>

    <div class="kpi-card">
        <div class="kpi-label">Invoice Revenue</div>
        <div class="kpi-value"><?= e(fmt_money($invoice_rev['total'])) ?></div>
        <div class="kpi-sub"><?= (int)$invoice_rev['cnt'] ?> paid invoice<?= (int)$invoice_rev['cnt'] !== 1 ? 's' : '' ?></div>
    </div>

    <div class="kpi-card">
        <div class="kpi-label">Work Order Revenue</div>
        <div class="kpi-value"><?= e(fmt_money($wo_rev['total'])) ?></div>
        <div class="kpi-sub"><?= (int)$wo_rev['cnt'] ?> completed WO<?= (int)$wo_rev['cnt'] !== 1 ? 's' : '' ?></div>
    </div>

    <?php if ($stripe_configured && !$stripe_error): ?>
    <div class="kpi-card">
        <div class="kpi-label">Stripe Charges (Month)</div>
        <div class="kpi-value"><?= e(fmt_money($stripe_month_revenue)) ?></div>
        <div class="kpi-sub"><?= $stripe_failed_count ?> failed charge<?= $stripe_failed_count !== 1 ? 's' : '' ?></div>
    </div>
    <?php endif; ?>

</div>

<!-- ── Stripe Balance ── -->
<?php if ($stripe_configured && $stripe_balance && !$stripe_error): ?>
<div class="tp-card mb-4">
    <div class="tp-card-header">
        <i class="fa-brands fa-stripe me-2 text-muted"></i> Stripe Account Balance
        <?php if (stripe_is_test_mode()): ?>
        <span class="tp-badge badge-scheduled ms-2" style="font-size:.7rem;">TEST MODE</span>
        <?php endif; ?>
    </div>
    <div class="tp-card-body">
        <div class="row g-3">
            <?php
            // Build a map of currency → available/pending
            $balance_map = [];
            foreach ($stripe_balance->available as $entry) {
                $cur = strtoupper($entry->currency);
                $balance_map[$cur]['available'] = ($balance_map[$cur]['available'] ?? 0) + $entry->amount;
            }
            foreach ($stripe_balance->pending as $entry) {
                $cur = strtoupper($entry->currency);
                $balance_map[$cur]['pending'] = ($balance_map[$cur]['pending'] ?? 0) + $entry->amount;
            }
            foreach ($balance_map as $cur => $bal):
                $avail   = ($bal['available'] ?? 0) / 100;
                $pending = ($bal['pending'] ?? 0) / 100;
                $total_b = $avail + $pending;
            ?>
            <div class="col-6 col-md-3">
                <div class="text-muted" style="font-size:.8rem;">Available (<?= e($cur) ?>)</div>
                <div class="fw-bold" style="font-size:1.2rem;color:#28a745;"><?= e(fmt_money($avail)) ?></div>
                <div class="text-muted" style="font-size:.75rem;">Ready to pay out</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-muted" style="font-size:.8rem;">Pending (<?= e($cur) ?>)</div>
                <div class="fw-bold" style="font-size:1.2rem;color:#fd7e14;"><?= e(fmt_money($pending)) ?></div>
                <div class="text-muted" style="font-size:.75rem;">Processing (2–3 days)</div>
            </div>
            <div class="col-6 col-md-3">
                <div class="text-muted" style="font-size:.8rem;">Total Balance (<?= e($cur) ?>)</div>
                <div class="fw-bold" style="font-size:1.2rem;"><?= e(fmt_money($total_b)) ?></div>
                <div class="text-muted" style="font-size:.75rem;">Available + pending</div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-4">

    <!-- Left: Recent Stripe Charges -->
    <?php if ($stripe_configured && !$stripe_error): ?>
    <div class="col-12 col-xl-7">
        <div class="tp-card h-100">
            <div class="tp-card-header">
                <i class="fa-solid fa-credit-card me-2 text-muted"></i>
                Recent Stripe Charges <small class="text-muted">(this month)</small>
            </div>
            <div class="tp-card-body p-0">
                <?php if (empty($stripe_charges)): ?>
                <p class="text-muted p-3 mb-0">No charges found for this month.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0" style="font-size:.85rem;">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Customer</th>
                                <th>Description</th>
                                <th class="text-end">Amount</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($stripe_charges as $ch):
                            $ch_date   = date('M j, Y g:i A', $ch->created);
                            $ch_name   = $ch->billing_details->name ?? $ch->metadata['customer_name'] ?? '—';
                            $ch_desc   = $ch->description ?? $ch->metadata['booking_number'] ?? '';
                            $ch_amount = $ch->amount / 100;
                            $ch_status = $ch->refunded ? 'refunded' : $ch->status;
                            $ch_url    = stripe_dashboard_url($ch->id);
                        ?>
                        <tr>
                            <td class="text-nowrap"><?= e($ch_date) ?></td>
                            <td><?= e($ch_name) ?></td>
                            <td class="text-muted"><?= e($ch_desc) ?></td>
                            <td class="text-end fw-semibold"><?= e(fmt_money($ch_amount)) ?></td>
                            <td>
                                <span class="charge-status-<?= e($ch_status) ?> fw-semibold">
                                    <?= e(ucfirst($ch_status)) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($ch_url): ?>
                                <a href="<?= e($ch_url) ?>" target="_blank" rel="noopener noreferrer"
                                   class="text-muted" title="View in Stripe" style="font-size:.75rem;">
                                    <i class="fa-brands fa-stripe"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Right: Stripe Payouts -->
    <?php if ($stripe_configured && !$stripe_error): ?>
    <div class="col-12 col-xl-5">
        <div class="tp-card mb-4">
            <div class="tp-card-header">
                <i class="fa-solid fa-building-columns me-2 text-muted"></i> Recent Payouts to Bank
            </div>
            <div class="tp-card-body p-0">
                <?php if (empty($stripe_payouts)): ?>
                <p class="text-muted p-3 mb-0">No payouts found.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0" style="font-size:.85rem;">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Description</th>
                                <th class="text-end">Amount</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($stripe_payouts as $payout):
                            $p_date   = date('M j, Y', $payout->arrival_date ?? $payout->created);
                            $p_desc   = $payout->description ?: 'Automatic payout';
                            $p_amount = $payout->amount / 100;
                            $p_status = $payout->status; // paid, pending, in_transit, canceled, failed
                            $p_colors = [
                                'paid'       => '#28a745',
                                'in_transit' => '#fd7e14',
                                'pending'    => '#6c757d',
                                'canceled'   => '#dc3545',
                                'failed'     => '#dc3545',
                            ];
                            $p_color = $p_colors[$p_status] ?? '#222';
                        ?>
                        <tr>
                            <td class="text-nowrap"><?= e($p_date) ?></td>
                            <td class="text-muted"><?= e($p_desc) ?></td>
                            <td class="text-end fw-semibold"><?= e(fmt_money($p_amount)) ?></td>
                            <td>
                                <span style="color:<?= e($p_color) ?>;font-weight:600;">
                                    <?= e(ucfirst(str_replace('_', ' ', $p_status))) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Full-width if Stripe not configured: show local payments table -->
    <?php if (!$stripe_configured || $stripe_error): ?>
    <div class="col-12">
    <?php else: ?>
    <div class="col-12">
    <?php endif; ?>
        <div class="tp-card">
            <div class="tp-card-header">
                <i class="fa-solid fa-list me-2 text-muted"></i> Recent Payments (App Records)
            </div>
            <div class="tp-card-body p-0">
                <?php if (empty($recent_bookings)): ?>
                <p class="text-muted p-3 mb-0">No payments recorded yet.</p>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm mb-0" style="font-size:.85rem;">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Booking #</th>
                                <th>Customer</th>
                                <th class="text-end">Amount</th>
                                <th>Method</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($recent_bookings as $row):
                            $pay_url = stripe_dashboard_url($row['stripe_payment_id'] ?: $row['stripe_session_id']);
                        ?>
                        <tr>
                            <td class="text-nowrap"><?= e(fmt_date($row['updated_at'])) ?></td>
                            <td>
                                <a href="../bookings/view.php?id=<?= (int)$row['id'] ?>">
                                    <?= e($row['booking_number']) ?>
                                </a>
                            </td>
                            <td><?= e($row['customer_name']) ?></td>
                            <td class="text-end fw-semibold"><?= e(fmt_money($row['total_amount'])) ?></td>
                            <td><?= e(ucfirst($row['payment_method'])) ?></td>
                            <td><?= payment_badge($row['payment_status']) ?></td>
                            <td>
                                <?php if ($pay_url): ?>
                                <a href="<?= e($pay_url) ?>" target="_blank" rel="noopener noreferrer"
                                   class="text-muted" title="View in Stripe" style="font-size:.75rem;">
                                    <i class="fa-brands fa-stripe"></i>
                                </a>
                                <?php endif; ?>
                                <?php if ($row['payment_status'] === 'paid' && $row['payment_method'] === 'stripe'): ?>
                                <a href="../bookings/refund.php?id=<?= (int)$row['id'] ?>"
                                   class="ms-1" title="Issue Refund" style="font-size:.75rem;color:#dc3545;">
                                    <i class="fa-solid fa-rotate-left"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<?php if (!$stripe_configured): ?>
<div class="alert alert-info mt-4" style="font-size:.88rem;">
    <i class="fa-solid fa-circle-info me-1"></i>
    <strong>Stripe not configured.</strong>
    To enable live balance, payout, and charge data, add your
    <a href="../settings/index.php">Stripe API keys in Settings</a>.
</div>
<?php endif; ?>

<?php
layout_end();
