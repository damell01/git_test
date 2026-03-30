<?php
/**
 * Entry point – redirect to dashboard if logged in, otherwise to login.
 *
 * Trash Panda Roll-Offs
 */

require_once __DIR__ . '/includes/bootstrap.php';

if (!empty($_SESSION['user_id'])) {
    redirect(APP_URL . '/dashboard.php');
} else {
    redirect(APP_URL . '/login.php');
}
