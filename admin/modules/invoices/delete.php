<?php
/**
 * Invoices – Delete
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_login();
require_role('admin', 'office');

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) { flash_error('Invalid invoice ID.'); redirect('index.php'); }

$inv = db_fetch('SELECT id, invoice_number FROM invoices WHERE id = ? LIMIT 1', [$id]);
if (!$inv) { flash_error('Invoice not found.'); redirect('index.php'); }

// Confirm via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    // invoice_items deleted via FK CASCADE
    db_execute('DELETE FROM invoices WHERE id = ?', [$id]);
    log_activity('delete_invoice', 'Deleted invoice ' . $inv['invoice_number'], 'invoice', $id);
    flash_success('Invoice ' . $inv['invoice_number'] . ' deleted.');
    redirect('index.php');
}

// Show confirmation page
require_once TMPL_PATH . '/layout.php';
layout_start('Delete Invoice', 'invoices');
?>

<div class="tp-card" style="max-width:500px;">
    <h5 class="mb-3"><i class="fa-solid fa-triangle-exclamation text-danger me-2"></i>Delete Invoice</h5>
    <p>Are you sure you want to permanently delete invoice
       <strong><?= e($inv['invoice_number']) ?></strong>?
       This will also delete all its line items and cannot be undone.
    </p>
    <form method="post" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="d-flex gap-2">
            <button type="submit" class="btn-tp-primary text-danger">
                <i class="fa-solid fa-trash me-1"></i> Yes, Delete
            </button>
            <a href="view.php?id=<?= $id ?>" class="btn-tp-ghost">Cancel</a>
        </div>
    </form>
</div>

<?php layout_end(); ?>
