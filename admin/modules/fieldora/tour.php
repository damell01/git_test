<?php
require_once __DIR__ . '/_bootstrap.php';

$user = current_user();
if (!$user) {
    redirect(SITE_URL . '/login');
}

$tour = preg_replace('/[^a-z0-9_]/i', '', (string) ($_GET['tour'] ?? 'dashboard'));
$done = (string) ($_GET['done'] ?? '1') === '1' ? '1' : '0';
\TrashPanda\Fieldora\Services\TenantService::saveSetting(current_tenant_id(), 'tour_' . (int) $user['id'] . '_' . $tour, $done);
redirect((string) ($_SERVER['HTTP_REFERER'] ?? (APP_URL . '/dashboard.php')));
