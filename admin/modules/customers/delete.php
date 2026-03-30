<?php
/**
 * Customers – Delete
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';

require_login();
require_role('admin', 'office');

$id   = (int)($_GET['id'] ?? 0);
$cust = $id ? db_fetch("SELECT * FROM customers WHERE id = ? LIMIT 1", [$id]) : false;

if (!$cust) {
    flash_error('Customer not found.');
    redirect(APP_URL . '/modules/customers/index.php');
}

// ── Check for active work orders ─────────────────────────────────────────────
$active_wo_count = (int)(db_fetch(
    "SELECT COUNT(*) AS cnt
     FROM work_orders
     WHERE customer_id = ?
       AND status NOT IN ('completed', 'canceled')",
    [$id]
)['cnt'] ?? 0);

if ($active_wo_count > 0) {
    flash_error(
        'Cannot delete "' . $cust['name'] . '" — they have ' . $active_wo_count .
        ' active work order' . ($active_wo_count !== 1 ? 's' : '') .
        '. Complete or cancel all work orders first.'
    );
    redirect(APP_URL . '/modules/customers/view.php?id=' . $id);
}

// ── Hard delete the customer ──────────────────────────────────────────────────
db_execute("DELETE FROM customers WHERE id = ?", [$id]);

log_activity('delete', 'Deleted customer: ' . $cust['name'], 'customer', $id);

flash_info('Customer "' . $cust['name'] . '" has been deleted.');
redirect(APP_URL . '/modules/customers/index.php');
