<?php
/**
 * Bootstrap – load all core dependencies and initialise the session.
 * Include this file at the top of every entry-point script.
 *
 * Trash Panda Roll-Offs
 */

// ── 1. Configuration ────────────────────────────────────────────────────────
require_once __DIR__ . '/../config/config.php';

// ── 2. Core includes (order matters: db first, then auth which calls db helpers,
//        then helpers which may call db helpers too) ──────────────────────────
require_once INC_PATH . '/db.php';
require_once INC_PATH . '/auth.php';
require_once INC_PATH . '/helpers.php';

// ── 3. Start / resume the session ───────────────────────────────────────────
session_init();

// ── 4. Force password-change flow ───────────────────────────────────────────
// If the logged-in user has must_change_pw set, redirect them to the change-
// password page unless they are already on it (avoids an infinite loop).
if (!empty($_SESSION['user_id'])) {
    $current_script = $_SERVER['SCRIPT_FILENAME'] ?? '';
    $change_pw_file = ROOT_PATH . '/modules/settings/change_password.php';

    if (realpath($current_script) !== realpath($change_pw_file)) {
        $user = current_user();
        if ($user && !empty($user['must_change_pw'])) {
            redirect(APP_URL . '/modules/settings/change_password.php?force=1');
        }
    }
}
