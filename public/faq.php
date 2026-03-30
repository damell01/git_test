<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Frequently Asked Questions';
$meta_desc  = 'Got questions about dumpster rentals? Find answers to our most common questions about sizes, pricing, weight limits, permits, scheduling, and more.';
require_once __DIR__ . '/includes/header.php';

$faqs = [
    [
        'What dumpster sizes do you offer?',
        'We offer four roll-off dumpster sizes: <strong>10 yard</strong> (small cleanouts, single-room projects), <strong>15 yard</strong> (bathroom/kitchen remodels, medium debris), <strong>20 yard</strong> (most popular — large renovations, home cleanouts), and <strong>30 yard</strong> (commercial construction, large demolition). Visit our <a href="/public/sizes.php">Dumpster Sizes page</a> for detailed dimensions and recommendations.',
    ],
    [
        'How long can I keep the dumpster?',
        'Standard rental periods are 3, 5, 7, 10, or 14 days. Need more time? Just call us before your rental period ends and we can extend it—additional daily fees may apply. We\'re flexible and work around your timeline.',
    ],
    [
        'What can I put in the dumpster?',
        'You can toss most household and construction waste: furniture, appliances, drywall, wood, roofing materials, flooring, yard waste, concrete, and general junk. Check our <a href="/public/sizes.php">sizes page</a> for a complete accepted materials list.',
    ],
    [
        'What items are NOT allowed in the dumpster?',
        'Prohibited items include: <strong>hazardous chemicals and solvents, asbestos, medical/biohazardous waste, propane tanks, car batteries, liquid paint, and radioactive materials</strong>. If you\'re unsure about a specific item, give us a call before tossing it in—we\'re happy to advise.',
    ],
    [
        'How do I schedule a pickup?',
        'When you\'re ready for pickup, just give us a call at <strong>' . SITE_PHONE . '</strong> or log into your account on our website. We typically schedule pickups within 24–48 hours of your request. For urgent pickups, call us first thing in the morning.',
    ],
    [
        'Do I need a permit to have a dumpster?',
        'Permits are generally <strong>not required</strong> if the dumpster is placed on your private property (driveway, yard). If you need to place it on a public street, sidewalk, or right-of-way, a permit may be required from your local city or county. Requirements vary by municipality — we can advise based on your specific address.',
    ],
    [
        'What happens if I exceed the weight limit?',
        'Each dumpster size comes with an included weight allowance (typically 2–5 tons depending on size). If your load exceeds that limit, overage fees apply per ton at the rate disclosed in your quote. We weigh the dumpster at disposal and notify you of any overage charges before invoicing.',
    ],
    [
        'How do I pay?',
        'We invoice you <strong>after service is completed</strong>. Once the dumpster is picked up and weighed, we\'ll send you an invoice via email. We accept major credit cards, checks, and ACH bank transfers. Commercial accounts can apply for net-15 or net-30 payment terms.',
    ],
    [
        'Is same-day delivery available?',
        'Yes! We offer same-day delivery based on availability. To request same-day service, please call us at <strong>' . SITE_PHONE . '</strong> as early in the morning as possible. We\'ll do our best to accommodate you. Online quote requests are best for next-day or scheduled deliveries.',
    ],
    [
        'Do you service my area?',
        'We serve communities throughout Upstate South Carolina including Greenville, Spartanburg, Anderson, Simpsonville, Greer, Mauldin, Easley, Taylors, Duncan, Boiling Springs, and more. Visit our <a href="/public/service-areas.php">Service Areas page</a> for the full list, or call us if you\'re not sure.',
    ],
    [
        'How far in advance should I book?',
        'We recommend booking <strong>at least 24–48 hours in advance</strong> to ensure your preferred dumpster size and delivery date are available. For busy seasons (spring and summer) or larger commercial orders, booking 3–5 days out is ideal. Same-day service is available by phone only.',
    ],
    [
        'Can I move the dumpster after it\'s placed?',
        'Please do <strong>not</strong> attempt to move the dumpster yourself — they are heavy and can cause property damage or injury. If you need it repositioned after delivery, call us and we\'ll arrange to move it for you. A repositioning fee may apply.',
    ],
    [
        'What if I need to cancel or reschedule?',
        'We understand plans change. Please call us at least <strong>24 hours before your scheduled delivery</strong> to cancel or reschedule at no charge. Cancellations with less than 24 hours notice may incur a small cancellation fee. We\'ll always work with you to find a solution.',
    ],
    [
        'Are you licensed and insured?',
        'Yes, Trash Panda Roll-Offs is fully <strong>licensed and insured</strong> in South Carolina. We carry liability insurance and comply with all state and local regulations for waste hauling and disposal. Ask for proof of insurance anytime—we\'re happy to provide it.',
    ],
    [
        'How do I prepare for dumpster delivery?',
        'Make sure the delivery area is clear of vehicles, overhead obstructions (low-hanging branches or wires), and has firm ground to support the weight. Our driver will call ahead on delivery day. Placing boards down can protect your driveway surface — we\'re always careful, but it\'s good practice for heavier loads.',
    ],
];
?>

