# Trash Panda Roll-Offs

A complete dumpster rental business platform — public-facing website + internal admin work-order panel.

> **Payments** are handled outside the system by the business — no payment processing is included.

---

## How This App Is Split

This repo has **two completely separate sections** that are deployed together but work independently:

| Section | Folder | Who uses it | URL |
|---------|--------|-------------|-----|
| **Public Website** | `/public/` | Customers & visitors | `https://yourdomain.com/` |
| **Admin / Work-Order Panel** | `/admin/` | Staff only (login required) | `https://yourdomain.com/admin/login.php` |

- Customers never see the admin panel — it lives at its own `/admin/` path with a separate login.
- Staff log in at `https://yourdomain.com/admin/login.php` to manage leads, quotes, and work orders.
- The public contact form (`/public/contact.php`) automatically saves submissions as leads inside the admin panel.

---

## Project Structure

```
DumpsterRentalApp/
├── public/                  ← Public-facing website (deploy to web root)
│   ├── index.php            — Homepage
│   ├── sizes.php            — Dumpster sizes
│   ├── residential.php      — Residential services
│   ├── commercial.php       — Commercial services
│   ├── service-areas.php    — Coverage map
│   ├── about.php            — About page
│   ├── faq.php              — FAQ
│   ├── contact.php          — Contact / quote-request form (saves leads)
│   ├── includes/
│   │   ├── config.php       — Site URL, phone, email settings
│   │   ├── header.php       — Navigation header
│   │   └── footer.php       — Footer
│   └── assets/css|js/       — Stylesheets and scripts
│
└── admin/                   ← Staff admin panel (deploy to /admin/ subfolder)
    ├── login.php            — Staff login page  ← separate login URL
    ├── dashboard.php        — Main dashboard
    ├── config/config.php    — Database credentials & app settings
    ├── modules/
    │   ├── leads/           — Incoming leads from the contact form
    │   ├── customers/       — Customer database
    │   ├── quotes/          — Quote builder (converts to work orders)
    │   ├── work_orders/     — Delivery & pickup tracking
    │   ├── dumpsters/       — Inventory management
    │   ├── scheduling/      — Calendar view
    │   ├── reports/         — Revenue & analytics
    │   └── settings/        — Users, roles, email, password
    ├── install/install.php  — One-time database installer
    └── cron/daily.php       — Scheduled reminders & alerts
```

---

## Tech Stack

- **PHP 8.1+**
- **MySQL 5.7+ / MariaDB 10.3+** via PDO
- **Bootstrap 5.3** — responsive CSS framework
- **Font Awesome 6** — icons
- **Vanilla JavaScript** — no build tools required

---

## Full Deployment Guide (cPanel / Shared Hosting)

### 1 — Upload Files

Upload the repo folders so your server looks like this:

```
public_html/               ← your domain's web root
├── index.php              ← from /public/index.php
├── sizes.php              ← from /public/sizes.php
├── residential.php        ← from /public/residential.php
├── commercial.php         ← from /public/commercial.php
├── service-areas.php      ← from /public/service-areas.php
├── about.php              ← from /public/about.php
├── faq.php                ← from /public/faq.php
├── contact.php            ← from /public/contact.php
├── includes/              ← from /public/includes/
├── assets/                ← from /public/assets/
└── admin/                 ← entire /admin/ folder goes here
    ├── login.php
    ├── dashboard.php
    ├── config/
    ├── modules/
    ├── install/
    └── ...
```

> **Tip:** The contents of `/public/` go directly into `public_html/` (your web root).  
> The `/admin/` folder goes inside `public_html/admin/`.

---

### 2 — Configure the Admin Panel

Edit `public_html/admin/config/config.php`:

```php
define('DB_HOST', 'localhost');          // usually localhost on shared hosting
define('DB_NAME', 'your_db_name');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

define('APP_URL',   'https://yourdomain.com/admin');
define('CRON_KEY',  'change-this-to-a-long-random-string');
```

---

### 3 — Configure the Public Website

Edit `public_html/includes/config.php`:

```php
define('SITE_NAME',  'Trash Panda Roll-Offs');
define('SITE_URL',   'https://yourdomain.com');
define('ADMIN_URL',  'https://yourdomain.com/admin');
define('SITE_PHONE', '(555) 867-5309');
define('SITE_EMAIL', 'info@yourdomain.com');
```

---

### 4 — Create the Database

In cPanel → MySQL Databases:

1. Create a new database
2. Create a database user and set a strong password
3. Add the user to the database with **All Privileges**

---

### 5 — Run the Installer

Visit this URL in your browser **once**:

```
https://yourdomain.com/admin/install/install.php
```

This will:
- Create all required database tables
- Seed 8 default dumpster units (TP-001 through TP-008)
- Create the initial admin user

---

### 6 — Lock the Installer

After installation, open `public_html/admin/config/config.php` and set:

```php
define('APP_INSTALLED', true);
```

This blocks the installer from running again.

---

### 7 — Set Folder Permissions

The logo upload folder needs to be writable:

```bash
chmod 755 public_html/admin/assets/img/
# or if 755 doesn't work:
chmod 775 public_html/admin/assets/img/
```

---

### 8 — Enable HTTPS

In `public_html/.htaccess` and `public_html/admin/.htaccess`, uncomment the redirect block:

```apache
RewriteCond %{HTTPS} off
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
```

---

### 9 — Set Up the Daily Cron Job

In cPanel → Cron Jobs, add this entry to run at 8 AM every day:

```
0 8 * * * php /home/youraccount/public_html/admin/cron/daily.php >> /dev/null 2>&1
```

Or trigger it via URL using the secure key from your config:

```
https://yourdomain.com/admin/cron/daily.php?key=YOUR_CRON_KEY
```

The cron job:
- Sends delivery reminder emails for tomorrow's scheduled deliveries
- Sends overdue pickup alerts to the company email
- Auto-activates work orders whose delivery date has passed

---

## Your Two URLs After Deployment

| Page | URL |
|------|-----|
| Public website homepage | `https://yourdomain.com/` |
| Public contact / quote form | `https://yourdomain.com/contact.php` |
| **Staff admin login** | `https://yourdomain.com/admin/login.php` |
| Admin dashboard (after login) | `https://yourdomain.com/admin/dashboard.php` |

---

## First Admin Login

| Field | Value |
|-------|-------|
| Email | `admin@example.com` |
| Password | `ChangeMe123!` |

> You will be **forced to change the password** on first login.

---

## Staff Roles

| Role | What they can do |
|------|-----------------|
| **admin** | Full access including user management |
| **office** | Create/edit leads, customers, quotes, work orders — no user management |
| **dispatcher** | View everything, update work order status only |
| **readonly** | View-only access to all records |

To add staff: log in as admin → **Settings → Users → Add User**.

---

## Security Checklist Before Going Live

- [ ] Change `CRON_KEY` in `config.php` to a random string
- [ ] Change the default admin password after first login
- [ ] Set `APP_INSTALLED = true` in `config.php` after running the installer
- [ ] Enable HTTPS and uncomment the redirect in `.htaccess`
- [ ] Enable two-factor authentication for admin accounts (Settings → Manage 2FA)
- [ ] Delete or block `/admin/install/` after setup is complete

---

For detailed admin panel documentation (2FA, email config, advanced settings), see **[admin/README.md](admin/README.md)**.
