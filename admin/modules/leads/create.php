<?php
/**
 * Leads – Create
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once TMPL_PATH . '/layout.php';

require_login();
require_role('admin', 'office');

$errors = [];
$data   = [
    'name'         => '',
    'phone'        => '',
    'email'        => '',
    'address'      => '',
    'city'         => '',
    'state'        => '',
    'zip'          => '',
    'size_needed'  => '',
    'project_type' => '',
    'source'       => '',
    'status'       => 'new',
    'assigned_to'  => '',
    'message'      => '',
];

// ── Handle POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    // Collect input
    $data = [
        'name'         => trim($_POST['name']         ?? ''),
        'phone'        => trim($_POST['phone']        ?? ''),
        'email'        => trim($_POST['email']        ?? ''),
        'address'      => trim($_POST['address']      ?? ''),
        'city'         => trim($_POST['city']         ?? ''),
        'state'        => trim($_POST['state']        ?? ''),
        'zip'          => trim($_POST['zip']          ?? ''),
        'size_needed'  => trim($_POST['size_needed']  ?? ''),
        'project_type' => trim($_POST['project_type'] ?? ''),
        'source'       => trim($_POST['source']       ?? ''),
        'status'       => trim($_POST['status']       ?? 'new'),
        'assigned_to'  => trim($_POST['assigned_to']  ?? ''),
        'message'      => trim($_POST['message']      ?? ''),
    ];

    // Validate
    $errors = validate_required(['name', 'phone'], $data);

    if (empty($errors)) {
        $insert = [
            'name'         => $data['name'],
            'phone'        => $data['phone'],
            'email'        => $data['email'],
            'address'      => $data['address'],
            'city'         => $data['city'],
            'state'        => $data['state'],
            'zip'          => $data['zip'],
            'size_needed'  => $data['size_needed'],
            'project_type' => $data['project_type'],
            'source'       => $data['source'],
            'status'       => in_array($data['status'], ['new','contacted','quoted','won','lost'], true)
                                ? $data['status'] : 'new',
            'assigned_to'  => $data['assigned_to'] !== '' ? (int)$data['assigned_to'] : null,
            'message'      => $data['message'],
            'archived'     => 0,
            'created_at'   => date('Y-m-d H:i:s'),
            'updated_at'   => date('Y-m-d H:i:s'),
        ];

        $lead_id = (int)db_insert('leads', $insert);

        log_activity('create', 'Created lead: ' . $data['name'], 'lead', $lead_id);

        flash_success('Lead "' . $data['name'] . '" created successfully.');
        redirect(APP_URL . '/modules/leads/view.php?id=' . $lead_id);
    }
}

// ── Fetch users for assignment select ────────────────────────────────────────
$users = db_fetchall("SELECT id, name FROM users WHERE active = 1 ORDER BY name ASC");

layout_start('New Lead', 'leads');
?>

<div class="tp-page-header d-flex align-items-center justify-content-between mb-3">
    <div>
        <a href="<?= APP_URL ?>/modules/leads/index.php" class="text-muted small text-decoration-none">
            <i class="fa-solid fa-arrow-left"></i> Back to Leads
        </a>
        <h2 class="tp-page-title mb-0 mt-1">New Lead</h2>
    </div>
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

<div class="tp-card">
    <form method="post" action="<?= APP_URL ?>/modules/leads/create.php" novalidate>
        <?= csrf_field() ?>

        <div class="tp-card-header mb-3">
            <h5 class="mb-0">Contact Information</h5>
        </div>

        <div class="row g-3">
            <!-- Name -->
            <div class="col-md-6">
                <label for="name" class="form-label">
                    Full Name <span class="text-danger">*</span>
                </label>
                <input type="text" id="name" name="name" class="form-control"
                       value="<?= e($data['name']) ?>" required>
            </div>

            <!-- Phone -->
            <div class="col-md-3">
                <label for="phone" class="form-label">
                    Phone <span class="text-danger">*</span>
                </label>
                <input type="tel" id="phone" name="phone" class="form-control"
                       value="<?= e($data['phone']) ?>" required>
            </div>

            <!-- Email -->
            <div class="col-md-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" id="email" name="email" class="form-control"
                       value="<?= e($data['email']) ?>">
            </div>

            <!-- Address -->
            <div class="col-md-6">
                <label for="address" class="form-label">Address</label>
                <input type="text" id="address" name="address" class="form-control"
                       value="<?= e($data['address']) ?>">
            </div>

            <!-- City -->
            <div class="col-md-3">
                <label for="city" class="form-label">City</label>
                <input type="text" id="city" name="city" class="form-control"
                       value="<?= e($data['city']) ?>">
            </div>

            <!-- State -->
            <div class="col-md-1">
                <label for="state" class="form-label">State</label>
                <input type="text" id="state" name="state" class="form-control"
                       value="<?= e($data['state']) ?>" maxlength="2">
            </div>

            <!-- Zip -->
            <div class="col-md-2">
                <label for="zip" class="form-label">ZIP</label>
                <input type="text" id="zip" name="zip" class="form-control"
                       value="<?= e($data['zip']) ?>" maxlength="10">
            </div>
        </div>

        <hr class="my-4">

        <div class="tp-card-header mb-3">
            <h5 class="mb-0">Project Details</h5>
        </div>

        <div class="row g-3">
            <!-- Size Needed -->
            <div class="col-md-3">
                <label for="size_needed" class="form-label">Dumpster Size</label>
                <select id="size_needed" name="size_needed" class="form-select">
                    <option value="">— Select Size —</option>
                    <?php foreach (dumpster_sizes() as $sz): ?>
                        <option value="<?= e($sz) ?>" <?= $data['size_needed'] === $sz ? 'selected' : '' ?>>
                            <?= e($sz) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Project Type -->
            <div class="col-md-3">
                <label for="project_type" class="form-label">Project Type</label>
                <select id="project_type" name="project_type" class="form-select">
                    <option value="">— Select Type —</option>
                    <?php foreach (project_types() as $pt): ?>
                        <option value="<?= e($pt) ?>" <?= $data['project_type'] === $pt ? 'selected' : '' ?>>
                            <?= e($pt) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Source -->
            <div class="col-md-3">
                <label for="source" class="form-label">Lead Source</label>
                <select id="source" name="source" class="form-select">
                    <option value="">— Select Source —</option>
                    <?php foreach (lead_sources() as $src): ?>
                        <option value="<?= e($src) ?>" <?= $data['source'] === $src ? 'selected' : '' ?>>
                            <?= e($src) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Status -->
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select id="status" name="status" class="form-select">
                    <?php foreach (['new' => 'New', 'contacted' => 'Contacted', 'quoted' => 'Quoted', 'won' => 'Won', 'lost' => 'Lost'] as $val => $lbl): ?>
                        <option value="<?= e($val) ?>" <?= $data['status'] === $val ? 'selected' : '' ?>>
                            <?= e($lbl) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Assigned To -->
            <div class="col-md-4">
                <label for="assigned_to" class="form-label">Assigned To</label>
                <select id="assigned_to" name="assigned_to" class="form-select">
                    <option value="">— Unassigned —</option>
                    <?php foreach ($users as $u): ?>
                        <option value="<?= (int)$u['id'] ?>"
                                <?= (string)$data['assigned_to'] === (string)$u['id'] ? 'selected' : '' ?>>
                            <?= e($u['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Message -->
            <div class="col-12">
                <label for="message" class="form-label">Message / Notes</label>
                <textarea id="message" name="message" class="form-control" rows="4"><?= e($data['message']) ?></textarea>
            </div>
        </div>

        <div class="d-flex gap-2 mt-4">
            <button type="submit" class="btn-tp-primary">
                <i class="fa-solid fa-floppy-disk"></i> Create Lead
            </button>
            <a href="<?= APP_URL ?>/modules/leads/index.php" class="btn-tp-ghost">
                Cancel
            </a>
        </div>

    </form>
</div>

<?php layout_end(); ?>
