<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Residential Dumpster Rentals';
$meta_desc  = 'Affordable residential dumpster rentals for home cleanouts, renovations, moving, landscaping, and junk removal. Fast delivery in Upstate SC.';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ── Page Hero ─────────────────────────────────────────────── -->
<section class="page-hero">
    <div class="container-fluid px-4 position-relative">
        <nav class="breadcrumb-nav" aria-label="breadcrumb">
            <a href="/public/index.php">Home</a>
            <span class="sep">/</span>
            <span>Residential</span>
        </nav>
        <h1 class="page-hero-title">Residential Dumpster Rentals</h1>
        <p class="page-hero-sub">
            Make your home project easier. We deliver right to your driveway and haul it all away when you're done.
        </p>
        <div class="mt-4 d-flex flex-wrap gap-3">
            <a href="/public/contact.php" class="btn-primary-cta">
                <i class="bi bi-chat-dots-fill"></i> Get a Free Quote
            </a>
            <a href="tel:5558675309" class="btn-secondary-cta">
                <i class="bi bi-telephone-fill"></i> <?php echo SITE_PHONE; ?>
            </a>
        </div>
    </div>
</section>

<!-- ── Use Cases ─────────────────────────────────────────────── -->
<section class="section-pad">
    <div class="container-fluid px-4">
        <div class="text-center mb-5">
            <span class="section-label">Perfect For</span>
            <h2 class="section-title">Residential Projects We Handle</h2>
            <p class="section-subtitle mx-auto text-muted">From small cleanouts to full renovations, we've got the right size dumpster for you.</p>
        </div>
        <div class="row g-4">
            <?php
            $uses = [
                ['bi-house-door-fill', 'Home Cleanouts', 'Clearing out an entire house, estate, or rental property? Our 15 or 20 yard dumpsters make it easy to remove years of accumulated items in one go.'],
                ['bi-tools', 'Home Renovations', 'Renovating your kitchen, bathroom, or adding an addition? We\'ll provide a dumpster to handle all your drywall, flooring, cabinetry, and demo debris.'],
                ['bi-boxes', 'Moving & Decluttering', 'Moving can be the perfect time to let go of unwanted stuff. Use a dumpster to dispose of furniture, boxes, and items you no longer need.'],
                ['bi-tree-fill', 'Yard & Landscaping', 'Clearing brush, pulling stumps, or redoing your yard? Our dumpsters handle grass clippings, branches, dirt, and mulch cleanly and efficiently.'],
                ['bi-trash3-fill', 'Junk Removal', 'Instead of making dozens of trips to the dump, toss everything in one dumpster. Appliances, mattresses, old furniture—load it up and we\'ll handle the rest.'],
                ['bi-hammer', 'DIY Projects', 'Tackling a big DIY weekend project? Having a dumpster on site means you can work faster and cleaner without constant haul-away trips.'],
            ];
            foreach ($uses as [$icon, $title, $desc]):
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

<!-- ── How It Works ───────────────────────────────────────────── -->
<section class="section-pad bg-light-gray">
    <div class="container-fluid px-4">
        <div class="row g-5 align-items-center">
            <div class="col-lg-5">
                <span class="section-label">Simple Process</span>
                <h2 class="section-title">How Residential Rentals Work</h2>
                <p class="text-muted">We've streamlined the process so you spend less time coordinating and more time on your project.</p>
                <a href="/public/contact.php" class="btn-primary-cta mt-3">
                    <i class="bi bi-chat-dots-fill"></i> Start Your Quote
                </a>
            </div>
            <div class="col-lg-7">
                <div class="row g-3">
                    <?php
                    $steps = [
                        ['1', 'Contact Us', 'Call or fill out our quick online form. Tell us your location, project type, and preferred delivery date.'],
                        ['2', 'Choose Your Size', 'We help you pick the right size—10, 15, or 20 yard depending on your project scope.'],
                        ['3', 'We Deliver', 'Our driver places the dumpster wherever you need it—driveway, street, or yard (with access).'],
                        ['4', 'Load It Up', 'Take your time. Fill it up at your own pace during your rental period.'],
                        ['5', 'We Pick It Up', 'When you\'re done, give us a call. We\'ll pick up the dumpster and handle disposal.'],
                        ['6', 'Pay After Service', 'We\'ll send you an invoice after pickup. Simple, transparent pricing—no hidden fees.'],
                    ];
                    foreach ($steps as [$num, $title, $desc]):
                    ?>
                    <div class="col-md-6">
                        <div class="d-flex gap-3 align-items-start p-3" style="background:#fff;border-radius:var(--radius-sm);border:1px solid rgba(0,0,0,.06);">
                            <div class="step-number" style="min-width:40px;height:40px;font-size:1rem;"><?php echo $num; ?></div>
                            <div>
                                <h6 style="color:var(--navy);margin-bottom:4px;font-size:.95rem;"><?php echo $title; ?></h6>
                                <p style="color:var(--text-mid);font-size:.85rem;margin:0;"><?php echo $desc; ?></p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── Why Homeowners Choose Us ───────────────────────────────── -->
