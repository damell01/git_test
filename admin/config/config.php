<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_db');
define('DB_USER', 'your_user');
define('DB_PASS', 'your_pass');
define('DB_CHARSET', 'utf8mb4');
define('APP_NAME', 'Trash Panda Roll-Offs');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'https://yourdomain.com/admin');  // no trailing slash
define('SESSION_NAME', 'tp_session');
define('CSRF_TOKEN_NAME', 'tp_csrf');
define('SESSION_LIFETIME', 7200);
define('ROOT_PATH', dirname(__DIR__));  // points to /admin/
define('INC_PATH', ROOT_PATH . '/includes');
define('TMPL_PATH', ROOT_PATH . '/templates');
define('ASSET_PATH', APP_URL . '/assets');
define('APP_INSTALLED', false);  // set true after running the installer (see install/install.php)
