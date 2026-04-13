<?php
/**
 * Fieldora bootstrap.
 */

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
        echo 'UNCAUGHT EXCEPTION' . "\n";
        echo get_class($e) . ': ' . $e->getMessage() . "\n\n";
        echo $e->getFile() . ':' . $e->getLine() . "\n\n";
        echo $e->getTraceAsString();
        echo '</pre>';
    });
} else {
    set_exception_handler(function (Throwable $e): void {
        error_log('[Fieldora] Uncaught ' . get_class($e) . ': ' . $e->getMessage()
            . ' in ' . $e->getFile() . ':' . $e->getLine());
        if (!headers_sent()) {
            http_response_code(500);
        }
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Error</title>'
            . '<style>body{font-family:Arial,sans-serif;background:#0f172a;color:#e2e8f0;display:flex;'
            . 'align-items:center;justify-content:center;min-height:100vh;margin:0}.box{background:#111827;'
            . 'border:1px solid #1f2937;border-radius:16px;padding:2rem 2.5rem;max-width:540px;text-align:center}'
            . 'h2{color:#60a5fa}a{color:#93c5fd}</style></head><body><div class="box">'
            . '<h2>Something went wrong</h2><p>An unexpected error occurred. Please try again.</p>'
            . '<p><a href="javascript:history.back()">Go back</a></p></div></body></html>';
    });
}

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com 'unsafe-inline'; style-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com 'unsafe-inline'; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: https:; connect-src 'self' https://api.stripe.com;");

require_once __DIR__ . '/../config/config.php';

$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}
unset($composerAutoload);

spl_autoload_register(static function (string $class): void {
    $prefix = 'TrashPanda\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $file = ROOT_PATH . '/src/' . str_replace('\\', DIRECTORY_SEPARATOR, $relative) . '.php';
    if (is_file($file)) {
        require_once $file;
    }
});

require_once INC_PATH . '/db.php';
require_once INC_PATH . '/auth.php';
require_once INC_PATH . '/helpers.php';
require_once INC_PATH . '/fieldora.php';

session_init();

if (!empty($_SESSION['user_id'])) {
    $currentScript = $_SERVER['SCRIPT_FILENAME'] ?? '';
    $changePwFile = ROOT_PATH . '/modules/settings/change_password.php';

    if (realpath($currentScript) !== realpath($changePwFile)) {
        $user = current_user();
        if ($user && !empty($user['must_change_pw'])) {
            redirect(APP_URL . '/modules/settings/change_password.php?force=1');
        }
    }
}
