<footer class="site-footer">
    <div class="container-fluid px-4">
        <div class="row g-4 py-5">

            <!-- Brand column -->
            <div class="col-lg-3 col-md-6">
                <div class="footer-brand mb-3">
                    🗑 <span><?php echo SITE_NAME; ?></span>
                </div>
                <p class="footer-tagline">Local family business delivering fast, affordable dumpster rentals you can count on.</p>
                <p class="footer-contact-item mt-3">
                    <i class="bi bi-telephone-fill me-2"></i>
                    <a href="tel:<?php echo preg_replace('/[^0-9]/', '', SITE_PHONE); ?>"><?php echo SITE_PHONE; ?></a>
                </p>
                <p class="footer-contact-item">
                    <i class="bi bi-envelope-fill me-2"></i>
                    <a href="mailto:<?php echo SITE_EMAIL; ?>"><?php echo SITE_EMAIL; ?></a>
                </p>
            </div>

            <!-- Quick Links -->
            <div class="col-lg-2 col-md-6">
                <h6 class="footer-heading">Quick Links</h6>
                <ul class="footer-links">
                    <li><a href="/public/index.php">Home</a></li>
                    <li><a href="/public/sizes.php">Dumpster Sizes</a></li>
                    <li><a href="/public/residential.php">Residential</a></li>
                    <li><a href="/public/commercial.php">Commercial</a></li>
                    <li><a href="/public/service-areas.php">Service Areas</a></li>
                    <li><a href="/public/about.php">About Us</a></li>
                    <li><a href="/public/faq.php">FAQ</a></li>
                    <li><a href="/public/contact.php">Contact / Quote</a></li>
                </ul>
            </div>

            <!-- Services -->
            <div class="col-lg-2 col-md-6">
                <h6 class="footer-heading">Services</h6>
                <ul class="footer-links">
                    <li><a href="/public/residential.php">Home Cleanouts</a></li>
                    <li><a href="/public/residential.php">Renovation Debris</a></li>
                    <li><a href="/public/residential.php">Moving &amp; Declutter</a></li>
                    <li><a href="/public/residential.php">Yard &amp; Landscaping</a></li>
                    <li><a href="/public/commercial.php">Construction Sites</a></li>
                    <li><a href="/public/commercial.php">Property Management</a></li>
                    <li><a href="/public/commercial.php">Commercial Hauling</a></li>
                </ul>
            </div>

            <!-- Service Areas -->
            <div class="col-lg-3 col-md-6">
                <h6 class="footer-heading">Service Areas</h6>
                <ul class="footer-links footer-links-2col">
                    <li><a href="/public/service-areas.php">Greenville</a></li>
                    <li><a href="/public/service-areas.php">Spartanburg</a></li>
                    <li><a href="/public/service-areas.php">Anderson</a></li>
                    <li><a href="/public/service-areas.php">Simpsonville</a></li>
                    <li><a href="/public/service-areas.php">Greer</a></li>
                    <li><a href="/public/service-areas.php">Mauldin</a></li>
                    <li><a href="/public/service-areas.php">Easley</a></li>
                    <li><a href="/public/service-areas.php">Taylors</a></li>
                    <li><a href="/public/service-areas.php">Duncan</a></li>
                    <li><a href="/public/service-areas.php">Boiling Springs</a></li>
                    <li><a href="/public/service-areas.php">Powdersville</a></li>
                    <li><a href="/public/service-areas.php">Fountain Inn</a></li>
                </ul>
            </div>

            <!-- Hours -->
            <div class="col-lg-2 col-md-6">
                <h6 class="footer-heading">Business Hours</h6>
                <ul class="footer-links">
                    <li>Mon – Fri: 7am – 6pm</li>
                    <li>Saturday: 8am – 4pm</li>
                    <li>Sunday: Closed</li>
                </ul>
                <a href="/public/contact.php" class="btn btn-cta-footer mt-3">Get a Free Quote</a>
            </div>

        </div><!-- /row -->

        <hr class="footer-divider">

        <div class="footer-bottom d-flex flex-column flex-md-row justify-content-between align-items-center py-3">
            <p class="mb-0">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
            <p class="mb-0 mt-2 mt-md-0">
                <small>
                    <a href="/admin/login.php" class="staff-portal-link">Staff Portal</a>
                </small>
            </p>
        </div>

    </div>
</footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-YvpcrYf0tY3lHB60NNkmXc4s9bIOgUxi8T/jzmFZLNNq3+5ZTbx6jP5DP7bEk7g"
            crossorigin="anonymous"></script>
    <!-- Custom JS -->
    <script src="/public/assets/js/main.js"></script>
</body>
</html>
