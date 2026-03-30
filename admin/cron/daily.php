<?php
/**
 * Daily Cron Job
 * Trash Panda Roll-Offs
 *
 * Run via cron: php /path/to/admin/cron/daily.php
 * Or via secure web endpoint: GET /admin/cron/daily.php?key=CRON_SECRET_KEY
 */

// Bootstrap without session
define('RUNNING_FROM_CRON', true);

require_once dirname(__DIR__) . '/config/config.php';
require_once INC_PATH . '/db.php';
require_once INC_PATH . '/helpers.php';
require_once INC_PATH . '/auth.php';
require_once INC_PATH . '/mailer.php';

// ── Auth: accept cron key or CLI ─────────────────────────────────────────────
$is_cli = (PHP_SAPI === 'cli');

if (!$is_cli) {
    $provided_key = $_GET['key'] ?? '';
    if (!defined('CRON_KEY')
        || CRON_KEY === ''
        || CRON_KEY === 'change-this-to-a-random-secret'
        || !hash_equals(CRON_KEY, $provided_key)
    ) {
        http_response_code(403);
        echo "Forbidden: invalid or unconfigured cron key.\n";
        exit;
    }
    header('Content-Type: text/plain');
}

$log   = [];
$start = microtime(true);

$log[] = '=== Trash Panda Roll-Offs Daily Cron ===';
$log[] = 'Started: ' . date('Y-m-d H:i:s');
$log[] = '';

// ── Task 1: Notify deliveries tomorrow ────────────────────────────────────────
$log[] = '[Task 1] Delivery Tomorrow Notifications';
try {
    notify_delivery_tomorrow();
    $log[] = '  → Done';
} catch (\Throwable $e) {
    $log[] = '  → ERROR: ' . $e->getMessage();
}

// ── Task 2: Notify overdue pickups ────────────────────────────────────────────
$log[] = '';
$log[] = '[Task 2] Overdue Pickup Alerts';
try {
    notify_pickup_overdue();
    $log[] = '  → Done';
} catch (\Throwable $e) {
    $log[] = '  → ERROR: ' . $e->getMessage();
}

// ── Task 3: Auto-activate scheduled WOs with delivery_date <= today ───────────
$log[] = '';
$log[] = '[Task 3] Auto-activate scheduled work orders';
try {
    $today = date('Y-m-d');
    $wos_to_activate = db_fetchall(
        "SELECT id, wo_number FROM work_orders
         WHERE status = 'scheduled' AND delivery_date <= ?",
        [$today]
    );

    $count = 0;
    foreach ($wos_to_activate as $wo) {
        db_execute(
            "UPDATE work_orders SET status = 'active', updated_at = NOW() WHERE id = ?",
            [$wo['id']]
        );
        db_execute(
            "INSERT INTO work_order_notes (wo_id, user_id, note, note_type, created_at)
             VALUES (?, 0, 'Status auto-updated to Active by daily cron.', 'system', NOW())",
            [$wo['id']]
        );
        $count++;
    }

    $log[] = '  → Activated ' . $count . ' work order(s)';
} catch (\Throwable $e) {
    $log[] = '  → ERROR: ' . $e->getMessage();
}

// ── Task 4: Log completion ────────────────────────────────────────────────────
$elapsed = round(microtime(true) - $start, 2);
$log[]   = '';
$log[]   = '[Task 4] Logging to activity_log';
try {
    db_execute(
        "INSERT INTO activity_log (user_id, action, description, entity_type, entity_id, ip_address, created_at)
         VALUES (0, 'cron', ?, 'system', 0, 'cron', NOW())",
        ['Daily cron completed in ' . $elapsed . 's']
    );
    $log[] = '  → Done';
} catch (\Throwable $e) {
    $log[] = '  → ERROR: ' . $e->getMessage();
}

// ── Output ─────────────────────────────────────────────────────────────────────
$log[] = '';
$log[] = 'Completed: ' . date('Y-m-d H:i:s') . ' (Elapsed: ' . $elapsed . 's)';
$log[] = '=========================================';

echo implode("\n", $log) . "\n";
