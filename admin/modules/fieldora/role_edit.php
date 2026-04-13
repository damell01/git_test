<?php
require_once __DIR__ . '/_bootstrap.php';

require_permission('team.manage');
require_feature('permissions_advanced');

$id = (int) ($_GET['id'] ?? 0);
$tenantId = current_tenant_id();
$role = db_fetch('SELECT * FROM roles WHERE tenant_id = ? AND id = ? LIMIT 1', [$tenantId, $id]);
if (!$role) {
    http_response_code(404);
    exit('Role not found');
}

$permissions = db_fetchall('SELECT * FROM permissions ORDER BY area ASC, name ASC');
$current = db_fetchall('SELECT permission_id FROM role_permissions WHERE role_id = ? AND allowed = 1', [$id]);
$currentIds = array_map(static fn($r) => (int) $r['permission_id'], $current);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    db_execute(
        'UPDATE roles SET name = ?, description = ?, is_active = ? WHERE id = ? AND tenant_id = ?',
        [trim((string) $_POST['name']), trim((string) ($_POST['description'] ?? '')), (int) ($_POST['is_active'] ?? 1), $id, $tenantId]
    );
    db_execute('DELETE FROM role_permissions WHERE role_id = ?', [$id]);
    foreach ((array) ($_POST['permission_ids'] ?? []) as $permissionId) {
        db_execute('INSERT INTO role_permissions (role_id, permission_id, allowed) VALUES (?, ?, 1)', [$id, (int) $permissionId]);
    }
    flash_success('Role updated.');
    redirect(APP_URL . '/modules/fieldora/roles.php');
}

fieldora_layout_start('Edit Role', 'roles');
?>
<form method="post" class="stack">
    <?= csrf_field() ?>
    <section class="card form-grid">
        <label><span>Name</span><input name="name" value="<?= e($role['name']) ?>" required></label>
        <label><span>Description</span><input name="description" value="<?= e((string) $role['description']) ?>" placeholder="Description"></label>
        <label><span>Status</span><select name="is_active"><option value="1"<?= (int) $role['is_active'] === 1 ? ' selected' : '' ?>>Active</option><option value="0"<?= (int) $role['is_active'] === 0 ? ' selected' : '' ?>>Inactive</option></select></label>
    </section>
    <section class="card">
        <h3>Permissions</h3>
        <?php foreach ($permissions as $permission): ?>
            <label class="service-option">
                <input type="checkbox" name="permission_ids[]" value="<?= (int) $permission['id'] ?>"<?= in_array((int) $permission['id'], $currentIds, true) ? ' checked' : '' ?>>
                <span><strong><?= e($permission['name']) ?></strong><small><?= e($permission['area']) ?> - <?= e($permission['description']) ?></small></span>
            </label>
        <?php endforeach; ?>
    </section>
    <button class="primary-btn" type="submit">Save role</button>
</form>
<?php fieldora_layout_end();
