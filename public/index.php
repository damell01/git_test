<?php

require_once dirname(__DIR__) . '/admin/includes/bootstrap.php';

use TrashPanda\Fieldora\Services\AuthService;
use TrashPanda\Fieldora\Services\BookingService;
use TrashPanda\Fieldora\Services\TenantService;

$uriPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$route = trim($uriPath, '/');
$segments = $route === '' ? [] : explode('/', $route);

if (isset($segments[0]) && $segments[0] === 'public') {
    array_shift($segments);
    $route = implode('/', $segments);
}

$page = $segments[0] ?? 'home';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if ($page === 'login') {
            $user = db_fetch('SELECT * FROM users WHERE email = ? AND active = 1 LIMIT 1', [trim((string) ($_POST['email'] ?? ''))]);
            if (!$user || !password_verify((string) ($_POST['password'] ?? ''), $user['password'])) {
                throw new RuntimeException('Invalid email or password.');
            }
            login_user($user);
            redirect(APP_URL . '/modules/fieldora/onboarding.php');
        }

        if ($page === 'register') {
            $user = AuthService::registerTenantOwner($_POST);
            login_user($user);
            redirect(APP_URL . '/modules/fieldora/onboarding.php');
        }

        if ($page === 'forgot-password') {
            AuthService::beginPasswordReset((string) ($_POST['email'] ?? ''));
            $success = 'If the account exists, a reset link has been queued.';
        }

        if ($page === 'reset-password') {
            $ok = AuthService::resetPassword((string) ($_POST['token'] ?? ''), (string) ($_POST['password'] ?? ''));
            if (!$ok) {
                throw new RuntimeException('That reset link is invalid or expired.');
            }
            $success = 'Password updated. You can sign in now.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

function render_site(string $title, string $content, string $description = 'Fieldora helps service businesses get booked and get paid online.'): void
{
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?= e($title) ?> | <?= e(APP_NAME) ?></title>
        <meta name="description" content="<?= e($description) ?>">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="<?= e(SITE_URL) ?>/fieldora.css">
    </head>
    <body>
        <header class="site-header">
            <a class="brand" href="<?= e(SITE_URL) ?>">Fieldora</a>
            <nav class="site-nav">
                <a href="<?= e(SITE_URL) ?>/pricing">Pricing</a>
                <a href="<?= e(SITE_URL) ?>/demo">Demo</a>
                <a href="<?= e(SITE_URL) ?>/login">Login</a>
                <a class="btn btn-primary" href="<?= e(SITE_URL) ?>/register">Start Free</a>
            </nav>
        </header>
        <?= $content ?>
        <footer class="site-footer">
            <div>
                <strong>Fieldora</strong>
                <p>Get booked and get paid online without the back-and-forth.</p>
            </div>
            <div class="footer-links">
                <a href="<?= e(SITE_URL) ?>">Home</a>
                <a href="<?= e(SITE_URL) ?>/pricing">Pricing</a>
                <a href="<?= e(SITE_URL) ?>/demo">Demo</a>
                <a href="<?= e(SITE_URL) ?>/register">Register</a>
            </div>
        </footer>
    </body>
    </html>
    <?php
}

if ($page === 'home') {
    ob_start(); ?>
    <main class="hero">
        <section class="hero-copy">
            <span class="eyebrow">Booking, invoicing, deposits, reminders</span>
            <h1>Get booked and get paid online.</h1>
            <p>Fieldora gives service and rental businesses a branded booking page, team-ready admin dashboard, deposits or full payment, invoices, reminders, exports, and operational tools in one place.</p>
            <div class="cta-row">
                <a class="btn btn-primary" href="<?= e(SITE_URL) ?>/register">Start Free</a>
                <a class="btn btn-secondary" href="<?= e(SITE_URL) ?>/pricing">See Pricing</a>
            </div>
            <div class="proof-grid">
                <div><strong>Online booking</strong><span>Public pages for many industries</span></div>
                <div><strong>Deposits + invoices</strong><span>Collect money sooner</span></div>
                <div><strong>Team operations</strong><span>Assignments, roles, exports</span></div>
            </div>
        </section>
        <section class="hero-panel">
            <div class="surface">
                <div class="stat">Today: 14 bookings</div>
                <div class="stat">Revenue: $4,980</div>
                <div class="stat">Jobs assigned: 9</div>
                <div class="stat">Invoices awaiting payment: 6</div>
            </div>
        </section>
    </main>
    <section class="section split">
        <div><h2>The problem</h2><p>Too many businesses still juggle calls, text messages, manual invoices, sticky notes, and payment follow-ups.</p></div>
        <div><h2>The solution</h2><p>Fieldora centralizes booking, customer records, jobs, invoices, payments, notifications, and team visibility in one modular SaaS platform.</p></div>
    </section>
    <section class="section steps">
        <h2>How it works</h2>
        <div class="cards three">
            <article class="card"><strong>1. Publish a branded booking page</strong><p>Choose services, availability rules, deposits, and colors.</p></article>
            <article class="card"><strong>2. Collect bookings and payment</strong><p>Let customers request or instantly book with deposit or full payment.</p></article>
            <article class="card"><strong>3. Run the day from one dashboard</strong><p>Manage jobs, invoices, staff, reminders, exports, and route groupings.</p></article>
        </div>
    </section>
    <section class="section">
        <h2>Features</h2>
        <div class="cards four">
            <article class="card"><strong>Booking pages</strong><p>Generic enough for rentals, cleaning, lawn care, detailing, contractors, and mobile services.</p></article>
            <article class="card"><strong>Payments</strong><p>Stripe-ready deposits, full payments, manual payment logging, and balance tracking.</p></article>
            <article class="card"><strong>Invoices</strong><p>Create, send, resend, export, and track invoice payment status.</p></article>
            <article class="card"><strong>Teams + permissions</strong><p>Owner, Manager, Staff, and Accounting roles with plan-gated controls.</p></article>
            <article class="card"><strong>Notifications</strong><p>Email now, SMS-ready architecture for Growth and Pro tenants.</p></article>
            <article class="card"><strong>Automations + webhooks</strong><p>Trigger follow-ups, status updates, reminders, and outbound events.</p></article>
            <article class="card"><strong>Analytics</strong><p>Track bookings, revenue, invoices, payments, and recent activity.</p></article>
            <article class="card"><strong>Exports</strong><p>Accounting-friendly CSV exports for bookings, invoices, and payments.</p></article>
        </div>
    </section>
    <section class="section split">
        <div><h2>Who it fits</h2><p>Rental companies, cleaners, lawn care teams, detailers, contractors, home services, mobile crews, and booking-led local businesses.</p></div>
        <div><h2>Testimonials</h2><p>"We cut down booking back-and-forth and stopped chasing deposits manually."<br>"My office team finally has one place for jobs, invoices, and payments."</p></div>
    </section>
    <section class="section faq">
        <h2>FAQ</h2>
        <div class="cards two">
            <article class="card"><strong>Can customers pay a deposit instead of full balance?</strong><p>Yes. Services support none, percent, or fixed deposits with full-payment options when enabled.</p></article>
            <article class="card"><strong>Does it support teams?</strong><p>Yes. Pro includes team members, advanced permissions, and role-based access.</p></article>
            <article class="card"><strong>Can I use my own branding?</strong><p>Yes. Upload a logo, set colors, business info, and tailor your booking page messaging.</p></article>
            <article class="card"><strong>Will it work on shared hosting?</strong><p>Yes. The initial build is cron-friendly and PHP/MySQL shared-hosting compatible.</p></article>
        </div>
    </section>
    <section class="section cta-banner">
        <h2>Ready to stop losing time to manual follow-up?</h2>
        <a class="btn btn-primary" href="<?= e(SITE_URL) ?>/register">Create your workspace</a>
    </section>
    <?php render_site('Get booked and get paid online', ob_get_clean());
    exit;
}

if ($page === 'pricing') {
    ob_start(); ?>
    <main class="page-shell"><h1>Pricing</h1><p class="page-lead">Simple plans with feature gates that scale cleanly as your team grows.</p>
        <div class="cards three pricing">
            <article class="card"><h2>Starter</h2><strong>$29/mo</strong><p>Booking page, services, payments, deposits, invoicing, email notifications, dashboard, customers, and booking management.</p></article>
            <article class="card featured"><h2>Growth</h2><strong>$59/mo</strong><p>Everything in Starter plus SMS architecture, better reporting, analytics, improved invoice/payment tools, and outbound webhooks.</p></article>
            <article class="card"><h2>Pro</h2><strong>$99/mo</strong><p>Everything in Growth plus automations, teams, advanced permissions, deeper branding, route tools, and operational modules.</p></article>
        </div>
    </main>
    <?php render_site('Pricing', ob_get_clean()); exit;
}

if ($page === 'demo') {
    ob_start(); ?>
    <main class="page-shell"><h1>Demo</h1><p class="page-lead">Fieldora is designed to feel fast for the customer and calm for the team.</p>
        <div class="cards two">
            <article class="card"><strong>Customer view</strong><p>Choose services, request a date/time, add notes, and pay deposit or full amount at booking.</p></article>
            <article class="card"><strong>Admin view</strong><p>Review bookings, jobs, payments, invoices, activity, analytics, team assignments, route tools, and exports.</p></article>
        </div>
    </main>
    <?php render_site('Demo', ob_get_clean()); exit;
}

if (in_array($page, ['login', 'register', 'forgot-password', 'reset-password'], true)) {
    $token = (string) ($_GET['token'] ?? '');
    ob_start(); ?>
    <main class="auth-shell">
        <form class="auth-card" method="post">
            <h1><?= e(ucwords(str_replace('-', ' ', $page))) ?></h1>
            <?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="notice success"><?= e($success) ?></div><?php endif; ?>

            <?php if ($page === 'login'): ?>
                <input name="email" type="email" placeholder="Email address" required>
                <input name="password" type="password" placeholder="Password" required>
                <button class="btn btn-primary" type="submit">Sign in</button>
                <a href="<?= e(SITE_URL) ?>/forgot-password">Forgot your password?</a>
            <?php elseif ($page === 'register'): ?>
                <input name="business_name" type="text" placeholder="Business name" required>
                <input name="owner_name" type="text" placeholder="Your name" required>
                <input name="email" type="email" placeholder="Email address" required>
                <input name="business_phone" type="text" placeholder="Business phone">
                <input name="timezone" type="text" placeholder="Timezone" value="<?= e(APP_TIMEZONE) ?>">
                <input name="password" type="password" placeholder="Password" required>
                <button class="btn btn-primary" type="submit">Create account</button>
            <?php elseif ($page === 'forgot-password'): ?>
                <input name="email" type="email" placeholder="Email address" required>
                <button class="btn btn-primary" type="submit">Send reset link</button>
            <?php else: ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <input name="password" type="password" placeholder="New password" required>
                <button class="btn btn-primary" type="submit">Update password</button>
            <?php endif; ?>
        </form>
    </main>
    <?php render_site(ucwords(str_replace('-', ' ', $page)), ob_get_clean()); exit;
}

if ($page === 'book' && isset($segments[1])) {
    $tenant = TenantService::findBySlug($segments[1]);
    if (!$tenant) {
        http_response_code(404);
        render_site('Booking page not found', '<main class="page-shell"><h1>Booking page not found</h1></main>');
        exit;
    }

    $services = BookingService::tenantServices((int) $tenant['id']);
    $brand = db_fetch('SELECT * FROM tenant_branding WHERE tenant_id = ? LIMIT 1', [$tenant['id']]) ?: [];
    $paymentAccount = db_fetch('SELECT * FROM tenant_payment_accounts WHERE tenant_id = ? AND provider = ? LIMIT 1', [$tenant['id'], 'stripe']) ?: [];
    $showPrices = TenantService::setting((int) $tenant['id'], 'show_public_prices', '1') === '1';
    $allowFullPayment = TenantService::setting((int) $tenant['id'], 'allow_full_payment', '1') === '1';
    $requireDeposit = TenantService::setting((int) $tenant['id'], 'require_deposit', '1') === '1';
    $approvalMode = TenantService::setting((int) $tenant['id'], 'booking_approval_mode', 'request');
    $bookingTagline = TenantService::setting((int) $tenant['id'], 'booking_tagline', 'Book online in minutes');
    $bookingHeaderNote = TenantService::setting((int) $tenant['id'], 'booking_header_note', (string) ($brand['booking_intro'] ?? 'Choose what you need, pick a day, and we will take it from there.'));
    $bookingFooterNote = TenantService::setting((int) $tenant['id'], 'booking_footer_note', (string) ($brand['footer_text'] ?? 'Questions, special timing, or custom work? Call or text us and we will help you book the right option.'));
    $callTextFallback = TenantService::setting((int) $tenant['id'], 'call_text_fallback', 'Prefer to book by phone? Call or text us and we will help.');
    $rules = BookingService::bookingRulesSummary((int) $tenant['id']);
    $successMessage = isset($_GET['success']) ? 'Payment received. Your booking is confirmed.' : (isset($_GET['canceled']) ? 'Checkout was canceled. You can try again or choose manual payment.' : '');
    $brandStyle = '--brand:' . e($brand['primary_color'] ?? '#2563eb') . ';';
    ob_start(); ?>
    <main class="booking-shell" style="<?= $brandStyle ?>">
        <section class="booking-hero">
            <?php if (!empty($brand['logo_path'])): ?>
                <img src="<?= e($brand['logo_path']) ?>" alt="<?= e($tenant['name']) ?>" style="max-height:72px;max-width:220px;margin-bottom:16px;">
            <?php endif; ?>
            <p class="eyebrow"><?= e($bookingTagline) ?></p>
            <h1><?= e($tenant['name']) ?></h1>
            <p><?= e($brand['marketing_headline'] ?: $bookingHeaderNote) ?></p>
            <p class="muted">Mode: <?= e($approvalMode === 'instant' ? 'Instant booking' : 'Request and approval') ?> - <?= $requireDeposit ? 'Deposit enabled' : 'Pay later allowed' ?></p>
            <p class="muted"><?= e(trim((string) ($tenant['business_phone'] ?? ''))) ?> <?= !empty($tenant['business_email']) ? '- ' . e($tenant['business_email']) : '' ?></p>
            <?php if (!empty($tenant['address_line1']) || !empty($tenant['city'])): ?>
                <p class="muted"><?= e(trim(implode(', ', array_filter([(string) ($tenant['address_line1'] ?? ''), (string) ($tenant['city'] ?? ''), (string) ($tenant['state'] ?? ''), (string) ($tenant['postal_code'] ?? '')])))) ?></p>
            <?php endif; ?>
            <p class="muted">Hours: <?= e($rules['working_hours_text']) ?></p>
            <p class="muted">Minimum notice: <?= e((string) $rules['minimum_notice_hours']) ?> hour(s)</p>
        </section>
        <?php if ($successMessage !== ''): ?>
            <div class="notice <?= isset($_GET['success']) ? 'success' : 'error' ?>"><?= e($successMessage) ?></div>
        <?php endif; ?>
        <form id="booking-form" class="booking-form">
            <input type="hidden" name="tenant_slug" value="<?= e($tenant['slug']) ?>">
            <div class="cards two">
                <article class="card">
                    <h2>Choose services</h2>
                    <?php foreach ($services as $service): ?>
                        <label class="service-option">
                            <input type="checkbox" name="service_ids[]" value="<?= (int) $service['id'] ?>" data-price="<?= e($service['price']) ?>">
                            <span><strong><?= e($service['name']) ?></strong><small><?= $showPrices ? '$' . number_format((float) $service['price'], 2) : 'Contact for pricing' ?><?= $service['duration_minutes'] ? ' - ' . (int) $service['duration_minutes'] . ' min' : '' ?><?= !empty($service['description']) ? ' - ' . e($service['description']) : '' ?></small></span>
                        </label>
                    <?php endforeach; ?>
                </article>
                <article class="card">
                    <h2>Your details</h2>
                    <input name="customer_name" type="text" placeholder="Full name" required>
                    <input name="email" type="email" placeholder="Email">
                    <input name="phone" type="text" placeholder="Phone">
                    <input name="scheduled_date" type="date" required>
                    <input name="start_time" type="time">
                    <input name="request_window" type="text" placeholder="Preferred window (optional)">
                    <input name="address_line1" type="text" placeholder="Service address">
                    <div class="inline-grid">
                        <input name="city" type="text" placeholder="City">
                        <input name="state" type="text" placeholder="State">
                        <input name="postal_code" type="text" placeholder="ZIP">
                    </div>
                    <select name="payment_option">
                        <option value="deposit">Pay <?= $requireDeposit ? 'deposit' : 'now' ?></option>
                        <?php if ($allowFullPayment): ?><option value="full">Pay in full</option><?php endif; ?>
                    </select>
                    <select name="payment_method">
                        <?php if (!empty($paymentAccount['stripe_account_id']) && ($paymentAccount['account_status'] ?? '') !== 'disconnected'): ?><option value="stripe">Card via Stripe</option><?php endif; ?>
                        <option value="manual">Manual payment</option>
                    </select>
                    <textarea name="notes" placeholder="Notes or instructions"></textarea>
                    <div class="booking-total">Estimated total: <strong id="booking-total"><?= $showPrices ? '$0.00' : 'Calculated after review' ?></strong></div>
                    <div id="booking-result" class="notice" style="display:none"></div>
                    <button class="btn btn-primary" type="submit">Continue</button>
                </article>
            </div>
        </form>
        <section class="card" style="margin-top:20px;">
            <h2>Need help booking?</h2>
            <p class="muted"><?= e($bookingFooterNote) ?></p>
            <p class="muted"><?= e($callTextFallback) ?></p>
        </section>
        <script>
        const form = document.getElementById('booking-form');
        const totalEl = document.getElementById('booking-total');
        const resultEl = document.getElementById('booking-result');
        const showPrices = <?= $showPrices ? 'true' : 'false' ?>;
        function recalc(){let total=0;form.querySelectorAll('input[name="service_ids[]"]:checked').forEach(el=>total+=parseFloat(el.dataset.price||'0'));totalEl.textContent=showPrices?('$'+total.toFixed(2)):'Calculated after review';}
        form.querySelectorAll('input[name="service_ids[]"]').forEach(el=>el.addEventListener('change',recalc));recalc();
        form.addEventListener('submit',async(e)=>{e.preventDefault();const fd=new FormData(form);const res=await fetch('<?= e(SITE_URL) ?>/api/fieldora-booking.php',{method:'POST',body:fd});const json=await res.json();resultEl.style.display='block';if(json.success&&json.checkout_url){window.location=json.checkout_url;return;}if(json.success){resultEl.className='notice success';resultEl.textContent='Booking submitted successfully.';form.reset();recalc();}else{resultEl.className='notice error';resultEl.textContent=json.error||'Unable to submit booking.';}});
        </script>
    </main>
    <?php render_site($tenant['name'] . ' booking', ob_get_clean()); exit;
}

http_response_code(404);
render_site('Not found', '<main class="page-shell"><h1>Not found</h1></main>');
