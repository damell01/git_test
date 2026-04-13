<?php

namespace TrashPanda\Fieldora\Services;

class DashboardService
{
    public static function summary(int $tenantId): array
    {
        return [
            'bookings' => (int) ((\db_fetch('SELECT COUNT(*) AS cnt FROM bookings WHERE tenant_id = ? AND deleted_at IS NULL', [$tenantId]))['cnt'] ?? 0),
            'jobs' => (int) ((\db_fetch('SELECT COUNT(*) AS cnt FROM jobs WHERE tenant_id = ? AND deleted_at IS NULL', [$tenantId]))['cnt'] ?? 0),
            'customers' => (int) ((\db_fetch('SELECT COUNT(*) AS cnt FROM customers WHERE tenant_id = ? AND deleted_at IS NULL', [$tenantId]))['cnt'] ?? 0),
            'invoices_open' => (int) ((\db_fetch("SELECT COUNT(*) AS cnt FROM invoices WHERE tenant_id = ? AND status IN ('sent','partially_paid') AND deleted_at IS NULL", [$tenantId]))['cnt'] ?? 0),
            'payments_completed' => (float) ((\db_fetch("SELECT COALESCE(SUM(amount),0) AS total FROM payments WHERE tenant_id = ? AND payment_status = 'completed'", [$tenantId]))['total'] ?? 0),
            'recent_bookings' => \db_fetchall('SELECT booking_number, status, total_amount, created_at FROM bookings WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 6', [$tenantId]),
            'recent_jobs' => \db_fetchall('SELECT job_number, title, status, scheduled_date FROM jobs WHERE tenant_id = ? ORDER BY created_at DESC LIMIT 6', [$tenantId]),
        ];
    }

    public static function gettingStarted(int $tenantId): array
    {
        $serviceCount = (int) ((\db_fetch('SELECT COUNT(*) AS cnt FROM services WHERE tenant_id = ? AND deleted_at IS NULL', [$tenantId]))['cnt'] ?? 0);
        $invoiceCount = (int) ((\db_fetch('SELECT COUNT(*) AS cnt FROM invoices WHERE tenant_id = ? AND deleted_at IS NULL', [$tenantId]))['cnt'] ?? 0);
        $bookingCount = (int) ((\db_fetch('SELECT COUNT(*) AS cnt FROM bookings WHERE tenant_id = ? AND deleted_at IS NULL', [$tenantId]))['cnt'] ?? 0);
        $tenant = TenantService::find($tenantId) ?: [];
        $paymentAccount = \db_fetch('SELECT * FROM tenant_payment_accounts WHERE tenant_id = ? AND provider = ? LIMIT 1', [$tenantId, 'stripe']) ?: [];

        return [
            [
                'label' => 'Complete onboarding',
                'done' => !empty($tenant['onboarding_completed_at']),
                'href' => APP_URL . '/modules/fieldora/onboarding.php',
            ],
            [
                'label' => 'Add first service',
                'done' => $serviceCount > 0,
                'href' => APP_URL . '/modules/fieldora/services.php',
            ],
            [
                'label' => 'Connect Stripe',
                'done' => ($paymentAccount['account_status'] ?? '') === 'connected',
                'href' => APP_URL . '/modules/fieldora/billing.php',
            ],
            [
                'label' => 'Test booking page',
                'done' => $bookingCount > 0,
                'href' => tenant_booking_url($tenant),
            ],
            [
                'label' => 'Send first invoice',
                'done' => $invoiceCount > 0,
                'href' => APP_URL . '/modules/fieldora/invoices.php',
            ],
        ];
    }
}
