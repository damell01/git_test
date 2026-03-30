<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Commercial Dumpster Rentals';
$meta_desc  = 'Commercial dumpster rental services for contractors, property managers, and businesses in Upstate SC. Flexible scheduling, multiple sizes, and commercial invoicing.';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ── Page Hero ─────────────────────────────────────────────── -->
<section class="page-hero">
    <div class="container-fluid px-4 position-relative">
        <nav class="breadcrumb-nav" aria-label="breadcrumb">
            <a href="/public/index.php">Home</a>
            <span class="sep">/</span>
            <span>Commercial</span>
        </nav>
        <h1 class="page-hero-title">Commercial Dumpster Rentals</h1>
        <p class="page-hero-sub">
            Reliable roll-off solutions for contractors, property managers, businesses, and construction job sites. Flexible scheduling. Commercial invoicing available.
        </p>
        <div class="mt-4 d-flex flex-wrap gap-3">
            <a href="/public/contact.php" class="btn-primary-cta">
                <i class="bi bi-chat-dots-fill"></i> Request a Commercial Quote
            </a>
            <a href="tel:<?= preg_replace('/[^0-9]/','',SITE_PHONE) ?>" class="btn-secondary-cta">
                <i class="bi bi-telephone-fill"></i> <?php echo SITE_PHONE; ?>
            </a>
        </div>
    </div>
</section>

<!-- ── Who We Serve ───────────────────────────────────────────── -->
<section class="section-pad">
    <div class="container-fluid px-4">
        <div class="text-center mb-5">
            <span class="section-label">Who We Serve</span>
            <h2 class="section-title">Built for Business &amp; Commercial Needs</h2>
            <p class="section-subtitle mx-auto text-muted">
                From single-day job sites to ongoing commercial contracts, we have a solution that fits your workflow.
            </p>
        </div>
        <div class="row g-4">
            <?php
            $clients = [
                ['bi-building', 'General Contractors', 'Keep your job site clean and compliant. We deliver and swap dumpsters on your schedule so debris never slows you down.'],
                ['bi-house-gear-fill', 'Property Managers', 'Managing apartment complexes, commercial properties, or rental units? We offer repeat service and priority scheduling.'],
                ['bi-shop', 'Retail &amp; Business', 'Store renovations, office cleanouts, retail buildouts—we support businesses of all sizes with fast, reliable dumpster rentals.'],
                ['bi-hammer', 'Remodeling Crews', 'Keep projects moving efficiently. We work around your crew\'s schedule and can turn containers around quickly between jobs.'],
                ['bi-tree-fill', 'Landscaping Companies', 'Dispose of mulch, brush, sod, and yard debris quickly and cleanly. We have the right size for any landscaping project.'],
                ['bi-buildings-fill', 'HOAs &amp; Communities', 'Organizing a community cleanup event? We can supply multiple dumpsters and coordinate delivery across your community.'],
            ];
            foreach ($clients as [$icon, $title, $desc]):
            ?>
            <div class="col-lg-4 col-md-6">
                <div class="card-feature">
                    <div class="card-feature-icon"><i class="bi <?php echo $icon; ?>"></i></div>
                    <h4><?php echo $title; ?></h4>
                    <p><?php echo $desc; ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ── Commercial Features ────────────────────────────────────── -->
<section class="section-pad bg-light-gray">
    <div class="container-fluid px-4">
        <div class="row g-5 align-items-center">
            <div class="col-lg-6">
                <span class="section-label">Commercial Benefits</span>
                <h2 class="section-title">Why Contractors &amp; Businesses Partner With Us</h2>
                <ul class="check-list mt-4">
                    <?php
                    $features = [
                        'Flexible scheduling—deliveries around your crew\'s hours',
                        'Multiple dumpster sizes: 10, 15, 20, and 30 yard',
                        'Priority pickup and swap-out for active job sites',
                        'Commercial invoicing and net payment terms available',
                        'Repeat service discounts for ongoing accounts',
                        'Dedicated account contact—no hold music, no runaround',
                        'Clean, well-maintained containers for professional job sites',
                        'Serving Upstate SC with a local, reliable team you can trust',
                    ];
                    foreach ($features as $f):
                    ?>
                    <li><i class="bi bi-check-circle-fill"></i> <?php echo $f; ?></li>
                    <?php endforeach; ?>
                </ul>
                <a href="/public/contact.php" class="btn-primary-cta mt-4">
                    <i class="bi bi-chat-dots-fill"></i> Set Up a Commercial Account
                </a>
            </div>
            <div class="col-lg-6">
                <div class="row g-3">
                    <?php
                    $stats = [
                        ['100+', 'Commercial Jobs Completed'],
                        ['4', 'Dumpster Sizes Available'],
                        ['24hr', 'Average Response Time'],
                        ['5★', 'Local Customer Rating'],
                    ];
                    foreach ($stats as [$num, $label]):
                    ?>
                    <div class="col-6">
                        <div style="background:var(--navy);border-radius:var(--radius);padding:32px 20px;text-align:center;">
                            <span class="stat-number"><?php echo $num; ?></span>
                            <span class="stat-label"><?php echo $label; ?></span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── Sizes for Commercial ───────────────────────────────────── -->
