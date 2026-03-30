<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Fast & Affordable Dumpster Rentals';
$meta_desc  = 'Trash Panda Roll-Offs offers fast, affordable dumpster rentals for residential and commercial projects. Same-day delivery available. Serving the Upstate SC area.';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ── Hero ──────────────────────────────────────────────────── -->
<section class="hero">
    <div class="container-fluid px-4">
        <div class="row align-items-center gy-5">
            <div class="col-lg-6">
                <div class="hero-badge">
                    <i class="bi bi-geo-alt-fill"></i> Serving Upstate South Carolina
                </div>
                <h1 class="hero-title">
                    Fast &amp; Affordable<br>
                    <span class="accent">Dumpster Rentals</span>
                </h1>
                <p class="hero-subtitle">
                    Roll-off dumpsters delivered to your door—same day available. Residential cleanouts,
                    commercial construction, renovations, and more. No hidden fees.
                </p>
                <div class="hero-actions">
                    <a href="/public/contact.php" class="btn-primary-cta">
                        <i class="bi bi-chat-dots-fill"></i> Get a Free Quote
                    </a>
                    <a href="/public/sizes.php" class="btn-secondary-cta">
                        <i class="bi bi-grid-fill"></i> View Dumpster Sizes
                    </a>
                </div>
                <div class="d-flex flex-wrap gap-3">
                    <span style="color:rgba(255,255,255,.65);font-size:.85rem;"><i class="bi bi-star-fill text-orange me-1"></i> Rated 5 Stars Locally</span>
                    <span style="color:rgba(255,255,255,.65);font-size:.85rem;"><i class="bi bi-shield-check-fill text-orange me-1"></i> Licensed &amp; Insured</span>
                    <span style="color:rgba(255,255,255,.65);font-size:.85rem;"><i class="bi bi-house-heart-fill text-orange me-1"></i> Family Owned</span>
                </div>
            </div>
            <div class="col-lg-5 offset-lg-1 hero-visual">
                <div class="hero-dumpster-card">
                    <span class="hero-dumpster-icon">🗑</span>
                    <h3 style="color:#fff;font-size:1.3rem;margin-bottom:.5rem;">Roll-Off Dumpster Rentals</h3>
                    <p style="color:rgba(255,255,255,.68);font-size:.9rem;margin-bottom:1.4rem;">
                        10, 15, 20 &amp; 30 Yard Sizes Available
                    </p>
                    <div class="d-flex justify-content-around text-center">
                        <div>
                            <div style="font-family:'Barlow Condensed',sans-serif;font-size:1.6rem;font-weight:800;color:var(--orange);">24hr</div>
                            <div style="font-size:.75rem;color:rgba(255,255,255,.55);text-transform:uppercase;letter-spacing:.06em;">Delivery</div>
                        </div>
                        <div style="width:1px;background:rgba(255,255,255,.12);"></div>
                        <div>
                            <div style="font-family:'Barlow Condensed',sans-serif;font-size:1.6rem;font-weight:800;color:var(--orange);">4</div>
                            <div style="font-size:.75rem;color:rgba(255,255,255,.55);text-transform:uppercase;letter-spacing:.06em;">Sizes</div>
                        </div>
                        <div style="width:1px;background:rgba(255,255,255,.12);"></div>
                        <div>
                            <div style="font-family:'Barlow Condensed',sans-serif;font-size:1.6rem;font-weight:800;color:var(--orange);">100+</div>
                            <div style="font-size:.75rem;color:rgba(255,255,255,.55);text-transform:uppercase;letter-spacing:.06em;">Customers</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── Trust Bar ─────────────────────────────────────────────── -->
<div class="trust-bar">
    <div class="container-fluid px-4">
        <div class="trust-bar-inner">
            <div class="trust-item"><i class="bi bi-lightning-charge-fill"></i> Same-Day Delivery</div>
            <div class="trust-item"><i class="bi bi-shield-fill-check"></i> Licensed &amp; Insured</div>
            <div class="trust-item"><i class="bi bi-house-heart-fill"></i> Local Family Business</div>
            <div class="trust-item"><i class="bi bi-people-fill"></i> 100+ Happy Customers</div>
            <div class="trust-item"><i class="bi bi-tag-fill"></i> No Hidden Fees</div>
        </div>
    </div>
</div>

