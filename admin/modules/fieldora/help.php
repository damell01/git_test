<?php
require_once __DIR__ . '/_bootstrap.php';

$search = strtolower(trim((string) ($_GET['search'] ?? '')));
$roleRow = db_fetch(
    'SELECT LOWER(r.code) AS role_code
     FROM user_roles ur
     INNER JOIN roles r ON r.id = ur.role_id
     WHERE ur.user_id = ?
     ORDER BY r.id ASC
     LIMIT 1',
    [(int) (current_user()['id'] ?? 0)]
);
$role = strtolower((string) ($roleRow['role_code'] ?? (current_user()['role'] ?? 'owner')));

$articles = [
    ['category' => 'Getting Started', 'title' => 'Launch your workspace', 'content' => 'Complete onboarding, add your first service, connect Stripe, test your booking page, and send your first invoice.', 'roles' => ['owner', 'manager', 'staff', 'accounting']],
    ['category' => 'Booking Setup', 'title' => 'Set your booking rules', 'content' => 'Use Settings to configure working hours, blackout dates, minimum notice, approval mode, and deposit defaults.', 'roles' => ['owner', 'manager']],
    ['category' => 'Payments & Stripe', 'title' => 'Connect Stripe Express', 'content' => 'Open Billing, connect the tenant Stripe account, finish onboarding, and refresh until the account is connected.', 'roles' => ['owner']],
    ['category' => 'Invoicing', 'title' => 'Send invoices with payment links', 'content' => 'Create invoices, send them from the invoice detail page, and let customers pay through the generated Stripe payment link.', 'roles' => ['owner', 'manager', 'accounting']],
    ['category' => 'Team & Permissions', 'title' => 'Invite team members', 'content' => 'Use Team and Roles to add staff, assign roles, and control access to bookings, jobs, invoices, payments, and settings.', 'roles' => ['owner', 'manager']],
    ['category' => 'Webhooks & Automation', 'title' => 'Connect Zapier, Make, or n8n', 'content' => 'Create a webhook endpoint, choose events, send a test event, and review delivery logs to verify your integration.', 'roles' => ['owner', 'manager']],
    ['category' => 'Jobs', 'title' => 'Work from My Jobs', 'content' => 'Staff users see their assigned work in Jobs and can keep the day moving from one place.', 'roles' => ['staff']],
];

$filtered = array_values(array_filter($articles, static function (array $article) use ($search, $role): bool {
    if (!in_array($role, $article['roles'], true) && !in_array('all', $article['roles'], true)) {
        return false;
    }

    if ($search === '') {
        return true;
    }

    $haystack = strtolower($article['category'] . ' ' . $article['title'] . ' ' . $article['content']);
    return str_contains($haystack, $search);
}));

fieldora_layout_start('Help', 'help');
?>
<form method="get" class="card form-grid">
    <label><span>Search help</span><input name="search" value="<?= e($search) ?>" placeholder="Search help articles"></label>
    <button class="primary-btn" type="submit">Search</button>
</form>

<section class="card" style="margin-top:20px;">
    <h3>Need a quick walkthrough?</h3>
    <p class="muted">Use the onboarding flow, dashboard checklist, and page tips to set up Fieldora without leaving the app.</p>
    <div class="topbar-actions">
        <a class="ghost-link" href="<?= e(APP_URL) ?>/modules/fieldora/onboarding.php">Open onboarding</a>
        <a class="ghost-link" href="<?= e(APP_URL) ?>/modules/fieldora/webhook_docs.php">Webhook docs</a>
    </div>
</section>

<section class="card" style="margin-top:20px;">
    <h3>Watch how it works</h3>
    <p class="muted">Add a short demo video later. For now, onboarding plus the Getting Started checklist will walk a new user through the live setup.</p>
    <div class="service-option"><span><strong>Demo placeholder</strong><small>Drop in a Loom, YouTube, or hosted walkthrough when you are ready.</small></span></div>
</section>

<div class="grid two" style="margin-top:20px;">
    <?php if ($filtered === []): ?>
        <section class="card">
            <h3>No help articles matched</h3>
            <p class="muted">Try a broader keyword like booking, invoice, Stripe, jobs, or webhook.</p>
        </section>
    <?php else: ?>
        <?php foreach ($filtered as $article): ?>
            <section class="card">
                <p class="muted"><?= e($article['category']) ?></p>
                <h3><?= e($article['title']) ?></h3>
                <p><?= e($article['content']) ?></p>
            </section>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php fieldora_layout_end();
