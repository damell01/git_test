<?php
/**
 * Bookings – Cancel (POST-only)
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once INC_PATH . '/mailer.php';
require_login();
require_role('admin', 'office');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

csrf_check();

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    flash_error('Invalid booking ID.');
    redirect('index.php');
}

$booking = db_fetch('SELECT * FROM bookings WHERE id = ? LIMIT 1', [$id]);
if (!$booking) {
    flash_error('Booking not found.');
    redirect('index.php');
}

if ($booking['booking_status'] === 'canceled') {
    flash_error('Booking is already canceled.');
    redirect('view.php?id=' . $id);
}

db_update('bookings', [
    'booking_status' => 'canceled',
    'updated_at'     => date('Y-m-d H:i:s'),
], 'id', $id);

// Release dumpster if no other active bookings/work-orders hold it
if (!empty($booking['dumpster_id'])) {
    release_dumpster_if_free((int)$booking['dumpster_id'], $id);
}

// Notify customer
notify_booking_cancelled($booking);

log_activity('cancel', "Canceled booking {$booking['booking_number']}", 'booking', $id);
flash_success("Booking {$booking['booking_number']} has been canceled.");
redirect('view.php?id=' . $id);
