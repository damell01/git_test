<?php

namespace TrashPanda\Fieldora\Services;

class AutomationService
{
    public static function queueTrigger(int $tenantId, string $triggerKey, string $entityType, int $entityId, array $payload = []): void
    {
        $rules = \db_fetchall(
            'SELECT * FROM automation_rules WHERE tenant_id = ? AND trigger_key = ? AND is_active = 1',
            [$tenantId, $triggerKey]
        );

        foreach ($rules as $rule) {
            \db_insert('automation_runs', [
                'tenant_id' => $tenantId,
                'automation_rule_id' => $rule['id'],
                'trigger_key' => $triggerKey,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'scheduled_for' => date('Y-m-d H:i:s'),
                'status' => 'queued',
                'output_json' => json_encode($payload, JSON_UNESCAPED_SLASHES),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }
}
