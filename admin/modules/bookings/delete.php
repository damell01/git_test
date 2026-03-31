<?php
/**
 * Bookings – Delete
 * Trash Panda Roll-Offs
 *
 * Permanently removes a booking record and releases the associated dumpster
 * if no other active bookings or work orders still hold it.
 * Admin role required.
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_login();
require_role('admin');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    flash_error('Invalid booking ID.');
    redirect('index.php');
}

$booking = db_fetch('SELECT * FROM bookings WHERE id = ? LIMIT 1', [$id]);
if (!$booking) {
    flash_error('Booking not found.');
    redirect('index.php');
}

$booking_number = $booking['booking_number'];
$dumpster_id    = !empty($booking['dumpster_id']) ? (int)$booking['dumpster_id'] : null;

// Delete the booking
db_execute('DELETE FROM bookings WHERE id = ?', [$id]);

// Release dumpster back to available if no other active bookings/work-orders hold it
if ($dumpster_id) {
    $still_active = db_fetch(
        "SELECT COUNT(*) AS cnt
           FROM bookings
          WHERE dumpster_id = ?
            AND booking_status NOT IN ('canceled', 'completed')",
        [$dumpster_id]
    );
    $wo_active = db_fetch(
        "SELECT COUNT(*) AS cnt
           FROM work_orders
          WHERE dumpster_id = ?
            AND status NOT IN ('completed', 'canceled')",
        [$dumpster_id]
    );
    if ((int)($still_active['cnt'] ?? 0) === 0 && (int)($wo_active['cnt'] ?? 0) === 0) {
        db_update('dumpsters', [
            'status'     => 'available',
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id', $dumpster_id);
    }
}

log_activity('delete', 'Deleted booking ' . $booking_number, 'booking', $id);
flash_success('Booking ' . htmlspecialchars($booking_number, ENT_QUOTES, 'UTF-8') . ' has been permanently deleted.');
redirect('index.php');
