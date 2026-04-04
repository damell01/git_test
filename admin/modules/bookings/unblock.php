<?php
/**
 * Bookings – Remove Inventory Block
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_login();
require_role('admin', 'office');

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    flash_error('Invalid block ID.');
    redirect(APP_URL . '/modules/calendar/index.php');
}

$block = db_fetch('SELECT * FROM inventory_blocks WHERE id = ? LIMIT 1', [$id]);
if (!$block) {
    flash_error('Inventory block not found.');
    redirect(APP_URL . '/modules/calendar/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    db_query('DELETE FROM inventory_blocks WHERE id = ?', [$id]);
    log_activity('delete', "Removed inventory block #$id for dumpster #{$block['dumpster_id']} ({$block['block_start']} – {$block['block_end']})", 'inventory_block', $id);
    flash_success('Inventory block removed.');
    redirect(APP_URL . '/modules/calendar/index.php');
}

// GET: confirm form
require_once TMPL_PATH . '/layout.php';
layout_start('Remove Inventory Block', 'calendar');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Remove Inventory Block</h5>
    <a href="<?= e(APP_URL) ?>/modules/calendar/index.php" class="btn-tp-ghost btn-tp-sm">
        <i class="fa-solid fa-arrow-left"></i> Back to Calendar
    </a>
</div>

<div class="tp-card" style="max-width:480px;">
    <p class="mb-3">
        Are you sure you want to remove the following inventory block?
    </p>
    <table class="table table-sm mb-3">
        <tr>
            <th>Unit</th>
            <td><?php
                $unit = db_fetch('SELECT unit_code, size FROM dumpsters WHERE id = ? LIMIT 1', [$block['dumpster_id']]);
                echo e(($unit['unit_code'] ?? 'Unknown') . ($unit['size'] ? ' — ' . $unit['size'] : ''));
            ?></td>
        </tr>
        <tr>
            <th>Block Start</th>
            <td><?= e(fmt_date($block['block_start'])) ?></td>
        </tr>
        <tr>
            <th>Block End</th>
            <td><?= e(fmt_date($block['block_end'])) ?></td>
        </tr>
        <tr>
            <th>Reason</th>
            <td><?= e($block['reason'] ?: '—') ?></td>
        </tr>
    </table>
    <form method="POST" action="unblock.php?id=<?= (int)$id ?>">
        <?= csrf_field() ?>
        <div class="d-flex gap-2">
            <button type="submit" class="btn-tp-danger">
                <i class="fa-solid fa-trash"></i> Remove Block
            </button>
            <a href="<?= e(APP_URL) ?>/modules/calendar/index.php" class="btn-tp-ghost">Cancel</a>
        </div>
    </form>
</div>

<?php
layout_end();
