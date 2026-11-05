document.querySelectorAll('[data-hss-flip]').forEach(function (card) {
    card.addEventListener('click', function () {
        const isFlipped = card.classList.toggle('is-flipped');
        card.setAttribute('aria-expanded', isFlipped ? 'true' : 'false');
    });
});

// Reveal each card once; no scroll handlers or continuously running effects.
(function () {
    const motion = window.matchMedia('(prefers-reduced-motion: reduce)');
    if (motion.matches || !('IntersectionObserver' in window)) return;

    const elements = document.querySelectorAll('.admin-section .reveal-up, .admin-section .reveal-scale');
    const observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (!entry.isIntersecting) return;
            const element = entry.target;
            element.classList.remove('admin-scroll-pending');
            element.classList.add('admin-scroll-revealing');
            element.addEventListener('animationend', function finish(event) {
                if (event.target !== element) return;
                element.classList.remove('admin-scroll-revealing');
                element.removeEventListener('animationend', finish);
            });
            observer.unobserve(element);
        });
    }, { threshold: 0.08 });

    elements.forEach(function (element) {
        element.classList.add('admin-scroll-pending');
        observer.observe(element);
    });

    if (motion.addEventListener) motion.addEventListener('change', function () {
        if (!motion.matches) return;
        observer.disconnect();
        elements.forEach(function (element) {
            element.classList.remove('admin-scroll-pending', 'admin-scroll-revealing');
        });
    });
})();
