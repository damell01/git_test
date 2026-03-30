<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Dumpster Sizes & Pricing';
$meta_desc  = 'Compare our 10, 15, 20, and 30 yard dumpster sizes. Find the right roll-off for your project. Detailed dimensions, weight limits, and best-use guide.';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ── Page Hero ─────────────────────────────────────────────── -->
<section class="page-hero">
    <div class="container-fluid px-4 position-relative">
        <nav class="breadcrumb-nav" aria-label="breadcrumb">
            <a href="/public/index.php">Home</a>
            <span class="sep">/</span>
            <span>Dumpster Sizes</span>
        </nav>
        <h1 class="page-hero-title">Dumpster Sizes &amp; Guide</h1>
        <p class="page-hero-sub">
            Not sure which size you need? Compare options below or <a href="/public/contact.php" style="color:var(--orange);">ask our team</a>—we'll match you to the perfect dumpster.
        </p>
    </div>
</section>

<!-- ── Size Comparison ────────────────────────────────────────── -->
<section class="section-pad">
    <div class="container-fluid px-4">
        <div class="text-center mb-5">
            <span class="section-label">Detailed Size Guide</span>
            <h2 class="section-title">Choose the Right Roll-Off Dumpster</h2>
        </div>

        <!-- 10 Yard -->
        <div class="row g-4 align-items-center mb-5 pb-4 border-bottom">
            <div class="col-lg-3 text-center">
                <div class="size-card" style="max-width:240px;margin:0 auto;">
                    <div class="size-yardage">10<sup>yd</sup></div>
                    <span class="size-label">Small Projects</span>
                    <div style="font-size:3rem;">🗑</div>
                    <p class="size-desc mb-0">Great starter size</p>
                </div>
            </div>
            <div class="col-lg-9">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="card-feature" style="text-align:left;padding:22px 20px;">
                            <h6 class="text-orange" style="font-family:'Barlow Condensed',sans-serif;letter-spacing:.06em;text-transform:uppercase;font-size:.8rem;font-weight:700;">Dimensions (approx.)</h6>
                            <ul class="check-list mt-2">
                                <li><i class="bi bi-arrows-angle-expand"></i> 10 ft long × 7 ft wide</li>
                                <li><i class="bi bi-arrows-vertical"></i> 3.5 ft tall</li>
                                <li><i class="bi bi-truck"></i> Fits in most driveways</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card-feature" style="text-align:left;padding:22px 20px;">
                            <h6 class="text-orange" style="font-family:'Barlow Condensed',sans-serif;letter-spacing:.06em;text-transform:uppercase;font-size:.8rem;font-weight:700;">Weight &amp; Capacity</h6>
                            <ul class="check-list mt-2">
                                <li><i class="bi bi-speedometer2"></i> Up to 2–3 tons included</li>
                                <li><i class="bi bi-boxes"></i> ~3 pickup-truck loads</li>
                                <li><i class="bi bi-info-circle"></i> Overage fees may apply</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card-feature" style="text-align:left;padding:22px 20px;">
                            <h6 class="text-orange" style="font-family:'Barlow Condensed',sans-serif;letter-spacing:.06em;text-transform:uppercase;font-size:.8rem;font-weight:700;">Best For</h6>
                            <ul class="check-list mt-2">
                                <li><i class="bi bi-check-circle-fill"></i> Garage / attic cleanouts</li>
                                <li><i class="bi bi-check-circle-fill"></i> Small deck or fence removal</li>
                                <li><i class="bi bi-check-circle-fill"></i> Single-room remodels</li>
                                <li><i class="bi bi-check-circle-fill"></i> Small landscaping debris</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="/public/contact.php" class="btn-primary-cta">
                        <i class="bi bi-chat-dots-fill"></i> Get a Quote for 10 Yd
                    </a>
                </div>
            </div>
        </div>

        <!-- 15 Yard -->
        <div class="row g-4 align-items-center mb-5 pb-4 border-bottom">
            <div class="col-lg-3 text-center">
                <div class="size-card" style="max-width:240px;margin:0 auto;">
                    <div class="size-yardage">15<sup>yd</sup></div>
                    <span class="size-label">Mid-Size Projects</span>
                    <div style="font-size:3rem;">🗑</div>
                    <p class="size-desc mb-0">Versatile mid-size option</p>
                </div>
            </div>
            <div class="col-lg-9">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="card-feature" style="text-align:left;padding:22px 20px;">
                            <h6 class="text-orange" style="font-family:'Barlow Condensed',sans-serif;letter-spacing:.06em;text-transform:uppercase;font-size:.8rem;font-weight:700;">Dimensions (approx.)</h6>
                            <ul class="check-list mt-2">
                                <li><i class="bi bi-arrows-angle-expand"></i> 14 ft long × 7.5 ft wide</li>
                                <li><i class="bi bi-arrows-vertical"></i> 4 ft tall</li>
                                <li><i class="bi bi-truck"></i> Fits standard driveways</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card-feature" style="text-align:left;padding:22px 20px;">
                            <h6 class="text-orange" style="font-family:'Barlow Condensed',sans-serif;letter-spacing:.06em;text-transform:uppercase;font-size:.8rem;font-weight:700;">Weight &amp; Capacity</h6>
                            <ul class="check-list mt-2">
                                <li><i class="bi bi-speedometer2"></i> Up to 3–4 tons included</li>
                                <li><i class="bi bi-boxes"></i> ~4–5 pickup-truck loads</li>
                                <li><i class="bi bi-info-circle"></i> Overage fees may apply</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card-feature" style="text-align:left;padding:22px 20px;">
                            <h6 class="text-orange" style="font-family:'Barlow Condensed',sans-serif;letter-spacing:.06em;text-transform:uppercase;font-size:.8rem;font-weight:700;">Best For</h6>
                            <ul class="check-list mt-2">
                                <li><i class="bi bi-check-circle-fill"></i> Bathroom &amp; kitchen remodels</li>
                                <li><i class="bi bi-check-circle-fill"></i> Flooring &amp; drywall removal</li>
                                <li><i class="bi bi-check-circle-fill"></i> Medium yard cleanups</li>
                                <li><i class="bi bi-check-circle-fill"></i> Basement junk removal</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="/public/contact.php" class="btn-primary-cta">
                        <i class="bi bi-chat-dots-fill"></i> Get a Quote for 15 Yd
                    </a>
                </div>
            </div>
        </div>

        <!-- 20 Yard -->
        <div class="row g-4 align-items-center mb-5 pb-4 border-bottom">
            <div class="col-lg-3 text-center">
                <div class="size-card popular" style="max-width:240px;margin:0 auto;">
                    <span class="popular-badge">Most Popular</span>
                    <div class="size-yardage">20<sup>yd</sup></div>
                    <span class="size-label">Large Projects</span>
                    <div style="font-size:3rem;">🗑</div>
                    <p class="size-desc mb-0">Our top seller</p>
                </div>
            </div>
            <div class="col-lg-9">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="card-feature" style="text-align:left;padding:22px 20px;">
                            <h6 class="text-orange" style="font-family:'Barlow Condensed',sans-serif;letter-spacing:.06em;text-transform:uppercase;font-size:.8rem;font-weight:700;">Dimensions (approx.)</h6>
                            <ul class="check-list mt-2">
                                <li><i class="bi bi-arrows-angle-expand"></i> 18 ft long × 8 ft wide</li>
                                <li><i class="bi bi-arrows-vertical"></i> 4.5 ft tall</li>
                                <li><i class="bi bi-truck"></i> Needs wider driveway or street</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card-feature" style="text-align:left;padding:22px 20px;">
                            <h6 class="text-orange" style="font-family:'Barlow Condensed',sans-serif;letter-spacing:.06em;text-transform:uppercase;font-size:.8rem;font-weight:700;">Weight &amp; Capacity</h6>
                            <ul class="check-list mt-2">
                                <li><i class="bi bi-speedometer2"></i> Up to 4–5 tons included</li>
                                <li><i class="bi bi-boxes"></i> ~6–8 pickup-truck loads</li>
                                <li><i class="bi bi-info-circle"></i> Overage fees may apply</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card-feature" style="text-align:left;padding:22px 20px;">
                            <h6 class="text-orange" style="font-family:'Barlow Condensed',sans-serif;letter-spacing:.06em;text-transform:uppercase;font-size:.8rem;font-weight:700;">Best For</h6>
                            <ul class="check-list mt-2">
                                <li><i class="bi bi-check-circle-fill"></i> Full home renovations</li>
                                <li><i class="bi bi-check-circle-fill"></i> Estate &amp; full-home cleanouts</li>
                                <li><i class="bi bi-check-circle-fill"></i> Moving &amp; decluttering</li>
                                <li><i class="bi bi-check-circle-fill"></i> Multi-room remodels</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="/public/contact.php" class="btn-primary-cta">
                        <i class="bi bi-chat-dots-fill"></i> Get a Quote for 20 Yd
                    </a>
                </div>
            </div>
        </div>

        <!-- 30 Yard -->
        <div class="row g-4 align-items-center mb-4">
            <div class="col-lg-3 text-center">
                <div class="size-card" style="max-width:240px;margin:0 auto;">
                    <div class="size-yardage">30<sup>yd</sup></div>
                    <span class="size-label">Commercial / Construction</span>
                    <div style="font-size:3rem;">🗑</div>
                    <p class="size-desc mb-0">Maximum capacity</p>
                </div>
            </div>
            <div class="col-lg-9">
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="card-feature" style="text-align:left;padding:22px 20px;">
                            <h6 class="text-orange" style="font-family:'Barlow Condensed',sans-serif;letter-spacing:.06em;text-transform:uppercase;font-size:.8rem;font-weight:700;">Dimensions (approx.)</h6>
                            <ul class="check-list mt-2">
                                <li><i class="bi bi-arrows-angle-expand"></i> 22 ft long × 8 ft wide</li>
                                <li><i class="bi bi-arrows-vertical"></i> 6 ft tall</li>
                                <li><i class="bi bi-truck"></i> Requires ample clearance</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card-feature" style="text-align:left;padding:22px 20px;">
                            <h6 class="text-orange" style="font-family:'Barlow Condensed',sans-serif;letter-spacing:.06em;text-transform:uppercase;font-size:.8rem;font-weight:700;">Weight &amp; Capacity</h6>
                            <ul class="check-list mt-2">
                                <li><i class="bi bi-speedometer2"></i> Up to 5–7 tons included</li>
                                <li><i class="bi bi-boxes"></i> ~9–12 pickup-truck loads</li>
                                <li><i class="bi bi-info-circle"></i> Commercial invoicing available</li>
                            </ul>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card-feature" style="text-align:left;padding:22px 20px;">
                            <h6 class="text-orange" style="font-family:'Barlow Condensed',sans-serif;letter-spacing:.06em;text-transform:uppercase;font-size:.8rem;font-weight:700;">Best For</h6>
                            <ul class="check-list mt-2">
                                <li><i class="bi bi-check-circle-fill"></i> New construction &amp; framing</li>
                                <li><i class="bi bi-check-circle-fill"></i> Large demolition projects</li>
                                <li><i class="bi bi-check-circle-fill"></i> Commercial job sites</li>
                                <li><i class="bi bi-check-circle-fill"></i> Property management debris</li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="/public/contact.php" class="btn-primary-cta">
                        <i class="bi bi-chat-dots-fill"></i> Get a Quote for 30 Yd
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── What Can't Go In ───────────────────────────────────────── -->
<section class="section-pad bg-light-gray">
    <div class="container-fluid px-4">
        <div class="row g-5 align-items-start">
            <div class="col-lg-6">
                <span class="section-label">Accepted Materials</span>
                <h2 class="section-title">What Can Go In</h2>
                <ul class="check-list mt-3">
                    <?php $ok = ['Household junk &amp; furniture','Construction debris (wood, drywall, flooring)','Yard waste &amp; landscaping debris','Roofing shingles','Concrete &amp; dirt (separate quote required)','Appliances (some restrictions apply)','Cardboard &amp; packaging','Renovation materials']; foreach ($ok as $i): ?>
                    <li><i class="bi bi-check-circle-fill text-success"></i> <?php echo $i; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="col-lg-6">
                <span class="section-label">Prohibited Items</span>
                <h2 class="section-title">What <span style="color:#dc3545;">Cannot</span> Go In</h2>
                <ul class="check-list mt-3">
                    <?php $no = ['Hazardous chemicals &amp; solvents','Asbestos or lead-containing materials','Medical / biohazardous waste','Propane tanks &amp; compressed gas cylinders','Tires (in large quantities)','Car batteries','Paints (liquid)','Radioactive materials']; foreach ($no as $i): ?>
                    <li><i class="bi bi-x-circle-fill" style="color:#dc3545;"></i> <?php echo $i; ?></li>
                    <?php endforeach; ?>
                </ul>
                <p class="text-muted mt-3" style="font-size:.88rem;">
                    Not sure about an item? <a href="/public/contact.php">Ask us before loading</a>—we're happy to help.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- ── CTA ────────────────────────────────────────────────────── -->
<section class="cta-banner">
    <div class="container-fluid px-4 position-relative">
        <span class="section-label">Still Not Sure?</span>
        <h2>We'll Help You Pick the Right Size</h2>
        <p>Call us or submit a quote—our team will recommend the best option for your project and budget.</p>
        <div class="d-flex flex-wrap justify-content-center gap-3">
            <a href="/public/contact.php" class="btn-primary-cta">
                <i class="bi bi-chat-dots-fill"></i> Request a Free Quote
            </a>
            <a href="tel:5558675309" class="btn-secondary-cta">
                <i class="bi bi-telephone-fill"></i> <?php echo SITE_PHONE; ?>
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