<section class="section-pad">
    <div class="container-fluid px-4">
        <div class="text-center mb-5">
            <span class="section-label">Commercial Sizes</span>
            <h2 class="section-title">Right-Size Your Job Site</h2>
        </div>
        <div class="row g-4 justify-content-center">
            <?php
            $sizes = [
                ['15', 'Smaller Commercial Jobs', 'Interior demo, small office cleanouts, retail renovations. Fits in most commercial parking lots.', false],
                ['20', 'Most Job Sites', 'Our most popular commercial size. Handles roofing, flooring, framing, and medium renovation debris.', true],
                ['30', 'Large Construction', 'Full construction projects, large demolition, apartment turnovers, and major commercial buildouts.', false],
            ];
            foreach ($sizes as [$yd, $label, $desc, $popular]):
            ?>
            <div class="col-lg-4 col-md-6">
                <div class="size-card <?php echo $popular ? 'popular' : ''; ?>">
                    <?php if ($popular): ?><span class="popular-badge">Most Popular</span><?php endif; ?>
                    <div class="size-yardage"><?php echo $yd; ?><sup>yd</sup></div>
                    <span class="size-label"><?php echo $label; ?></span>
                    <p class="size-desc"><?php echo $desc; ?></p>
                    <a href="/public/contact.php" class="btn-primary-cta w-100 justify-content-center" style="font-size:.9rem;padding:11px 20px;">
                        Get Commercial Quote
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-4">
            <a href="/public/sizes.php" class="btn-navy-cta">
                <i class="bi bi-grid-fill"></i> Full Size Guide
            </a>
        </div>
    </div>
</section>

<!-- ── Commercial FAQ ─────────────────────────────────────────── -->
<section class="section-pad bg-light-gray">
    <div class="container-fluid px-4">
        <div class="text-center mb-5">
            <span class="section-label">FAQ</span>
            <h2 class="section-title">Commercial Customer Questions</h2>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="accordion" id="commercialFaq">
                    <?php
                    $faqs = [
                        ['Do you offer net payment terms for commercial accounts?', 'Yes! We work with established commercial accounts to offer net-15 and net-30 payment terms. Contact us to set up a commercial account and discuss invoicing arrangements.'],
                        ['Can you accommodate multiple dumpsters on the same job site?', 'Absolutely. We can coordinate delivery of multiple roll-off containers and stagger pickups and drop-offs to keep your site clean and efficient throughout the project.'],
                        ['How quickly can you turn around a container swap?', 'For active commercial accounts, we prioritize same-day or next-day swap-outs. Availability depends on the season, but we work hard to keep your project timeline moving.'],
                        ['Do you service job sites on weekends?', 'Yes, we offer Saturday delivery and pickup. Contact us to confirm weekend availability in your area and to schedule accordingly.'],
                        ['Can you provide documentation for waste disposal?', 'Yes, we can provide weight tickets and disposal documentation upon request. This is commonly required for commercial projects, LEED documentation, or insurance purposes.'],
                    ];
                    foreach ($faqs as $i => [$q, $a]):
                    ?>
                    <div class="accordion-item mb-2">
                        <h2 class="accordion-header">
                            <button class="accordion-button <?php echo $i > 0 ? 'collapsed' : ''; ?>"
                                    type="button" data-bs-toggle="collapse"
                                    data-bs-target="#cfaq<?php echo $i; ?>"
                                    aria-expanded="<?php echo $i === 0 ? 'true' : 'false'; ?>">
                                <?php echo htmlspecialchars($q); ?>
                            </button>
                        </h2>
                        <div id="cfaq<?php echo $i; ?>" class="accordion-collapse collapse <?php echo $i === 0 ? 'show' : ''; ?>" data-bs-parent="#commercialFaq">
                            <div class="accordion-body"><?php echo $a; ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── CTA ────────────────────────────────────────────────────── -->
<section class="cta-banner">
    <div class="container-fluid px-4 position-relative">
        <span class="section-label">Let's Work Together</span>
        <h2>Start a Commercial Account Today</h2>
        <p>Priority service, flexible scheduling, and commercial invoicing. Let us be your go-to dumpster partner.</p>
        <div class="d-flex flex-wrap justify-content-center gap-3">
            <a href="/public/contact.php" class="btn-primary-cta">
                <i class="bi bi-building"></i> Request a Commercial Quote
            </a>
            <a href="tel:<?= preg_replace('/[^0-9]/','',SITE_PHONE) ?>" class="btn-secondary-cta">
                <i class="bi bi-telephone-fill"></i> <?php echo SITE_PHONE; ?>
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
