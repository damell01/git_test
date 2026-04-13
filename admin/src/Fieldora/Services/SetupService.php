<?php

namespace TrashPanda\Fieldora\Services;

class SetupService
{
    public static function seedTenant(int $tenantId, int $ownerUserId): void
    {
        self::seedRoles($tenantId, $ownerUserId);
        self::seedSettings($tenantId);
        self::seedWorkingHours($tenantId);
        self::seedTemplates($tenantId);
    }

    public static function seedRoles(int $tenantId, int $ownerUserId): void
    {
        $roles = [
            'owner' => ['Owner', 'Full account access', ['bookings.view','bookings.manage','jobs.view','jobs.manage','customers.view','customers.manage','invoices.view','invoices.manage','payments.view','billing.manage','settings.manage','team.manage','webhooks.manage','automations.manage','analytics.view','branding.manage','exports.view']],
            'manager' => ['Manager', 'Operational management access', ['bookings.view','bookings.manage','jobs.view','jobs.manage','customers.view','customers.manage','invoices.view','invoices.manage','payments.view','analytics.view','exports.view']],
            'staff' => ['Staff', 'Assigned jobs and bookings only', ['bookings.view','jobs.view']],
            'accounting' => ['Accounting', 'Invoices, payments and exports', ['invoices.view','invoices.manage','payments.view','analytics.view','exports.view']],
        ];

        foreach ($roles as $key => [$name, $description, $permissions]) {
            \db_execute(
                'INSERT INTO roles (tenant_id, role_key, name, description, is_system, is_active, created_at)
                 VALUES (?, ?, ?, ?, 1, 1, NOW())
                 ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), is_active = 1',
                [$tenantId, $key, $name, $description]
            );

            $role = \db_fetch('SELECT id FROM roles WHERE tenant_id = ? AND role_key = ? LIMIT 1', [$tenantId, $key]);
            if (!$role) {
                continue;
            }

            foreach ($permissions as $permissionKey) {
                $permission = \db_fetch('SELECT id FROM permissions WHERE permission_key = ? LIMIT 1', [$permissionKey]);
                if (!$permission) {
                    continue;
                }
                \db_execute(
                    'INSERT INTO role_permissions (role_id, permission_id, allowed)
                     VALUES (?, ?, 1)
                     ON DUPLICATE KEY UPDATE allowed = 1',
                    [$role['id'], $permission['id']]
                );
            }
        }

        $ownerRole = \db_fetch('SELECT id FROM roles WHERE tenant_id = ? AND role_key = ? LIMIT 1', [$tenantId, 'owner']);
        if ($ownerRole) {
            \db_execute(
                'INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)',
                [$ownerUserId, $ownerRole['id']]
            );
        }
    }

    public static function seedSettings(int $tenantId): void
    {
        $defaults = [
            'booking_approval_mode' => FIELDORA_BOOKING_APPROVAL_DEFAULT,
            'allow_instant_booking' => '0',
            'allow_full_payment' => '1',
            'require_deposit' => '1',
            'default_deposit_mode' => 'percent',
            'default_deposit_value' => '25',
            'max_bookings_per_day' => '0',
            'minimum_notice_hours' => '2',
            'show_public_prices' => '1',
            'timezone' => APP_TIMEZONE,
            'theme_mode' => 'dark',
            'currency' => 'usd',
            'sms_provider' => 'twilio',
            'email_from_name' => APP_NAME,
            'smtp_host' => '',
            'smtp_port' => '587',
            'smtp_username' => '',
            'smtp_password' => '',
            'smtp_encryption' => 'tls',
            'smtp_from_name' => APP_NAME,
            'smtp_from_email' => '',
        ];

        foreach ($defaults as $key => $value) {
            TenantService::saveSetting($tenantId, $key, $value);
        }

        \db_execute(
            'INSERT IGNORE INTO tenant_branding (tenant_id, primary_color, secondary_color, accent_color, marketing_headline, booking_intro, footer_text)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$tenantId, '#2563eb', '#0f172a', '#f59e0b', 'Get booked and get paid online.', 'Book services, collect deposits, and keep the day moving.', 'Powered by Fieldora']
        );

        \db_execute(
            'INSERT IGNORE INTO onboarding_progress (tenant_id, current_step, completed_steps_json, last_completed_step)
             VALUES (?, 1, JSON_ARRAY(), 0)',
            [$tenantId]
        );
    }

    public static function seedWorkingHours(int $tenantId): void
    {
        for ($day = 0; $day <= 6; $day++) {
            $active = $day >= 1 && $day <= 5 ? 1 : 0;
            \db_execute(
                'INSERT IGNORE INTO working_hours (tenant_id, weekday, start_time, end_time, is_active)
                 VALUES (?, ?, ?, ?, ?)',
                [$tenantId, $day, '08:00:00', '17:00:00', $active]
            );
        }
    }

    public static function seedTemplates(int $tenantId): void
    {
        $templates = [
            ['booking_confirmation', 'email', 'Your booking is in', "Hi {{customer_name}},\n\nYour booking {{booking_number}} has been received.\nWe'll follow up with the next steps soon."],
            ['payment_confirmation', 'email', 'Payment received', "Hi {{customer_name}},\n\nWe received your payment of {{amount}} for booking {{booking_number}}."],
            ['invoice_sent', 'email', 'Invoice {{invoice_number}}', "Hi {{customer_name}},\n\nYour invoice {{invoice_number}} is ready. Balance due: {{balance_due}}."],
            ['invoice_paid', 'email', 'Invoice paid', "Hi {{customer_name}},\n\nInvoice {{invoice_number}} has been marked paid. Thank you."],
            ['booking_confirmation', 'sms', null, 'Booking {{booking_number}} confirmed. We will keep you posted.'],
            ['payment_confirmation', 'sms', null, 'Payment received for {{booking_number}}. Thank you.'],
        ];

        foreach ($templates as [$key, $channel, $subject, $body]) {
            \db_execute(
                'INSERT IGNORE INTO notification_templates (tenant_id, template_key, channel, subject_template, body_template)
                 VALUES (?, ?, ?, ?, ?)',
                [$tenantId, $key, $channel, $subject, $body]
            );
        }
    }
}
