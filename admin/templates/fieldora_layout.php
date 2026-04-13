<?php

function fieldora_layout_start(string $title, string $active = 'dashboard'): void
{
    $tenant = current_tenant();
    $tenantName = $tenant['name'] ?? APP_NAME;
    $theme = tenant_theme_mode();
    $nav = [
        'dashboard' => ['label' => 'Dashboard', 'href' => APP_URL . '/dashboard.php'],
        'onboarding' => ['label' => 'Onboarding', 'href' => APP_URL . '/modules/fieldora/onboarding.php'],
        'services' => ['label' => 'Services', 'href' => APP_URL . '/modules/fieldora/services.php', 'permission' => 'bookings.manage'],
        'bookings' => ['label' => 'Bookings', 'href' => APP_URL . '/modules/fieldora/bookings.php', 'permission' => 'bookings.view'],
        'jobs' => ['label' => 'Jobs', 'href' => APP_URL . '/modules/fieldora/jobs.php', 'permission' => 'jobs.view'],
        'customers' => ['label' => 'Customers', 'href' => APP_URL . '/modules/fieldora/customers.php', 'permission' => 'customers.view'],
        'invoices' => ['label' => 'Invoices', 'href' => APP_URL . '/modules/fieldora/invoices.php', 'permission' => 'invoices.view'],
        'payments' => ['label' => 'Payments', 'href' => APP_URL . '/modules/fieldora/payments.php', 'permission' => 'payments.view'],
        'notifications' => ['label' => 'Notifications', 'href' => APP_URL . '/modules/fieldora/notifications.php', 'permission' => 'bookings.view'],
        'analytics' => ['label' => 'Analytics', 'href' => APP_URL . '/modules/fieldora/analytics.php', 'permission' => 'analytics.view'],
        'team' => ['label' => 'Team', 'href' => APP_URL . '/modules/fieldora/team.php', 'permission' => 'team.manage', 'feature' => 'team'],
        'roles' => ['label' => 'Roles', 'href' => APP_URL . '/modules/fieldora/roles.php', 'permission' => 'team.manage', 'feature' => 'permissions_advanced'],
        'billing' => ['label' => 'Billing', 'href' => APP_URL . '/modules/fieldora/billing.php', 'permission' => 'billing.manage'],
        'branding' => ['label' => 'Branding', 'href' => APP_URL . '/modules/fieldora/branding.php', 'permission' => 'branding.manage'],
        'webhooks' => ['label' => 'Webhooks', 'href' => APP_URL . '/modules/fieldora/webhooks.php', 'permission' => 'webhooks.manage', 'feature' => 'webhooks'],
        'automations' => ['label' => 'Automations', 'href' => APP_URL . '/modules/fieldora/automations.php', 'permission' => 'automations.manage', 'feature' => 'automations'],
        'share' => ['label' => 'Share', 'href' => APP_URL . '/modules/fieldora/share.php'],
        'exports' => ['label' => 'Exports', 'href' => APP_URL . '/modules/fieldora/exports.php', 'permission' => 'exports.view', 'feature' => 'exports'],
        'routes' => ['label' => 'Routes', 'href' => APP_URL . '/modules/fieldora/routes.php', 'feature' => 'route_tools'],
        'settings' => ['label' => 'Settings', 'href' => APP_URL . '/modules/fieldora/settings.php', 'permission' => 'settings.manage'],
        'help' => ['label' => 'Help', 'href' => APP_URL . '/modules/fieldora/help.php'],
    ];
    ?>
    <!DOCTYPE html>
    <html lang="en" data-theme="<?= e($theme) ?>">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= e($title) ?> | <?= e($tenantName) ?></title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="<?= e(ASSET_PATH) ?>/css/fieldora.css">
    </head>
    <body>
    <div class="admin-shell">
        <aside class="admin-sidebar">
            <a class="admin-brand" href="<?= e(APP_URL) ?>/dashboard.php">Fieldora</a>
            <div class="admin-tenant"><?= e($tenantName) ?></div>
            <nav>
                <?php foreach ($nav as $key => $item): ?>
                    <?php
                    if (isset($item['permission']) && !user_can($item['permission'])) {
                        continue;
                    }
                    if (isset($item['feature']) && !tenant_has_feature($item['feature'])) {
                        continue;
                    }
                    ?>
                    <a class="<?= $active === $key ? 'active' : '' ?>" href="<?= e($item['href']) ?>"><?= e($item['label']) ?></a>
                <?php endforeach; ?>
            </nav>
        </aside>
        <main class="admin-main">
            <header class="admin-topbar">
                <div>
                    <h1><?= e($title) ?></h1>
                    <p><?= e($tenantName) ?> - <?= e(current_plan_code()) ?> plan</p>
                </div>
                <div class="topbar-actions">
                    <a class="theme-toggle" href="<?= e(APP_URL) ?>/modules/fieldora/theme.php?mode=<?= $theme === 'dark' ? 'light' : 'dark' ?>">Switch to <?= $theme === 'dark' ? 'light' : 'dark' ?> mode</a>
                    <a class="ghost-link" href="<?= e(tenant_booking_url()) ?>" target="_blank" rel="noopener">View booking page</a>
                    <a class="ghost-link" href="<?= e(APP_URL) ?>/logout.php">Logout</a>
                </div>
            </header>
            <?php render_flash(); ?>
    <?php
}

function fieldora_layout_end(): void
{
    ?>
        </main>
    </div>
    </body>
    </html>
    <?php
}
