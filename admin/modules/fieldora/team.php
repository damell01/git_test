<?php
require_once __DIR__ . '/_bootstrap.php';

require_permission('team.manage');
require_feature('team');

$tenantId = current_tenant_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    if ($email === '' || trim((string) ($_POST['name'] ?? '')) === '') {
        flash_error('Name and email are required.');
        redirect($_SERVER['REQUEST_URI']);
    }

    $existing = db_fetch('SELECT id FROM users WHERE email = ? LIMIT 1', [$email]);
    if ($existing) {
        flash_error('A user with that email already exists.');
        redirect($_SERVER['REQUEST_URI']);
    }

    $password = bin2hex(random_bytes(4));
    $userId = (int) db_insert('users', [
        'tenant_id' => $tenantId,
        'name' => trim((string) $_POST['name']),
        'email' => $email,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'role' => trim((string) ($_POST['role'] ?? 'staff')),
        'theme_preference' => 'dark',
        'active' => 1,
        'must_change_pw' => 1,
        'created_at' => date('Y-m-d H:i:s'),
        'updated_at' => date('Y-m-d H:i:s'),
    ]);

    $role = db_fetch('SELECT id FROM roles WHERE tenant_id = ? AND role_key = ? LIMIT 1', [$tenantId, trim((string) ($_POST['role'] ?? 'staff'))]);
    if ($role) {
        db_execute('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)', [$userId, $role['id']]);
    }

    flash_success('Team member created. Temporary password: ' . $password);
    redirect($_SERVER['REQUEST_URI']);
}

$search = trim((string) ($_GET['search'] ?? ''));
$users = db_fetchall(
    'SELECT * FROM users
     WHERE tenant_id = ? AND deleted_at IS NULL AND (? = "" OR name LIKE ? OR email LIKE ?)
     ORDER BY created_at DESC',
    [$tenantId, $search, '%' . $search . '%', '%' . $search . '%']
);

fieldora_layout_start('Team', 'team');
?>
<form method="post" class="card form-grid">
    <?= csrf_field() ?>
    <label><span>Name</span><input name="name" placeholder="Name" required></label>
    <label><span>Email</span><input name="email" type="email" placeholder="Email" required></label>
    <label><span>Role</span><select name="role"><option value="owner">Owner</option><option value="manager">Manager</option><option value="staff">Staff</option><option value="accounting">Accounting</option></select></label>
    <button class="primary-btn" type="submit">Add team member</button>
</form>
<form method="get" class="card form-grid" style="margin-top:20px;">
    <label><span>Search team</span><input name="search" value="<?= e($search) ?>" placeholder="Search team"></label>
    <button class="primary-btn" type="submit">Filter</button>
</form>
<section class="table-wrap" style="margin-top:20px;">
    <table>
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($users as $row): ?>
            <tr>
                <td><a href="<?= e(APP_URL) ?>/modules/fieldora/team_edit.php?id=<?= (int) $row['id'] ?>"><?= e($row['name']) ?></a></td>
                <td><?= e($row['email']) ?></td>
                <td><?= e($row['role']) ?></td>
                <td><?= $row['active'] ? 'Active' : 'Inactive' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php fieldora_layout_end();