<section class="section-pad">
    <div class="container-fluid px-4">
        <div class="row g-5 align-items-center">
            <div class="col-lg-6">
                <span class="section-label">Our Advantage</span>
                <h2 class="section-title">Why Homeowners Choose Trash Panda</h2>
                <ul class="check-list mt-4">
                    <?php
                    $reasons = [
                        'Same-day and next-day delivery available throughout Upstate SC',
                        'Flat-rate pricing—no surprise fees when we pick it up',
                        'Flexible rental periods from 3 to 14 days',
                        'We\'re local—you talk to a real person, not a call center',
                        'Dumpsters placed exactly where you need them',
                        'Reliable pickup scheduling—we show up when we say we will',
                        'We recycle and properly dispose of all waste',
                        'Family-owned business with 100+ satisfied local customers',
                    ];
                    foreach ($reasons as $r):
                    ?>
                    <li><i class="bi bi-check-circle-fill"></i> <?php echo $r; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="col-lg-6">
                <div class="bg-light-gray p-4 rounded-3 border">
                    <h4 class="text-navy mb-3">Recommended Sizes by Project</h4>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" style="font-size:.9rem;">
                            <thead style="background:var(--navy);color:#fff;">
                                <tr>
                                    <th class="py-2 px-3" style="border:none;">Project Type</th>
                                    <th class="py-2 px-3" style="border:none;">Recommended Size</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $recs = [
                                    ['Garage cleanout', '10–15 yd'],
                                    ['Bathroom remodel', '10–15 yd'],
                                    ['Kitchen remodel', '15–20 yd'],
                                    ['Full home cleanout', '20 yd'],
                                    ['Landscaping / yard', '10–15 yd'],
                                    ['Moving debris', '15–20 yd'],
                                    ['Multi-room renovation', '20 yd'],
                                    ['Estate cleanout', '20–30 yd'],
                                ];
                                foreach ($recs as [$proj, $size]):
                                ?>
                                <tr>
                                    <td class="py-2 px-3"><?php echo $proj; ?></td>
                                    <td class="py-2 px-3"><strong class="text-orange"><?php echo $size; ?></strong></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── Residential FAQ ────────────────────────────────────────── -->
<section class="section-pad bg-light-gray">
    <div class="container-fluid px-4">
        <div class="text-center mb-5">
            <span class="section-label">FAQ</span>
            <h2 class="section-title">Homeowner Questions Answered</h2>
        </div>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="accordion" id="residentialFaq">
                    <?php
                    $faqs = [
                        ['How far in advance do I need to book?', 'We recommend booking at least 24–48 hours in advance to guarantee your preferred date and size. That said, we do offer same-day delivery when availability allows—just call us as early as possible.'],
                        ['Can I put the dumpster in my driveway?', 'Absolutely! Most homeowners place them in the driveway. We\'ll need about 2 feet of clearance on each side and adequate overhead clearance for drop-off. We can also place them on the street with proper notice.'],
                        ['What if I fill it up before my rental period ends?', 'Just call us! We can swap out the full dumpster for an empty one (additional charge applies) or you can schedule an early pickup and then request another delivery.'],
                        ['Do I need a permit to have a dumpster in my driveway?', 'Permits are generally not required for dumpsters placed on private property like your driveway. If you need to place it on a public street or sidewalk, you may need a permit from your city or county. We can advise based on your location.'],
                        ['What happens if my debris is heavier than the weight limit?', 'If your load exceeds the included weight limit, overage fees will apply per ton. We\'ll let you know the overage rate upfront so there are no surprises. We weigh the load when we pick it up.'],
                    ];
                    foreach ($faqs as $i => [$q, $a]):
                    ?>
                    <div class="accordion-item mb-2">
                        <h2 class="accordion-header">
                            <button class="accordion-button <?php echo $i > 0 ? 'collapsed' : ''; ?>"
                                    type="button" data-bs-toggle="collapse"
                                    data-bs-target="#rfaq<?php echo $i; ?>"
                                    aria-expanded="<?php echo $i === 0 ? 'true' : 'false'; ?>">
                                <?php echo htmlspecialchars($q); ?>
                            </button>
                        </h2>
                        <div id="rfaq<?php echo $i; ?>" class="accordion-collapse collapse <?php echo $i === 0 ? 'show' : ''; ?>" data-bs-parent="#residentialFaq">
                            <div class="accordion-body"><?php echo $a; ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-center mt-4">
                    <p class="text-muted">More questions? <a href="/public/faq.php">Visit our full FAQ page</a> or <a href="/public/contact.php">contact us directly</a>.</p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── CTA ────────────────────────────────────────────────────── -->
<section class="cta-banner">
    <div class="container-fluid px-4 position-relative">
        <span class="section-label">Ready to Get Started?</span>
        <h2>Get Your Residential Dumpster Today</h2>
        <p>Quick delivery. Flat-rate pricing. No hassle. That's the Trash Panda way.</p>
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
