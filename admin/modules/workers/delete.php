<?php
/**
 * Workers – Delete
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_login();
require_role('admin', 'office');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    flash_error('Invalid worker ID.');
    redirect('index.php');
}

$worker = db_fetch('SELECT * FROM workers WHERE id = ? LIMIT 1', [$id]);
if (!$worker) {
    flash_error('Worker not found.');
    redirect('index.php');
}

// Unassign from any bookings before deleting (FK ON DELETE SET NULL handles this,
// but we log the count for informational purposes).
try {
    $assigned = db_fetch(
        "SELECT COUNT(*) AS cnt FROM bookings WHERE worker_id = ? AND booking_status != 'canceled'",
        [$id]
    );
    $assigned_count = (int)($assigned['cnt'] ?? 0);
} catch (\Throwable $e) {
    $assigned_count = 0;
}

db_execute("DELETE FROM workers WHERE id = ?", [$id]);

log_activity('delete', "Deleted worker: {$worker['name']}", 'worker', $id);

$msg = "Worker <strong>" . e($worker['name']) . "</strong> deleted.";
if ($assigned_count > 0) {
    $msg .= " ($assigned_count booking(s) unassigned.)";
}
flash_success($msg);
redirect('index.php');
