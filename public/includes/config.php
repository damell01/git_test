<?php
// Pull in DB credentials and core constants from admin config
require_once dirname(__DIR__, 2) . '/admin/config/config.php';

define('SITE_NAME',  'Trash Panda Roll-Offs');
define('SITE_URL',   'https://yourdomain.com');
define('ADMIN_URL',  'https://yourdomain.com/admin');
define('SITE_PHONE', '(555) 867-5309');
define('SITE_EMAIL', 'info@trashpandarolloffs.com');

// PDO helper – returns a singleton connection using admin DB constants
function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return $pdo;
}
