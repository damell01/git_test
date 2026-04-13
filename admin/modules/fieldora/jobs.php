<?php
require_once __DIR__ . '/_bootstrap.php';

require_permission('jobs.view');

$tenantId = current_tenant_id();
$user = current_user();
$search = trim((string) ($_GET['search'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));

$sql = 'SELECT j.*, u.name AS assigned_name FROM jobs j LEFT JOIN users u ON u.id = j.assigned_user_id WHERE j.tenant_id = ? AND j.deleted_at IS NULL';
$params = [$tenantId];
if (($user['role'] ?? '') === 'staff') {
    $sql .= ' AND j.assigned_user_id = ?';
    $params[] = $user['id'];
}
$sql .= ' AND (? = "" OR j.status = ?)';
$params[] = $status;
$params[] = $status;
$sql .= ' AND (? = "" OR j.job_number LIKE ? OR j.title LIKE ?)';
$params[] = $search;
$params[] = '%' . $search . '%';
$params[] = '%' . $search . '%';
$sql .= ' ORDER BY j.scheduled_date DESC, j.created_at DESC';
$rows = db_fetchall($sql, $params);

fieldora_layout_start('Jobs', 'jobs');
?>
<form method="get" class="card form-grid">
    <label><span>Search jobs</span><input name="search" value="<?= e($search) ?>" placeholder="Search jobs"></label>
    <label><span>Status</span><select name="status"><option value="">All statuses</option><?php foreach (['scheduled', 'in_progress', 'waiting', 'completed', 'canceled'] as $option): ?><option value="<?= e($option) ?>"<?= $status === $option ? ' selected' : '' ?>><?= e($option) ?></option><?php endforeach; ?></select></label>
    <button class="primary-btn" type="submit">Filter</button>
</form>
<section class="table-wrap" style="margin-top:20px;">
    <table>
        <thead><tr><th>Job</th><th>Title</th><th>Status</th><th>Scheduled</th><th>Assigned</th></tr></thead>
        <tbody>
        <?php foreach ($rows as $row): ?>
            <tr>
                <td><a href="<?= e(APP_URL) ?>/modules/fieldora/job_view.php?id=<?= (int) $row['id'] ?>"><?= e($row['job_number']) ?></a></td>
                <td><?= e($row['title']) ?></td>
                <td><span class="tag"><?= e($row['status']) ?></span></td>
                <td><?= e((string) $row['scheduled_date']) ?></td>
                <td><?= e((string) ($row['assigned_name'] ?: 'Unassigned')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php fieldora_layout_end();
