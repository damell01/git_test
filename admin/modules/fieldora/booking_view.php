<?php
require_once __DIR__ . '/_bootstrap.php';

require_permission('bookings.view');

$id = (int) ($_GET['id'] ?? 0);
$row = db_fetch(
    'SELECT b.*, CONCAT_WS(" ", c.first_name, c.last_name) AS customer_name, c.email, c.phone
     FROM bookings b
     LEFT JOIN customers c ON c.id = b.customer_id
     WHERE b.tenant_id = ? AND b.id = ? LIMIT 1',
    [current_tenant_id(), $id]
);
$items = db_fetchall('SELECT * FROM booking_items WHERE booking_id = ?', [$id]);

if (!$row) {
    http_response_code(404);
    exit('Booking not found');
}

if (isset($_GET['action']) && user_can('bookings.manage')) {
    $action = (string) $_GET['action'];
    if (in_array($action, ['approve', 'cancel'], true)) {
        $status = $action === 'approve' ? 'confirmed' : 'canceled';
        $jobStatus = $action === 'approve' ? 'scheduled' : 'canceled';
        db_execute('UPDATE bookings SET status = ?, updated_at = NOW(), confirmed_at = CASE WHEN ? = "confirmed" AND confirmed_at IS NULL THEN NOW() ELSE confirmed_at END WHERE id = ? AND tenant_id = ?', [$status, $status, $id, current_tenant_id()]);
        db_execute('UPDATE jobs SET status = ?, updated_at = NOW(), completed_at = CASE WHEN ? = "canceled" THEN NOW() ELSE completed_at END WHERE booking_id = ? AND tenant_id = ?', [$jobStatus, $jobStatus, $id, current_tenant_id()]);
        \TrashPanda\Fieldora\Services\WebhookService::queueEvent(current_tenant_id(), $action === 'approve' ? 'booking.approved' : 'booking.cancelled', ['booking_id' => $id]);
        log_fieldora_event('booking.status_changed', 'Updated booking status to ' . $status, 'booking', $id);
        flash_success('Booking status updated.');
        redirect(APP_URL . '/modules/fieldora/booking_view.php?id=' . $id);
    }
}

fieldora_layout_start('Booking Detail', 'bookings');
?>
<div class="grid two">
    <section class="card stack">
        <div>
            <h3><?= e($row['booking_number']) ?></h3>
            <p class="muted"><?= e(trim((string) $row['customer_name'])) ?> - <?= e((string) $row['email']) ?> - <?= e((string) $row['phone']) ?></p>
        </div>
        <p>Status: <span class="tag"><?= e($row['status']) ?></span></p>
        <p>Payment: <span class="tag"><?= e($row['payment_state']) ?></span></p>
        <p>Date: <?= e((string) $row['scheduled_date']) ?> <?= e((string) $row['start_time']) ?></p>
        <p>Notes: <?= e((string) $row['notes']) ?></p>
        <div class="topbar-actions">
            <a class="primary-btn" href="<?= e(APP_URL) ?>/modules/fieldora/booking_edit.php?id=<?= $id ?>">Edit booking</a>
            <?php if ($row['status'] === 'requested'): ?>
                <a class="ghost-link" href="?id=<?= $id ?>&action=approve">Approve</a>
            <?php endif; ?>
            <?php if ($row['status'] !== 'canceled' && $row['status'] !== 'completed'): ?>
                <a class="ghost-link" href="?id=<?= $id ?>&action=cancel">Cancel</a>
            <?php endif; ?>
        </div>
    </section>
    <section class="table-wrap">
        <table>
            <thead><tr><th>Item</th><th>Amount</th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= e($item['item_name']) ?></td>
                    <td>$<?= number_format((float) $item['amount'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</div>
<?php fieldora_layout_end();
