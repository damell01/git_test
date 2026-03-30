/* shared-components.js — injects nav + footer + ticker into every page */

const LOGO_SRC = 'assets/logo.jpeg';

const navHTML = `
<nav class="site-nav" id="siteNav">
  <div class="nav-inner">
    <a href="index.html" class="nav-logo">
      <img src="${LOGO_SRC}" alt="Trash Panda Roll-Offs">
      <div class="nav-logo-text">TRASH PANDA<br><em>ROLL-OFFS</em></div>
    </a>
    <ul class="nav-links">
      <li><a href="index.html">Home</a></li>
      <li><a href="services.html">Services</a></li>
      <li><a href="sizes.html">Dumpster Sizes</a></li>
      <li><a href="service-areas.html">Service Areas</a></li>
      <li><a href="about.html">About</a></li>
      <li><a href="faq.html">FAQ</a></li>
      <li><a href="/book.php" class="nav-cta-btn">Book Now</a></li>
    </ul>
    <a href="tel:+12513334444" class="nav-phone d-none d-xl-flex"><i class="fas fa-phone"></i>(251) 333-4444</a>
    <button class="nav-hamburger" id="navHamburger" aria-label="Menu">
      <span></span><span></span><span></span>
    </button>
  </div>
</nav>
<div class="mobile-nav" id="mobileNav">
  <ul>
    <li><a href="index.html">Home</a></li>
    <li><a href="services.html">Services</a></li>
    <li><a href="sizes.html">Dumpster Sizes</a></li>
    <li><a href="service-areas.html">Service Areas</a></li>
    <li><a href="about.html">About</a></li>
    <li><a href="faq.html">FAQ</a></li>
    <li><a href="/book.php">Book Now</a></li>
    <li><a href="tel:+12513334444"><i class="fas fa-phone me-2" style="color:var(--orange)"></i>(251) 333-4444</a></li>
  </ul>
</div>`;

const tickerHTML = `
<div class="ticker-wrap">
  <div class="ticker-track">
    ${Array(3).fill(`
      <div class="ticker-item">
        Same-Day Delivery Available <span class="ticker-sep"></span>
        Baldwin County & Mobile Area <span class="ticker-sep"></span>
        No Hidden Fees — Ever <span class="ticker-sep"></span>
        10, 20, 30 & 40 Yard Dumpsters <span class="ticker-sep"></span>
        Residential & Commercial <span class="ticker-sep"></span>
        Licensed & Insured <span class="ticker-sep"></span>
        Call (251) 333-4444 <span class="ticker-sep"></span>
        Eco-Responsible Disposal <span class="ticker-sep"></span>
      </div>
    `).join('')}
  </div>
</div>`;

