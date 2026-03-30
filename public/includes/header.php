<?php
require_once __DIR__ . '/config.php';
$current = basename($_SERVER['PHP_SELF']);

function nav_class(string $page, string $current): string {
    return $current === $page ? 'nav-link active' : 'nav-link';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' | ' : ''; ?><?php echo SITE_NAME; ?></title>
    <meta name="description" content="<?php echo isset($meta_desc) ? htmlspecialchars($meta_desc) : 'Fast &amp; affordable dumpster rentals for residential and commercial projects. Same-day delivery available. Call ' . SITE_PHONE . '.'; ?>">

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@400;600;700;800&family=Barlow:wght@400;500;600;700&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

    <!-- Custom CSS -->
    <link href="/public/assets/css/style.css" rel="stylesheet">
</head>
<body>

<!-- Top bar -->
<div class="top-bar d-none d-md-block">
    <div class="container-fluid px-4">
        <div class="d-flex justify-content-between align-items-center">
            <div class="top-bar-left">
                <span><i class="bi bi-telephone-fill me-1"></i> <?php echo SITE_PHONE; ?></span>
                <span class="ms-3"><i class="bi bi-envelope-fill me-1"></i> <?php echo SITE_EMAIL; ?></span>
            </div>
            <div class="top-bar-right">
                <a href="/admin/login.php" class="staff-login-link">Staff Login</a>
            </div>
        </div>
    </div>
</div>

<!-- Main Navbar -->
<nav class="navbar navbar-expand-lg navbar-dark sticky-top" id="mainNav">
    <div class="container-fluid px-4">

        <!-- Brand -->
        <a class="navbar-brand" href="/public/index.php">
            🗑 <span>Trash Panda Roll-Offs</span>
        </a>

        <!-- Hamburger -->
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navMenu"
                aria-controls="navMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <!-- Nav links -->
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav mx-auto mb-2 mb-lg-0">
                <li class="nav-item">
                    <a class="<?php echo nav_class('index.php', $current); ?>" href="/public/index.php">Home</a>
                </li>
                <li class="nav-item">
                    <a class="<?php echo nav_class('sizes.php', $current); ?>" href="/public/sizes.php">Dumpster Sizes</a>
                </li>
                <li class="nav-item">
                    <a class="<?php echo nav_class('residential.php', $current); ?>" href="/public/residential.php">Residential</a>
                </li>
                <li class="nav-item">
                    <a class="<?php echo nav_class('commercial.php', $current); ?>" href="/public/commercial.php">Commercial</a>
                </li>
                <li class="nav-item">
                    <a class="<?php echo nav_class('service-areas.php', $current); ?>" href="/public/service-areas.php">Service Areas</a>
                </li>
                <li class="nav-item">
                    <a class="<?php echo nav_class('about.php', $current); ?>" href="/public/about.php">About</a>
                </li>
                <li class="nav-item">
                    <a class="<?php echo nav_class('faq.php', $current); ?>" href="/public/faq.php">FAQ</a>
                </li>
                <li class="nav-item">
                    <a class="<?php echo nav_class('contact.php', $current); ?>" href="/public/contact.php">Contact</a>
                </li>
            </ul>

            <!-- CTA -->
            <a href="/public/contact.php" class="btn btn-cta-nav ms-lg-3">
                <i class="bi bi-chat-dots-fill me-1"></i> Get a Quote
            </a>
        </div>

    </div>
</nav>
