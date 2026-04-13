<?php
require_once __DIR__ . '/_bootstrap.php';
require_permission('bookings.manage');
csrf_check();
$id = (int) ($_POST['id'] ?? 0);
db_execute('UPDATE services SET is_active = 0, updated_at = NOW() WHERE id = ? AND tenant_id = ?', [$id, current_tenant_id()]);
log_fieldora_event('service.deactivated','Deactivated service','service',$id);
flash_success('Service deactivated.');
redirect(APP_URL . '/modules/fieldora/services.php');
