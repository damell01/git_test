<?php

// Fieldora configuration and lightweight .env loader.
(static function (): void {
    $envFile = dirname(__DIR__, 2) . '/.env';
    if (!is_file($envFile)) {
        return;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }

        $eq = strpos($line, '=');
        if ($eq === false) {
            continue;
        }

        $key = trim(substr($line, 0, $eq));
        $value = trim(substr($line, $eq + 1));
        if (
            strlen($value) >= 2
            && (($value[0] === '"' && substr($value, -1) === '"')
                || ($value[0] === "'" && substr($value, -1) === "'"))
        ) {
            $value = substr($value, 1, -1);
        }

        if ($key !== '' && !array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
})();

$_env = static fn(string $key, string $default): string =>
    isset($_ENV[$key]) && $_ENV[$key] !== '' ? (string) $_ENV[$key] : $default;

define('DB_HOST', $_env('DB_HOST', '127.0.0.1'));
define('DB_NAME', $_env('DB_NAME', 'fieldora'));
define('DB_USER', $_env('DB_USER', 'root'));
define('DB_PASS', $_env('DB_PASS', ''));
define('DB_CHARSET', $_env('DB_CHARSET', 'utf8mb4'));

define('APP_NAME', $_env('APP_NAME', 'Fieldora'));
define('APP_VERSION', $_env('APP_VERSION', '2.0.0'));

$appScheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$rawHost = $_SERVER['HTTP_HOST'] ?? 'localhost';
$appHost = preg_replace('/[^a-zA-Z0-9\-\.:]/', '', $rawHost);

define('SITE_URL', rtrim($_env('SITE_URL', $appScheme . '://' . $appHost), '/'));
define('APP_URL', SITE_URL . '/admin');

unset($appScheme, $rawHost, $appHost);

$debugRaw = strtolower($_env('APP_DEBUG', 'false'));
define('APP_DEBUG', in_array($debugRaw, ['true', '1', 'yes'], true));
unset($debugRaw);

define('SESSION_NAME', $_env('SESSION_NAME', 'fieldora_session'));
define('CSRF_TOKEN_NAME', $_env('CSRF_TOKEN_NAME', 'fieldora_csrf'));
define('SESSION_LIFETIME', (int) $_env('SESSION_LIFETIME', '7200'));
define('APP_TIMEZONE', $_env('APP_TIMEZONE', 'America/Chicago'));
define('ROOT_PATH', dirname(__DIR__));
define('INC_PATH', ROOT_PATH . '/includes');
define('TMPL_PATH', ROOT_PATH . '/templates');
define('ASSET_PATH', APP_URL . '/assets');

$installedRaw = strtolower($_env('APP_INSTALLED', 'false'));
define('APP_INSTALLED', in_array($installedRaw, ['true', '1', 'yes'], true));
unset($installedRaw);

define('CRON_KEY', $_env('CRON_KEY', 'change-this-to-a-random-secret'));
define('DEFAULT_TENANT_PLAN', $_env('DEFAULT_TENANT_PLAN', 'starter'));
define('FIELDORA_BOOKING_APPROVAL_DEFAULT', $_env('FIELDORA_BOOKING_APPROVAL_DEFAULT', 'request'));
define('STRIPE_PLATFORM_SECRET_KEY', $_env('STRIPE_PLATFORM_SECRET_KEY', ''));
define('STRIPE_PLATFORM_PUBLISHABLE_KEY', $_env('STRIPE_PLATFORM_PUBLISHABLE_KEY', ''));
define('STRIPE_CONNECT_WEBHOOK_SECRET', $_env('STRIPE_CONNECT_WEBHOOK_SECRET', ''));

date_default_timezone_set(APP_TIMEZONE);

unset($_env);
