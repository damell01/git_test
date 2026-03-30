# Trash Panda Roll-Offs

A complete dumpster rental business platform — public-facing website + internal admin panel.

## Project Structure

| Folder | Description |
|--------|-------------|
| `/public/` | Client-facing website (Home, Sizes, Residential, Commercial, Service Areas, About, FAQ, Contact/Quote) |
| `/admin/` | Internal staff admin panel (Leads, Customers, Quotes, Work Orders, Inventory, Scheduling, Settings) |

## Quick Start

1. Upload both `/public/` and `/admin/` to your web server
2. Configure `/admin/config/config.php` with your database credentials
3. Update `/public/includes/config.php` with your site URL and contact info
4. Run the installer at `https://yourdomain.com/admin/install/install.php`
5. Staff access the admin panel via `https://yourdomain.com/admin/login.php`

> **Payments** are handled outside the system by the business — no payment processing is included.

For full setup instructions, see **[admin/README.md](admin/README.md)**.
