<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'Contact & Get a Quote';
$meta_desc  = 'Request a free dumpster rental quote from Trash Panda Roll-Offs. Fill out our quick form and we\'ll get back to you fast. Serving Upstate SC.';

// ── Form submission ─────────────────────────────────────────────────
$success = false;
$errors  = [];
$old     = [];   // repopulate fields on error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Sanitize / retrieve
    $fields = ['name','phone','email','address','city','size_needed','project_type',
                'delivery_date','rental_duration','message','source'];
    foreach ($fields as $f) {
        $old[$f] = trim($_POST[$f] ?? '');
    }

    // Validate required fields
    if (empty($old['name']))         $errors[] = 'Full name is required.';
    if (empty($old['phone']))        $errors[] = 'Phone number is required.';
    if (!empty($old['email']) && !filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (empty($errors)) {
        try {
            $pdo = db();
            $stmt = $pdo->prepare("
                INSERT INTO leads
                    (name, email, phone, address, city, size_needed, project_type,
                     rental_duration, source, message, status, created_at)
                VALUES
                    (:name, :email, :phone, :address, :city, :size_needed, :project_type,
                     :rental_duration, :source, :message, 'new', :created_at)
            ");
            $stmt->execute([
                ':name'             => $old['name'],
                ':email'            => $old['email'],
                ':phone'            => $old['phone'],
                ':address'          => $old['address'],
                ':city'             => $old['city'],
                ':size_needed'      => $old['size_needed'],
                ':project_type'     => $old['project_type'],
                ':rental_duration'  => $old['rental_duration'],
                ':source'           => $old['source'],
                ':message'          => $old['message'],
                ':created_at'       => date('Y-m-d H:i:s'),
            ]);
            $success = true;
            $old = [];   // clear form
        } catch (\PDOException $e) {
            // Fail gracefully — log internally, show generic message
            $errors[] = 'There was a problem submitting your request. Please call us directly at ' . SITE_PHONE . '.';
        }
    }
}

// Helper to safely output old values
function old(string $key, array $old): string {
    return htmlspecialchars($old[$key] ?? '', ENT_QUOTES, 'UTF-8');
}
function sel(string $key, string $value, array $old): string {
    return ($old[$key] ?? '') === $value ? ' selected' : '';
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- ── Page Hero ─────────────────────────────────────────────── -->
<section class="page-hero">
    <div class="container-fluid px-4 position-relative">
        <nav class="breadcrumb-nav" aria-label="breadcrumb">
            <a href="/public/index.php">Home</a>
            <span class="sep">/</span>
            <span>Contact &amp; Quote</span>
        </nav>
        <h1 class="page-hero-title">Get a Free Quote</h1>
        <p class="page-hero-sub">
            Fill out the form and we'll respond fast—usually within the hour during business hours. Rather talk? Call us directly.
        </p>
    </div>
</section>

<!-- ── Contact + Form ────────────────────────────────────────── -->
<section class="section-pad">
    <div class="container-fluid px-4">
        <div class="row g-5 align-items-start">

            <!-- ── Left: Contact Info ────────────────────────────── -->
            <div class="col-lg-4">
                <div class="contact-info-card">
                    <h3><i class="bi bi-headset me-2"></i>Contact Us</h3>

                    <div class="contact-item">
                        <div class="contact-item-icon"><i class="bi bi-telephone-fill"></i></div>
                        <div class="contact-item-body">
                            <h6>Phone</h6>
                            <a href="tel:<?php echo preg_replace('/[^0-9]/', '', SITE_PHONE); ?>">
                                <?php echo SITE_PHONE; ?>
                            </a>
                        </div>
                    </div>

                    <div class="contact-item">
                        <div class="contact-item-icon"><i class="bi bi-envelope-fill"></i></div>
                        <div class="contact-item-body">
                            <h6>Email</h6>
                            <a href="mailto:<?php echo SITE_EMAIL; ?>"><?php echo SITE_EMAIL; ?></a>
                        </div>
                    </div>

                    <div class="contact-item">
                        <div class="contact-item-icon"><i class="bi bi-clock-fill"></i></div>
                        <div class="contact-item-body">
                            <h6>Business Hours</h6>
                            <p>Mon–Fri: 7am – 6pm</p>
                            <p style="font-size:.85rem;color:rgba(255,255,255,.6);">Saturday: 8am – 4pm<br>Sunday: Closed</p>
                        </div>
                    </div>

                    <div class="contact-item">
                        <div class="contact-item-icon"><i class="bi bi-geo-alt-fill"></i></div>
                        <div class="contact-item-body">
                            <h6>Service Area</h6>
                            <p>Upstate South Carolina</p>
                            <a href="/public/service-areas.php" style="color:var(--orange);font-size:.85rem;">View all areas →</a>
                        </div>
                    </div>

                    <hr style="border-color:rgba(255,255,255,.1);margin:1.5rem 0;">

                    <div style="background:rgba(249,115,22,.12);border:1px solid rgba(249,115,22,.3);border-radius:var(--radius-sm);padding:16px;">
                        <p style="font-size:.85rem;color:rgba(255,255,255,.8);margin:0;line-height:1.6;">
                            <i class="bi bi-lightning-charge-fill text-orange me-1"></i>
                            <strong style="color:#fff;">Need same-day service?</strong><br>
                            Call us directly — online quotes are best for next-day and scheduled deliveries.
                        </p>
                    </div>
                </div>
            </div>

            <!-- ── Right: Quote Form ─────────────────────────────── -->
            <div class="col-lg-8">
                <div class="form-card">
                    <div class="mb-4">
                        <h3 style="color:var(--navy);font-size:1.6rem;margin-bottom:.25rem;">Submit Your Quote Request</h3>
                        <p style="color:var(--text-mid);font-size:.92rem;margin:0;">We'll review your request and contact you with pricing and availability.</p>
                    </div>

                    <?php if ($success): ?>
                    <div class="alert-success-custom">
                        <strong><i class="bi bi-check-circle-fill me-2"></i>Quote Request Received!</strong><br>
                        Thank you! We've received your request and will be in touch shortly. For faster service, call us at <strong><?php echo SITE_PHONE; ?></strong>.
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($errors)): ?>
                    <div class="alert-error-custom">
                        <strong><i class="bi bi-exclamation-triangle-fill me-2"></i>Please correct the following:</strong>
                        <ul class="mb-0 mt-2 ps-3">
                            <?php foreach ($errors as $err): ?>
                            <li><?php echo htmlspecialchars($err); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <?php endif; ?>

                    <?php if (!$success): ?>
                    <form method="POST" action="/public/contact.php" novalidate>

                        <!-- Row 1: Name + Phone -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="name" class="form-label">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?php echo isset($errors) && !empty($errors) && empty($old['name']) ? 'is-invalid' : ''; ?>"
                                       id="name" name="name"
                                       value="<?php echo old('name', $old); ?>"
                                       placeholder="John Smith" required autocomplete="name">
                            </div>
                            <div class="col-md-6">
                                <label for="phone" class="form-label">Phone Number <span class="text-danger">*</span></label>
                                <input type="tel" class="form-control"
                                       id="phone" name="phone"
                                       value="<?php echo old('phone', $old); ?>"
                                       placeholder="(555) 000-0000" required autocomplete="tel">
                            </div>
                        </div>

                        <!-- Row 2: Email -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email Address</label>
                                <input type="email" class="form-control"
                                       id="email" name="email"
                                       value="<?php echo old('email', $old); ?>"
                                       placeholder="you@example.com" autocomplete="email">
                            </div>
                            <div class="col-md-6">
                                <label for="city" class="form-label">City</label>
                                <input type="text" class="form-control"
                                       id="city" name="city"
                                       value="<?php echo old('city', $old); ?>"
                                       placeholder="Greenville" autocomplete="address-level2">
                            </div>
                        </div>

                        <!-- Row 3: Service Address -->
                        <div class="mb-3">
                            <label for="address" class="form-label">Service Address</label>
                            <input type="text" class="form-control"
                                   id="address" name="address"
                                   value="<?php echo old('address', $old); ?>"
                                   placeholder="123 Main St" autocomplete="street-address">
                        </div>

                        <!-- Row 4: Size + Project Type -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="size_needed" class="form-label">Dumpster Size</label>
                                <select class="form-select" id="size_needed" name="size_needed">
                                    <option value=""<?php echo sel('size_needed','', $old); ?>>-- Select a size --</option>
                                    <option value="10 yd"<?php echo sel('size_needed','10 yd', $old); ?>>10 Yard</option>
                                    <option value="15 yd"<?php echo sel('size_needed','15 yd', $old); ?>>15 Yard</option>
                                    <option value="20 yd"<?php echo sel('size_needed','20 yd', $old); ?>>20 Yard (Most Popular)</option>
                                    <option value="30 yd"<?php echo sel('size_needed','30 yd', $old); ?>>30 Yard</option>
                                    <option value="Not Sure"<?php echo sel('size_needed','Not Sure', $old); ?>>Not Sure — Help Me Choose</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="project_type" class="form-label">Project Type</label>
                                <select class="form-select" id="project_type" name="project_type">
                                    <option value=""<?php echo sel('project_type','', $old); ?>>-- Select project --</option>
                                    <option value="Home Cleanout"<?php echo sel('project_type','Home Cleanout', $old); ?>>Home Cleanout</option>
                                    <option value="Renovation"<?php echo sel('project_type','Renovation', $old); ?>>Renovation / Remodel</option>
                                    <option value="Construction"<?php echo sel('project_type','Construction', $old); ?>>Construction</option>
                                    <option value="Commercial"<?php echo sel('project_type','Commercial', $old); ?>>Commercial Project</option>
                                    <option value="Moving"<?php echo sel('project_type','Moving', $old); ?>>Moving / Declutter</option>
                                    <option value="Landscaping"<?php echo sel('project_type','Landscaping', $old); ?>>Landscaping / Yard</option>
                                    <option value="Other"<?php echo sel('project_type','Other', $old); ?>>Other</option>
                                </select>
                            </div>
                        </div>

                        <!-- Row 5: Delivery Date + Duration -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="delivery_date" class="form-label">Preferred Delivery Date</label>
                                <input type="date" class="form-control"
                                       id="delivery_date" name="delivery_date"
                                       value="<?php echo old('delivery_date', $old); ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="rental_duration" class="form-label">Rental Duration</label>
                                <select class="form-select" id="rental_duration" name="rental_duration">
                                    <option value=""<?php echo sel('rental_duration','', $old); ?>>-- Select duration --</option>
                                    <option value="3 days"<?php echo sel('rental_duration','3 days', $old); ?>>3 Days</option>
                                    <option value="5 days"<?php echo sel('rental_duration','5 days', $old); ?>>5 Days</option>
                                    <option value="7 days"<?php echo sel('rental_duration','7 days', $old); ?>>7 Days</option>
                                    <option value="10 days"<?php echo sel('rental_duration','10 days', $old); ?>>10 Days</option>
                                    <option value="14 days"<?php echo sel('rental_duration','14 days', $old); ?>>14 Days</option>
                                    <option value="Flexible"<?php echo sel('rental_duration','Flexible', $old); ?>>Flexible — I\'ll Call When Done</option>
                                </select>
                            </div>
                        </div>

                        <!-- Message -->
                        <div class="mb-3">
                            <label for="message" class="form-label">Message / Project Details</label>
                            <textarea class="form-control" id="message" name="message" rows="4"
                                      placeholder="Tell us about your project, any special access needs, or questions you have..."><?php echo old('message', $old); ?></textarea>
                        </div>

                        <!-- How did you hear -->
                        <div class="mb-4">
                            <label for="source" class="form-label">How Did You Hear About Us?</label>
                            <select class="form-select" id="source" name="source">
                                <option value=""<?php echo sel('source','', $old); ?>>-- Select one --</option>
                                <option value="Google"<?php echo sel('source','Google', $old); ?>>Google</option>
                                <option value="Facebook"<?php echo sel('source','Facebook', $old); ?>>Facebook</option>
                                <option value="Word of Mouth"<?php echo sel('source','Word of Mouth', $old); ?>>Word of Mouth</option>
                                <option value="Nextdoor"<?php echo sel('source','Nextdoor', $old); ?>>Nextdoor</option>
                                <option value="Other"<?php echo sel('source','Other', $old); ?>>Other</option>
                            </select>
                        </div>

                        <button type="submit" class="btn-submit">
                            <i class="bi bi-send-fill me-2"></i> Submit My Quote Request
                        </button>

                        <p class="text-muted mt-3 mb-0" style="font-size:.8rem;text-align:center;">
                            <i class="bi bi-shield-lock-fill me-1"></i>
                            Your information is kept private and never sold or shared.
                        </p>

                    </form>
                    <?php endif; ?>

                </div>
            </div>

        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
