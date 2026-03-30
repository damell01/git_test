<?php
define('DB_HOST', '193.203.166.222');
define('DB_NAME', 'u307979562_trashpanda');
define('DB_USER', '	u307979562_trashpanda');
define('DB_PASS', 'ReallyStrongPassword1!');
define('DB_CHARSET', 'utf8mb4');
define('APP_NAME', 'Trash Panda Roll-Offs');
define('APP_VERSION', '1.0.0');
// Dynamically build APP_URL from the current request so the admin panel
// works on any domain without manual configuration.
// HTTP_HOST is sanitised to prevent host-header injection.
$_app_scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$_raw_host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
// Allow only valid hostname characters (letters, digits, hyphens, dots) and an optional :port
$_app_host   = preg_replace('/[^a-zA-Z0-9\-\.:]/', '', $_raw_host);
define('APP_URL', $_app_scheme . '://' . $_app_host . '/admin');  // no trailing slash
unset($_app_scheme, $_raw_host, $_app_host);
define('SESSION_NAME', 'tp_session');
define('CSRF_TOKEN_NAME', 'tp_csrf');
define('SESSION_LIFETIME', 7200);
define('ROOT_PATH', dirname(__DIR__));  // points to /admin/
define('INC_PATH', ROOT_PATH . '/includes');
define('TMPL_PATH', ROOT_PATH . '/templates');
define('ASSET_PATH', APP_URL . '/assets');
define('APP_INSTALLED', false);  // set true after running the installer (see install/install.php)

// ── Cron ──────────────────────────────────────────────────────────────────────
define('CRON_KEY', 'change-this-to-a-random-secret');
