<?php

use TrashPanda\Fieldora\Services\FeatureService;
use TrashPanda\Fieldora\Services\PermissionService;
use TrashPanda\Fieldora\Services\TenantService;

function fieldora_table_exists(string $table): bool
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    try {
        $row = db_fetch('SHOW TABLES LIKE ?', [$table]);
        $cache[$table] = $row !== false;
    } catch (Throwable $e) {
        $cache[$table] = false;
    }

    return $cache[$table];
}

function current_tenant_id(): int
{
    if (!empty($_SESSION['tenant_id'])) {
        return (int) $_SESSION['tenant_id'];
    }

    $user = current_user();
    return (int) ($user['tenant_id'] ?? 0);
}

function current_tenant(): ?array
{
    static $tenant = false;
    if ($tenant !== false) {
        return $tenant;
    }

    if (!fieldora_table_exists('tenants')) {
        $tenant = null;
        return $tenant;
    }

    $tenantId = current_tenant_id();
    if ($tenantId <= 0) {
        $tenant = null;
        return $tenant;
    }

    $tenant = TenantService::find($tenantId);
    return $tenant;
}

function current_plan_code(): string
{
    $tenant = current_tenant();
    return (string) ($tenant['plan_code'] ?? DEFAULT_TENANT_PLAN);
}

function tenant_setting(string $key, string $default = ''): string
{
    if (!fieldora_table_exists('tenant_settings')) {
        return $default;
    }

    $tenantId = current_tenant_id();
    if ($tenantId <= 0) {
        return $default;
    }

    return TenantService::setting($tenantId, $key, $default);
}

function tenant_booking_url(?array $tenant = null): string
{
    $tenant = $tenant ?: current_tenant();
    $slug = trim((string) ($tenant['slug'] ?? 'demo'));
    return SITE_URL . '/book/' . rawurlencode($slug);
}

function tenant_brand_value(string $key, string $default = ''): string
{
    if (!fieldora_table_exists('tenant_branding')) {
        return $default;
    }

    $row = db_fetch('SELECT * FROM tenant_branding WHERE tenant_id = ? LIMIT 1', [current_tenant_id()]);
    return (string) ($row[$key] ?? $default);
}

function user_can(string $permission): bool
{
    $user = current_user();
    if ($user === null) {
        return false;
    }

    if (!fieldora_table_exists('permissions')) {
        return has_role('admin');
    }

    return PermissionService::userCan((int) $user['id'], $permission);
}

function require_permission(string $permission): void
{
    require_login();

    if (!user_can($permission)) {
        http_response_code(403);
        die('You do not have permission to access this page.');
    }
}

function tenant_has_feature(string $featureKey): bool
{
    $tenantId = current_tenant_id();
    if ($tenantId <= 0 || !fieldora_table_exists('features')) {
        return false;
    }

    return FeatureService::tenantHasFeature($tenantId, $featureKey);
}

function require_feature(string $featureKey): void
{
    if (!tenant_has_feature($featureKey)) {
        flash_error('Your current plan does not include this feature.');
        redirect(APP_URL . '/modules/fieldora/settings.php?tab=plans');
    }
}

function tenant_theme_mode(): string
{
    $user = current_user();
    $value = strtolower((string) ($user['theme_preference'] ?? tenant_setting('theme_mode', 'dark')));
    return in_array($value, ['light', 'dark'], true) ? $value : 'dark';
}

function is_onboarding_complete(): bool
{
    $tenant = current_tenant();
    return !empty($tenant['onboarding_completed_at']);
}

function log_fieldora_event(
    string $action,
    string $description,
    string $entityType = '',
    int $entityId = 0,
    array $context = []
): void {
    if (!fieldora_table_exists('activity_log')) {
        return;
    }

    db_insert('activity_log', [
        'tenant_id' => current_tenant_id() ?: null,
        'user_id' => (int) ($_SESSION['user_id'] ?? 0) ?: null,
        'action' => $action,
        'description' => $description,
        'entity_type' => $entityType,
        'entity_id' => $entityId ?: null,
        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        'context_json' => $context ? json_encode($context, JSON_UNESCAPED_SLASHES) : null,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
}
