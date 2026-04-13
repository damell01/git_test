<?php

require_once __DIR__ . '/modules/fieldora/_bootstrap.php';

use TrashPanda\Fieldora\Services\DashboardService;

$summary = DashboardService::summary(current_tenant_id());
$checklist = DashboardService::gettingStarted(current_tenant_id());
$user = current_user();
$showTour = tenant_setting('tour_' . (int) $user['id'] . '_dashboard', '0') !== '1';

fieldora_layout_start('Dashboard', 'dashboard');
?>
<div class="grid three">
    <section class="card"><div class="metric"><?= (int) $summary['bookings'] ?></div><div class="muted">Bookings</div></section>
    <section class="card"><div class="metric"><?= (int) $summary['jobs'] ?></div><div class="muted">Jobs</div></section>
    <section class="card"><div class="metric">$<?= number_format((float) $summary['payments_completed'], 2) ?></div><div class="muted">Completed payments</div></section>
</div>

<div class="grid two" style="margin-top:20px;">
    <section class="card">
        <h3>Getting Started</h3>
        <p class="muted">Use this checklist to move from signup to a fully testable workspace.</p>
        <div class="stack">
            <?php foreach ($checklist as $item): ?>
                <a class="service-option" href="<?= e($item['href']) ?>"<?= str_starts_with($item['href'], 'http') ? ' target="_blank" rel="noopener"' : '' ?>>
                    <span><strong><?= $item['done'] ? '[x]' : '[ ]' ?> <?= e($item['label']) ?></strong><small><?= $item['done'] ? 'Completed' : 'Needs attention' ?></small></span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <section class="table-wrap">
        <table><thead><tr><th>Recent bookings</th><th>Status</th><th>Total</th></tr></thead><tbody>
        <?php if ($summary['recent_bookings'] === []): ?><tr><td colspan="3">No bookings yet. Share your booking page to start collecting requests.</td></tr><?php else: ?><?php foreach ($summary['recent_bookings'] as $row): ?><tr><td><?= e($row['booking_number']) ?></td><td><?= e($row['status']) ?></td><td>$<?= number_format((float) $row['total_amount'], 2) ?></td></tr><?php endforeach; ?><?php endif; ?>
        </tbody></table>
    </section>
</div>

<div class="grid two" style="margin-top:20px;">
    <section class="table-wrap">
        <table><thead><tr><th>Recent jobs</th><th>Status</th><th>Date</th></tr></thead><tbody>
        <?php if ($summary['recent_jobs'] === []): ?><tr><td colspan="3">No jobs yet. Jobs are created from bookings and appear here once work is scheduled.</td></tr><?php else: ?><?php foreach ($summary['recent_jobs'] as $row): ?><tr><td><?= e($row['job_number']) ?> - <?= e($row['title']) ?></td><td><?= e($row['status']) ?></td><td><?= e($row['scheduled_date']) ?></td></tr><?php endforeach; ?><?php endif; ?>
        </tbody></table>
    </section>
    <section class="card">
        <h3>Need help?</h3>
        <p class="muted">Use onboarding for setup, Help for role-specific guidance, and Share to test your public booking page.</p>
        <div class="topbar-actions">
            <a class="ghost-link" href="<?= e(APP_URL) ?>/modules/fieldora/help.php">Open help</a>
            <a class="ghost-link" href="<?= e(APP_URL) ?>/modules/fieldora/onboarding.php">Open onboarding</a>
        </div>
    </section>
</div>

<?php if ($showTour): ?>
    <section class="card" style="margin-top:20px;">
        <h3>Dashboard tour</h3>
        <p class="muted">Start with the Getting Started checklist, then review recent bookings and jobs to monitor activity as you set up the workspace.</p>
        <div class="topbar-actions">
            <a class="primary-btn" href="<?= e(APP_URL) ?>/modules/fieldora/tour.php?tour=dashboard&done=1">Got it</a>
            <a class="ghost-link" href="<?= e(APP_URL) ?>/modules/fieldora/tour.php?tour=dashboard&done=1">Skip</a>
        </div>
    </section>
<?php endif; ?>
<?php fieldora_layout_end();
