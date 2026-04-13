<?php
require_once __DIR__ . '/_bootstrap.php';

require_permission('team.manage');
require_feature('team');

$id = (int) ($_GET['id'] ?? 0);
$tenantId = current_tenant_id();
$row = db_fetch('SELECT * FROM users WHERE tenant_id = ? AND id = ? LIMIT 1', [$tenantId, $id]);
if (!$row) {
    http_response_code(404);
    exit('User not found');
}

$roles = db_fetchall('SELECT * FROM roles WHERE tenant_id = ? ORDER BY name ASC', [$tenantId]);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $existing = db_fetch('SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1', [$email, $id]);
    if ($existing) {
        flash_error('Another user already uses that email.');
        redirect($_SERVER['REQUEST_URI']);
    }

    db_execute(
        'UPDATE users SET name = ?, email = ?, phone = ?, role = ?, active = ?, theme_preference = ?, updated_at = NOW() WHERE id = ? AND tenant_id = ?',
        [
            trim((string) $_POST['name']),
            $email,
            trim((string) ($_POST['phone'] ?? '')),
            trim((string) ($_POST['role'] ?? 'staff')),
            (int) ($_POST['active'] ?? 1),
            trim((string) ($_POST['theme_preference'] ?? 'dark')),
            $id,
            $tenantId,
        ]
    );
    db_execute('DELETE FROM user_roles WHERE user_id = ?', [$id]);
    foreach ((array) ($_POST['role_ids'] ?? []) as $roleId) {
        db_execute('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)', [$id, (int) $roleId]);
    }
    flash_success('Team member updated.');
    redirect(APP_URL . '/modules/fieldora/team.php');
}

$assigned = db_fetchall('SELECT role_id FROM user_roles WHERE user_id = ?', [$id]);
$assignedIds = array_map(static fn($r) => (int) $r['role_id'], $assigned);

fieldora_layout_start('Edit Team Member', 'team');
?>
<form method="post" class="card form-grid">
    <?= csrf_field() ?>
    <label><span>Name</span><input name="name" value="<?= e($row['name']) ?>" required></label>
    <label><span>Email</span><input name="email" type="email" value="<?= e($row['email']) ?>" required></label>
    <label><span>Phone</span><input name="phone" value="<?= e((string) $row['phone']) ?>" placeholder="Phone"></label>
    <label><span>Primary role</span><select name="role"><option value="owner"<?= $row['role'] === 'owner' ? ' selected' : '' ?>>Owner</option><option value="manager"<?= $row['role'] === 'manager' ? ' selected' : '' ?>>Manager</option><option value="staff"<?= $row['role'] === 'staff' ? ' selected' : '' ?>>Staff</option><option value="accounting"<?= $row['role'] === 'accounting' ? ' selected' : '' ?>>Accounting</option></select></label>
    <label><span>Status</span><select name="active"><option value="1"<?= (int) $row['active'] === 1 ? ' selected' : '' ?>>Active</option><option value="0"<?= (int) $row['active'] === 0 ? ' selected' : '' ?>>Inactive</option></select></label>
    <label><span>Theme</span><select name="theme_preference"><option value="dark"<?= $row['theme_preference'] === 'dark' ? ' selected' : '' ?>>Dark</option><option value="light"<?= $row['theme_preference'] === 'light' ? ' selected' : '' ?>>Light</option></select></label>
    <div class="card" style="grid-column:1/-1">
        <strong>Assigned roles</strong>
        <?php foreach ($roles as $role): ?>
            <label class="service-option">
                <input type="checkbox" name="role_ids[]" value="<?= (int) $role['id'] ?>"<?= in_array((int) $role['id'], $assignedIds, true) ? ' checked' : '' ?>>
                <span><?= e($role['name']) ?> <small><?= e((string) $role['description']) ?></small></span>
            </label>
        <?php endforeach; ?>
    </div>
    <button class="primary-btn" type="submit">Save team member</button>
</form>
<?php fieldora_layout_end();
