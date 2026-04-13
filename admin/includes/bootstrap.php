<?php
/**
 * Fieldora bootstrap.
 */

require_once __DIR__ . '/../config/config.php';

$debugMode = (isset($_GET['debug']) && $_GET['debug'] === '1')
    || (defined('APP_DEBUG') && APP_DEBUG === true);

if (PHP_VERSION_ID < 80000) {
    http_response_code(500);
    echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>PHP Version Error</title>'
        . '<style>body{font-family:Arial,sans-serif;background:#0f172a;color:#e2e8f0;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}.box{background:#111827;border:1px solid #1f2937;border-radius:16px;padding:2rem 2.5rem;max-width:640px;text-align:center}h2{color:#fda4af}code{background:#020617;padding:2px 6px;border-radius:6px}</style></head><body><div class="box">'
        . '<h2>Fieldora requires PHP 8.0 or newer</h2>'
        . '<p>The web server is currently running <code>' . htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8') . '</code>.</p>'
        . '<p>If your host says PHP 8.3 is enabled but Composer or the site still shows PHP 7.4, the CLI and web PHP versions are not aligned yet.</p>'
        . '</div></body></html>';
    exit;
}

$fieldoraLogDir = ROOT_PATH . '/uploads/logs';
if (!is_dir($fieldoraLogDir)) {
    @mkdir($fieldoraLogDir, 0775, true);
}
$fieldoraLogFile = $fieldoraLogDir . '/fieldora-' . date('Y-m-d') . '.log';

$logFieldoraException = static function (Throwable $e) use ($fieldoraLogFile): string {
    $reference = 'ERR-' . date('YmdHis') . '-' . substr(bin2hex(random_bytes(4)), 0, 8);
    $message = '[' . date('c') . '] ' . $reference . ' '
        . get_class($e) . ': ' . $e->getMessage()
        . ' in ' . $e->getFile() . ':' . $e->getLine()
        . PHP_EOL . $e->getTraceAsString() . PHP_EOL . PHP_EOL;
    @file_put_contents($fieldoraLogFile, $message, FILE_APPEND);
    error_log('[Fieldora] ' . $reference . ' ' . get_class($e) . ': ' . $e->getMessage()
        . ' in ' . $e->getFile() . ':' . $e->getLine());
    return $reference;
};

if ($debugMode) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);

    set_exception_handler(function (Throwable $e) use ($logFieldoraException): void {
        $reference = $logFieldoraException($e);
        if (!headers_sent()) {
            http_response_code(500);
        }
        echo '<pre style="white-space:pre-wrap;background:#111;color:#f5f5f5;padding:16px;border:1px solid #444;">';
        echo 'UNCAUGHT EXCEPTION' . "\n";
        echo 'Reference: ' . $reference . "\n\n";
        echo get_class($e) . ': ' . $e->getMessage() . "\n\n";
        echo $e->getFile() . ':' . $e->getLine() . "\n\n";
        echo $e->getTraceAsString();
        echo '</pre>';
    });
} else {
    set_exception_handler(function (Throwable $e) use ($logFieldoraException): void {
        $reference = $logFieldoraException($e);
        if (!headers_sent()) {
            http_response_code(500);
        }
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Error</title>'
            . '<style>body{font-family:Arial,sans-serif;background:#0f172a;color:#e2e8f0;display:flex;'
            . 'align-items:center;justify-content:center;min-height:100vh;margin:0}.box{background:#111827;'
            . 'border:1px solid #1f2937;border-radius:16px;padding:2rem 2.5rem;max-width:540px;text-align:center}'
            . 'h2{color:#60a5fa}a{color:#93c5fd}</style></head><body><div class="box">'
            . '<h2>Something went wrong</h2><p>An unexpected error occurred. Please try again.</p>'
            . '<p>Reference: <code>' . htmlspecialchars($reference, ENT_QUOTES, 'UTF-8') . '</code></p>'
            . '<p><a href="javascript:history.back()">Go back</a></p></div></body></html>';
    });
}

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com 'unsafe-inline'; style-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com 'unsafe-inline'; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com; img-src 'self' data: https:; connect-src 'self' https://api.stripe.com;");

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
