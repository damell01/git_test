# Fieldora

Fieldora is a modular, multi-tenant SaaS booking and payments platform for service and rental businesses. It includes:

- a public marketing site
- tenant registration and onboarding
- branded public booking pages
- admin dashboards for bookings, jobs, customers, invoices, payments, team, webhooks, and settings
- Stripe Connect-based payment architecture
- cron-friendly background processing for shared hosting

## Current Status

This repository has been repurposed from an older project into Fieldora. Some legacy files still exist, but the active app flow is now Fieldora-first:

- public app/router: `public/index.php`
- admin app: `admin/dashboard.php` and `admin/modules/fieldora/*`
- installer: `admin/install/fieldora_install.php`
- cron: `admin/cron/fieldora.php`

## Server Requirements

- PHP `8.0+`
- MySQL `5.7+` or MariaDB `10.3+`
- PDO + `pdo_mysql`
- Apache with `mod_rewrite`
- Composer for Stripe + PHPMailer dependencies

Important:

- Fieldora will not run on PHP 7.4.
- If your host says PHP 8.3 is enabled but Composer says PHP 7.4, your web runtime and CLI runtime are different.
- The site may work in-browser on PHP 8.x while terminal Composer commands still fail under PHP 7.4.

## Very Common Hostinger Issue

Hostinger often has different PHP versions for:

- web requests
- terminal / CLI

If Composer says something like:

```text
your php version (7.4.33) does not satisfy that requirement
```

that means Composer is using the CLI PHP binary, not necessarily the PHP version serving your website.

### What to do

1. Confirm the web version by opening a temporary `phpinfo()` page or by loading the app with `?debug=1`.
2. In terminal, run:

```bash
php -v
which php
```

3. If CLI is still PHP 7.4, run Composer with the host's PHP 8.x binary instead.

Common patterns on shared hosting are things like:

```bash
php83 composer.phar install
```

or

```bash
/usr/local/bin/php83 composer install
```

or

```bash
/opt/alt/php83/usr/bin/php composer install
```

The exact binary path depends on your hosting environment.

If you cannot get Composer to use PHP 8.x on the server, install dependencies locally and upload the `admin/vendor/` folder.

## Composer Dependencies

Fieldora currently uses:

- `stripe/stripe-php`
- `phpmailer/phpmailer`

The old push-notification dependency was removed from `admin/composer.json` because it was part of the legacy app and was blocking installs unnecessarily.

## Installation

### 1. Upload the project

Upload the repository so these folders exist:

- `public/`
- `admin/`
- root `.htaccess`

### 2. Configure environment

Copy:

```text
.env.example -> .env
```

Set at minimum:

```env
DB_HOST=localhost
DB_NAME=fieldora
DB_USER=your_db_user
DB_PASS=your_db_password
SITE_URL=https://your-domain.com
APP_DEBUG=false
APP_INSTALLED=false
CRON_KEY=change-this
STRIPE_PLATFORM_SECRET_KEY=
STRIPE_PLATFORM_PUBLISHABLE_KEY=
STRIPE_CONNECT_WEBHOOK_SECRET=
```

### 3. Install Composer packages

From `admin/`:

```bash
composer install
```

If that fails because the host terminal is still on PHP 7.4, use the host's PHP 8.x binary or install locally and upload `admin/vendor/`.

### 4. Run the installer

Open:

```text
https://your-domain.com/admin/install/fieldora_install.php
```

The installer will create the schema and the first owner account.

### 5. Log in

Use:

```text
https://your-domain.com/login
```

Then continue through onboarding.

### 6. Add cron

Set a cron job to hit:

```text
https://your-domain.com/admin/cron/fieldora.php?key=YOUR_CRON_KEY
```

Recommended interval:

- every 5 minutes

## Active URLs

Public:

- `/`
- `/pricing`
- `/demo`
- `/login`
- `/register`
- `/forgot-password`
- `/reset-password`
- `/book/{tenant_slug}`

Admin:

- `/admin/dashboard.php`
- `/admin/modules/fieldora/services.php`
- `/admin/modules/fieldora/bookings.php`
- `/admin/modules/fieldora/jobs.php`
- `/admin/modules/fieldora/customers.php`
- `/admin/modules/fieldora/invoices.php`
- `/admin/modules/fieldora/payments.php`
- `/admin/modules/fieldora/team.php`
- `/admin/modules/fieldora/roles.php`
- `/admin/modules/fieldora/billing.php`
- `/admin/modules/fieldora/webhooks.php`
- `/admin/modules/fieldora/settings.php`
- `/admin/modules/fieldora/help.php`

## Debugging a Broken Page

If a page shows:

```text
Something went wrong
An unexpected error occurred. Please try again.
```

do this:

1. Temporarily set `APP_DEBUG=true` in `.env`
2. Reload the page, or append `?debug=1`
3. Check the new log file:

```text
admin/uploads/logs/fieldora-YYYY-MM-DD.log
```

The error page now also shows a reference code you can match against the log file.

## Booking Page Notes

The public booking page at `/book/{tenant_slug}` depends on:

- tenant record
- tenant branding/settings
- at least one active service
- Fieldora schema installed

If booking pages fail:

1. confirm the installer ran successfully
2. confirm the tenant slug exists
3. confirm at least one service exists for that tenant
4. enable `APP_DEBUG=true`
5. check `admin/uploads/logs/fieldora-YYYY-MM-DD.log`

## Shared Hosting Notes

Fieldora is structured to work on shared hosting first:

- cron instead of long-running workers
- lightweight server-rendered PHP pages
- env-driven config
- Stripe Connect and SMTP support without requiring a VPS

Before public launch on a VPS, you should still plan to:

- switch cron work to a stronger queue/worker setup
- add monitoring
- add real SMTP credentials
- finish final live Stripe testing
- remove/archive remaining legacy files

## Current Known Caveats

- Some legacy non-Fieldora files still exist in the repo.
- The repo is in transition from the older app to Fieldora.
- PHP CLI was not available in this coding environment, so I could not run `php -l` here.

## Recommended First Test

After install:

1. register or create the first owner
2. complete onboarding
3. add one service
4. open the booking page
5. submit one test booking
6. connect Stripe
7. send one invoice
8. run cron once
9. test one webhook

That gives you a real end-to-end smoke test of the current platform.