const footerHTML = `
<footer class="site-footer">
  <div class="footer-top">
    <div class="container" style="max-width:1300px;">
      <div class="row gy-5">
        <div class="col-lg-4">
          <div class="footer-logo-text">TRASH PANDA <em>ROLL-OFFS</em></div>
          <p class="footer-tagline">Baldwin County & Mobile's most trusted<br>dumpster rental crew.</p>
          <div class="social-row mb-4">
            <a href="#" class="social-btn"><i class="fab fa-facebook-f"></i></a>
            <a href="#" class="social-btn"><i class="fab fa-instagram"></i></a>
            <a href="#" class="social-btn"><i class="fab fa-google"></i></a>
            <a href="#" class="social-btn"><i class="fab fa-yelp"></i></a>
          </div>
          <a href="tel:+12513334444" style="font-family:var(--font-cond);font-weight:900;font-size:1.5rem;color:var(--orange);text-decoration:none;letter-spacing:0.02em;display:flex;align-items:center;gap:8px;"><i class="fas fa-phone" style="font-size:1rem;"></i>(251) 333-4444</a>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
          <div class="footer-heading">Services</div>
          <ul class="footer-links">
            <li><a href="services.html">Residential Rentals</a></li>
            <li><a href="services.html">Commercial Rentals</a></li>
            <li><a href="services.html">Construction Cleanup</a></li>
            <li><a href="services.html">Estate Cleanouts</a></li>
            <li><a href="services.html">Storm Debris</a></li>
            <li><a href="services.html">Roofing Projects</a></li>
          </ul>
        </div>
        <div class="col-6 col-md-3 col-lg-2">
          <div class="footer-heading">Company</div>
          <ul class="footer-links">
            <li><a href="about.html">About Us</a></li>
            <li><a href="faq.html">FAQ</a></li>
            <li><a href="service-areas.html">Service Areas</a></li>
            <li><a href="contact.html">Contact</a></li>
            <li><a href="sizes.html">Dumpster Sizes</a></li>
          </ul>
        </div>
        <div class="col-lg-4">
          <div class="footer-heading">Service Areas</div>
          <ul class="footer-links">
            <li><a href="service-areas.html"><i class="fas fa-map-marker-alt" style="color:var(--orange);width:14px;"></i> Gulf Shores, AL</a></li>
            <li><a href="service-areas.html"><i class="fas fa-map-marker-alt" style="color:var(--orange);width:14px;"></i> Orange Beach, AL</a></li>
            <li><a href="service-areas.html"><i class="fas fa-map-marker-alt" style="color:var(--orange);width:14px;"></i> Foley, AL</a></li>
            <li><a href="service-areas.html"><i class="fas fa-map-marker-alt" style="color:var(--orange);width:14px;"></i> Daphne, AL</a></li>
            <li><a href="service-areas.html"><i class="fas fa-map-marker-alt" style="color:var(--orange);width:14px;"></i> Fairhope, AL</a></li>
            <li><a href="service-areas.html"><i class="fas fa-map-marker-alt" style="color:var(--orange);width:14px;"></i> Mobile, AL</a></li>
            <li><a href="service-areas.html"><i class="fas fa-map-marker-alt" style="color:var(--orange);width:14px;"></i> Spanish Fort, AL</a></li>
          </ul>
          <a href="service-areas.html" style="font-family:var(--font-cond);font-weight:700;font-size:0.8rem;letter-spacing:0.1em;text-transform:uppercase;color:var(--orange);text-decoration:none;margin-top:0.75rem;display:inline-block;">View All Areas →</a>
        </div>
      </div>
    </div>
  </div>
  <div class="footer-bottom">
    <div class="container d-flex justify-content-between align-items-center flex-wrap gap-2" style="max-width:1300px;">
      <p class="footer-bottom-text mb-0">© 2025 Trash Panda Roll-Offs. All rights reserved. | Baldwin County & Mobile, AL</p>
      <p class="footer-bottom-text mb-0">Made with 🦝 for the Gulf Coast</p>
    </div>
  </div>
</footer>`;

// Init
document.addEventListener('DOMContentLoaded', () => {
  // Inject nav
  const navWrap = document.getElementById('nav-placeholder');
  if (navWrap) navWrap.innerHTML = navHTML;

  // Inject ticker
  const tickerWrap = document.getElementById('ticker-placeholder');
  if (tickerWrap) tickerWrap.innerHTML = tickerHTML;

  // Inject footer
  const footerWrap = document.getElementById('footer-placeholder');
  if (footerWrap) footerWrap.innerHTML = footerHTML;

  // Nav scroll effect
  const nav = document.getElementById('siteNav');
  const onScroll = () => {
    if (window.scrollY > 30) nav?.classList.add('scrolled');
    else nav?.classList.remove('scrolled');
  };
  window.addEventListener('scroll', onScroll);
  onScroll();

  // Hamburger
  const ham = document.getElementById('navHamburger');
  const mob = document.getElementById('mobileNav');
  ham?.addEventListener('click', () => mob?.classList.toggle('open'));

  // Active nav link
  const page = window.location.pathname.split('/').pop() || 'index.html';
  document.querySelectorAll('.nav-links a, .mobile-nav a').forEach(a => {
    if (a.getAttribute('href') === page) a.classList.add('active');
  });

  // Smooth scroll for anchor links
  document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', e => {
      const t = document.querySelector(a.getAttribute('href'));
      if (t) { e.preventDefault(); t.scrollIntoView({ behavior:'smooth', block:'start' }); }
    });
  });
});
