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
    $type      = trim($_POST['type']      ?? 'dumpster');
    $size      = trim($_POST['size']      ?? '');
    $daily_rate   = (float)($_POST['daily_rate']   ?? 0.00);
    $weekly_rate  = (float)($_POST['weekly_rate']  ?? 0.00);
    $monthly_rate = (float)($_POST['monthly_rate'] ?? 0.00);
    $active    = isset($_POST['active']) ? 1 : 0;
    $status    = trim($_POST['status']    ?? 'available');
    $condition = trim($_POST['condition'] ?? 'good');
    $notes     = trim($_POST['notes']     ?? '');
    $image     = null;

    // Required field validation
    if ($unit_code === '') {
        $errors[] = 'Unit Code is required.';
    }
    if ($size === '') {
        $errors[] = 'Size is required.';
    }
    if (!in_array($type, ['dumpster', 'trailer'], true)) {
        $errors[] = 'Invalid type selected.';
    }

    // Validate size is in allowed list
    if ($size !== '' && !in_array($size, dumpster_sizes(), true)) {
        $errors[] = 'Invalid size selected.';
    }

    // Handle image upload
    if (!empty($_FILES['image']['name'])) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size      = 5 * 1024 * 1024; // 5 MB
        $upload_dir    = dirname(__DIR__, 3) . '/public/uploads/dumpsters/';

        $file_type = mime_content_type($_FILES['image']['tmp_name']);
        if (!in_array($file_type, $allowed_types, true)) {
            $errors[] = 'Invalid image type. Allowed: JPG, PNG, GIF, WebP.';
        } elseif ($_FILES['image']['size'] > $max_size) {
            $errors[] = 'Image must be under 5 MB.';
        } elseif ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Image upload failed. Please try again.';
        } else {
            $ext      = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $filename = 'dumpster_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $upload_dir . $filename)) {
                $errors[] = 'Failed to save image. Check directory permissions.';
            } else {
                $image = '/uploads/dumpsters/' . $filename;
            }
        }
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
        $insert_data = [
            'unit_code'    => $unit_code,
            'type'         => $type,
            'size'         => $size,
            'daily_rate'   => $daily_rate,
            'weekly_rate'  => $weekly_rate,
            'monthly_rate' => $monthly_rate,
            'active'       => $active,
            'status'       => $status,
            'condition'    => $condition,
            'notes'        => $notes,
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ];
        if ($image !== null) {
            $insert_data['image'] = $image;
        }
        $id = db_insert('dumpsters', $insert_data);

        log_activity('create', "Created dumpster $unit_code ($size)", 'dumpster', (int)$id);
        flash_success("Dumpster $unit_code added successfully.");
        redirect('index.php');
    }
}

// ── Pre-fill form values on validation failure ────────────────────────────────
$f = [
    'unit_code'  => $_POST['unit_code']  ?? '',
    'type'       => $_POST['type']       ?? 'dumpster',
    'size'       => $_POST['size']       ?? '',
    'daily_rate'   => $_POST['daily_rate']   ?? '0.00',
    'weekly_rate'  => $_POST['weekly_rate']  ?? '0.00',
    'monthly_rate' => $_POST['monthly_rate'] ?? '0.00',
    'active'     => isset($_POST['active']) ? 1 : (isset($_POST['unit_code']) ? 0 : 1),
    'status'     => $_POST['status']     ?? 'available',
    'condition'  => $_POST['condition']  ?? 'good',
    'notes'      => $_POST['notes']      ?? '',
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
    <form method="POST" action="create.php" enctype="multipart/form-data">
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

            <!-- Type -->
            <div class="col-md-4">
                <label class="form-label" for="type">Type</label>
                <select id="type" name="type" class="form-select">
                    <option value="dumpster" <?= $f['type'] === 'dumpster' ? 'selected' : '' ?>>Dumpster</option>
                    <option value="trailer"  <?= $f['type'] === 'trailer'  ? 'selected' : '' ?>>Trailer</option>
                </select>
            </div>

            <!-- Daily Rate -->
            <div class="col-md-4">
                <label class="form-label" for="daily_rate">Daily Rate ($)</label>
                <input type="number"
                       id="daily_rate"
                       name="daily_rate"
                       class="form-control"
                       step="0.01"
                       min="0"
                       value="<?= e(number_format((float)$f['daily_rate'], 2, '.', '')) ?>"
                       placeholder="0.00">
            </div>

            <!-- Weekly Rate -->
            <div class="col-md-4">
                <label class="form-label" for="weekly_rate">Weekly Rate ($) <small class="text-muted">optional</small></label>
                <input type="number"
                       id="weekly_rate"
                       name="weekly_rate"
                       class="form-control"
                       step="0.01"
                       min="0"
                       value="<?= e(number_format((float)$f['weekly_rate'], 2, '.', '')) ?>"
                       placeholder="0.00">
            </div>

            <!-- Monthly Rate -->
            <div class="col-md-4">
                <label class="form-label" for="monthly_rate">Monthly Rate ($) <small class="text-muted">optional</small></label>
                <input type="number"
                       id="monthly_rate"
                       name="monthly_rate"
                       class="form-control"
                       step="0.01"
                       min="0"
                       value="<?= e(number_format((float)$f['monthly_rate'], 2, '.', '')) ?>"
                       placeholder="0.00">
            </div>

            <!-- Active -->
            <div class="col-md-4 d-flex align-items-end pb-1">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="active" name="active" value="1"
                           <?= $f['active'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="active">Active (available for booking)</label>
                </div>
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

            <!-- Image -->
            <div class="col-12">
                <label class="form-label" for="image">
                    Dumpster Photo <span style="color:var(--gy);font-size:.8rem;">(optional, max 5 MB — JPG/PNG/GIF/WebP)</span>
                </label>
                <input type="file" id="image" name="image" class="form-control"
                       accept="image/jpeg,image/png,image/gif,image/webp">
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
