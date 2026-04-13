<?php

require_once dirname(__DIR__) . '/config/config.php';
require_once INC_PATH . '/db.php';

use TrashPanda\Fieldora\Services\AuthService;

$schemaFile = __DIR__ . '/fieldora_schema.sql';
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $dsn = sprintf('mysql:host=%s;charset=%s', DB_HOST, DB_CHARSET);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo->exec('USE `' . DB_NAME . '`');
        foreach (explode(';', file_get_contents($schemaFile)) as $statement) {
            $statement = trim(preg_replace('/--[^\n]*/', '', $statement));
            if ($statement !== '') {
                $pdo->exec($statement);
            }
        }

        require_once dirname(__DIR__) . '/includes/bootstrap.php';
        $user = AuthService::registerTenantOwner([
            'business_name' => $_POST['business_name'] ?? 'Fieldora Demo',
            'owner_name' => $_POST['owner_name'] ?? 'Owner',
            'email' => $_POST['email'] ?? 'owner@example.com',
            'password' => $_POST['password'] ?? 'ChangeMe123!',
            'business_phone' => $_POST['business_phone'] ?? '',
            'timezone' => $_POST['timezone'] ?? APP_TIMEZONE,
        ]);
        $message = 'Install complete. Owner user created: ' . ($user['email'] ?? '');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}
?><!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Fieldora Installer</title><style>body{font-family:Arial,sans-serif;background:#0f172a;color:#e2e8f0;display:flex;justify-content:center;padding:40px}.card{width:min(100%,540px);background:#111827;border:1px solid #1f2937;border-radius:18px;padding:24px}input{width:100%;padding:12px;margin:8px 0;border-radius:12px;border:1px solid #334155;background:#020617;color:#fff}button{padding:12px 18px;border:none;background:#2563eb;color:#fff;border-radius:12px;width:100%}.msg{padding:12px;border-radius:12px;margin-bottom:12px}.ok{background:#0d3328}.err{background:#451a24}</style></head><body><form class="card" method="post"><h1>Fieldora Installer</h1><?php if($message): ?><div class="msg ok"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?><?php if($error): ?><div class="msg err"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?><input name="business_name" placeholder="Business name" required><input name="owner_name" placeholder="Owner name" required><input name="email" type="email" placeholder="Owner email" required><input name="business_phone" placeholder="Business phone"><input name="timezone" value="<?= htmlspecialchars(APP_TIMEZONE, ENT_QUOTES, 'UTF-8') ?>" placeholder="Timezone"><input name="password" type="password" placeholder="Owner password" required><button type="submit">Install Fieldora</button></form></body></html>
