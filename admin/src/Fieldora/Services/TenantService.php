<?php

namespace TrashPanda\Fieldora\Services;

class TenantService
{
    private static array $tenantCache = [];
    private static array $slugCache = [];
    private static array $settingsCache = [];

    public static function find(int $tenantId): ?array
    {
        if ($tenantId <= 0) {
            return null;
        }

        if (array_key_exists($tenantId, self::$tenantCache)) {
            return self::$tenantCache[$tenantId];
        }

        $row = \db_fetch(
            'SELECT t.*, p.code AS plan_code, p.name AS plan_name
             FROM tenants t
             LEFT JOIN plans p ON p.id = t.plan_id
             WHERE t.id = ? LIMIT 1',
            [$tenantId]
        );

        self::$tenantCache[$tenantId] = $row ?: null;
        return self::$tenantCache[$tenantId];
    }

    public static function findBySlug(string $slug): ?array
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        if (array_key_exists($slug, self::$slugCache)) {
            return self::$slugCache[$slug];
        }

        $row = \db_fetch(
            'SELECT t.*, p.code AS plan_code, p.name AS plan_name
             FROM tenants t
             LEFT JOIN plans p ON p.id = t.plan_id
             WHERE t.slug = ? LIMIT 1',
            [$slug]
        );

        self::$slugCache[$slug] = $row ?: null;
        if ($row) {
            self::$tenantCache[(int) $row['id']] = $row;
        }

        return self::$slugCache[$slug];
    }

    public static function setting(int $tenantId, string $key, string $default = ''): string
    {
        if ($tenantId <= 0 || $key === '') {
            return $default;
        }

        if (!isset(self::$settingsCache[$tenantId])) {
            self::$settingsCache[$tenantId] = [];
        }

        if (!array_key_exists($key, self::$settingsCache[$tenantId])) {
            $row = \db_fetch(
                'SELECT setting_value FROM tenant_settings WHERE tenant_id = ? AND setting_key = ? LIMIT 1',
                [$tenantId, $key]
            );
            self::$settingsCache[$tenantId][$key] = $row !== false ? (string) ($row['setting_value'] ?? '') : null;
        }

        $value = self::$settingsCache[$tenantId][$key];
        return $value !== null && $value !== '' ? (string) $value : $default;
    }

    public static function saveSetting(int $tenantId, string $key, ?string $value): void
    {
        \db_execute(
            'INSERT INTO tenant_settings (tenant_id, setting_key, setting_value, updated_at)
             VALUES (?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()',
            [$tenantId, $key, $value]
        );

        self::$settingsCache[$tenantId][$key] = $value;
    }
}
