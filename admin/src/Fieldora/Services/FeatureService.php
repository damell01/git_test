<?php

namespace TrashPanda\Fieldora\Services;

class FeatureService
{
    public static function tenantHasFeature(int $tenantId, string $featureKey): bool
    {
        $row = \db_fetch(
            'SELECT COALESCE(tf.enabled, pf.enabled, 0) AS enabled
             FROM features f
             LEFT JOIN tenants t ON t.id = ?
             LEFT JOIN plan_features pf ON pf.plan_id = t.plan_id AND pf.feature_id = f.id
             LEFT JOIN tenant_features tf ON tf.tenant_id = t.id AND tf.feature_id = f.id
             WHERE f.feature_key = ?
             LIMIT 1',
            [$tenantId, $featureKey]
        );

        return (int) ($row['enabled'] ?? 0) === 1;
    }

    public static function mapForTenant(int $tenantId): array
    {
        $rows = \db_fetchall(
            'SELECT f.feature_key, COALESCE(tf.enabled, pf.enabled, 0) AS enabled
             FROM features f
             LEFT JOIN tenants t ON t.id = ?
             LEFT JOIN plan_features pf ON pf.plan_id = t.plan_id AND pf.feature_id = f.id
             LEFT JOIN tenant_features tf ON tf.tenant_id = t.id AND tf.feature_id = f.id
             ORDER BY f.sort_order ASC, f.feature_key ASC',
            [$tenantId]
        );

        $map = [];
        foreach ($rows as $row) {
            $map[$row['feature_key']] = (int) $row['enabled'] === 1;
        }

        return $map;
    }
}