<!-- ── Page Hero ─────────────────────────────────────────────── -->
<section class="page-hero">
    <div class="container-fluid px-4 position-relative">
        <nav class="breadcrumb-nav" aria-label="breadcrumb">
            <a href="/public/index.php">Home</a>
            <span class="sep">/</span>
            <span>FAQ</span>
        </nav>
        <h1 class="page-hero-title">Frequently Asked Questions</h1>
        <p class="page-hero-sub">
            Everything you need to know about renting a dumpster from Trash Panda Roll-Offs. Can't find your answer? <a href="/public/contact.php" style="color:var(--orange);">Contact us</a>.
        </p>
    </div>
</section>

<!-- ── FAQ Accordion ─────────────────────────────────────────── -->
<section class="section-pad">
    <div class="container-fluid px-4">
        <div class="row justify-content-center">
            <div class="col-lg-9">

                <div class="mb-5">
                    <span class="section-label">All Questions</span>
                    <h2 class="section-title"><?php echo count($faqs); ?> Common Questions Answered</h2>
                    <p class="text-muted">Click any question to expand the answer.</p>
                </div>

                <div class="accordion" id="mainFaq">
                    <?php foreach ($faqs as $i => [$q, $a]): ?>
                    <div class="accordion-item mb-2">
                        <h2 class="accordion-header">
                            <button class="accordion-button <?php echo $i > 0 ? 'collapsed' : ''; ?>"
                                    type="button" data-bs-toggle="collapse"
                                    data-bs-target="#faq<?php echo $i; ?>"
                                    aria-expanded="<?php echo $i === 0 ? 'true' : 'false'; ?>">
                                <?php echo htmlspecialchars($q); ?>
                            </button>
                        </h2>
                        <div id="faq<?php echo $i; ?>" class="accordion-collapse collapse <?php echo $i === 0 ? 'show' : ''; ?>" data-bs-parent="#mainFaq">
                            <div class="accordion-body"><?php echo $a; ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Still have questions -->
                <div class="text-center mt-5 p-5" style="background:var(--bg-light);border-radius:var(--radius);border:1px solid rgba(0,0,0,.06);">
                    <div style="font-size:2.5rem;margin-bottom:.75rem;">🤔</div>
                    <h4 class="text-navy mb-2">Still Have a Question?</h4>
                    <p class="text-muted mb-4">We're real people and we're happy to help. Call us or send a message and we'll get back to you fast.</p>
                    <div class="d-flex flex-wrap justify-content-center gap-3">
                        <a href="/public/contact.php" class="btn-primary-cta">
                            <i class="bi bi-chat-dots-fill"></i> Send Us a Message
                        </a>
                        <a href="tel:5558675309" class="btn-navy-cta">
                            <i class="bi bi-telephone-fill"></i> <?php echo SITE_PHONE; ?>
                        </a>
                    </div>
                </div>

            </div>
        </div>
    </div>
</section>

<!-- ── CTA ────────────────────────────────────────────────────── -->
<section class="cta-banner">
    <div class="container-fluid px-4 position-relative">
        <span class="section-label">Ready to Rent?</span>
        <h2>Get a Free Quote in Minutes</h2>
        <p>Fill out our quick form or give us a call. We'll get you set up fast.</p>
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
