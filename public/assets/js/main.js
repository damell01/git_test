// Trash Panda Roll-Offs — Main JS

document.addEventListener('DOMContentLoaded', () => {

    // ── Sticky navbar shrink on scroll ──────────────────────
    const nav = document.getElementById('mainNav');
    if (nav) {
        const onScroll = () => {
            nav.classList.toggle('scrolled', window.scrollY > 60);
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    // ── Smooth scroll for on-page anchor links ───────────────
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', e => {
            const target = document.querySelector(anchor.getAttribute('href'));
            if (target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });

    // ── Auto-dismiss alerts after 8 seconds ─────────────────
    document.querySelectorAll('.alert-success-custom, .alert-error-custom').forEach(el => {
        setTimeout(() => {
            el.style.transition = 'opacity .5s ease';
            el.style.opacity = '0';
            setTimeout(() => el.remove(), 500);
        }, 8000);
    });

    // ── Contact form: delivery date minimum = today ──────────
    const dateInput = document.getElementById('delivery_date');
    if (dateInput) {
        const today = new Date().toISOString().split('T')[0];
        dateInput.setAttribute('min', today);
    }

    // ── Animate elements into view ───────────────────────────
    if ('IntersectionObserver' in window) {
        const targets = document.querySelectorAll('.card-feature, .size-card, .step-card, .team-card, .value-card, .area-pill');
        const io = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                    io.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12 });

        targets.forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity .45s ease, transform .45s ease';
            io.observe(el);
        });
    }
});
