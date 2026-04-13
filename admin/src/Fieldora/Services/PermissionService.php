<?php

namespace TrashPanda\Fieldora\Services;

class PermissionService
{
    public static function userCan(int $userId, string $permission): bool
    {
        static $cache = [];

        $cacheKey = $userId . ':' . $permission;
        if (array_key_exists($cacheKey, $cache)) {
            return $cache[$cacheKey];
        }

        $row = \db_fetch(
            'SELECT COUNT(*) AS cnt
             FROM user_roles ur
             INNER JOIN roles r ON r.id = ur.role_id AND r.is_active = 1
             INNER JOIN role_permissions rp ON rp.role_id = r.id AND rp.allowed = 1
             INNER JOIN permissions p ON p.id = rp.permission_id
             WHERE ur.user_id = ?
               AND p.permission_key = ?
             LIMIT 1',
            [$userId, $permission]
        );

        $allowed = (int) ($row['cnt'] ?? 0) > 0;

        if (!$allowed) {
            $user = \db_fetch('SELECT role FROM users WHERE id = ? LIMIT 1', [$userId]);
            $allowed = ($user['role'] ?? '') === 'admin';
        }

        $cache[$cacheKey] = $allowed;

        return $allowed;
    }
}
