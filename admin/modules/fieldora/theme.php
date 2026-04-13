<?php
require_once __DIR__ . '/_bootstrap.php';

$mode = strtolower((string) ($_GET['mode'] ?? 'dark'));
$mode = in_array($mode, ['light', 'dark'], true) ? $mode : 'dark';
$user = current_user();

if ($user) {
    db_execute('UPDATE users SET theme_preference = ?, updated_at = NOW() WHERE id = ?', [$mode, (int) $user['id']]);
    flash_success('Theme preference updated.');
}

$redirect = (string) ($_SERVER['HTTP_REFERER'] ?? (APP_URL . '/dashboard.php'));
redirect($redirect);