<!-- ── Why Choose Us ─────────────────────────────────────────── -->
<section class="section-pad bg-light-gray">
    <div class="container-fluid px-4">
        <div class="text-center mb-5">
            <span class="section-label">Why Choose Us</span>
            <h2 class="section-title">The Smarter Way to Rent a Dumpster</h2>
            <p class="section-subtitle mx-auto text-muted">
                We keep it simple, transparent, and stress-free so you can focus on your project.
            </p>
        </div>
        <div class="row g-4">
            <div class="col-lg-4 col-md-6">
                <div class="card-feature">
                    <div class="card-feature-icon"><i class="bi bi-lightning-charge-fill"></i></div>
                    <h4>Fast Delivery</h4>
                    <p>Need it today? We offer same-day and next-day delivery throughout Upstate SC. Just call or submit a quote and we'll be there.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="card-feature">
                    <div class="card-feature-icon"><i class="bi bi-currency-dollar"></i></div>
                    <h4>Affordable Pricing</h4>
                    <p>Transparent, flat-rate pricing with no surprises. What you see is what you pay. We beat or match local competitor quotes.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="card-feature">
                    <div class="card-feature-icon"><i class="bi bi-check-circle-fill"></i></div>
                    <h4>Reliable Pickup</h4>
                    <p>Schedule your pickup at your convenience. We show up on time, every time—because your project timeline matters to us.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="card-feature">
                    <div class="card-feature-icon"><i class="bi bi-headset"></i></div>
                    <h4>Local Support</h4>
                    <p>You call and talk to a real local person, not a call center. We know the area and can answer your questions fast.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="card-feature">
                    <div class="card-feature-icon"><i class="bi bi-recycle"></i></div>
                    <h4>Eco-Responsible</h4>
                    <p>We dispose of waste responsibly and recycle where possible. Doing right by the community and environment is our commitment.</p>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="card-feature">
                    <div class="card-feature-icon"><i class="bi bi-calendar-check-fill"></i></div>
                    <h4>Flexible Rentals</h4>
                    <p>Rent for 3, 5, 7, 10, or 14 days. Need more time? Just call us—we work around your schedule, not ours.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── How It Works ───────────────────────────────────────────── -->
<section class="section-pad">
    <div class="container-fluid px-4">
        <div class="text-center mb-5">
            <span class="section-label">The Process</span>
            <h2 class="section-title">How It Works</h2>
            <p class="section-subtitle mx-auto text-muted">Three easy steps and you're ready to go.</p>
        </div>
        <div class="row g-4 justify-content-center">
            <div class="col-lg-4 col-md-6">
                <div class="step-card">
                    <div class="step-number">1</div>
                    <div>
                        <h4>Call or Submit a Quote</h4>
                        <p>Tell us your project, location, dumpster size, and preferred delivery date. We'll confirm availability and pricing within minutes.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="step-card">
                    <div class="step-number">2</div>
                    <div>
                        <h4>We Deliver Your Dumpster</h4>
                        <p>We drop off the dumpster right where you need it. Load it up at your own pace—no rushing on our end.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 col-md-6">
                <div class="step-card">
                    <div class="step-number">3</div>
                    <div>
                        <h4>We Pick It Up When Done</h4>
                        <p>When you're finished, just give us a call or let your rental period expire. We'll haul it away and handle the disposal.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── Dumpster Sizes Preview ─────────────────────────────────── -->
