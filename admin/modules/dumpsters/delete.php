<?php
/**
 * Dumpsters – Delete
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_login();
require_role('admin', 'office');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    flash_error('Invalid dumpster ID.');
    redirect('index.php');
}

// ── Fetch dumpster or 404 ─────────────────────────────────────────────────────
$dumpster = db_fetch('SELECT * FROM dumpsters WHERE id = ? LIMIT 1', [$id]);
if (!$dumpster) {
    http_response_code(404);
    flash_error('Dumpster not found.');
    redirect('index.php');
}

// ── Guard: only available dumpsters may be deleted ────────────────────────────
if ($dumpster['status'] !== 'available') {
    flash_error('Cannot delete a dumpster that is not available. Update its status first.');
    redirect('index.php');
}

// ── Delete ────────────────────────────────────────────────────────────────────
$unit_code = $dumpster['unit_code'];

db_execute('DELETE FROM dumpsters WHERE id = ?', [$id]);

log_activity('delete', "Deleted dumpster $unit_code", 'dumpster', $id);
flash_info("Dumpster $unit_code has been deleted.");
redirect('index.php');
