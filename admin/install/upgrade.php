<?php
/**
 * Trash Panda Roll-Offs — Database Upgrade Script
 * ================================================
 * Run this script ONCE after pulling new code that adds database columns or tables.
 * It is safe to run multiple times — every change uses IF NOT EXISTS / IF EXISTS guards.
 *
 * HOW TO RUN:
 *   Option A (browser): https://your-domain.com/admin/install/upgrade.php?secret=YOUR_UPGRADE_SECRET
 *   Option B (CLI):     php admin/install/upgrade.php
 *
 * Set UPGRADE_SECRET below to a long random string to protect the browser URL.
 * Delete or rename this file after running it in production.
 */

// ─── Security ────────────────────────────────────────────────────────────────
define('UPGRADE_SECRET', 'change-this-to-a-random-string-before-use');

$isCli = (PHP_SAPI === 'cli');

if (!$isCli) {
    $provided = $_GET['secret'] ?? '';
    if ($provided !== UPGRADE_SECRET || UPGRADE_SECRET === 'change-this-to-a-random-string-before-use') {
        http_response_code(403);
        die('<pre>Access denied. Set a custom UPGRADE_SECRET in upgrade.php and pass ?secret=YOUR_SECRET in the URL.</pre>');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

// ─── Bootstrap ───────────────────────────────────────────────────────────────
$_admin_root = dirname(__DIR__);
require_once $_admin_root . '/config/config.php';
require_once INC_PATH . '/db.php';

$pdo = get_db();

// ─── Helpers ─────────────────────────────────────────────────────────────────
$log   = [];
$errors = [];

function run_step(PDO $pdo, string $label, string $sql): void {
    global $log, $errors;
    try {
        $pdo->exec($sql);
        $log[] = "[OK]  $label";
    } catch (PDOException $e) {
        // "Duplicate column name" and "already exists" are not real errors for idempotent upgrades
        $msg = $e->getMessage();
        if (
            stripos($msg, 'Duplicate column') !== false ||
            stripos($msg, 'already exists')   !== false ||
            stripos($msg, 'Duplicate key')     !== false
        ) {
            $log[] = "[SKIP] $label (already applied)";
        } else {
            $errors[] = "[FAIL] $label — " . $msg;
        }
    }
}

function column_exists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?"
    );
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function table_exists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare(
        "SELECT COUNT(*) FROM information_schema.TABLES
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?"
    );
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

echo "Trash Panda Roll-Offs — Database Upgrade\n";
echo str_repeat('=', 60) . "\n\n";

// =============================================================================
// UPGRADE 1 — dumpsters: add type, daily_rate, active, image columns
// =============================================================================
echo "--- Upgrade 1: dumpsters table enhancements ---\n";

if (!column_exists($pdo, 'dumpsters', 'type')) {
    run_step($pdo, "dumpsters.type", "ALTER TABLE `dumpsters`
        ADD COLUMN `type` ENUM('dumpster','trailer') NOT NULL DEFAULT 'dumpster' AFTER `unit_code`");
} else {
    $log[] = "[SKIP] dumpsters.type (already exists)";
}

if (!column_exists($pdo, 'dumpsters', 'daily_rate')) {
    run_step($pdo, "dumpsters.daily_rate", "ALTER TABLE `dumpsters`
        ADD COLUMN `daily_rate` DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER `size`");
} else {
    $log[] = "[SKIP] dumpsters.daily_rate (already exists)";
}

if (!column_exists($pdo, 'dumpsters', 'active')) {
    run_step($pdo, "dumpsters.active", "ALTER TABLE `dumpsters`
        ADD COLUMN `active` TINYINT(1) NOT NULL DEFAULT 1 AFTER `daily_rate`");
} else {
    $log[] = "[SKIP] dumpsters.active (already exists)";
}

if (!column_exists($pdo, 'dumpsters', 'image')) {
    run_step($pdo, "dumpsters.image", "ALTER TABLE `dumpsters`
        ADD COLUMN `image` VARCHAR(255) DEFAULT NULL AFTER `active`");
} else {
    $log[] = "[SKIP] dumpsters.image (already exists)";
}

// =============================================================================
// UPGRADE 2 — bookings table
// =============================================================================
echo "\n--- Upgrade 2: bookings table ---\n";

if (!table_exists($pdo, 'bookings')) {
    run_step($pdo, "CREATE TABLE bookings", "
        CREATE TABLE `bookings` (
          `id`                 INT(11)       NOT NULL AUTO_INCREMENT,
          `booking_number`     VARCHAR(20)   NOT NULL,
          `customer_name`      VARCHAR(100)  NOT NULL,
          `customer_phone`     VARCHAR(25)            DEFAULT NULL,
          `customer_email`     VARCHAR(150)           DEFAULT NULL,
          `customer_address`   VARCHAR(200)           DEFAULT NULL,
          `customer_city`      VARCHAR(100)           DEFAULT NULL,
          `dumpster_id`        INT(11)                DEFAULT NULL,
          `unit_code`          VARCHAR(50)            DEFAULT NULL,
          `unit_type`          VARCHAR(50)            DEFAULT NULL,
          `unit_size`          VARCHAR(50)            DEFAULT NULL,
          `rental_start`       DATE          NOT NULL,
          `rental_end`         DATE          NOT NULL,
          `rental_days`        INT(11)       NOT NULL DEFAULT 1,
          `daily_rate`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
          `total_amount`       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
          `payment_method`     ENUM('stripe','cash','check') NOT NULL DEFAULT 'stripe',
          `payment_status`     ENUM('unpaid','pending','paid','refunded','pending_cash','paid_cash','pending_check','paid_check') NOT NULL DEFAULT 'unpaid',
          `stripe_session_id`  VARCHAR(255)           DEFAULT NULL,
          `stripe_payment_id`  VARCHAR(255)           DEFAULT NULL,
          `booking_status`     ENUM('pending','confirmed','paid','canceled','completed') NOT NULL DEFAULT 'pending',
          `notes`              TEXT                   DEFAULT NULL,
          `created_by`         INT(11)                DEFAULT NULL,
          `created_at`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
          `updated_at`         DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uq_bookings_number` (`booking_number`),
          CONSTRAINT `fk_bookings_dumpster_id` FOREIGN KEY (`dumpster_id`) REFERENCES `dumpsters` (`id`) ON DELETE SET NULL,
          CONSTRAINT `fk_bookings_created_by`  FOREIGN KEY (`created_by`)  REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} else {
    $log[] = "[SKIP] bookings table (already exists)";
}

// =============================================================================
// UPGRADE 3 — inventory_blocks table
// =============================================================================
echo "\n--- Upgrade 3: inventory_blocks table ---\n";

if (!table_exists($pdo, 'inventory_blocks')) {
    run_step($pdo, "CREATE TABLE inventory_blocks", "
        CREATE TABLE `inventory_blocks` (
          `id`           INT(11)      NOT NULL AUTO_INCREMENT,
          `dumpster_id`  INT(11)      NOT NULL,
          `block_start`  DATE         NOT NULL,
          `block_end`    DATE         NOT NULL,
          `reason`       VARCHAR(200)          DEFAULT NULL,
          `created_by`   INT(11)               DEFAULT NULL,
          `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          CONSTRAINT `fk_ib_dumpster_id`  FOREIGN KEY (`dumpster_id`) REFERENCES `dumpsters` (`id`) ON DELETE CASCADE,
          CONSTRAINT `fk_ib_created_by`   FOREIGN KEY (`created_by`)  REFERENCES `users`     (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} else {
    $log[] = "[SKIP] inventory_blocks table (already exists)";
}

// =============================================================================
// UPGRADE 4 — settings: Stripe & booking defaults
// =============================================================================
echo "\n--- Upgrade 4: settings defaults ---\n";

if (table_exists($pdo, 'settings')) {
    $defaults = [
        'stripe_publishable_key' => '',
        'stripe_secret_key'      => '',
        'stripe_webhook_secret'  => '',
        'stripe_mode'            => 'test',
        'booking_terms'          => 'By completing this booking, you agree to our rental terms and conditions.',
        'currency'               => 'usd',
    ];
    foreach ($defaults as $key => $value) {
        try {
            $pdo->prepare("INSERT IGNORE INTO `settings` (`key`, `value`) VALUES (?, ?)")
                ->execute([$key, $value]);
            $log[] = "[OK]  settings.$key (inserted if missing)";
        } catch (PDOException $e) {
            $errors[] = "[FAIL] settings.$key — " . $e->getMessage();
        }
    }
} else {
    $log[] = "[SKIP] settings table does not exist — skipping defaults";
}

// =============================================================================
// UPGRADE 5 — customers: remove active column reference (schema cleanup note)
// =============================================================================
echo "\n--- Upgrade 5: customers table ---\n";
// The customers table never had an `active` column per the schema.
// The create.php bug was in PHP code (now fixed). No DB change needed.
$log[] = "[INFO] customers.active — no DB change needed (PHP code already fixed)";

// =============================================================================
// Summary
// =============================================================================
echo "\n" . str_repeat('=', 60) . "\n";
echo "RESULTS:\n\n";

foreach ($log as $line) {
    echo "  $line\n";
}

if (!empty($errors)) {
    echo "\nERRORS:\n\n";
    foreach ($errors as $line) {
        echo "  $line\n";
    }
    echo "\nUpgrade completed with " . count($errors) . " error(s). Review the errors above.\n";
} else {
    echo "\nAll upgrades applied successfully. No errors.\n";
}

echo "\n[IMPORTANT] Delete or rename this file after running it in production:\n";
echo "  rm admin/install/upgrade.php\n";
echo "  or rename it: mv admin/install/upgrade.php admin/install/upgrade.php.done\n\n";
