<?php
/**
 * Dumpsters – Create
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once TMPL_PATH . '/layout.php';
require_login();
require_role('admin', 'office');

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

    // Check unit_code uniqueness
    if ($unit_code !== '' && empty($errors)) {
        $existing = db_fetch('SELECT id FROM dumpsters WHERE unit_code = ? LIMIT 1', [$unit_code]);
        if ($existing) {
            flash_error('A dumpster with that Unit Code already exists. Please use a unique code.');
            redirect('create.php');
        }
    }

    if (empty($errors)) {
        $id = db_insert('dumpsters', [
            'unit_code'  => $unit_code,
            'size'       => $size,
            'status'     => $status,
            'condition'  => $condition,
            'notes'      => $notes,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        log_activity('create', "Created dumpster $unit_code ($size)", 'dumpster', (int)$id);
        flash_success("Dumpster $unit_code added successfully.");
        redirect('index.php');
    }
}

// ── Pre-fill form values on validation failure ────────────────────────────────
$f = [
    'unit_code' => $_POST['unit_code'] ?? '',
    'size'      => $_POST['size']      ?? '',
    'status'    => $_POST['status']    ?? 'available',
    'condition' => $_POST['condition'] ?? 'good',
    'notes'     => $_POST['notes']     ?? '',
];

layout_start('Add Dumpster', 'inventory');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Add Dumpster</h5>
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
    <form method="POST" action="create.php">
        <?= csrf_field() ?>

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
                       value="<?= e($f['unit_code']) ?>"
                       placeholder="e.g. TP-001"
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
                    <option value="<?= e($sz) ?>" <?= $f['size'] === $sz ? 'selected' : '' ?>>
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
                    <option value="<?= e($val) ?>" <?= $f['status'] === $val ? 'selected' : '' ?>>
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
                    <option value="<?= e($val) ?>" <?= $f['condition'] === $val ? 'selected' : '' ?>>
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
                          rows="3"
                          placeholder="Optional notes about this dumpster…"><?= e($f['notes']) ?></textarea>
            </div>

            <!-- Submit -->
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn-tp-primary">
                    <i class="fa-solid fa-plus"></i> Add Dumpster
                </button>
                <a href="index.php" class="btn-tp-ghost">Cancel</a>
            </div>

        </div>
    </form>
</div>

<?php
layout_end();
