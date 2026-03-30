<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Service Areas';
$meta_desc  = 'Trash Panda Roll-Offs serves Greenville, Spartanburg, Anderson, Simpsonville, Greer, and surrounding communities throughout Upstate South Carolina.';
require_once __DIR__ . '/includes/header.php';

$areas = [
    ['Greenville',      'The heart of Upstate SC — our home base. We serve the entire city and surrounding neighborhoods.'],
    ['Spartanburg',     'Fast delivery throughout Spartanburg and the surrounding Boiling Springs / Duncan area.'],
    ['Anderson',        'Serving Anderson, Belton, Williamston, and the greater Anderson County area.'],
    ['Simpsonville',    'One of our busiest service zones — residential and commercial deliveries available daily.'],
    ['Greer',           'Serving Greer and nearby Taylors, Wade Hampton, and Five Forks communities.'],
    ['Mauldin',         'Quick delivery to Mauldin, Donaldson, and the Mauldin Road corridor.'],
    ['Easley',          'Serving Easley and the Pickens County area including Liberty and Pickens.'],
    ['Taylors',         'Taylors, Paris Mountain area, and nearby north Greenville communities.'],
    ['Duncan',          'Duncan, Wellford, and the Hwy 290 commercial and residential corridor.'],
    ['Boiling Springs', 'Serving Boiling Springs, Inman, and the northern Spartanburg County region.'],
    ['Powdersville',    'Powdersville, Piedmont, and the eastern Anderson/Greenville county line area.'],
    ['Fountain Inn',    'Fountain Inn, Gray Court, and surrounding Laurens County communities.'],
    ['Pelham',          'Pelham Road corridor, southern Spartanburg County, and adjacent neighborhoods.'],
    ['Lyman',           'Lyman, Wellford, and the Hwy 9 industrial and residential zones.'],
    ['Inman',           'Inman, Campobello, and northern Spartanburg county communities.'],
];
?>

<!-- ── Page Hero ─────────────────────────────────────────────── -->
<section class="page-hero">
    <div class="container-fluid px-4 position-relative">
        <nav class="breadcrumb-nav" aria-label="breadcrumb">
            <a href="/public/index.php">Home</a>
            <span class="sep">/</span>
            <span>Service Areas</span>
        </nav>
        <h1 class="page-hero-title">We Serve Your Area</h1>
        <p class="page-hero-sub">
            Proudly delivering dumpsters throughout Upstate South Carolina. If you're within our service radius, we'll be there.
        </p>
    </div>
</section>

<!-- ── Area Pills ────────────────────────────────────────────── -->
<section class="section-pad">
    <div class="container-fluid px-4">
        <div class="text-center mb-5">
            <span class="section-label">Coverage Area</span>
            <h2 class="section-title">Communities We Serve</h2>
            <p class="section-subtitle mx-auto text-muted">
                We cover a wide radius throughout the Upstate. Don't see your city? Give us a call—we may still be able to help.
            </p>
        </div>

        <!-- Pills -->
        <div class="d-flex flex-wrap justify-content-center gap-3 mb-5">
            <?php foreach ($areas as [$name, $_]): ?>
            <span class="area-pill">
                <i class="bi bi-geo-alt-fill"></i> <?php echo htmlspecialchars($name); ?>
            </span>
            <?php endforeach; ?>
        </div>

        <!-- Area Cards Grid -->
        <div class="row g-4">
            <?php foreach ($areas as [$name, $desc]): ?>
            <div class="col-lg-4 col-md-6">
                <div class="card-feature" style="text-align:left;padding:26px 24px;">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <div class="card-feature-icon" style="margin:0;width:44px;height:44px;font-size:1.1rem;flex-shrink:0;">
                            <i class="bi bi-geo-alt-fill"></i>
                        </div>
                        <h5 style="margin:0;font-size:1.1rem;color:var(--navy);"><?php echo htmlspecialchars($name); ?></h5>
                    </div>
                    <p style="margin:0;font-size:.88rem;color:var(--text-mid);"><?php echo htmlspecialchars($desc); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ── Map & Radius ───────────────────────────────────────────── -->
<section class="section-pad bg-light-gray">
    <div class="container-fluid px-4">
        <div class="row g-5 align-items-center">
            <div class="col-lg-6">
                <span class="section-label">Coverage Radius</span>
                <h2 class="section-title">How Far Do We Go?</h2>
                <p class="text-muted">
                    Our primary service area covers approximately a <strong>50-mile radius</strong> from Greenville, SC, encompassing most of Upstate South Carolina.
                </p>
                <ul class="check-list mt-3">
                    <li><i class="bi bi-check-circle-fill"></i> Full service within 30 miles of Greenville — fastest delivery</li>
                    <li><i class="bi bi-check-circle-fill"></i> 30–50 mile radius — standard delivery, availability may vary</li>
                    <li><i class="bi bi-check-circle-fill"></i> Beyond 50 miles — call us to check availability and pricing</li>
                    <li><i class="bi bi-check-circle-fill"></i> Commercial accounts may qualify for extended service areas</li>
                </ul>
                <div class="mt-4">
                    <a href="/public/contact.php" class="btn-primary-cta">
                        <i class="bi bi-chat-dots-fill"></i> Check My Area
                    </a>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="map-placeholder">
                    <i class="bi bi-map"></i>
                    <strong style="font-size:1.1rem;">Upstate South Carolina</strong>
                    <p style="font-size:.85rem;text-align:center;max-width:240px;margin:0;color:var(--text-mid);">
                        Interactive map coming soon. For now, call or submit a quote to confirm service in your area.
                    </p>
                    <a href="/public/contact.php" class="btn-primary-cta" style="font-size:.9rem;padding:10px 22px;">
                        Confirm My Area
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── Not In Our Area? ───────────────────────────────────────── -->
<section class="section-pad">
    <div class="container-fluid px-4">
        <div class="text-center">
            <span class="section-label">Don't See Your City?</span>
            <h2 class="section-title">We Might Still Be Able to Help</h2>
            <p class="section-subtitle mx-auto text-muted mb-4">
                Our service area is growing. If your location isn't listed above, give us a call or submit a quote request—we'll let you know right away if we can service you.
            </p>
            <div class="d-flex flex-wrap justify-content-center gap-3">
                <a href="/public/contact.php" class="btn-primary-cta">
                    <i class="bi bi-chat-dots-fill"></i> Submit a Quote Request
                </a>
                <a href="tel:5558675309" class="btn-navy-cta">
                    <i class="bi bi-telephone-fill"></i> <?php echo SITE_PHONE; ?>
                </a>
            </div>
        </div>
    </div>
</section>

<!-- ── CTA Banner ─────────────────────────────────────────────── -->
<section class="cta-banner">
    <div class="container-fluid px-4 position-relative">
        <span class="section-label">Ready?</span>
        <h2>Book a Dumpster in Your Area Today</h2>
        <p>Same-day and next-day delivery available throughout our service zone.</p>
        <div class="d-flex flex-wrap justify-content-center gap-3">
            <a href="/public/contact.php" class="btn-primary-cta">
                <i class="bi bi-chat-dots-fill"></i> Get a Free Quote
            </a>
            <a href="tel:5558675309" class="btn-secondary-cta">
                <i class="bi bi-telephone-fill"></i> <?php echo SITE_PHONE; ?>
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
