# Fieldora Admin

Primary admin entry points:

- `/admin/dashboard.php`
- `/admin/modules/fieldora/onboarding.php`
- `/admin/modules/fieldora/services.php`
- `/admin/modules/fieldora/bookings.php`
- `/admin/modules/fieldora/jobs.php`
- `/admin/modules/fieldora/customers.php`
- `/admin/modules/fieldora/invoices.php`
- `/admin/modules/fieldora/payments.php`
- `/admin/modules/fieldora/analytics.php`
- `/admin/modules/fieldora/team.php`
- `/admin/modules/fieldora/roles.php`
- `/admin/modules/fieldora/branding.php`
- `/admin/modules/fieldora/webhooks.php`
- `/admin/modules/fieldora/automations.php`
- `/admin/modules/fieldora/share.php`
- `/admin/modules/fieldora/exports.php`
- `/admin/modules/fieldora/routes.php`
- `/admin/modules/fieldora/settings.php`

All new business logic should live in `admin/src/Fieldora/Services/` and all tenant-aware page logic should scope data by `tenant_id`.
