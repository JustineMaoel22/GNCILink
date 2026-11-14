document.querySelectorAll('[data-hss-flip]').forEach(function (card) {
    const inner = card.querySelector('.hss-flip-card-inner');
    const front = card.querySelector('.hss-face-front');
    const back = card.querySelector('.hss-face-back');
    if (!inner || !front || !back) return;
    let frame = 0;

    function showFacingSide() {
        // Follow the rendered rotation, including eased and interrupted flips.
        // A fixed half-duration delay does not coincide with the edge of an eased flip.
        const transform = getComputedStyle(inner).transform;
        const frontFacing = transform === 'none' || new DOMMatrixReadOnly(transform).m11 >= 0;
        front.style.visibility = frontFacing ? 'visible' : 'hidden';
        back.style.visibility = frontFacing ? 'hidden' : 'visible';
    }
    function followFlip() {
        showFacingSide();
        frame = inner.getAnimations().some(function (animation) {
            return animation.transitionProperty === 'transform' && animation.playState === 'running';
        }) ? requestAnimationFrame(followFlip) : 0;
    }

    showFacingSide();
    card.addEventListener('click', function () {
        const isFlipped = card.classList.toggle('is-flipped');
        card.setAttribute('aria-expanded', isFlipped ? 'true' : 'false');
        showFacingSide();
        if (!frame) frame = requestAnimationFrame(followFlip);
    });
});

// Reveal each card once; no scroll handlers or continuously running effects.
(function () {
    // Category links reload this page; show the new category immediately.
    if (new URLSearchParams(location.search).has('section')) return;
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
