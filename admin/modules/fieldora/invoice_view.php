<?php
require_once __DIR__ . '/_bootstrap.php';

use TrashPanda\Fieldora\Services\NotificationService;
use TrashPanda\Fieldora\Services\PaymentService;

require_permission('invoices.view');

$id = (int) ($_GET['id'] ?? 0);
$tenantId = current_tenant_id();
$row = db_fetch(
    'SELECT i.*, CONCAT_WS(" ", c.first_name, c.last_name) AS customer_name, c.email AS customer_email
     FROM invoices i
     LEFT JOIN customers c ON c.id = i.customer_id
     WHERE i.tenant_id = ? AND i.id = ? LIMIT 1',
    [$tenantId, $id]
);

if (!$row) {
    http_response_code(404);
    exit('Invoice not found');
}

$items = db_fetchall('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id ASC', [$id]);
$payments = db_fetchall('SELECT * FROM payments WHERE invoice_id = ? ORDER BY created_at DESC', [$id]);

if (isset($_GET['send']) && tenant_has_feature('invoice_management') && user_can('invoices.manage')) {
    try {
        $url = PaymentService::createInvoiceCheckout(
            current_tenant(),
            $row,
            array_map(static fn($item) => ['description' => $item['description'], 'amount' => $item['amount'], 'quantity' => $item['quantity']], $items)
        );

        if ($url) {
            db_execute('UPDATE invoices SET status = ?, sent_at = NOW(), payment_link_url = ?, updated_at = NOW() WHERE id = ?', ['sent', $url, $id]);
            \TrashPanda\Fieldora\Services\WebhookService::queueEvent($tenantId, 'invoice.sent', ['invoice_id' => $id]);
            if (!empty($row['customer_email'])) {
                NotificationService::queueTemplate($tenantId, 'invoice_sent', 'email', (string) $row['customer_email'], [
                    'customer_name' => (string) ($row['customer_name'] ?? 'Customer'),
                    'invoice_number' => (string) $row['invoice_number'],
                    'balance_due' => number_format((float) $row['balance_due'], 2),
                ], [
                    'invoice_id' => $id,
                    'customer_id' => (int) $row['customer_id'],
                ]);
            }
            flash_success('Invoice sent and payment link generated.');
            redirect(APP_URL . '/modules/fieldora/invoice_view.php?id=' . $id);
        }
    } catch (Throwable $e) {
        flash_error($e->getMessage());
        redirect(APP_URL . '/modules/fieldora/invoice_view.php?id=' . $id);
    }
}

if (isset($_GET['mark']) && user_can('invoices.manage')) {
    $mark = (string) $_GET['mark'];
    if ($mark === 'paid') {
        db_execute('UPDATE invoices SET status = ?, amount_paid = total, balance_due = 0, paid_at = NOW(), updated_at = NOW() WHERE id = ? AND tenant_id = ?', ['paid', $id, $tenantId]);
        flash_success('Invoice marked paid.');
    } elseif ($mark === 'unpaid') {
        db_execute('UPDATE invoices SET status = ?, amount_paid = 0, balance_due = total, paid_at = NULL, updated_at = NOW() WHERE id = ? AND tenant_id = ?', ['draft', $id, $tenantId]);
        flash_success('Invoice reset to unpaid.');
    }
    redirect(APP_URL . '/modules/fieldora/invoice_view.php?id=' . $id);
}

$row = db_fetch(
    'SELECT i.*, CONCAT_WS(" ", c.first_name, c.last_name) AS customer_name, c.email AS customer_email
     FROM invoices i
     LEFT JOIN customers c ON c.id = i.customer_id
     WHERE i.tenant_id = ? AND i.id = ? LIMIT 1',
    [$tenantId, $id]
);

fieldora_layout_start('Invoice Detail', 'invoices');
?>
<div class="grid two">
    <section class="card stack">
        <div>
            <h3><?= e($row['invoice_number']) ?></h3>
            <p class="muted"><?= e((string) $row['customer_name']) ?><?= $row['customer_email'] ? ' - ' . e($row['customer_email']) : '' ?></p>
        </div>
        <p>Status: <span class="tag"><?= e($row['status']) ?></span></p>
        <p>Total: $<?= number_format((float) $row['total'], 2) ?> - Paid: $<?= number_format((float) $row['amount_paid'], 2) ?> - Balance: $<?= number_format((float) $row['balance_due'], 2) ?></p>
        <p><?= e((string) $row['notes']) ?></p>
        <div class="topbar-actions">
            <a class="primary-btn" href="<?= e(APP_URL) ?>/modules/fieldora/invoice_edit.php?id=<?= $id ?>">Edit invoice</a>
            <?php if (tenant_has_feature('invoice_management')): ?><a class="ghost-link" href="?id=<?= $id ?>&send=1">Send/resend</a><?php endif; ?>
            <?php if ($row['status'] !== 'paid'): ?><a class="ghost-link" href="?id=<?= $id ?>&mark=paid">Mark paid</a><?php endif; ?>
            <?php if ($row['status'] === 'paid'): ?><a class="ghost-link" href="?id=<?= $id ?>&mark=unpaid">Mark unpaid</a><?php endif; ?>
        </div>
        <?php if (!empty($row['payment_link_url'])): ?>
            <p class="muted" style="margin-top:12px;"><a href="<?= e($row['payment_link_url']) ?>" target="_blank" rel="noopener">Open payment link</a></p>
        <?php endif; ?>
    </section>
    <section class="table-wrap">
        <table>
            <thead><tr><th>Line item</th><th>Qty</th><th>Amount</th></tr></thead>
            <tbody>
            <?php foreach ($items as $item): ?>
                <tr>
                    <td><?= e($item['description']) ?></td>
                    <td><?= e((string) $item['quantity']) ?></td>
                    <td>$<?= number_format((float) $item['amount'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</div>
<section class="table-wrap" style="margin-top:20px;">
    <table>
        <thead><tr><th>Payments</th><th>Status</th><th>Amount</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($payments as $payment): ?>
            <tr>
                <td><?= e($payment['payment_method']) ?></td>
                <td><?= e($payment['payment_status']) ?></td>
                <td>$<?= number_format((float) $payment['amount'], 2) ?></td>
                <td><?= e((string) ($payment['paid_at'] ?: $payment['created_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php fieldora_layout_end();
