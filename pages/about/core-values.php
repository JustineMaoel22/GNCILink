<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Core Values</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/base-style.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="/assets/css/core-values-style.css?v=3" rel="stylesheet">
    <link href="/assets/css/skeleton-style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>
    <?php $activeSection = 'about'; ?>
    <?php include_once __DIR__ . '/../../components/navbar.php'; ?>

    <!-- Opening transition: panels wipe up to reveal the page (pure CSS, never blocks clicks) -->
    <div class="cv-open" aria-hidden="true">
        <i style="--n:0"></i><i style="--n:1"></i><i style="--n:2"></i><i style="--n:3"></i><i style="--n:4"></i><i style="--n:5"></i>
        <div class="cv-open-label">
            <img src="/assets/images/logos/gnc-logo-v1.svg" alt="">
            <span>Core Values</span>
        </div>
    </div>

    <section class="core-values-intro">
        <div class="core-values-hero">
            <div class="core-values-hero-inner">
                <nav class="core-values-breadcrumb reveal-up" aria-label="breadcrumb">
                    <a class="gnc-breadcrumb-home" href="/" aria-label="Home" title="Home"><svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" focusable="false"><path d="M8 1 0 7h2v8h4v-5h4v5h4V7h2L8 1Z"/></svg></a><i class="bi bi-chevron-right" aria-hidden="true"></i>
                    <span>About</span>
                    <i class="bi bi-chevron-right"></i>
                    <span class="dept-current">Core Values</span>
                </nav>
                <h1 class="reveal-up" style="transition-delay: 0.1s;">Core Values</h1>
                <p class="reveal-up" style="transition-delay: 0.2s;">Our core values guide our actions and decisions, reflecting our commitment to excellence and integrity.</p>
            </div>
        </div>
    </section>

    <section class="core-values-main">
        <div class="section-title reveal-up">
            <h2>Our Core Values</h2>
        </div>
        
        <div class="cards-container">
            <!-- Fides Card -->
            <div class="value-card theme-gold reveal-scale" style="transition-delay: 0.1s;">
                <div class="icon-wrapper">
                    <!-- 👇 PUT YOUR FIDES (CROSS) ICON HERE 👇 -->
                    <img src="/assets/images/svg/Fides Symbol.svg" alt="Fides Icon" style="width: 45px;">
                    <!-- 👆 PUT YOUR FIDES (CROSS) ICON HERE 👆 -->
                </div>
                <h3>Fides</h3>
                <p>Faith in God and<br>One's Self.</p>
            </div>

            <!-- Scientia Card -->
            <div class="value-card theme-green reveal-scale" style="transition-delay: 0.2s;">
                <div class="icon-wrapper">
                    <!-- 👇 PUT YOUR SCIENTIA (BOOK) ICON HERE 👇 -->
                    <img src="/assets/images/svg/Scientia Symbol.svg" alt="Scientia Icon" style="width: 55px;">
                    <!-- 👆 PUT YOUR SCIENTIA (BOOK) ICON HERE 👆 -->
                </div>
                <h3>Scientia</h3>
                <p>Search for Truth<br>and Knowledge.</p>
            </div>

            <!-- Patria Card -->
            <div class="value-card theme-red reveal-scale" style="transition-delay: 0.3s;">
                <div class="icon-wrapper">
                    <!-- 👇 PUT YOUR PATRIA (SWORD) ICON HERE 👇 -->
                    <img src="/assets/images/svg/Patria Symbol.svg" alt="Patria Icon" style="width: 50px;">
                    <!-- 👆 PUT YOUR PATRIA (SWORD) ICON HERE 👆 -->
                </div>
                <h3>Patria</h3>
                <p>Love for Country.</p>
            </div>
        </div>
    </section>

    <?php include_once __DIR__ . '/../../components/footer.php'; ?>
    <?php include_once $_SERVER['DOCUMENT_ROOT'] . '/chatbot/widget.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="/assets/js/core-values.js"></script>
    <script>
    /* Extras: make sure reveal classes get applied (harmless if core-values.js
       already does it) and clear each card's entrance delay once it has
       appeared, so hover reacts instantly. */
    (function () {
        'use strict';
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var els = Array.prototype.slice.call(document.querySelectorAll('.reveal-up, .reveal-scale'));
        if ('IntersectionObserver' in window && !reduce) {
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (en) {
                    if (en.isIntersecting) { en.target.classList.add('is-visible'); io.unobserve(en.target); }
                });
            }, { threshold: 0.15 });
            els.forEach(function (el) { io.observe(el); });
        } else {
            els.forEach(function (el) { el.classList.add('is-visible'); });
        }
        Array.prototype.slice.call(document.querySelectorAll('.value-card')).forEach(function (card) {
            function clearDelay(e) {
                if (e.target !== card || e.propertyName !== 'opacity') return;
                card.style.transitionDelay = '0s';
                card.removeEventListener('transitionend', clearDelay);
            }
            card.addEventListener('transitionend', clearDelay);
            if (reduce) card.style.transitionDelay = '0s';
        });
    })();
    </script>
</body>
</html>