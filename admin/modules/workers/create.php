<?php
/**
 * Workers – Create
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once TMPL_PATH . '/layout.php';
require_login();
require_role('admin', 'office');

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
        $id = db_insert('workers', [
            'name'       => $name,
            'phone'      => $phone ?: null,
            'active'     => $active,
            'notes'      => $notes ?: null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        log_activity('create', "Created worker: $name", 'worker', (int)$id);
        flash_success("Worker <strong>" . e($name) . "</strong> added successfully.");
        redirect('index.php');
    }
}

$f = [
    'name'   => $_POST['name']   ?? '',
    'phone'  => $_POST['phone']  ?? '',
    'active' => isset($_POST['name']) ? (isset($_POST['active']) ? 1 : 0) : 1,
    'notes'  => $_POST['notes']  ?? '',
];

layout_start('Add Worker', 'workers');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Add Worker</h5>
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
    <form method="POST" action="create.php">
        <?= csrf_field() ?>
        <div class="row g-3">

            <div class="col-12">
                <label class="form-label" for="name">Name <span class="text-danger">*</span></label>
                <input type="text" id="name" name="name" class="form-control"
                       value="<?= e($f['name']) ?>" placeholder="e.g. John Smith" required>
            </div>

            <div class="col-12">
                <label class="form-label" for="phone">Phone <small class="text-muted">optional</small></label>
                <input type="tel" id="phone" name="phone" class="form-control"
                       value="<?= e($f['phone']) ?>" placeholder="e.g. 555-867-5309">
            </div>

            <div class="col-12">
                <label class="form-label" for="notes">Notes <small class="text-muted">optional</small></label>
                <textarea id="notes" name="notes" class="form-control" rows="2"
                          placeholder="Optional notes (license, skills, etc.)"><?= e($f['notes']) ?></textarea>
            </div>

            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="active" name="active"
                           value="1" <?= $f['active'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="active">Active (available for assignment)</label>
                </div>
            </div>

            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn-tp-primary">
                    <i class="fa-solid fa-plus"></i> Add Worker
                </button>
                <a href="index.php" class="btn-tp-ghost">Cancel</a>
            </div>
        </div>
    </form>
</div>

<?php layout_end(); ?>
