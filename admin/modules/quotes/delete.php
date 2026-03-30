<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_login();
require_role('admin', 'office');

$id   = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare('SELECT * FROM quotes WHERE id = ? LIMIT 1');
$stmt->execute([$id]);
$quote = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quote) {
    flash_error('Quote not found.');
    redirect('index.php');
}

// Prevent deletion if already converted to a work order
if (!empty($quote['converted_to'])) {
    flash_error('This quote has already been converted to a Work Order and cannot be deleted.');
    redirect('view.php?id=' . $id);
}

$del = $pdo->prepare('DELETE FROM quotes WHERE id = ?');
$del->execute([$id]);

log_activity('delete_quote', 'Deleted quote ' . $quote['quote_number'], $id);
flash_success('Quote ' . htmlspecialchars($quote['quote_number']) . ' has been deleted.');
redirect('index.php');
