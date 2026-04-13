<?php

namespace TrashPanda\Fieldora\Services;

use RuntimeException;

class AuthService
{
    public static function registerTenantOwner(array $data): array
    {
        $businessName = trim((string) ($data['business_name'] ?? ''));
        $ownerName = trim((string) ($data['owner_name'] ?? ''));
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $password = (string) ($data['password'] ?? '');

        if ($businessName === '' || $ownerName === '' || $email === '' || $password === '') {
            throw new RuntimeException('Business name, owner name, email, and password are required.');
        }

        $existing = \db_fetch('SELECT id FROM users WHERE email = ? LIMIT 1', [$email]);
        if ($existing) {
            throw new RuntimeException('That email is already in use.');
        }

        $slug = self::uniqueSlug($businessName);
        $plan = \db_fetch('SELECT id FROM plans WHERE code = ? LIMIT 1', [DEFAULT_TENANT_PLAN]);
        if (!$plan) {
            throw new RuntimeException('Plans are not installed yet.');
        }

        $pdo = \get_db();
        $pdo->beginTransaction();

        try {
            $tenantId = (int) \db_insert('tenants', [
                'name' => $businessName,
                'slug' => $slug,
                'plan_id' => $plan['id'],
                'subscription_status' => 'trialing',
                'trial_ends_at' => date('Y-m-d H:i:s', strtotime('+14 days')),
                'business_email' => $email,
                'business_phone' => trim((string) ($data['business_phone'] ?? '')),
                'timezone' => trim((string) ($data['timezone'] ?? APP_TIMEZONE)),
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $userId = (int) \db_insert('users', [
                'tenant_id' => $tenantId,
                'name' => $ownerName,
                'email' => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
                'role' => 'owner',
                'theme_preference' => 'dark',
                'active' => 1,
                'must_change_pw' => 0,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            SetupService::seedTenant($tenantId, $userId);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }

        $user = \db_fetch('SELECT * FROM users WHERE id = ? LIMIT 1', [$userId]);
        return $user ?: [];
    }

    public static function beginPasswordReset(string $email): ?string
    {
        $user = \db_fetch('SELECT * FROM users WHERE email = ? AND active = 1 LIMIT 1', [trim($email)]);
        if (!$user) {
            return null;
        }

        $token = bin2hex(random_bytes(24));
        \db_insert('password_resets', [
            'user_id' => $user['id'],
            'email' => $user['email'],
            'token' => $token,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+2 hours')),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $token;
    }

    public static function resetPassword(string $token, string $password): bool
    {
        $row = \db_fetch(
            'SELECT * FROM password_resets WHERE token = ? AND used_at IS NULL AND expires_at >= NOW() LIMIT 1',
            [trim($token)]
        );
        if (!$row) {
            return false;
        }

        \db_execute('UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?', [
            password_hash($password, PASSWORD_DEFAULT),
            $row['user_id'],
        ]);
        \db_execute('UPDATE password_resets SET used_at = NOW() WHERE id = ?', [$row['id']]);

        return true;
    }

    private static function uniqueSlug(string $businessName): string
    {
        $base = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $businessName), '-'));
        $base = $base !== '' ? $base : 'fieldora-business';
        $slug = $base;
        $i = 1;

        while (\db_fetch('SELECT id FROM tenants WHERE slug = ? LIMIT 1', [$slug])) {
            $i++;
            $slug = $base . '-' . $i;
        }

        return $slug;
    }
}
