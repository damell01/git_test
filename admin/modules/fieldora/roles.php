<?php
require_once __DIR__ . '/_bootstrap.php';

require_permission('team.manage');
require_feature('permissions_advanced');

$tenantId = current_tenant_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $name = trim((string) ($_POST['name'] ?? ''));
    if ($name === '') {
        flash_error('Role name is required.');
        redirect($_SERVER['REQUEST_URI']);
    }

    $roleKey = strtolower(preg_replace('/[^a-z0-9]+/', '_', $name) ?: 'custom_role');
    db_execute(
        'INSERT INTO roles (tenant_id, role_key, name, description, is_system, is_active, created_at)
         VALUES (?, ?, ?, ?, 0, 1, NOW())',
        [$tenantId, $roleKey . '_' . substr(md5($name . microtime(true)), 0, 6), $name, trim((string) ($_POST['description'] ?? ''))]
    );
    flash_success('Role created.');
    redirect($_SERVER['REQUEST_URI']);
}

$roles = db_fetchall('SELECT * FROM roles WHERE tenant_id = ? ORDER BY is_system DESC, name ASC', [$tenantId]);
fieldora_layout_start('Roles', 'roles');
?>
<form method="post" class="card form-grid">
    <?= csrf_field() ?>
    <label><span>Role name</span><input name="name" placeholder="Operations Lead" required></label>
    <label><span>Description</span><input name="description" placeholder="Short role description"></label>
    <button class="primary-btn" type="submit">Create role</button>
</form>
<div class="grid two" style="margin-top:20px;">
<?php foreach ($roles as $role): $permissions = db_fetchall('SELECT p.name FROM role_permissions rp INNER JOIN permissions p ON p.id = rp.permission_id WHERE rp.role_id = ? AND rp.allowed = 1 ORDER BY p.area ASC, p.name ASC', [$role['id']]); ?>
    <section class="card">
        <h3><a href="<?= e(APP_URL) ?>/modules/fieldora/role_edit.php?id=<?= (int) $role['id'] ?>"><?= e($role['name']) ?></a></h3>
        <p class="muted"><?= e((string) $role['description']) ?></p>
        <div class="stack">
            <?php foreach ($permissions as $permission): ?>
                <span class="tag"><?= e($permission['name']) ?></span>
            <?php endforeach; ?>
        </div>
    </section>
<?php endforeach; ?>
</div>
<?php fieldora_layout_end();
