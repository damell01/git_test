<?php
require_once __DIR__ . '/_bootstrap.php';
require_permission('jobs.view');
$id=(int)($_GET['id']??0); $row=db_fetch('SELECT j.*, u.name AS assigned_name FROM jobs j LEFT JOIN users u ON u.id = j.assigned_user_id WHERE j.tenant_id = ? AND j.id = ? LIMIT 1',[current_tenant_id(),$id]); if(!$row){http_response_code(404);exit('Job not found');}
$notes=db_fetchall('SELECT jn.*, u.name AS user_name FROM job_notes jn LEFT JOIN users u ON u.id = jn.user_id WHERE jn.job_id = ? ORDER BY jn.created_at DESC',[$id]);
if($_SERVER['REQUEST_METHOD']==='POST'){csrf_check(); db_insert('job_notes',['tenant_id'=>current_tenant_id(),'job_id'=>$id,'user_id'=>$_SESSION['user_id'],'note'=>trim((string)$_POST['note']),'note_type'=>'note','created_at'=>date('Y-m-d H:i:s')]); flash_success('Job note added.'); redirect($_SERVER['REQUEST_URI']);}
fieldora_layout_start('Job Detail','jobs'); ?>
<div class="grid two"><section class="card"><h3><?= e($row['job_number']) ?></h3><p class="muted"><?= e($row['title']) ?></p><p>Status: <span class="tag"><?= e($row['status']) ?></span></p><p>Assigned: <?= e($row['assigned_name'] ?? 'Unassigned') ?></p><p><?= e(trim(($row['address_line1']??'').' '.($row['city']??'').' '.($row['state']??'').' '.($row['postal_code']??''))) ?></p><a class="primary-btn" href="<?= e(APP_URL) ?>/modules/fieldora/job_edit.php?id=<?= $id ?>">Edit job</a></section><section class="card"><form method="post" class="stack"><?= csrf_field() ?><textarea name="note" placeholder="Add job note"></textarea><button class="primary-btn" type="submit">Add note</button></form></section></div>
<section class="table-wrap" style="margin-top:20px;"><table><thead><tr><th>Note</th><th>User</th><th>When</th></tr></thead><tbody><?php foreach($notes as $note): ?><tr><td><?= e($note['note']) ?></td><td><?= e($note['user_name']) ?></td><td><?= e($note['created_at']) ?></td></tr><?php endforeach; ?></tbody></table></section>
<?php fieldora_layout_end();
