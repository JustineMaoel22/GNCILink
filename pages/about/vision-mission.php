<?php
/**
 * Public Vision & Mission page.
 * Vision/Mission text is managed from the admin Content Editor
 * (Select Page → Vision & Mission) and stored in page_sections;
 * only the text is dynamic — icons and layout are unchanged.
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../admin/admin-functions.php';

// In the Content Editor's preview (?ce_preview=1, signed-in editors only) the
// unpublished draft is shown, so editors see their own work. Visitors always
// get the published text.
$visionText  = getSectionContent('vision-mission', 'vision',  cePreviewActive());
$missionText = getSectionContent('vision-mission', 'mission', cePreviewActive());

// Fallback to the original copy if the CMS row hasn't been created yet
// (e.g. migration not yet run), so the page never renders blank.
if ($visionText === null) {
    $visionText = "Our graduates will be globally\ncompetitive citizens.\nThey will uphold our founding values.";
}
if ($missionText === null) {
    $missionText = "We will be the leading learning\ninstitution in Western\nPampanga.";
}

/**
 * Prints the text of one card. Plain text keeps its original <p> markup;
 * text that was formatted in the Content Editor (bold, lists, links...) is
 * printed as sanitised HTML inside a <div>, because <p> can't hold lists.
 * In the editor preview it is always a <div> carrying the editor marker.
 */
function vmRenderText(?string $text, string $key): void {
    $isHtml = richTextLooksLikeHtml((string)$text);
    $tag    = ($isHtml || cePreviewActive()) ? 'div' : 'p';
    echo '<' . $tag . ' class="vm-text' . ($isHtml ? ' is-html' : '') . '"'
       . ceMark('data-ve-section="' . ceAttr($key) . '" data-ve-label="' . ceAttr($key === 'vision' ? 'Our Vision' : 'Our Mission') . '"') . '>'
       . renderSectionContent($text)
       . '</' . $tag . '>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vision and Mission</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/base-style.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="/assets/css/vision-mission-style.css?v=3" rel="stylesheet">
    <link href="/assets/css/skeleton-style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>
    <?php $activeSection = 'about'; ?>
    <?php include_once __DIR__ . '/../../components/navbar.php'; ?>

    <!-- Opening transition: panels wipe up to reveal the page (pure CSS, never blocks clicks) -->
    <div class="vm-open" aria-hidden="true">
        <i style="--n:0"></i><i style="--n:1"></i><i style="--n:2"></i><i style="--n:3"></i><i style="--n:4"></i><i style="--n:5"></i>
        <div class="vm-open-label">
            <img src="/assets/images/logos/gnc-logo-v1.svg" alt="">
            <span>Vision and Mission</span>
        </div>
    </div>

    <section class="vision-mission-intro">
        <div class="vision-mission-hero">
            <div class="vision-mission-hero-inner">
                <nav class="vision-mission-breadcrumb reveal-up" aria-label="breadcrumb">
                    <span>About</span>
                    <i class="bi bi-chevron-right"></i>
                    <span class="dept-current">Vision and Mission</span>
                </nav>
                <h1 class="reveal-up" style="transition-delay: 0.1s;">Vision and Mission</h1>
                <p class="reveal-up" style="transition-delay: 0.2s;">Our vision inspires our direction and our mission drives our daily commitment to education and service.</p>
            </div>
        </div>
    </section>

    <section class="vision-mission-content py-5">
        <div class="container vision-mission-container">
            <div class="row g-4 justify-content-center">

                <!-- Vision Card -->
                <div class="col-lg-6">
                    <div class="vm-card vm-card--green reveal-scale" style="transition-delay: 0.1s;">
                        <!-- Colored sidebar with seal watermark and icon circle inside -->
                        <div class="vm-sidebar vm-bg-green">
                            <div class="vm-watermark-seal"></div>
                            <div class="vm-icon-circle">
                                <img src="/assets/images/svg/vision icon.svg" alt="Vision Icon" style="width: 42px;">
                            </div>
                        </div>

                        <!-- Full-card watermark (architectural illustration) -->
                        <div class="vm-watermark-building"></div>

                        <!-- Text Content -->
                        <div class="vm-content">
                            <h2 class="vm-title text-green">Our Vision</h2>
                            <?php vmRenderText($visionText, 'vision'); ?>
                            <span class="vm-quote-mark" aria-hidden="true">&#8221;</span>
                        </div>
                    </div>
                </div>

                <!-- Mission Card -->
                <div class="col-lg-6">
                    <div class="vm-card vm-card--gold reveal-scale" style="transition-delay: 0.3s;">
                        <!-- Colored sidebar with seal watermark and icon circle inside -->
                        <div class="vm-sidebar vm-bg-gold">
                            <div class="vm-watermark-seal"></div>
                            <div class="vm-icon-circle">
                                <img src="/assets/images/svg/mission icon.svg" alt="Mission Icon" style="width: 42px;">
                            </div>
                        </div>

                        <!-- Full-card watermark (architectural illustration) -->
                        <div class="vm-watermark-building"></div>

                        <!-- Text Content -->
                        <div class="vm-content">
                            <h2 class="vm-title text-gold">Our Mission</h2>
                            <?php vmRenderText($missionText, 'mission'); ?>
                            <span class="vm-quote-mark" aria-hidden="true">&#8221;</span>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <?php include_once __DIR__ . '/../../components/footer.php'; ?>
    <?php include_once $_SERVER['DOCUMENT_ROOT'] . '/chatbot/widget.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="/assets/js/vision-mission.js"></script>
    <script>
    /* Extras only: makes sure reveal classes get applied (harmless if
       vision-mission.js already does it) and adds a soft card tilt. */
    (function () {
        'use strict';
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var canHover = window.matchMedia && window.matchMedia('(hover: hover)').matches;
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

        /* The cards use an inline transition-delay to stagger their entrance.
           That delay would also slow down hover, so clear it once the entrance
           has finished. */
        Array.prototype.slice.call(document.querySelectorAll('.vm-card')).forEach(function (card) {
            function clearDelay(e) {
                if (e.target !== card || e.propertyName !== 'opacity') return;
                card.style.transitionDelay = '0s';
                card.removeEventListener('transitionend', clearDelay);
            }
            card.addEventListener('transitionend', clearDelay);
            if (reduce) card.style.transitionDelay = '0s';
        });

        if (canHover && !reduce) {
            Array.prototype.slice.call(document.querySelectorAll('.vm-card')).forEach(function (card) {
                card.addEventListener('mousemove', function (e) {
                    var r = card.getBoundingClientRect();
                    var px = (e.clientX - r.left) / r.width - 0.5;
                    var py = (e.clientY - r.top) / r.height - 0.5;
                    card.style.setProperty('--tilt', 'perspective(1000px) rotateX(' + (-py * 3.5) + 'deg) rotateY(' + (px * 4.5) + 'deg)');
                });
                card.addEventListener('mouseleave', function () { card.style.removeProperty('--tilt'); });
            });
        }
    })();
    </script>
</body>
</html>