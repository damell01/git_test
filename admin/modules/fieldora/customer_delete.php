<?php
require_once __DIR__ . '/_bootstrap.php';
require_permission('customers.manage');
csrf_check();
$id=(int)($_POST['id']??0); db_execute('UPDATE customers SET deleted_at = NOW(), updated_at = NOW() WHERE id = ? AND tenant_id = ?',[ $id, current_tenant_id() ]); flash_success('Customer archived.'); redirect(APP_URL.'/modules/fieldora/customers.php');
