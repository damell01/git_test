# Trash Panda Roll-Offs — Dumpster Rental Website & Work Order System

A complete dumpster rental business platform including a public-facing website and an internal admin work order management system. Payments are handled outside the system by the business.

## Project Structure

```
/public/   — Client-facing public website (Home, Sizes, Contact, etc.)
/admin/    — Internal staff admin panel (protected, login required)
```

## Features

- Leads Management
- Customer Database
- Quote Builder with print layout
- Work Order Management (with printable invoice)
- Dumpster Inventory
- Scheduling Calendar
- Reports & Revenue Tracking
- User & Role Management
- Public website with quote request form (saves leads to admin database)

## Tech Stack

- PHP 8.1+
- MySQL / MariaDB via PDO
- Bootstrap 5.3
- Vanilla JavaScript
- Font Awesome 6

---

## Requirements

- PHP 8.1+
- MySQL 5.7+ or MariaDB 10.3+
- PDO with PDO_MySQL driver enabled

---

## Upload Instructions

Upload the entire `/admin/` folder to your web server's public directory, e.g.:

```
public_html/admin/
```

---

## Config Setup

Edit `config/config.php` and update the following values:

```php
define('DB_HOST', 'your_db_host');
define('DB_NAME', 'your_db_name');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('APP_URL', 'https://yourdomain.com');
```

---

## Running the Installer

Navigate to:

```
https://yourdomain.com/admin/install/install.php
```

The installer will:
- Create all required database tables
- Seed default data
- Create the initial admin user

---

## First Login Credentials

| Field    | Value               |
|----------|---------------------|
| Email    | admin@example.com   |
| Password | ChangeMe123!        |

> **You will be forced to change the password on first login.**

---

## Lock the Installer

After successful installation, open `config/config.php` and set:

```php
define('APP_INSTALLED', true);
```

This prevents the installer from running again.

---

## Folder Permissions

`/admin/assets/img/` should be writable if you want to upload a logo via Settings:

```bash
chmod 755 admin/assets/img/
# or
chmod 775 admin/assets/img/
```

---

## Default Dumpster Units

8 dumpster units are seeded by the installer:

| Unit ID | Size     |
|---------|----------|
| TP-001  | 10 yard  |
| TP-002  | 10 yard  |
| TP-003  | 15 yard  |
| TP-004  | 15 yard  |
| TP-005  | 20 yard  |
| TP-006  | 20 yard  |
| TP-007  | 30 yard  |
| TP-008  | 40 yard  |

Update or delete these through the **Inventory** module after logging in.

---

## Role Descriptions

| Role       | Access Level |
|------------|--------------|
| admin      | Full access to all features including user management |
| office     | Can create/edit leads, customers, quotes, and work orders. Cannot manage users. |
| dispatcher | Can view all records and update work order statuses only |
| readonly   | View-only access to all records |

---

## How to Add Users

1. Log in as **admin**
2. Go to **Settings → Users → Add User**
3. Fill in name, email, role, and password

---

## Two-Factor Authentication

1. Log in and go to **Settings → Two-Factor Authentication**
2. Scan the QR code with Google Authenticator, Authy, or any TOTP app
3. Enter the 6-digit code to confirm setup
4. Save your 8 backup codes in a secure location (shown only once)
5. Admins can disable 2FA for any user via **Settings → Users**

---

## Cron Job Setup (cPanel)

In cPanel → Cron Jobs, add the following entry to run daily at 8 AM:

```
0 8 * * * php /home/youraccount/public_html/admin/cron/daily.php >> /dev/null 2>&1
```

Or call via web (with the secure key from `config.php`):
```
https://yourdomain.com/admin/cron/daily.php?key=YOUR_CRON_KEY
```

The cron job:
- Sends delivery reminder emails (for tomorrow's deliveries)
- Sends overdue pickup alerts to the company email
- Auto-activates work orders whose delivery date has passed

---

## Email

The system uses PHP `mail()` for sending emails. For reliable delivery:

- Use your hosting's built-in SMTP relay
- Or configure SendGrid/Mailgun as an SMTP relay via cPanel
- Email settings can be configured in **Settings → Email Configuration**

---

## Security Notes

- Change `CRON_KEY` in `config.php` to a random string before going live
- Keep `STRIPE_SECRET_KEY` private — never commit it to version control
- Delete or restrict access to `/install/` after installation
- Enable HTTPS and uncomment the redirect in `.htaccess`
- 2FA is optional per-user but strongly recommended for admin accounts
- Login rate limiting is enabled: 10 failed attempts locks an IP for 15 minutes
