# Trash Panda Roll-Offs — Dumpster Rental Website & Work Order System

A complete dumpster rental business platform including a public-facing website and an internal admin work order management system. Payments are handled outside the system by the business.

## Project Structure

```
/public/        — Client-facing public website (Home, Sizes, Services, Contact, FAQ, Service Areas)
/public/api/    — Backend API endpoint (contact form → saves lead + emails admin)
/admin/         — Internal staff admin panel (protected, login required)
```

A root-level `.htaccess` file ensures that visiting the bare domain URL (e.g. `https://yourdomain.com/`) automatically serves the public website from `/public/`. The `/admin/` path continues to work independently.

---

## Features

### Public Website (`/public/`)
- Full branded multi-page site: Home, Sizes, Services, FAQ, Contact, Service Areas
- Contact / quote-request form that saves directly to the admin Leads module and triggers email notifications
- Interactive Leaflet.js service-area map (no API key required)
- Fully responsive — built with Bootstrap 5.3 and vanilla JavaScript
- Shared navigation and footer injected via `shared-components.js`

### Admin Panel (`/admin/`)
- **Leads Management** — contact form submissions land here automatically
- **Customer Database**
- **Quote Builder** with print layout
- **Work Order Management** with printable invoice
- **Dumpster Inventory** tracking
- **Scheduling Calendar**
- **Reports & Revenue Tracking**
- **User & Role Management** (admin, office, dispatcher, readonly)
- **Email Notifications** via PHPMailer (SMTP) or PHP `mail()` fallback
- **Two-Factor Authentication** per user (TOTP — Google Authenticator, Authy, etc.)

---

## Tech Stack

| Layer | Technology |
|-------|-----------|
| Frontend | HTML5, Bootstrap 5.3, Font Awesome 6, Vanilla JS, Leaflet.js |
| Backend | PHP 8.1+, PDO |
| Database | MySQL 5.7+ / MariaDB 10.3+ |
| Email | PHPMailer 6 (optional SMTP) or PHP `mail()` fallback |
| Maps | Leaflet.js (no API key needed) |

---

## ① Server Requirements

- PHP **8.1** or higher
- MySQL **5.7+** or MariaDB **10.3+**
- PDO + `pdo_mysql` extension enabled
- `mod_rewrite` enabled (Apache)
- Composer (optional — required only for PHPMailer SMTP)

---

## ② Upload the Files

Upload both folders to your hosting public directory. Typical layout on cPanel hosting:

```
public_html/
  ├── .htaccess           ← root redirect (included in repo root)
  ├── public/             ← public website files
  │   ├── index.html
  │   ├── about.html
  │   ├── contact.html
  │   ├── faq.html
  │   ├── services.html
  │   ├── service-areas.html
  │   ├── sizes.html
  │   ├── shared.css
  │   ├── shared-components.js
  │   ├── assets/
  │   └── api/
  │       └── contact.php
  └── admin/              ← admin panel
        ├── config/
        ├── includes/
        ├── modules/
        └── ...
```

**Option A — Deploy the whole repo directly**

Upload the entire repository to `public_html/`. The root `.htaccess` will redirect the domain URL to the `public/` website automatically.

**Option B — Copy files manually**
```bash
# From repo root
cp .htaccess          /home/youraccount/public_html/.htaccess
cp -r public/         /home/youraccount/public_html/public/
cp -r admin/          /home/youraccount/public_html/admin/
```

**Option C — Flatten public files to web root (traditional)**
```bash
cp -r public/.        /home/youraccount/public_html/
cp -r admin/          /home/youraccount/public_html/admin/
```
> If you choose Option C you do not need the root `.htaccess`.

---

## ③ Configure the Admin

Edit `admin/config/config.php`:

```php
define('DB_HOST', 'localhost');       // usually localhost on shared hosting
define('DB_NAME', 'your_db_name');   // create this DB first in cPanel → MySQL Databases
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
// APP_URL is now auto-detected from the server's host — no manual edit needed.

define('CRON_KEY', 'change-me-to-a-random-secret'); // used to secure the cron URL
```

---

## ④ Run the Installer

Navigate to your site's installer URL, for example:

```
https://your-actual-domain.com/admin/install/install.php
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

**Lock the installer** after setup — open `admin/config/config.php` and set:

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

If Composer is not available on your server:

```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php --install-dir=/usr/local/bin --filename=composer
php -r "unlink('composer-setup.php');"
```

Many cPanel/shared hosts also offer Composer under **cPanel → Software → PHP Composer**.

### Configure SMTP in the Admin Panel

1. Log in → **Settings** → **Email Configuration**
2. Fill in SMTP Host, Port, Username, Password, and Encryption (TLS recommended)
3. Set **From Name** and **From Email**
4. Set **Notification Email(s)** — comma-separated addresses that receive an alert each time the contact form is submitted
5. Click **Send Test Email** to verify

> Without Composer / SMTP configured, leave `smtp_host` blank and the system uses PHP's `mail()`.

---

## ⑥ Set Folder Permissions

```bash
chmod 755 admin/assets/img/
```

On some shared hosts the web server runs as a different user in the same group. If PHP cannot write to the directory (e.g. uploads fail), try:

```bash
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

Replace `yourdomain.com` with your actual domain.

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

1. The form POSTs to `public/api/contact.php`
2. The submission is saved as a **Lead** in the admin database (source: *Website Contact Form*)
3. An email notification is sent to every address configured in **Settings → Notification Email(s)**
4. The admin can view and manage the lead under **Admin → Leads**

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
- [ ] Enable HTTPS (uncomment block in `admin/.htaccess`)
- [ ] Set `APP_URL` to your real domain
- [ ] Enable 2FA for admin accounts
- [ ] Login rate limiting is active — 10 failed attempts locks the IP for 15 minutes
- [ ] `/admin/install/` is blocked from repeat runs by the `APP_INSTALLED` flag

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

