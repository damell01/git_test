<?php
/**
 * Bootstrap – load all core dependencies and initialise the session.
 * Include this file at the top of every entry-point script.
 *
 * Trash Panda Roll-Offs
 */

// ── Temporary Debug Mode (?debug=1) ─────────────────────────────────────────
// Use this on a failing admin URL to surface HTTP 500 root-cause details.
$debugMode = (isset($_GET['debug']) && $_GET['debug'] === '1')
    || (defined('APP_DEBUG') && APP_DEBUG === true);

if ($debugMode) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);

    set_exception_handler(function (Throwable $e): void {
        if (!headers_sent()) {
            http_response_code(500);
        }
        echo '<pre style="white-space:pre-wrap;background:#111;color:#f5f5f5;padding:16px;border:1px solid #444;">';
        echo 'UNCAUGHT EXCEPTION\n';
        echo get_class($e) . ': ' . $e->getMessage() . "\n\n";
        echo $e->getFile() . ':' . $e->getLine() . "\n\n";
        echo $e->getTraceAsString();
        echo '</pre>';
    });

    register_shutdown_function(function (): void {
        $err = error_get_last();
        if (!$err) {
            return;
        }

        $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];
        if (!in_array($err['type'], $fatalTypes, true)) {
            return;
        }

        if (!headers_sent()) {
            http_response_code(500);
        }

        echo '<pre style="white-space:pre-wrap;background:#111;color:#f5f5f5;padding:16px;border:1px solid #444;">';
        echo 'FATAL ERROR\n';
        echo ($err['message'] ?? 'Unknown error') . "\n\n";
        echo ($err['file'] ?? 'unknown file') . ':' . ($err['line'] ?? 0);
        echo '</pre>';
    });
}

// ── Security Headers ─────────────────────────────────────────────────────────
header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com 'unsafe-inline'; style-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com 'unsafe-inline'; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data:; connect-src 'self';");

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
