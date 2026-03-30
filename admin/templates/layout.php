<?php
/**
 * Layout Template — Trash Panda Roll-Offs Admin
 *
 * Provides layout_start($page_title, $active_nav) and layout_end().
 */

/**
 * Render the top of the page: <html>, <head>, sidebar, topbar, and opens .tp-content-inner.
 *
 * @param string $page_title  The page-specific title shown in <title> and the topbar.
 * @param string $active_nav  Key string matching one of the sidebar nav items (e.g. 'dashboard').
 */
function layout_start(string $page_title, string $active_nav = ''): void
{
    // Sidebar nav item definitions
    // [key, label, icon-class, href, role-gate (null = everyone, array = has_role check)]
    $nav_items = [
        [
            'key'   => 'dashboard',
            'label' => 'Dashboard',
            'icon'  => 'fa-gauge',
            'href'  => APP_URL . '/dashboard.php',
            'roles' => null,
            'badge' => null,
        ],
        [
            'key'   => 'leads',
            'label' => 'Leads',
            'icon'  => 'fa-funnel',
            'href'  => APP_URL . '/modules/leads/index.php',
            'roles' => null,
            'badge' => 'leads',
        ],
        [
            'key'   => 'customers',
            'label' => 'Customers',
            'icon'  => 'fa-users',
            'href'  => APP_URL . '/modules/customers/index.php',
            'roles' => null,
            'badge' => null,
        ],
        [
            'key'   => 'quotes',
            'label' => 'Quotes',
            'icon'  => 'fa-file-invoice-dollar',
            'href'  => APP_URL . '/modules/quotes/index.php',
            'roles' => null,
            'badge' => null,
        ],
        [
            'key'   => 'work_orders',
            'label' => 'Work Orders',
            'icon'  => 'fa-clipboard-list',
            'href'  => APP_URL . '/modules/work_orders/index.php',
            'roles' => null,
            'badge' => 'work_orders',
        ],
        [
            'key'   => 'scheduling',
            'label' => 'Scheduling',
            'icon'  => 'fa-calendar-days',
            'href'  => APP_URL . '/modules/scheduling/index.php',
            'roles' => null,
            'badge' => null,
        ],
        [
            'key'   => 'inventory',
            'label' => 'Inventory',
            'icon'  => 'fa-dumpster',
            'href'  => APP_URL . '/modules/dumpsters/index.php',
            'roles' => null,
            'badge' => null,
        ],
        [
            'key'   => 'reports',
            'label' => 'Reports',
            'icon'  => 'fa-chart-bar',
            'href'  => APP_URL . '/modules/reports/index.php',
            'roles' => null,
            'badge' => null,
        ],
        [
            'key'   => 'payments',
            'label' => 'Payments',
            'icon'  => 'fa-credit-card',
            'href'  => APP_URL . '/modules/payments/index.php',
            'roles' => null,
            'badge' => null,
        ],
        [
            'key'   => 'notifications',
            'label' => 'Notifications',
            'icon'  => 'fa-bell',
            'href'  => APP_URL . '/modules/notifications/index.php',
            'roles' => ['admin', 'office'],
            'badge' => null,
        ],
        [
            'key'   => 'settings',
            'label' => 'Settings',
            'icon'  => 'fa-gear',
            'href'  => APP_URL . '/modules/settings/index.php',
            'roles' => ['admin', 'office'],
            'badge' => null,
        ],
        [
            'key'   => 'users',
            'label' => 'Users',
            'icon'  => 'fa-user-group',
            'href'  => APP_URL . '/modules/settings/users.php',
            'roles' => ['admin'],
            'badge' => null,
        ],
    ];

    // Resolve badge counts lazily so a missing DB connection does not fatal-error the layout.
    $get_badge = function (string $type): int {
        if (!function_exists('db_query')) {
            return 0;
        }
        try {
            if ($type === 'leads') {
                $row = db_fetch(
                    "SELECT COUNT(*) AS cnt FROM leads WHERE status IN ('new','contacted')"
                );
                return (int) ($row['cnt'] ?? 0);
            }
            if ($type === 'work_orders') {
                $row = db_fetch(
                    "SELECT COUNT(*) AS cnt FROM work_orders WHERE status IN ('scheduled','pickup_requested')"
                );
                return (int) ($row['cnt'] ?? 0);
            }
        } catch (\Throwable $e) {
            // Swallow DB errors — badge is cosmetic.
        }
        return 0;
    };

    // Current user info
    $current_user_name = '';
    $current_user_role = '';
    if (function_exists('current_user')) {
        $u = current_user();
        $current_user_name = htmlspecialchars($u['name'] ?? $u['username'] ?? 'User', ENT_QUOTES, 'UTF-8');
        $current_user_role = htmlspecialchars(ucfirst($u['role'] ?? ''), ENT_QUOTES, 'UTF-8');
    }

    $escaped_title = htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8');
    $app_name      = defined('APP_NAME') ? htmlspecialchars(APP_NAME, ENT_QUOTES, 'UTF-8') : 'Trash Panda Roll-Offs';
    $asset_path    = defined('ASSET_PATH') ? ASSET_PATH : '';
    $app_url       = defined('APP_URL')    ? APP_URL    : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $escaped_title ?> | <?= $app_name ?></title>

    <!-- Bootstrap 5.3 -->
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
          integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN"
          crossorigin="anonymous">

    <!-- Font Awesome 6 -->
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css"
          integrity="sha512-z3gLpd7yknf1YoNbCzqRKc4qyor8gaKU1qmn+CShxbuBusANI9QpRohGBreCFkKxLhei6S9CQXFEbbKuqLzsA=="
          crossorigin="anonymous"
          referrerpolicy="no-referrer">

    <!-- Google Fonts: Barlow Condensed, Barlow, Black Han Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;500;600;700&family=Barlow:wght@400;500&family=Black+Han+Sans:wght@400&display=swap">

    <!-- App styles -->
    <link rel="stylesheet" href="<?= htmlspecialchars($asset_path, ENT_QUOTES, 'UTF-8') ?>/css/app.css">
</head>
<body>

<!-- =====================================================================
     SIDEBAR
     ===================================================================== -->
<div class="tp-sidebar" id="tpSidebar">

    <!-- Brand -->
    <div class="sb-brand">
        <?php if (!empty($asset_path)): ?>
        <img src="<?= htmlspecialchars($asset_path, ENT_QUOTES, 'UTF-8') ?>/img/logo.png"
             alt="<?= $app_name ?>"
             onerror="this.style.display='none';document.getElementById('sb-brand-fallback').style.display='block'">
        <span class="sb-brand-text" id="sb-brand-fallback" style="display:none;">
            Trash Panda Roll-Offs
        </span>
        <?php else: ?>
        <span class="sb-brand-text">Trash Panda Roll-Offs</span>
        <?php endif; ?>
    </div>

    <!-- Navigation -->
    <nav class="sb-nav">
        <ul class="list-unstyled mb-0">
        <?php foreach ($nav_items as $item):
            // Role-gate: skip if user does not meet requirements
            if (!empty($item['roles'])) {
                if (!function_exists('has_role') || !has_role(...$item['roles'])) {
                    continue;
                }
            }

            $is_active  = ($active_nav === $item['key']);
            $item_class = 'sb-item' . ($is_active ? ' active' : '');
            $badge_count = 0;
            if (!empty($item['badge'])) {
                $badge_count = $get_badge($item['badge']);
            }
        ?>
            <li>
                <a href="<?= htmlspecialchars($item['href'], ENT_QUOTES, 'UTF-8') ?>"
                   class="<?= $item_class ?>">
                    <span class="sb-icon">
                        <i class="fa-solid <?= htmlspecialchars($item['icon'], ENT_QUOTES, 'UTF-8') ?>"></i>
                    </span>
                    <span class="sb-label"><?= htmlspecialchars($item['label'], ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if ($badge_count > 0): ?>
                    <span class="sb-count ms-auto"><?= $badge_count ?></span>
                    <?php endif; ?>
                </a>
            </li>
        <?php endforeach; ?>
        </ul>
    </nav>

    <!-- Footer / user info -->
    <div class="sb-footer">
        <?php if ($current_user_name): ?>
        <div class="sb-user d-flex align-items-center gap-2 mb-2">
            <div class="flex-grow-1 overflow-hidden">
                <div class="text-truncate" style="color:var(--wh);font-size:.85rem;font-weight:500;">
                    <?= $current_user_name ?>
                </div>
                <?php if ($current_user_role): ?>
                <div style="font-size:.7rem;color:var(--gy);"><?= $current_user_role ?></div>
                <?php endif; ?>
            </div>
            <?php if ($current_user_role): ?>
            <span class="tp-badge badge-scheduled" style="font-size:.65rem;">
                <?= $current_user_role ?>
            </span>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <a href="<?= htmlspecialchars($app_url, ENT_QUOTES, 'UTF-8') ?>/logout.php"
           class="btn-tp-ghost btn-tp-sm w-100 justify-content-center">
            <i class="fa-solid fa-arrow-right-from-bracket"></i>
            Logout
        </a>
    </div>

</div><!-- /.tp-sidebar -->


<!-- =====================================================================
     MAIN WRAPPER
     ===================================================================== -->
<div class="tp-main">

    <!-- Top bar -->
    <div class="tp-topbar">
        <!-- Hamburger (mobile) -->
        <button class="hamburger-btn btn-tp-ghost btn-tp-sm me-1" id="hamburgerBtn"
                aria-label="Toggle navigation" aria-expanded="false" aria-controls="tpSidebar">
            <i class="fa-solid fa-bars"></i>
        </button>

        <!-- Page title -->
        <h4 class="mb-0 me-auto" style="font-family:'Barlow Condensed',sans-serif;font-size:1.1rem;font-weight:600;color:var(--wh);letter-spacing:.04em;">
            <?= $escaped_title ?>
        </h4>

        <!-- Right-side quick actions -->
        <div class="d-flex gap-2 align-items-center">
            <a href="<?= htmlspecialchars($app_url, ENT_QUOTES, 'UTF-8') ?>/modules/leads/create.php"
               class="btn-tp-ghost btn-tp-sm no-print">
                <i class="fa-solid fa-plus"></i>
                New Lead
            </a>
            <a href="<?= htmlspecialchars($app_url, ENT_QUOTES, 'UTF-8') ?>/modules/work_orders/create.php"
               class="btn-tp-primary btn-tp-sm no-print">
                <i class="fa-solid fa-plus"></i>
                New Work Order
            </a>
        </div>
    </div><!-- /.tp-topbar -->

    <!-- Content area -->
    <div class="tp-content">

        <!-- Flash messages -->
        <?php if (function_exists('render_flash')) { render_flash(); } ?>

        <!-- Page body (opened here, closed by layout_end) -->
        <div class="tp-content-inner">
<?php
} // end layout_start()


/**
 * Close the page: ends .tp-content-inner, .tp-content, .tp-main, loads JS, closes </body></html>.
 */
function layout_end(): void
{
    $asset_path = defined('ASSET_PATH') ? ASSET_PATH : '';
?>
        </div><!-- /.tp-content-inner -->
    </div><!-- /.tp-content -->
</div><!-- /.tp-main -->

<!-- Bootstrap 5.3 JS bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL"
        crossorigin="anonymous"></script>

<!-- App scripts -->
<script src="<?= htmlspecialchars($asset_path, ENT_QUOTES, 'UTF-8') ?>/js/app.js"></script>

</body>
</html>
<?php
} // end layout_end()
