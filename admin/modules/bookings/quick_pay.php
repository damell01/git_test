<?php
/**
 * Bookings – Quick Pay (POST-only)
 * Lets admin record cash/check payments inline from the booking view.
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_login();
require_role('admin', 'office');
csrf_check();

$id     = (int)($_POST['id'] ?? 0);
$action = trim($_POST['action'] ?? '');

if ($id <= 0) {
    flash_error('Invalid booking ID.');
    redirect('index.php');
}

$booking = db_fetch('SELECT * FROM bookings WHERE id = ? LIMIT 1', [$id]);
if (!$booking) {
    flash_error('Booking not found.');
    redirect('index.php');
}

$payment_notes = trim($_POST['payment_notes'] ?? '');

$allowed = [
    'mark_paid_cash'    => ['payment_status' => 'paid_cash',    'payment_method' => 'cash'],
    'mark_paid_check'   => ['payment_status' => 'paid_check',   'payment_method' => 'check'],
    'mark_pending_cash' => ['payment_status' => 'pending_cash', 'payment_method' => 'cash'],
    'mark_pending_check'=> ['payment_status' => 'pending_check','payment_method' => 'check'],
    'revert_unpaid'     => ['payment_status' => 'unpaid',       'payment_method' => null],
];

if (!array_key_exists($action, $allowed)) {
    flash_error('Invalid action.');
    redirect('view.php?id=' . $id);
}

$update = [
    'payment_status' => $allowed[$action]['payment_status'],
    'updated_at'     => date('Y-m-d H:i:s'),
];

if ($allowed[$action]['payment_method'] !== null) {
    $update['payment_method'] = $allowed[$action]['payment_method'];
}

if ($payment_notes !== '') {
    $update['payment_notes'] = $payment_notes;
}

db_update('bookings', $update, 'id', $id);

$status_label = [
    'paid_cash'     => 'Cash (Paid)',
    'paid_check'    => 'Check (Paid)',
    'pending_cash'  => 'Cash (Pending)',
    'pending_check' => 'Check (Pending)',
    'unpaid'        => 'Unpaid',
][$allowed[$action]['payment_status']] ?? $allowed[$action]['payment_status'];

log_activity('update', "Quick pay: booking {$booking['booking_number']} → $status_label", 'booking', $id);
flash_success("Payment status updated: $status_label.");

// Allow the caller to specify a redirect target (e.g. back to the list)
$redirect_to = trim($_POST['redirect_to'] ?? '');
if ($redirect_to !== '' && preg_match('/^[a-zA-Z0-9_.?&=%-]+$/', $redirect_to)) {
    redirect($redirect_to);
}
redirect('view.php?id=' . $id);
