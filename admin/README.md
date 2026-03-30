# Trash Panda Roll-Offs — Dumpster Rental Website & Work Order System

A complete dumpster rental business platform including a public-facing website and an internal admin work order management system. Payments are handled outside the system by the business.

## Project Structure

```
/public/       — Client-facing public website (Home, Sizes, Contact, FAQ, etc.)
/public/api/   — Backend API endpoint (contact form → saves lead + emails admin)
/admin/        — Internal staff admin panel (protected, login required)
```

## Features

- **Public Website** — full branded site with contact form that saves directly to admin leads
- **Interactive Map** — real Leaflet.js service-area map (no API key required)
- **Leads Management** — web contact form submissions land here automatically with email alerts
- **Customer Database**
- **Quote Builder** with print layout
- **Work Order Management** with printable invoice
- **Dumpster Inventory**
- **Scheduling Calendar**
- **Reports & Revenue Tracking**
- **User & Role Management**
- **Email Notifications** via PHPMailer (SMTP) or PHP `mail()` fallback
- **Two-Factor Authentication** per user

## Tech Stack

- PHP 8.1+
- MySQL 5.7+ / MariaDB 10.3+ via PDO
- PHPMailer 6 (optional, installed via Composer — see below)
- Bootstrap 5.3
- Leaflet.js (interactive maps, no API key needed)
- Vanilla JavaScript
- Font Awesome 6

---

## ① Server Requirements

- PHP **8.1** or higher
- MySQL **5.7+** or MariaDB **10.3+**
- PDO + `pdo_mysql` extension enabled
- `mod_rewrite` enabled (Apache)
- Composer (optional, required for PHPMailer SMTP)

---

## ② Upload the Files

Upload **both** folders to your hosting public directory. Typical layout:

```
public_html/
  ├── index.html          ← public website files go at root
  ├── about.html
  ├── contact.html
  ├── ... (all other public/ files)
  └── admin/              ← admin panel lives here
        ├── config/
        ├── includes/
        └── ...
```

**Option A — cPanel File Manager / FTP**
1. Upload everything in `/public/` directly to `public_html/`
2. Upload everything in `/admin/` to `public_html/admin/`

**Option B — Command line**
```bash
# From repo root
cp -r public/. /home/youraccount/public_html/
cp -r admin/   /home/youraccount/public_html/admin/
```

---

## ③ Configure the Admin

Edit `admin/config/config.php`:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'your_db_name');    // create this DB first in cPanel → MySQL Databases
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('APP_URL', 'https://yourdomain.com/admin');  // no trailing slash

define('CRON_KEY', 'change-me-to-a-random-secret'); // for the cron job URL
```

---

## ④ Run the Installer

Navigate to:

```
https://yourdomain.com/admin/install/install.php
```

The installer will:
- Verify PHP version, PDO, and DB credentials
- Create the database (if it doesn't exist)
- Import the full schema (all tables)
- Seed default settings, dumpster inventory, and the admin user

**First Login Credentials**

| Field    | Value             |
|----------|-------------------|
| Email    | admin@example.com |
| Password | ChangeMe123!      |

> You will be forced to change the password on first login.

**Lock the installer** — open `config/config.php` and set:

```php
define('APP_INSTALLED', true);
```

---

## ⑤ PHPMailer (Recommended for Reliable Email)

PHPMailer is **not required** — the system falls back to PHP's built-in `mail()` — but SMTP via PHPMailer is strongly recommended for reliable delivery (Gmail, Mailgun, SendGrid, etc.).

### Install via Composer

```bash
cd /path/to/public_html/admin
composer install
```

If Composer is not installed on your server, install it first:

```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
php -r "unlink('composer-setup.php');"
```

Or on cPanel/shared hosting — many hosts have Composer available. Check **cPanel → Software → PHP Composer**.

### Configure SMTP in the Admin Panel

1. Log in → **Settings** → **Email Configuration**
2. Fill in:
   - **SMTP Host** — e.g. `smtp.mailgun.org`, `smtp.sendgrid.net`, `smtp.gmail.com`
   - **SMTP Port** — `587` (TLS) or `465` (SSL)
   - **SMTP Username** and **SMTP Password**
   - **Encryption** — `TLS` (recommended)
3. Set **From Name** and **From Email**
4. Set **Notification Email(s)** — comma-separated list of addresses that receive an alert every time the public contact form is submitted
5. Click **Send Test Email** to verify

> **Without Composer / SMTP configured**, leave `smtp_host` blank and the system uses `mail()`. Your hosting's built-in SMTP relay must be configured for `mail()` to work.

---

## ⑥ Set Folder Permissions

```bash
chmod 755 admin/assets/img/
# or if needed:
chmod 775 admin/assets/img/
```

---

## ⑦ Set Up the Daily Cron Job (cPanel)

Go to **cPanel → Cron Jobs** and add:

```
0 8 * * * php /home/youraccount/public_html/admin/cron/daily.php >> /dev/null 2>&1
```

Or call it via URL with the secure key:

```
https://yourdomain.com/admin/cron/daily.php?key=YOUR_CRON_KEY
```

The cron job:
- Sends delivery reminder emails (for tomorrow's scheduled deliveries)
- Sends overdue pickup alerts to the company email
- Auto-activates work orders whose delivery date has passed

---

## ⑧ Enable HTTPS

In `admin/.htaccess`, uncomment the HTTPS redirect block:

```apache
<IfModule mod_rewrite.c>
  RewriteCond %{HTTPS} off
  RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
</IfModule>
```

---

## Contact Form → Admin Leads Integration

When a visitor fills out the quote request form on the public website:

1. The form POSTs to `api/contact.php`
2. The submission is saved as a **Lead** in the admin database (source: *Website Contact Form*)
3. An email notification is sent to every address in **Settings → Notification Email(s)**
4. The admin can view and manage the lead at **Admin → Leads**

No additional configuration is needed beyond setting the notification email(s) in Settings.

---

## Role Descriptions

| Role       | Access Level |
|------------|--------------|
| admin      | Full access including user management |
| office     | Create/edit leads, customers, quotes, work orders. No user management. |
| dispatcher | View all records; can update work order statuses only |
| readonly   | View-only access |

---

## Two-Factor Authentication

1. Log in → **Settings → Two-Factor Authentication**
2. Scan the QR code with Google Authenticator, Authy, or any TOTP app
3. Enter the 6-digit code to confirm
4. Save your 8 backup codes in a safe place (shown only once)
5. Admins can disable 2FA for any user via **Settings → Users**

---

## Security Checklist

- [ ] Change `CRON_KEY` in `config.php` before going live
- [ ] Set `APP_INSTALLED = true` after installation
- [ ] Enable HTTPS (uncomment in `.htaccess`)
- [ ] Set `APP_URL` to your real domain
- [ ] Enable 2FA for admin accounts
- [ ] Login rate limiting is active — 10 failed attempts locks IP for 15 minutes
- [ ] `/admin/install/` is blocked from repeat runs by `APP_INSTALLED` flag

---

## Default Dumpster Units (seeded by installer)

| Unit ID | Size    |
|---------|---------|
| TP-001  | 10 yard |
| TP-002  | 10 yard |
| TP-003  | 15 yard |
| TP-004  | 15 yard |
| TP-005  | 20 yard |
| TP-006  | 20 yard |
| TP-007  | 30 yard |
| TP-008  | 40 yard |

Manage inventory via **Admin → Inventory** after first login.
