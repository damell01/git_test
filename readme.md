# Fieldora

Fieldora is a modular multi-tenant SaaS booking and payments platform for service and rental businesses. This repo now contains:

- Public marketing + auth routes through `public/index.php`
- Branded public booking pages at `/book/{tenant_slug}`
- Tenant-aware admin workspace under `/admin`
- Multi-tenant schema at `admin/install/fieldora_schema.sql`
- Shared-hosting installer at `admin/install/fieldora_install.php`
- Cron-friendly processor at `admin/cron/fieldora.php`

## Core areas

- Plans and feature gating: Starter, Growth, Pro
- Tenants, branding, settings, onboarding
- Services/products catalog
- Bookings, jobs, customers
- Payments, invoices, exports
- Notifications, webhooks, automations
- Team, roles, permissions
- Route tools and analytics

## Local run

1. Copy `.env.example` to `.env`
2. Update DB credentials and `SITE_URL`
3. Create the MySQL database
4. Visit `/admin/install/fieldora_install.php`
5. Create the first tenant owner account
6. Sign in at `/login`

## Shared hosting run

1. Upload the repo so the root `.htaccess` stays in place
2. Point the domain at the repo root / `public_html`
3. Create `.env` with your hosting DB credentials and production `SITE_URL`
4. Visit `/admin/install/fieldora_install.php`
5. Add a cron job for `admin/cron/fieldora.php?key=YOUR_CRON_KEY`

Example cron via URL:

```text
*/5 * * * * /usr/bin/curl -fsS "https://your-domain.com/admin/cron/fieldora.php?key=YOUR_CRON_KEY" >/dev/null 2>&1
```

Example cron via PHP CLI:

```text
*/5 * * * * /usr/bin/php /home/youraccount/public_html/admin/cron/fieldora.php >/dev/null 2>&1
```

## Stripe notes

- Booking payments are abstracted through `TrashPanda\Fieldora\Services\PaymentService`
- The current implementation uses Stripe Checkout when tenant keys are configured
- The schema includes customer/subscription fields for future Billing work
- The architecture leaves room for a later Stripe Connect migration

## Key paths

- `public/index.php`: marketing, auth, booking router
- `public/api/fieldora-booking.php`: booking API endpoint
- `admin/modules/fieldora/`: admin product modules
- `admin/templates/fieldora_layout.php`: shared admin layout
- `admin/src/Fieldora/Services/`: business logic layer
- `admin/install/fieldora_schema.sql`: installer schema
- `admin/install/fieldora_install.php`: browser installer
- `admin/cron/fieldora.php`: cron runner
