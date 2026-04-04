<?php
/**
 * Workers – Edit
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once TMPL_PATH . '/layout.php';
require_login();
require_role('admin', 'office');

$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    flash_error('Invalid worker ID.');
    redirect('index.php');
}

$worker = db_fetch('SELECT * FROM workers WHERE id = ? LIMIT 1', [$id]);
if (!$worker) {
    flash_error('Worker not found.');
    redirect('index.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $name   = trim($_POST['name']   ?? '');
    $phone  = trim($_POST['phone']  ?? '');
    $active = isset($_POST['active']) ? 1 : 0;
    $notes  = trim($_POST['notes']  ?? '');

    if ($name === '') {
        $errors[] = 'Name is required.';
    }

    if (empty($errors)) {
        db_update('workers', [
            'name'       => $name,
            'phone'      => $phone ?: null,
            'active'     => $active,
            'notes'      => $notes ?: null,
            'updated_at' => date('Y-m-d H:i:s'),
        ], 'id', $id);

        log_activity('update', "Updated worker: $name", 'worker', $id);
        flash_success("Worker <strong>" . e($name) . "</strong> updated.");
        redirect('index.php');
    }

    $worker = array_merge($worker, [
        'name'   => $name,
        'phone'  => $phone,
        'active' => $active,
        'notes'  => $notes,
    ]);
}

layout_start('Edit Worker', 'workers');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Edit Worker</h5>
    <a href="index.php" class="btn-tp-ghost btn-tp-sm">
        <i class="fa-solid fa-arrow-left"></i> Back
    </a>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <ul class="mb-0">
        <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="tp-card" style="max-width:520px;">
    <form method="POST" action="edit.php">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= $id ?>">
        <div class="row g-3">

            <div class="col-12">
                <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                <input type="text" id="name" name="name" class="form-control"
                       value="<?= e($worker['name']) ?>" required>
            </div>

            <div class="col-12">
                <label class="form-label" for="phone">Phone <small class="text-muted">optional</small></label>
                <input type="tel" id="phone" name="phone" class="form-control"
                       value="<?= e($worker['phone'] ?? '') ?>">
            </div>

            <div class="col-12">
                <label class="form-label" for="notes">Notes <small class="text-muted">optional</small></label>
                <textarea id="notes" name="notes" class="form-control" rows="2"><?= e($worker['notes'] ?? '') ?></textarea>
            </div>

            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="active" name="active"
                           value="1" <?= $worker['active'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="active">Active</label>
                </div>
            </div>

            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn-tp-primary">
                    <i class="fa-solid fa-floppy-disk"></i> Save Changes
                </button>
                <a href="index.php" class="btn-tp-ghost">Cancel</a>
            </div>
        </div>
    </form>
</div>

<?php layout_end(); ?>
