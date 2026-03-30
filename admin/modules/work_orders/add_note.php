<?php
require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_login();

// POST-only endpoint
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}

csrf_check();

$wo_id     = (int)($_POST['wo_id'] ?? 0);
$note_text = trim($_POST['note']   ?? '');

// ── Validate inputs ───────────────────────────────────────────────────────────
if ($wo_id === 0) {
    flash_error('Invalid work order.');
    redirect('index.php');
}

if ($note_text === '') {
    flash_error('Note cannot be empty.');
    redirect('view.php?id=' . $wo_id);
}

// ── Verify work order exists ──────────────────────────────────────────────────
$stmt = $pdo->prepare('SELECT id, wo_number FROM work_orders WHERE id = ? LIMIT 1');
$stmt->execute([$wo_id]);
$wo = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$wo) {
    flash_error('Work order not found.');
    redirect('index.php');
}

// ── Insert note ───────────────────────────────────────────────────────────────
$ins = $pdo->prepare(
    'INSERT INTO work_order_notes (wo_id, user_id, note, note_type, created_at)
     VALUES (?, ?, ?, ?, NOW())'
);
$ins->execute([$wo_id, $_SESSION['user_id'], $note_text, 'note']);

// ── Log activity ──────────────────────────────────────────────────────────────
log_activity('add_wo_note', 'Added note to work order ' . $wo['wo_number'], $wo_id);

redirect('view.php?id=' . $wo_id);
