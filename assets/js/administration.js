document.querySelectorAll('[data-hss-flip]').forEach(function (card) {
    card.addEventListener('click', function () {
        const isFlipped = card.classList.toggle('is-flipped');
        card.setAttribute('aria-expanded', isFlipped ? 'true' : 'false');
    });
});

document.addEventListener("DOMContentLoaded", function() {
    const observerOptions = {
        root: null,
        rootMargin: '0px',
        threshold: 0.15
    };

    const observer = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    const animatedElements = document.querySelectorAll('.reveal-up, .reveal-scale');
    animatedElements.forEach(el => observer.observe(el));
});