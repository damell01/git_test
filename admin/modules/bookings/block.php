<?php
/**
 * Bookings – Inventory Block (Create)
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once TMPL_PATH . '/layout.php';
require_login();
require_role('admin', 'office');

$errors = [];

$units = db_fetchall(
    "SELECT id, unit_code, size, type FROM dumpsters WHERE active = 1 ORDER BY size, unit_code"
);

$prefill_unit = (int)($_GET['dumpster_id'] ?? 0);

// ── POST handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $dumpster_id = (int)($_POST['dumpster_id'] ?? 0);
    $block_start = trim($_POST['block_start'] ?? '');
    $block_end   = trim($_POST['block_end']   ?? '');
    $reason      = trim($_POST['reason']      ?? '');

    if ($dumpster_id <= 0) {
        $errors[] = 'Please select a unit.';
    }
    if ($block_start === '') {
        $errors[] = 'Block Start date is required.';
    }
    if ($block_end === '') {
        $errors[] = 'Block End date is required.';
    }
    if (empty($errors) && $block_start > $block_end) {
        $errors[] = 'Block End must be on or after Block Start.';
    }

    if (empty($errors)) {
        $unit = db_fetch('SELECT id FROM dumpsters WHERE id = ? LIMIT 1', [$dumpster_id]);
        if (!$unit) {
            $errors[] = 'Selected unit not found.';
        }
    }

    if (empty($errors)) {
        db_insert('inventory_blocks', [
            'dumpster_id' => $dumpster_id,
            'block_start' => $block_start,
            'block_end'   => $block_end,
            'reason'      => $reason ?: null,
            'created_by'  => (int)($_SESSION['user_id'] ?? 0),
            'created_at'  => date('Y-m-d H:i:s'),
        ]);

        log_activity('create', "Created inventory block for dumpster #$dumpster_id ($block_start – $block_end)", 'inventory_block', 0);
        flash_success('Inventory block created.');

        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        if ($ref && strpos($ref, APP_URL) === 0) {
            redirect($ref);
        }
        redirect('index.php');
    }

    $prefill_unit = (int)($_POST['dumpster_id'] ?? 0);
}

layout_start('Create Inventory Block', 'bookings');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Create Inventory Block</h5>
    <a href="index.php" class="btn-tp-ghost btn-tp-sm">
        <i class="fa-solid fa-arrow-left"></i> Back to Bookings
    </a>
</div>

<p class="text-muted mb-4" style="font-size:.9rem;">
    Inventory blocks prevent a unit from being booked online during the specified period.
</p>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="tp-card" style="max-width:520px;">
    <form method="POST" action="block.php">
        <?= csrf_field() ?>

        <div class="row g-3">
            <div class="col-12">
                <label class="form-label" for="dumpster_id">
                    Unit <span class="text-danger">*</span>
                </label>
                <select id="dumpster_id" name="dumpster_id" class="form-select" required>
                    <option value="">— Select unit —</option>
                    <?php foreach ($units as $u): ?>
                    <option value="<?= (int)$u['id'] ?>"
                            <?= $prefill_unit === (int)$u['id'] ? 'selected' : '' ?>>
                        <?= e($u['unit_code']) ?> — <?= e($u['size']) ?> (<?= e(ucfirst($u['type'])) ?>)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-6">
                <label class="form-label" for="block_start">
                    Block Start <span class="text-danger">*</span>
                </label>
                <input type="date" id="block_start" name="block_start" class="form-control"
                       value="<?= e($_POST['block_start'] ?? '') ?>" required>
            </div>

            <div class="col-md-6">
                <label class="form-label" for="block_end">
                    Block End <span class="text-danger">*</span>
                </label>
                <input type="date" id="block_end" name="block_end" class="form-control"
                       value="<?= e($_POST['block_end'] ?? '') ?>" required>
            </div>

            <div class="col-12">
                <label class="form-label" for="reason">Reason</label>
                <input type="text" id="reason" name="reason" class="form-control"
                       value="<?= e($_POST['reason'] ?? '') ?>"
                       placeholder="e.g. Maintenance, Reserved for event…">
            </div>

            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn-tp-primary">
                    <i class="fa-solid fa-lock"></i> Create Block
                </button>
                <a href="index.php" class="btn-tp-ghost">Cancel</a>
            </div>
        </div>
    </form>
</div>

<?php
layout_end();
