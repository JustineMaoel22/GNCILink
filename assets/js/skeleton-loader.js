/**
 * Skeleton loading — shared across all program pages.
 * Pairs with /assets/css/skeleton-style.css.
 *
 * Any <img> inside a ".skeleton-wrap" container gets a shimmer
 * placeholder until it finishes loading, then fades in.
 *
 * Any element with class ".skeleton-hero" (typically the hero <section>,
 * which uses a CSS background-image instead of an <img> tag) has its
 * shimmer removed and the real background image restored once that
 * image is confirmed loaded.
 *
 * Any element with class ".skeleton-group" that contains a
 * ".skeleton-wrap img" is treated as a card: once that image finishes
 * loading (or errors), the whole group is marked "is-loaded" too, so a
 * ".skeleton-text-lines" placeholder inside it can be swapped for the
 * real ".skeleton-real-content" at the same moment the image appears,
 * instead of the image and its text loading independently.
 */
document.addEventListener('DOMContentLoaded', function () {
    // --- Regular <img> skeletons -------------------------------------
    document.querySelectorAll('.skeleton-wrap img').forEach(function (img) {
        function reveal() {
            img.classList.add('is-loaded');

            var wrap = img.closest('.skeleton-wrap');
            if (wrap) wrap.classList.add('is-loaded');

            // If this image lives inside a skeleton-group (e.g. an image
            // paired with a text block), bring the whole group's text
            // skeleton down with it so they finish together.
            var group = img.closest('.skeleton-group');
            if (group) group.classList.add('is-loaded');
        }
        // Image may already be cached/loaded by the time this runs
        if (img.complete && img.naturalHeight !== 0) {
            reveal();
        } else {
            img.addEventListener('load', reveal);
            // Reveal on error too, so a broken image link doesn't leave
            // a shimmer spinning forever
            img.addEventListener('error', reveal);
        }
    });
    // --- Hero background-image skeletons ------------------------------
    document.querySelectorAll('.skeleton-hero').forEach(function (hero) {
        var bgUrl = hero.getAttribute('data-bg');
        if (!bgUrl) return;
        var preload = new Image();
        preload.onload = function () {
            hero.style.backgroundImage = "url('" + bgUrl + "')";
            hero.classList.remove('skeleton-hero');
        };
        preload.onerror = function () {
            // Still remove the shimmer even if the image failed to load
            hero.classList.remove('skeleton-hero');
        };
        preload.src = bgUrl;
    });
});