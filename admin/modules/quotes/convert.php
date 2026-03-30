<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_login();
require_role('admin', 'office');
$pdo = get_db();

$quote_id = (int)($_GET['quote_id'] ?? 0);

// ── Fetch quote ───────────────────────────────────────────────────────────────
$stmt = $pdo->prepare('SELECT * FROM quotes WHERE id = ? LIMIT 1');
$stmt->execute([$quote_id]);
$quote = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quote) {
    flash_error('Quote not found.');
    redirect('index.php');
}

// ── Guard: already converted ──────────────────────────────────────────────────
if (!empty($quote['converted_to'])) {
    flash_error('This quote has already been converted to a Work Order.');
    redirect('view.php?id=' . $quote_id);
}

// ── Guard: must be approved ───────────────────────────────────────────────────
if ($quote['status'] !== 'approved') {
    flash_error('Quote must be approved before it can be converted to a Work Order.');
    redirect('view.php?id=' . $quote_id);
}

// ── Generate WO number ────────────────────────────────────────────────────────
$wo_number = next_number('WO', 'work_orders', 'wo_number');

// ── Fetch footer default ──────────────────────────────────────────────────────
$wo_footer = get_setting('wo_footer', '');

// ── Insert work order ─────────────────────────────────────────────────────────
$wo_id = db_insert('work_orders', [
    'wo_number'       => $wo_number,
    'customer_id'     => $quote['customer_id'] ?? null,
    'quote_id'        => $quote['id'],
    'cust_name'       => $quote['cust_name'],
    'cust_email'      => $quote['cust_email'],
    'cust_phone'      => $quote['cust_phone'],
    'service_address' => $quote['service_address'],
    'service_city'    => $quote['service_city'],
    'size'            => $quote['size'],
    'project_type'    => $quote['project_type'],
    'amount'          => $quote['total'],
    'status'          => 'scheduled',
    'priority'        => 'normal',
    'footer_notes'    => $wo_footer,
    'created_by'      => $_SESSION['user_id'],
    'created_at'      => date('Y-m-d H:i:s'),
    'updated_at'      => date('Y-m-d H:i:s'),
]);

// ── Mark quote as converted ───────────────────────────────────────────────────
$upd = $pdo->prepare('UPDATE quotes SET converted_to = ?, updated_at = NOW() WHERE id = ?');
$upd->execute([$wo_id, $quote_id]);

log_activity(
    'convert_quote',
    'Converted quote ' . $quote['quote_number'] . ' to Work Order ' . $wo_number,
    'quote',
    (int)$quote_id
);

flash_success('Quote converted to Work Order ' . $wo_number . ' successfully.');
redirect('../work_orders/view.php?id=' . $wo_id);
