<?php
require_once __DIR__ . '/_bootstrap.php';
$url = tenant_booking_url();
$slug = current_tenant()['slug'] ?? 'your-business';
$snippet = '<a href="' . $url . '" style="padding:12px 18px;background:#2563eb;color:#fff;border-radius:10px;text-decoration:none;">Book Now</a>';
$qr = 'https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=' . rawurlencode($url);
$showTour = tenant_setting('tour_' . (int) (current_user()['id'] ?? 0) . '_share', '0') !== '1';
fieldora_layout_start('Share Tools', 'share'); ?>
<?php if ($showTour): ?><section class="card" style="margin-bottom:20px;"><h3>Booking page tour</h3><p class="muted">This page helps you copy the public booking link, embed a button, and test what customers will see before you send traffic to it.</p><div class="topbar-actions"><a class="primary-btn" href="<?= e(APP_URL) ?>/modules/fieldora/tour.php?tour=share&done=1">Got it</a><a class="ghost-link" href="<?= e(APP_URL) ?>/modules/fieldora/tour.php?tour=share&done=1">Skip</a></div></section><?php endif; ?>
<div class="grid two">
    <section class="card"><h3>Booking link</h3><p class="muted"><?= e($url) ?></p><p class="muted">Public route: /book/<?= e($slug) ?></p></section>
    <section class="card"><h3>Embed snippet</h3><textarea style="width:100%;min-height:120px"><?= e($snippet) ?></textarea></section>
</div>
<div class="grid two" style="margin-top:20px;">
    <section class="card"><h3>QR code</h3><img src="<?= e($qr) ?>" alt="QR code" style="max-width:180px;border-radius:16px"></section>
    <section class="card"><h3>Share ideas</h3><p class="muted">Add the booking link to your website nav, Google Business profile, invoices, SMS reminders, and printed leave-behinds.</p></section>
</div>
<?php fieldora_layout_end();