<section class="section-pad bg-light-gray">
    <div class="container-fluid px-4">
        <div class="text-center mb-5">
            <span class="section-label">Dumpster Sizes</span>
            <h2 class="section-title">Find the Right Size for Your Project</h2>
            <p class="section-subtitle mx-auto text-muted">
                Not sure which size you need? <a href="/public/contact.php">Ask us</a>—we'll help you choose.
            </p>
        </div>
        <div class="row g-4">
            <div class="col-lg-3 col-md-6">
                <div class="size-card">
                    <div class="size-yardage">10<sup>yd</sup></div>
                    <span class="size-label">Small Projects</span>
                    <p class="size-desc">Perfect for small cleanouts, single-room renovations, and deck removal.</p>
                    <ul class="size-specs">
                        <li><i class="bi bi-dot"></i> ~2–3 ton capacity</li>
                        <li><i class="bi bi-dot"></i> Garage &amp; attic cleanouts</li>
                        <li><i class="bi bi-dot"></i> Small landscaping jobs</li>
                    </ul>
                    <a href="/public/contact.php" class="btn-primary-cta w-100 justify-content-center" style="font-size:.9rem;padding:11px 20px;">Get a Quote</a>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="size-card">
                    <div class="size-yardage">15<sup>yd</sup></div>
                    <span class="size-label">Mid-Size Projects</span>
                    <p class="size-desc">Ideal for bathroom and kitchen remodels, medium debris volumes.</p>
                    <ul class="size-specs">
                        <li><i class="bi bi-dot"></i> ~3–4 ton capacity</li>
                        <li><i class="bi bi-dot"></i> Bathroom remodels</li>
                        <li><i class="bi bi-dot"></i> Flooring removal</li>
                    </ul>
                    <a href="/public/contact.php" class="btn-primary-cta w-100 justify-content-center" style="font-size:.9rem;padding:11px 20px;">Get a Quote</a>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="size-card popular">
                    <span class="popular-badge">Most Popular</span>
                    <div class="size-yardage">20<sup>yd</sup></div>
                    <span class="size-label">Large Projects</span>
                    <p class="size-desc">Our most popular size. Great for large renovations, home cleanouts, and moving.</p>
                    <ul class="size-specs">
                        <li><i class="bi bi-dot"></i> ~4–5 ton capacity</li>
                        <li><i class="bi bi-dot"></i> Full home renovations</li>
                        <li><i class="bi bi-dot"></i> Estate cleanouts</li>
                    </ul>
                    <a href="/public/contact.php" class="btn-primary-cta w-100 justify-content-center" style="font-size:.9rem;padding:11px 20px;">Get a Quote</a>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <div class="size-card">
                    <div class="size-yardage">30<sup>yd</sup></div>
                    <span class="size-label">Commercial / Construction</span>
                    <p class="size-desc">Built for contractors and large-scale commercial construction projects.</p>
                    <ul class="size-specs">
                        <li><i class="bi bi-dot"></i> ~5–7 ton capacity</li>
                        <li><i class="bi bi-dot"></i> New construction</li>
                        <li><i class="bi bi-dot"></i> Commercial demolition</li>
                    </ul>
                    <a href="/public/contact.php" class="btn-primary-cta w-100 justify-content-center" style="font-size:.9rem;padding:11px 20px;">Get a Quote</a>
                </div>
            </div>
        </div>
        <div class="text-center mt-4">
            <a href="/public/sizes.php" class="btn-navy-cta">
                <i class="bi bi-arrow-right-circle-fill"></i> View Full Size Guide
            </a>
        </div>
    </div>
</section>

<!-- ── Service Areas ──────────────────────────────────────────── -->
<section class="section-pad">
    <div class="container-fluid px-4">
        <div class="text-center mb-5">
            <span class="section-label">Coverage</span>
            <h2 class="section-title">We Serve Your Area</h2>
            <p class="section-subtitle mx-auto text-muted">
                Proudly serving communities throughout Upstate South Carolina and surrounding areas.
            </p>
        </div>
        <div class="d-flex flex-wrap justify-content-center gap-3 mb-4">
            <?php
            $areas = ['Greenville','Spartanburg','Anderson','Simpsonville','Greer','Mauldin',
                      'Easley','Taylors','Duncan','Boiling Springs','Powdersville','Fountain Inn',
                      'Pelham','Lyman','Inman'];
            foreach ($areas as $area):
            ?>
            <a href="/public/service-areas.php" class="area-pill">
                <i class="bi bi-geo-alt-fill"></i> <?php echo htmlspecialchars($area); ?>
            </a>
            <?php endforeach; ?>
        </div>
        <div class="text-center">
            <a href="/public/service-areas.php" class="btn-navy-cta">
                <i class="bi bi-map-fill"></i> View All Service Areas
            </a>
        </div>
    </div>
</section>

<!-- ── CTA Banner ─────────────────────────────────────────────── -->
<section class="cta-banner">
    <div class="container-fluid px-4 position-relative">
        <span class="section-label" style="color:var(--orange);">Ready to Get Started?</span>
        <h2>Get Your Dumpster Delivered Today</h2>
        <p>Call us or submit a quote online. We'll get back to you fast.</p>
        <div class="d-flex flex-wrap justify-content-center gap-3">
            <a href="/public/contact.php" class="btn-primary-cta">
                <i class="bi bi-chat-dots-fill"></i> Request a Free Quote
            </a>
            <a href="tel:<?= preg_replace('/[^0-9]/','',SITE_PHONE) ?>" class="btn-secondary-cta">
                <i class="bi bi-telephone-fill"></i> <?php echo SITE_PHONE; ?>
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
