<?php
/**
 * Dumpsters – Edit
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once TMPL_PATH . '/layout.php';
require_login();
require_role('admin', 'office');

// ── Fetch dumpster ────────────────────────────────────────────────────────────
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    flash_error('Invalid dumpster ID.');
    redirect('index.php');
}

$dumpster = db_fetch('SELECT * FROM dumpsters WHERE id = ? LIMIT 1', [$id]);
if (!$dumpster) {
    flash_error('Dumpster not found.');
    redirect('index.php');
}

$errors = [];

// ── POST handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $unit_code = trim($_POST['unit_code'] ?? '');
    $size      = trim($_POST['size']      ?? '');
    $status    = trim($_POST['status']    ?? 'available');
    $condition = trim($_POST['condition'] ?? 'good');
    $notes     = trim($_POST['notes']     ?? '');

    // Required field validation
    if ($unit_code === '') {
        $errors[] = 'Unit Code is required.';
    }
    if ($size === '') {
        $errors[] = 'Size is required.';
    }

    // Validate size is in allowed list
    if ($size !== '' && !in_array($size, dumpster_sizes(), true)) {
        $errors[] = 'Invalid size selected.';
    }

    // If unit_code changed, check uniqueness
    if ($unit_code !== '' && $unit_code !== $dumpster['unit_code'] && empty($errors)) {
        $existing = db_fetch(
            'SELECT id FROM dumpsters WHERE unit_code = ? AND id != ? LIMIT 1',
            [$unit_code, $id]
        );
        if ($existing) {
            $errors[] = 'A dumpster with that Unit Code already exists. Please use a unique code.';
        }
    }

    if (empty($errors)) {
        db_update('dumpsters', [
            'unit_code'  => $unit_code,
            'size'       => $size,
            'status'     => $status,
            'condition'  => $condition,
            'notes'      => $notes,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id', $id);

        log_activity('update', "Updated dumpster $unit_code ($size)", 'dumpster', $id);
        flash_success("Dumpster $unit_code updated successfully.");
        redirect('index.php');
    }

    // Re-populate form values from POST on validation failure
    $dumpster = array_merge($dumpster, [
        'unit_code' => $unit_code,
        'size'      => $size,
        'status'    => $status,
        'condition' => $condition,
        'notes'     => $notes,
    ]);
}

layout_start('Edit Dumpster', 'inventory');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Edit Dumpster — <span class="text-muted"><?= e($dumpster['unit_code']) ?></span></h5>
    <a href="index.php" class="btn-tp-ghost btn-tp-sm">
        <i class="fa-solid fa-arrow-left"></i> Back to Inventory
    </a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $err): ?>
            <li><?= e($err) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="tp-card" style="max-width:640px;">
    <form method="POST" action="edit.php">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">

        <div class="row g-3">

            <!-- Unit Code -->
            <div class="col-md-6">
                <label class="form-label" for="unit_code">
                    Unit Code <span class="text-danger">*</span>
                </label>
                <input type="text"
                       id="unit_code"
                       name="unit_code"
                       class="form-control"
                       value="<?= e($dumpster['unit_code']) ?>"
                       required>
            </div>

            <!-- Size -->
            <div class="col-md-6">
                <label class="form-label" for="size">
                    Size <span class="text-danger">*</span>
                </label>
                <select id="size" name="size" class="form-select" required>
                    <option value="">— Select size —</option>
                    <?php foreach (dumpster_sizes() as $sz): ?>
                    <option value="<?= e($sz) ?>"
                            <?= $dumpster['size'] === $sz ? 'selected' : '' ?>>
                        <?= e($sz) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Status -->
            <div class="col-md-6">
                <label class="form-label" for="status">Status</label>
                <select id="status" name="status" class="form-select">
                    <?php foreach (['available' => 'Available', 'reserved' => 'Reserved', 'in_use' => 'In Use', 'maintenance' => 'Maintenance'] as $val => $label): ?>
                    <option value="<?= e($val) ?>"
                            <?= $dumpster['status'] === $val ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Condition -->
            <div class="col-md-6">
                <label class="form-label" for="condition">Condition</label>
                <select id="condition" name="condition" class="form-select">
                    <?php foreach (['excellent' => 'Excellent', 'good' => 'Good', 'fair' => 'Fair', 'poor' => 'Poor'] as $val => $label): ?>
                    <option value="<?= e($val) ?>"
                            <?= ($dumpster['condition'] ?? '') === $val ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Notes -->
            <div class="col-12">
                <label class="form-label" for="notes">Notes</label>
                <textarea id="notes"
                          name="notes"
                          class="form-control"
                          rows="3"><?= e($dumpster['notes'] ?? '') ?></textarea>
            </div>

            <!-- Submit -->
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn-tp-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Save Changes
                </button>
                <a href="index.php" class="btn-tp-ghost">Cancel</a>
            </div>

        </div>
    </form>
</div>

<?php
layout_end();
