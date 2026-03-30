<?php
/**
 * Leads – Delete (soft-archive)
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_login();
require_role('admin', 'office');

$id = (int)($_GET['id'] ?? 0);

if ($id > 0) {
    db_execute("UPDATE leads SET archived = 1, updated_at = NOW() WHERE id = ?", [$id]);
    log_activity('archive', 'Archived lead #' . $id, 'lead', $id);
}

flash_info('Lead archived.');
redirect(APP_URL . '/modules/leads/index.php');
