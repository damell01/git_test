<?php
/**
 * Settings – Create User
 * Trash Panda Roll-Offs
 */

require_once dirname(__DIR__, 2) . '/includes/bootstrap.php';
require_once TMPL_PATH . '/layout.php';
require_login();
require_role('admin');

$errors = [];

// ── POST handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $name            = trim($_POST['name']             ?? '');
    $email           = trim($_POST['email']            ?? '');
    $role            = trim($_POST['role']             ?? 'readonly');
    $active          = isset($_POST['active'])          ? 1 : 0;
    $must_change_pw  = isset($_POST['must_change_pw'])  ? 1 : 0;
    $password        = $_POST['password']              ?? '';
    $password_confirm = $_POST['password_confirm']     ?? '';

    // Required field validation
    if ($name === '') {
        $errors[] = 'Name is required.';
    }
    if ($email === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    if ($password === '') {
        $errors[] = 'Password is required.';
    }
    if ($password !== $password_confirm) {
        $errors[] = 'Passwords do not match.';
    }
    if ($password !== '' && strlen($password) < 8) {
        $errors[] = 'Password must be at least 8 characters.';
    }

    // Validate role
    if (!array_key_exists($role, user_roles())) {
        $errors[] = 'Invalid role selected.';
    }

    // Check email uniqueness
    if ($email !== '' && empty($errors)) {
        $existing = db_fetch('SELECT id FROM users WHERE email = ? LIMIT 1', [$email]);
        if ($existing) {
            $errors[] = 'A user with that email address already exists.';
        }
    }

    if (empty($errors)) {
        $hashed = password_hash($password, PASSWORD_BCRYPT);

        $uid = db_insert('users', [
            'name'           => $name,
            'email'          => $email,
            'password'       => $hashed,
            'role'           => $role,
            'active'         => $active,
            'must_change_pw' => $must_change_pw,
            'created_at'     => date('Y-m-d H:i:s'),
            'updated_at'     => date('Y-m-d H:i:s'),
        ]);

        log_activity('create', "Created user $name ($email)", 'user', (int)$uid);
        flash_success("User $name created successfully.");
        redirect('users.php');
    }
}

// ── Pre-fill form values on error ─────────────────────────────────────────────
$f = [
    'name'           => $_POST['name']           ?? '',
    'email'          => $_POST['email']          ?? '',
    'role'           => $_POST['role']           ?? 'readonly',
    'active'         => isset($_POST['active'])   ? (bool)$_POST['active']         : true,
    'must_change_pw' => isset($_POST['must_change_pw']) ? (bool)$_POST['must_change_pw'] : false,
];

layout_start('Create User', 'users');
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h5 class="mb-0">Create New User</h5>
    <a href="users.php" class="btn-tp-ghost btn-tp-sm">
        <i class="fa-solid fa-arrow-left"></i> Back to Users
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
    <form method="POST" action="create_user.php">
        <?= csrf_field() ?>

        <div class="row g-3">

            <!-- Name -->
            <div class="col-md-6">
                <label class="form-label" for="name">
                    Name <span class="text-danger">*</span>
                </label>
                <input type="text"
                       id="name"
                       name="name"
                       class="form-control"
                       value="<?= e($f['name']) ?>"
                       required>
            </div>

            <!-- Email -->
            <div class="col-md-6">
                <label class="form-label" for="email">
                    Email <span class="text-danger">*</span>
                </label>
                <input type="email"
                       id="email"
                       name="email"
                       class="form-control"
                       value="<?= e($f['email']) ?>"
                       required>
            </div>

            <!-- Role -->
            <div class="col-md-6">
                <label class="form-label" for="role">Role</label>
                <select id="role" name="role" class="form-select">
                    <?php foreach (user_roles() as $val => $label): ?>
                    <option value="<?= e($val) ?>"
                            <?= $f['role'] === $val ? 'selected' : '' ?>>
                        <?= e($label) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Password -->
            <div class="col-md-6">
                <label class="form-label" for="password">
                    Password <span class="text-danger">*</span>
                </label>
                <input type="password"
                       id="password"
                       name="password"
                       class="form-control"
                       autocomplete="new-password"
                       required>
                <div class="form-text">Minimum 8 characters.</div>
            </div>

            <!-- Confirm Password -->
            <div class="col-md-6">
                <label class="form-label" for="password_confirm">
                    Confirm Password <span class="text-danger">*</span>
                </label>
                <input type="password"
                       id="password_confirm"
                       name="password_confirm"
                       class="form-control"
                       autocomplete="new-password"
                       required>
            </div>

            <!-- Active -->
            <div class="col-md-3">
                <div class="form-check mt-4">
                    <input type="checkbox"
                           id="active"
                           name="active"
                           class="form-check-input"
                           value="1"
                           <?= $f['active'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="active">Active</label>
                </div>
            </div>

            <!-- Must Change Password -->
            <div class="col-md-9">
                <div class="form-check mt-4">
                    <input type="checkbox"
                           id="must_change_pw"
                           name="must_change_pw"
                           class="form-check-input"
                           value="1"
                           <?= $f['must_change_pw'] ? 'checked' : '' ?>>
                    <label class="form-check-label" for="must_change_pw">
                        Require password change on first login
                    </label>
                </div>
            </div>

            <!-- Submit -->
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn-tp-primary">
                    <i class="fa-solid fa-user-plus"></i> Create User
                </button>
                <a href="users.php" class="btn-tp-ghost">Cancel</a>
            </div>

        </div>
    </form>
</div>

<?php
layout_end();
