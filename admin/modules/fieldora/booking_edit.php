<?php
require_once __DIR__ . '/_bootstrap.php';

use TrashPanda\Fieldora\Services\BookingService;

require_permission('bookings.manage');

$id = (int) ($_GET['id'] ?? 0);
$tenantId = current_tenant_id();
$row = db_fetch('SELECT * FROM bookings WHERE tenant_id = ? AND id = ? LIMIT 1', [$tenantId, $id]);

if (!$row) {
    http_response_code(404);
    exit('Booking not found');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $status = trim((string) ($_POST['status'] ?? 'requested'));
    $paymentState = trim((string) ($_POST['payment_state'] ?? 'pending'));
    $scheduledDate = trim((string) ($_POST['scheduled_date'] ?? ''));
    $startTime = trim((string) ($_POST['start_time'] ?? ''));
    $endTime = trim((string) ($_POST['end_time'] ?? ''));

    if (!in_array($status, ['requested', 'confirmed', 'scheduled', 'completed', 'canceled'], true)) {
        flash_error('Invalid booking status.');
        redirect($_SERVER['REQUEST_URI']);
    }

    if ($scheduledDate !== '') {
        BookingService::validateRequestedSlot($tenantId, $scheduledDate, $startTime, $id);
    }

    db_execute(
        'UPDATE bookings
         SET status = ?, payment_state = ?, scheduled_date = ?, start_time = ?, end_time = ?, notes = ?, internal_notes = ?, assigned_user_id = ?, updated_at = NOW(),
             confirmed_at = CASE WHEN ? = "confirmed" AND confirmed_at IS NULL THEN NOW() ELSE confirmed_at END
         WHERE id = ? AND tenant_id = ?',
        [
            $status,
            $paymentState,
            $scheduledDate ?: null,
            $startTime ?: null,
            $endTime ?: null,
            trim((string) ($_POST['notes'] ?? '')),
            trim((string) ($_POST['internal_notes'] ?? '')),
            (int) ($_POST['assigned_user_id'] ?? 0) ?: null,
            $status,
            $id,
            $tenantId,
        ]
    );

    db_execute(
        'UPDATE jobs
         SET assigned_user_id = ?, scheduled_date = ?, start_time = ?, end_time = ?, status = ?, updated_at = NOW()
         WHERE booking_id = ? AND tenant_id = ?',
        [
            (int) ($_POST['assigned_user_id'] ?? 0) ?: null,
            $scheduledDate ?: null,
            $startTime ?: null,
            $endTime ?: null,
            $status === 'canceled' ? 'canceled' : ($status === 'completed' ? 'completed' : 'scheduled'),
            $id,
            $tenantId,
        ]
    );

    \TrashPanda\Fieldora\Services\WebhookService::queueEvent($tenantId, 'booking.updated', ['booking_id' => $id]);
    $jobRow = db_fetch('SELECT id FROM jobs WHERE tenant_id = ? AND booking_id = ? LIMIT 1', [$tenantId, $id]);
    if ($jobRow) {
        \TrashPanda\Fieldora\Services\WebhookService::queueEvent($tenantId, 'job.updated', ['job_id' => (int) $jobRow['id'], 'booking_id' => $id]);
    }
    log_fieldora_event('booking.updated', 'Updated booking', 'booking', $id);
    flash_success('Booking updated.');
    redirect(APP_URL . '/modules/fieldora/booking_view.php?id=' . $id);
}

$users = db_fetchall('SELECT id, name FROM users WHERE tenant_id = ? AND active = 1 ORDER BY name ASC', [$tenantId]);
fieldora_layout_start('Edit Booking', 'bookings');
?>
<form method="post" class="card form-grid">
    <?= csrf_field() ?>
    <label><span>Status</span><select name="status"><?php foreach (['requested', 'confirmed', 'scheduled', 'completed', 'canceled'] as $status): ?><option value="<?= e($status) ?>"<?= $row['status'] === $status ? ' selected' : '' ?>><?= e(ucfirst($status)) ?></option><?php endforeach; ?></select></label>
    <label><span>Payment state</span><select name="payment_state"><?php foreach (['unpaid', 'deposit_paid', 'paid', 'pending', 'failed', 'refunded', 'manual'] as $status): ?><option value="<?= e($status) ?>"<?= $row['payment_state'] === $status ? ' selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></label>
    <label><span>Scheduled date</span><input name="scheduled_date" type="date" value="<?= e((string) $row['scheduled_date']) ?>"></label>
    <label><span>Start time</span><input name="start_time" type="time" value="<?= e((string) $row['start_time']) ?>"></label>
    <label><span>End time</span><input name="end_time" type="time" value="<?= e((string) $row['end_time']) ?>"></label>
    <label><span>Assigned user</span><select name="assigned_user_id"><option value="">Unassigned</option><?php foreach ($users as $user): ?><option value="<?= (int) $user['id'] ?>"<?= (int) $row['assigned_user_id'] === (int) $user['id'] ? ' selected' : '' ?>><?= e($user['name']) ?></option><?php endforeach; ?></select></label>
    <label><span>Customer notes</span><textarea name="notes"><?= e((string) $row['notes']) ?></textarea></label>
    <label><span>Internal notes</span><textarea name="internal_notes"><?= e((string) $row['internal_notes']) ?></textarea></label>
    <button class="primary-btn" type="submit">Save booking</button>
</form>
<?php fieldora_layout_end();
