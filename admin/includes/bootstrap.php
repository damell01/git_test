<?php
/**
 * Bootstrap – load all core dependencies and initialise the session.
 * Include this file at the top of every entry-point script.
 *
 * Trash Panda Roll-Offs
 */

// ── Security Headers ─────────────────────────────────────────────────────────
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://js.stripe.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com 'unsafe-inline'; style-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com 'unsafe-inline'; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: https://chart.googleapis.com; frame-src https://js.stripe.com; connect-src 'self' https://api.stripe.com;");

// ── 1. Configuration ────────────────────────────────────────────────────────
require_once __DIR__ . '/../config/config.php';

// ── 2. Core includes (order matters: db first, then auth which calls db helpers,
//        then helpers which may call db helpers too) ──────────────────────────
require_once INC_PATH . '/db.php';
require_once INC_PATH . '/auth.php';
require_once INC_PATH . '/helpers.php';

// ── 3. Start / resume the session ───────────────────────────────────────────
session_init();

// ── 4. Force password-change flow ───────────────────────────────────────────
// If the logged-in user has must_change_pw set, redirect them to the change-
// password page unless they are already on it (avoids an infinite loop).
if (!empty($_SESSION['user_id'])) {
    $current_script = $_SERVER['SCRIPT_FILENAME'] ?? '';
    $change_pw_file = ROOT_PATH . '/modules/settings/change_password.php';

    if (realpath($current_script) !== realpath($change_pw_file)) {
        $user = current_user();
        if ($user && !empty($user['must_change_pw'])) {
            redirect(APP_URL . '/modules/settings/change_password.php?force=1');
        }
    }
}
