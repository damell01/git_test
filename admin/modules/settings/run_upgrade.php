<?php
/**
 * Settings – Run Database Upgrade (AJAX endpoint)
 * POST-only; requires admin login + valid CSRF token.
 * Returns JSON: { success, output, errors[] }
 *
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_login();
require_role('admin');

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

csrf_check();

// Tell upgrade.php it is being called internally so it skips the secret check.
define('RUNNING_FROM_ADMIN', true);

// Capture all output from the upgrade script.
ob_start();
require dirname(__DIR__, 2) . '/install/upgrade.php';
$rawOutput = ob_get_clean();

// $log and $errors are defined at global scope inside upgrade.php.
// Defensive fallback in case the file omits them for some reason.
$log    = $log    ?? [];
$errors = $errors ?? [];

$hadErrors = !empty($errors);

log_activity('update', 'Ran database upgrade script from Settings', 'settings', 0);

echo json_encode([
    'success' => !$hadErrors,
    'output'  => $rawOutput,
    'errors'  => $errors ?? [],
]);
