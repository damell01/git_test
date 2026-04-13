<?php

namespace TrashPanda\Fieldora\Services;

use RuntimeException;
use Stripe\Exception\ApiErrorException;
use Stripe\StripeClient;

class ConnectService
{
    public static function client(): StripeClient
    {
        if (!class_exists(StripeClient::class)) {
            throw new RuntimeException('Stripe PHP SDK is not installed.');
        }
        if (STRIPE_PLATFORM_SECRET_KEY === '') {
            throw new RuntimeException('Platform Stripe secret key is not configured.');
        }

        return new StripeClient(STRIPE_PLATFORM_SECRET_KEY);
    }

    public static function ensureAccount(int $tenantId): array
    {
        $existing = \db_fetch('SELECT * FROM tenant_payment_accounts WHERE tenant_id = ? AND provider = ? LIMIT 1', [$tenantId, 'stripe']);
        if ($existing && !empty($existing['stripe_account_id'])) {
            return self::syncAccount($tenantId, (string) $existing['stripe_account_id']);
        }

        $tenant = TenantService::find($tenantId);
        if (!$tenant) {
            throw new RuntimeException('Tenant not found.');
        }

        $client = self::client();
        $account = $client->accounts->create([
            'type' => 'express',
            'country' => strtoupper((string) ($tenant['country'] ?? 'US')),
            'email' => (string) ($tenant['business_email'] ?? ''),
            'business_type' => 'company',
            'capabilities' => [
                'card_payments' => ['requested' => true],
                'transfers' => ['requested' => true],
            ],
            'metadata' => [
                'tenant_id' => (string) $tenantId,
                'tenant_slug' => (string) $tenant['slug'],
            ],
            'business_profile' => [
                'name' => (string) $tenant['name'],
                'support_email' => (string) ($tenant['business_email'] ?? ''),
                'support_phone' => (string) ($tenant['business_phone'] ?? ''),
                'url' => SITE_URL . '/book/' . rawurlencode((string) $tenant['slug']),
            ],
        ]);

        \db_execute(
            'INSERT INTO tenant_payment_accounts (tenant_id, provider, stripe_account_id, charges_enabled, payouts_enabled, details_submitted, account_status, capabilities_json, requirements_json, last_synced_at, created_at, updated_at)
             VALUES (?, ?, ?, 0, 0, 0, ?, ?, ?, NOW(), NOW(), NOW())
             ON DUPLICATE KEY UPDATE stripe_account_id = VALUES(stripe_account_id), updated_at = NOW()',
            [
                $tenantId,
                'stripe',
                $account->id,
                self::deriveStatus($account),
                json_encode($account->capabilities ?? new \stdClass(), JSON_UNESCAPED_SLASHES),
                json_encode($account->requirements ?? new \stdClass(), JSON_UNESCAPED_SLASHES),
            ]
        );

        return self::syncAccount($tenantId, $account->id);
    }

    public static function onboardingLink(int $tenantId): string
    {
        $record = self::ensureAccount($tenantId);
        $client = self::client();
        $link = $client->accountLinks->create([
            'account' => $record['stripe_account_id'],
            'refresh_url' => APP_URL . '/modules/fieldora/billing.php?stripe=refresh',
            'return_url' => APP_URL . '/modules/fieldora/billing.php?stripe=return',
            'type' => 'account_onboarding',
        ]);

        \db_execute('UPDATE tenant_payment_accounts SET onboarding_url = ?, updated_at = NOW() WHERE tenant_id = ? AND provider = ?', [
            $link->url,
            $tenantId,
            'stripe',
        ]);

        return (string) $link->url;
    }

    public static function loginLink(int $tenantId): ?string
    {
        $record = \db_fetch('SELECT * FROM tenant_payment_accounts WHERE tenant_id = ? AND provider = ? LIMIT 1', [$tenantId, 'stripe']);
        if (!$record || empty($record['stripe_account_id'])) {
            return null;
        }

        try {
            $link = self::client()->accounts->createLoginLink((string) $record['stripe_account_id']);
            return (string) $link->url;
        } catch (ApiErrorException) {
            return null;
        }
    }

    public static function syncAccount(int $tenantId, string $accountId): array
    {
        $account = self::client()->accounts->retrieve($accountId, []);
        \db_execute(
            'UPDATE tenant_payment_accounts
             SET charges_enabled = ?, payouts_enabled = ?, details_submitted = ?, account_status = ?, capabilities_json = ?, requirements_json = ?, last_synced_at = NOW(), disconnected_at = NULL, updated_at = NOW()
             WHERE tenant_id = ? AND provider = ?',
            [
                (int) $account->charges_enabled,
                (int) $account->payouts_enabled,
                (int) $account->details_submitted,
                self::deriveStatus($account),
                json_encode($account->capabilities ?? new \stdClass(), JSON_UNESCAPED_SLASHES),
                json_encode($account->requirements ?? new \stdClass(), JSON_UNESCAPED_SLASHES),
                $tenantId,
                'stripe',
            ]
        );

        return \db_fetch('SELECT * FROM tenant_payment_accounts WHERE tenant_id = ? AND provider = ? LIMIT 1', [$tenantId, 'stripe']) ?: [];
    }

    public static function disconnect(int $tenantId): void
    {
        \db_execute(
            'UPDATE tenant_payment_accounts SET account_status = ?, disconnected_at = NOW(), updated_at = NOW() WHERE tenant_id = ? AND provider = ?',
            ['disconnected', $tenantId, 'stripe']
        );
    }

    public static function status(int $tenantId): ?array
    {
        return \db_fetch('SELECT * FROM tenant_payment_accounts WHERE tenant_id = ? AND provider = ? LIMIT 1', [$tenantId, 'stripe']) ?: null;
    }

    private static function deriveStatus(object $account): string
    {
        if (!empty($account->charges_enabled) && !empty($account->payouts_enabled)) {
            return 'connected';
        }
        if (!empty($account->requirements->currently_due)) {
            return 'pending';
        }
        if (!empty($account->requirements->disabled_reason)) {
            return 'restricted';
        }
        return 'pending';
    }
}
