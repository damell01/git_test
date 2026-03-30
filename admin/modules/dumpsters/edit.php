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

    $unit_code  = trim($_POST['unit_code']  ?? '');
    $type       = trim($_POST['type']       ?? 'dumpster');
    $size       = trim($_POST['size']       ?? '');
    $daily_rate = (float)($_POST['daily_rate'] ?? 0.00);
    $active     = isset($_POST['active']) ? 1 : 0;
    $status     = trim($_POST['status']     ?? 'available');
    $condition  = trim($_POST['condition']  ?? 'good');
    $notes      = trim($_POST['notes']      ?? '');
    $image      = $dumpster['image'] ?? null; // keep existing by default

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

    // Remove existing image if requested
    if (!empty($_POST['remove_image']) && $image) {
        $old_path = dirname(__DIR__, 3) . '/public' . $image;
        if (file_exists($old_path)) {
            @unlink($old_path);
        }
        $image = null;
    }

    // Handle new image upload
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
            // Remove old image if it exists
            if ($image) {
                $old_path = dirname(__DIR__, 3) . '/public' . $image;
                if (file_exists($old_path)) {
                    @unlink($old_path);
                }
            }
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
            'type'       => $type,
            'size'       => $size,
            'daily_rate' => $daily_rate,
            'active'     => $active,
            'status'     => $status,
            'condition'  => $condition,
            'notes'      => $notes,
            'image'      => $image,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id', $id);

        log_activity('update', "Updated dumpster $unit_code ($size)", 'dumpster', $id);
        flash_success("Dumpster $unit_code updated successfully.");
        redirect('index.php');
    }

    // Re-populate form values from POST on validation failure
    $dumpster = array_merge($dumpster, [
        'unit_code'  => $unit_code,
        'type'       => $type,
        'size'       => $size,
        'daily_rate' => $daily_rate,
        'active'     => $active,
        'status'     => $status,
        'condition'  => $condition,
        'notes'      => $notes,
        'image'      => $image,
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
    <form method="POST" action="edit.php" enctype="multipart/form-data">
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

            <!-- Type -->
            <div class="col-md-4">
                <label class="form-label" for="type">Type</label>
                <select id="type" name="type" class="form-select">
                    <option value="dumpster" <?= ($dumpster['type'] ?? 'dumpster') === 'dumpster' ? 'selected' : '' ?>>Dumpster</option>
                    <option value="trailer"  <?= ($dumpster['type'] ?? '') === 'trailer'  ? 'selected' : '' ?>>Trailer</option>
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
                       value="<?= e(number_format((float)($dumpster['daily_rate'] ?? 0), 2, '.', '')) ?>"
                       placeholder="0.00">
            </div>

            <!-- Active -->
            <div class="col-md-4 d-flex align-items-end pb-1">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="active" name="active" value="1"
                           <?= !empty($dumpster['active']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="active">Active (available for booking)</label>
                </div>
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

            <!-- Image -->
            <div class="col-12">
                <label class="form-label">Dumpster Photo</label>
                <?php if (!empty($dumpster['image'])): ?>
                <div class="mb-2">
                    <img src="<?= e($dumpster['image']) ?>" alt="Dumpster photo"
                         style="max-width:200px;border-radius:6px;border:1px solid var(--st);">
                    <div class="form-check mt-1">
                        <input class="form-check-input" type="checkbox" id="remove_image"
                               name="remove_image" value="1">
                        <label class="form-check-label text-danger" for="remove_image">
                            Remove current photo
                        </label>
                    </div>
                </div>
                <?php endif; ?>
                <input type="file" id="image" name="image" class="form-control"
                       accept="image/jpeg,image/png,image/gif,image/webp">
                <small class="text-muted">Max 5 MB — JPG, PNG, or WebP. Upload a new photo to replace the current one.</small>
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
