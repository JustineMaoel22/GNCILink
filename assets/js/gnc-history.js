document.addEventListener("DOMContentLoaded", function() {
    // Configuration for the IntersectionObserver
    const observerOptions = {
        root: null,
        rootMargin: '0px',
        threshold: 0.15 // Trigger when 15% of the element is visible
    };

    const observer = new IntersectionObserver((entries, observer) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                // Stop observing once the animation has been triggered
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Target all elements with these classes
    const animatedElements = document.querySelectorAll('.reveal-up, .reveal-scale');
    animatedElements.forEach(el => observer.observe(el));
});