<?php
require_once __DIR__ . '/includes/config.php';
$page_title = 'About Us';
$meta_desc  = 'Learn about Trash Panda Roll-Offs — a local family business serving Upstate SC with fast, affordable dumpster rentals and a commitment to the community.';
require_once __DIR__ . '/includes/header.php';
?>

<!-- ── Page Hero ─────────────────────────────────────────────── -->
<section class="page-hero">
    <div class="container-fluid px-4 position-relative">
        <nav class="breadcrumb-nav" aria-label="breadcrumb">
            <a href="/public/index.php">Home</a>
            <span class="sep">/</span>
            <span>About Us</span>
        </nav>
        <h1 class="page-hero-title">About Trash Panda Roll-Offs</h1>
        <p class="page-hero-sub">
            A local family business built on reliability, honesty, and a genuine love for our community.
        </p>
    </div>
</section>

<!-- ── Company Story ─────────────────────────────────────────── -->
<section class="section-pad">
    <div class="container-fluid px-4">
        <div class="row g-5 align-items-center">
            <div class="col-lg-6">
                <span class="section-label">Our Story</span>
                <h2 class="section-title">Started Local. Staying Local.</h2>
                <p class="text-muted mb-3">
                    Trash Panda Roll-Offs was founded right here in Upstate South Carolina by a family that grew up hauling, building, and working with their hands. After years of hearing neighbors and contractors complain about overpriced, unreliable dumpster companies, we decided to do it ourselves—and do it better.
                </p>
                <p class="text-muted mb-3">
                    We started with a single roll-off truck and a simple promise: show up on time, be upfront about pricing, and treat every customer like a neighbor—because in Upstate SC, they usually are.
                </p>
                <p class="text-muted mb-0">
                    Today, Trash Panda Roll-Offs serves homeowners, contractors, property managers, and businesses across more than 15 communities in the Upstate. We've grown because of word-of-mouth from satisfied customers, and we work every day to earn that trust again.
                </p>
            </div>
            <div class="col-lg-6">
                <div style="background:var(--bg-light);border-radius:var(--radius-lg);padding:48px 40px;text-align:center;border:1px solid rgba(0,0,0,.06);">
                    <div style="font-size:5rem;margin-bottom:1rem;">🗑</div>
                    <h3 style="color:var(--navy);font-size:1.8rem;margin-bottom:.5rem;">Our Mission</h3>
                    <hr class="section-divider mx-auto" style="width:50px;">
                    <p style="color:var(--text-mid);font-size:1.05rem;line-height:1.7;font-style:italic;">
                        "To make waste removal simple, affordable, and stress-free for every customer in our community—one dumpster at a time."
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ── Stats ─────────────────────────────────────────────────── -->
<section class="section-pad-sm" style="background:var(--navy);">
    <div class="container-fluid px-4">
        <div class="row g-0 text-center">
            <?php
            $stats = [
                ['100+', 'Happy Customers'],
                ['4', 'Dumpster Sizes'],
                ['15+', 'Communities Served'],
                ['5★', 'Average Review'],
            ];
            foreach ($stats as [$num, $label]):
            ?>
            <div class="col-6 col-md-3">
                <div class="stat-box">
                    <span class="stat-number"><?php echo $num; ?></span>
                    <span class="stat-label"><?php echo $label; ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ── Team ──────────────────────────────────────────────────── -->
<section class="section-pad bg-light-gray">
    <div class="container-fluid px-4">
        <div class="text-center mb-5">
            <span class="section-label">The Team</span>
            <h2 class="section-title">Meet the People Behind the Dumpsters</h2>
            <p class="section-subtitle mx-auto text-muted">
                We're your neighbors. We live, work, and raise our families right here in Upstate SC.
            </p>
        </div>
        <div class="row g-4 justify-content-center">
            <?php
            $team = [
                ['JW', 'Jake Wilson', 'Owner & Operator', 'Jake founded Trash Panda Roll-Offs after 10 years in construction management. He oversees daily operations and personally handles many deliveries.'],
                ['SW', 'Sarah Wilson', 'Office Manager', 'Sarah manages scheduling, customer communications, and billing. She\'s the reason things run smoothly behind the scenes.'],
                ['MT', 'Mike Thompson', 'Lead Driver', 'Mike has been with us since day one. He knows every road in Upstate SC and prides himself on careful, damage-free placements.'],
            ];
            foreach ($team as [$initials, $name, $role, $bio]):
            ?>
            <div class="col-lg-4 col-md-6">
                <div class="team-card">
                    <div class="team-avatar"><?php echo $initials; ?></div>
                    <h5><?php echo htmlspecialchars($name); ?></h5>
                    <p class="team-role"><?php echo htmlspecialchars($role); ?></p>
                    <p><?php echo htmlspecialchars($bio); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ── Values ────────────────────────────────────────────────── -->
<section class="section-pad">
    <div class="container-fluid px-4">
        <div class="text-center mb-5">
            <span class="section-label">What We Stand For</span>
            <h2 class="section-title">Our Core Values</h2>
        </div>
        <div class="row g-4">
            <?php
            $values = [
                ['bi-shield-check-fill', 'Reliability', 'When we say we\'ll be there, we\'re there. Dependability isn\'t a promise we make lightly—it\'s the foundation of everything we do.'],
                ['bi-currency-dollar', 'Affordability', 'Waste removal shouldn\'t break the bank. We work hard to keep our pricing competitive and our fees transparent with zero surprises.'],
                ['bi-recycle', 'Environmental Responsibility', 'We take our role as stewards of this community seriously. We recycle, sort debris responsibly, and follow all local disposal regulations.'],
                ['bi-heart-fill', 'Community First', 'We\'re not a national chain. We\'re your neighbors, and we invest in the communities we serve—sponsoring local events and giving back where we can.'],
            ];
            foreach ($values as [$icon, $title, $desc]):
            ?>
            <div class="col-lg-3 col-md-6">
                <div class="value-card">
                    <div style="font-size:1.8rem;color:var(--orange);margin-bottom:.75rem;"><i class="bi <?php echo $icon; ?>"></i></div>
                    <h5><?php echo $title; ?></h5>
                    <p><?php echo $desc; ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ── CTA ────────────────────────────────────────────────────── -->
<section class="cta-banner">
    <div class="container-fluid px-4 position-relative">
        <span class="section-label">Work With Us</span>
        <h2>Ready to Experience the Trash Panda Difference?</h2>
        <p>Join our growing list of happy customers throughout Upstate South Carolina.</p>
        <div class="d-flex flex-wrap justify-content-center gap-3">
            <a href="/public/contact.php" class="btn-primary-cta">
                <i class="bi bi-chat-dots-fill"></i> Request a Free Quote
            </a>
            <a href="/public/service-areas.php" class="btn-secondary-cta">
                <i class="bi bi-geo-alt-fill"></i> View Service Areas
            </a>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
