<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../admin/admin-functions.php';
$historyPreview = cePreviewActive();
$historyImageEditing = $historyPreview && hasPermission('manage_page_content');
$historyContent = function (string $key, string $fallback) use ($historyPreview): string {
    $value = getSectionContent('history', $key, $historyPreview);
    return $value === null || trim($value) === '' ? $fallback : $value;
};
$historyMark = function (string $key, string $label) use ($historyPreview): string {
    return ceMark('data-ve-section="' . ceAttr($key) . '" data-ve-label="' . ceAttr($label) . '"');
};
// ---------------------------------------------------------------
// Timeline entries. The gray boxes are the photo slots: to place a
// photo, set 'image' to its public path, e.g.
// '/assets/images/history/1918.jpg'. While 'image' is null the gray
// box is shown as-is.
// ---------------------------------------------------------------
$timeline = [
    [
        'year'     => '1918',
        'subtitle' => 'The Beginning',
        'image'    => null,
        'paras'    => [
            'In June 18, 1918, Rev. Fr. Nicanor Banzali, then parish priest of Guagua, founded a parochial school initially housed at the Catholic Convent in the town plaza.',
        ],
    ],
    [
        'year'     => '1922 and 1941',
        'subtitle' => 'Growth and Expansion',
        'image'    => null,
        'paras'    => [
            'The school became the Guagua National Institute (GNI) when it started to offer high school courses in 1922 and in 1941, the institution offered college courses.',
        ],
    ],
    [
        'year'     => '1941 — 1948',
        'subtitle' => 'Trials and Resilience',
        'image'    => null,
        'paras'    => [
            'However, on December 8, 1941, upon the outbreak of World War II, GNI had to temporarily close its doors.',
            'On February 10, 1942, its three school buildings were razed to the ground by a fire of incendiary (flammable) origin. Hundreds of alumni served in World War II (WW II), and among them was Capt. Euben Sangco, one of the notable heroes of Pampanga.',
        ],
    ],
    [
        'year'     => '1948',
        'subtitle' => 'A New Beginning',
        'image'    => null,
        'paras'    => [
            'On June 1, 1948, GNI reopened its doors in spite of the uncertainty of a place to house the school. Classes were held under \'Gampalati\' trees and in makeshift tents.',
            'The determination to move on in spite of the odds finds a parallel event in 1991, when a week after the eruption of Mt. Pinatubo, GNI resumed regular classes. The aim was to instill in the community and in the youth the importance of acting with dispatch in times of crisis.',
        ],
    ],
    [
        'year'     => '1949',
        'subtitle' => 'A Legacy of Excellence',
        'image'    => null,
        'paras'    => [
            'In 1949, the Institute became a College. GNI was, therefore, renamed to Guagua National Colleges, Inc. (GNC).',
            'Being a Catholic co-educational institution with its high quality of instruction, GNC has produced outstanding alumni who have distinguished themselves locally, countrywide, and abroad.',
            'GNC is a private college and a member in good standing of the Catholic Educational Association of the Philippines (CEAP).',
        ],
    ],
    [
        'year'     => 'Present',
        'subtitle' => 'Moving Forward Together',
        'image'    => null,
        'paras'    => [
            'At present, GNC\'s officials and administrators, educators, staff and students are united in carrying out the school\'s mission with vigor and is dedicated to the pursuit of quality education and academic excellence.',
        ],
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>History of GNC</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/base-style.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="/assets/css/gnc-history-style.css?v=<?= filemtime(__DIR__ . '/../../assets/css/gnc-history-style.css') ?>" rel="stylesheet">
    <link href="/assets/css/skeleton-style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
    <script>document.documentElement.classList.add('gh-js');</script>
    <noscript><style>.reveal-up,.reveal-scale{opacity:1!important;transform:none!important}</style></noscript>
</head>
<body>
    <?php $activeSection = 'about'; ?>
    <?php include_once __DIR__ . '/../../components/navbar.php'; ?>

    <!-- Opening transition: panels wipe up to reveal the page (pure CSS, never blocks clicks) -->
    <div class="gh-open" aria-hidden="true">
        <i style="--n:0"></i><i style="--n:1"></i><i style="--n:2"></i><i style="--n:3"></i><i style="--n:4"></i><i style="--n:5"></i>
        <div class="gh-open-label">
            <img src="/assets/images/logos/gnc-logo-v1.svg" alt="">
            <span>History of GNC</span>
        </div>
    </div>

    <main>
        <!-- Hero Section with Background and Title -->
        <section class="history-hero" id="ghHero">
            <div class="hero-bg" id="ghHeroBg"></div>
            <div class="hero-dust" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>

            <div class="hero-content">
                <div class="hero-eyebrow"<?= $historyMark('history_hero_eyebrow', 'History hero eyebrow') ?>><?= renderSectionContent($historyContent('history_hero_eyebrow', 'Since 1918')) ?></div>
                <div class="hero-title"<?= $historyMark('history_hero_title', 'History hero title') ?>><?= renderSectionContent($historyContent('history_hero_title', 'History of GNC')) ?></div>
                <span class="hero-rule"></span>
            </div>

            <!-- White Curve Divider -->
            <div class="curve-divider">
                <svg data-name="Layer 1" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none">
                    <path d="M0,0 Q600,120 1200,0 L1200,120 L0,120 Z" class="shape-fill"></path>
                </svg>
            </div>
        </section>

        <!-- Overlapping Logo -->
        <div class="logo-wrapper">
            <div class="logo-ring">
                <img src="/assets/images/logos/gnc-logo-v1.svg" alt="GNC Logo" class="overlap-logo">
            </div>
        </div>

        <!-- Journey Through Time Header -->
        <section class="journey-section">
            <div class="journey-title reveal-up"<?= $historyMark('history_intro_title', 'Journey heading') ?>><?= renderSectionContent($historyContent('history_intro_title', 'The Journey Through Time')) ?></div>
            <div class="title-underline reveal-up" style="--d:.12s"></div>
            <div class="journey-sub reveal-up" style="--d:.2s"<?= $historyMark('history_intro_text', 'Journey introduction') ?>><?= renderSectionContent($historyContent('history_intro_text', 'Scroll down to follow our story, from a parochial school in 1918 to the college we are today.')) ?></div>
        </section>
    </main>

    <!-- Timeline Section -->
    <section class="timeline-section">
        <div class="timeline-container" id="ghTimeline">
            <div class="timeline-track" aria-hidden="true"><div class="timeline-fill" id="ghFill"></div></div>

            <?php foreach ($timeline as $i => $t):
                $imageFirst = ($i % 2 === 0);
                $keys = ['history_1918', 'history_1922_1941', 'history_1941_1948', 'history_1948', 'history_1949', 'history_present'];
                $key = $keys[$i] ?? '';
                $t['image'] = $key !== '' ? (getPageSectionImagePath('history', $key) ?? ($t['image'] ?? null)) : ($t['image'] ?? null);

                $mediaHtml = function () use ($t, $imageFirst, $key, $historyImageEditing) {
                    ob_start(); ?>
                    <div class="timeline-content reveal-up <?= $imageFirst ? 'from-left' : 'from-right' ?>">
                        <div class="timeline-image-placeholder"<?= $historyImageEditing ? ' data-ve-img="history:' . htmlspecialchars($key, ENT_QUOTES, 'UTF-8') . '" data-ve-has-image="' . (!empty($t['image']) ? '1' : '0') . '" role="button" tabindex="0" aria-label="Add or replace photo for ' . htmlspecialchars($t['year'], ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                            <?php if (!empty($t['image'])): ?>
                            <img src="<?= htmlspecialchars($t['image']) ?>" alt="<?= htmlspecialchars($t['year'] . ' — ' . $t['subtitle']) ?>" loading="lazy" decoding="async">
                            <?php elseif ($historyImageEditing): ?>
                            <span class="history-photo-hint"><i class="bi bi-plus-circle"></i> Click to add photo</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php return ob_get_clean();
                };

                $fallbackText = '<h2>' . htmlspecialchars($t['year'], ENT_QUOTES, 'UTF-8') . '</h2><h3>' . htmlspecialchars($t['subtitle'], ENT_QUOTES, 'UTF-8') . '</h3>';
                foreach ($t['paras'] as $paragraph) $fallbackText .= '<p>' . htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8') . '</p>';
                $editableText = $key !== '' ? $historyContent($key, $fallbackText) : $fallbackText;
                $textHtml = function () use ($t, $imageFirst, $key, $editableText, $historyMark) {
                    ob_start(); ?>
                    <div class="timeline-content timeline-text reveal-up <?= $imageFirst ? 'from-right' : 'from-left' ?>" style="--d:.1s"<?= $historyMark($key, $t['year'] . ' — ' . $t['subtitle']) ?>>
                        <?= renderSectionContent($editableText) ?>
                    </div>
                    <?php return ob_get_clean();
                };
            ?>
            <!-- <?= htmlspecialchars($t['year']) ?> -->
            <div class="timeline-item">
                <?php if ($imageFirst): ?>
                    <?= $mediaHtml() ?>
                    <div class="timeline-dot reveal-scale"></div>
                    <?= $textHtml() ?>
                <?php else: ?>
                    <?= $textHtml() ?>
                    <div class="timeline-dot reveal-scale"></div>
                    <?= $mediaHtml() ?>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>

        </div>
    </section>

    <!-- Quote Banner Section -->
    <section class="quote-banner-section">
        <div class="container">
            <?php $historyQuoteBackground = getPageSectionImagePath('history', 'history_quote'); ?>
            <div class="quote-banner-box reveal-up" style="--history-quote-background:url('<?= htmlspecialchars($historyQuoteBackground ?? '/assets/images/gnc-header.png', ENT_QUOTES, 'UTF-8') ?>')">
                <?php if ($historyImageEditing): ?>
                <button type="button" class="history-background-edit" data-ve-img="history:history_quote" data-ve-history-background data-ve-has-image="<?= $historyQuoteBackground ? '1' : '0' ?>" data-ve-default-image="/assets/images/gnc-header.png" data-ve-label="Closing quote background"><i class="bi bi-image" aria-hidden="true"></i> Change background image</button>
                <?php endif; ?>
                <span class="quote-mark quote-left">&#8220;</span>
                <div class="quote-text"<?= $historyMark('history_quote', 'History closing statement') ?>><?= renderSectionContent($historyContent('history_quote', 'From humble beginnings to a legacy of service and excellence, GNC continues to educate, inspire, and transform lives.')) ?></div>
                <span class="quote-mark quote-right">&#8221;</span>
            </div>
        </div>
    </section>

    <button type="button" class="gh-backtop" id="ghBackTop" aria-label="Back to top"><i class="bi bi-arrow-up"></i></button>

    <?php include_once __DIR__ . '/../../components/footer.php'; ?>
    <?php include_once $_SERVER['DOCUMENT_ROOT'] . '/chatbot/widget.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script>
    /* GNC History page: reveal on scroll, timeline progress line, active
       milestone, hero parallax, scroll progress bar, back-to-top.
       Respects prefers-reduced-motion. */
    (function () {
        'use strict';
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var $ = function (s) { return document.querySelector(s); };
        var $$ = function (s) { return Array.prototype.slice.call(document.querySelectorAll(s)); };

        /* reveal */
        var revs = $$('.reveal-up, .reveal-scale');
        revs.forEach(function (el) {
            var d = el.style.getPropertyValue('--d');
            if (d) el.style.transitionDelay = d;
        });
        if ('IntersectionObserver' in window && !reduce) {
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (en) {
                    if (en.isIntersecting) { en.target.classList.add('is-visible'); io.unobserve(en.target); }
                });
            }, { threshold: 0.15, rootMargin: '0px 0px -6% 0px' });
            revs.forEach(function (el) { io.observe(el); });
        } else {
            revs.forEach(function (el) { el.classList.add('is-visible'); });
        }

        /* scroll-driven */
        var back = $('#ghBackTop');
        var heroBg = $('#ghHeroBg'), hero = $('#ghHero');
        var tl = $('#ghTimeline'), fill = $('#ghFill');
        var items = $$('.timeline-item');
        var ticking = false;

        function update() {
            ticking = false;
            var doc = document.documentElement;
            var y = window.pageYOffset || doc.scrollTop;
            var vh = window.innerHeight;
            if (back) back.classList.toggle('is-show', y > 700);
            if (heroBg && hero && !reduce && y < hero.offsetHeight) {
                heroBg.style.transform = 'translate3d(0,' + (y * 0.3) + 'px,0) scale(1.1)';
            }
            if (tl && fill) {
                var r = tl.getBoundingClientRect();
                var focus = vh * 0.55;
                var p = Math.max(0, Math.min(1, (focus - r.top) / r.height));
                fill.style.height = (p * 100) + '%';

                var current = null, best = Infinity;
                items.forEach(function (it) {
                    var ir = it.getBoundingClientRect();
                    var dot = it.querySelector('.timeline-dot');
                    var dy = dot ? dot.getBoundingClientRect().top : ir.top;
                    it.classList.toggle('is-passed', dy < focus);
                    var dist = Math.abs(ir.top + ir.height / 2 - focus);
                    if (dist < best) { best = dist; current = it; }
                });
                var inZone = r.top < vh * 0.7 && r.bottom > vh * 0.3;
                items.forEach(function (it) { it.classList.toggle('is-active', inZone && it === current); });
            }
        }
        window.addEventListener('scroll', function () {
            if (!ticking) { ticking = true; requestAnimationFrame(update); }
        }, { passive: true });
        window.addEventListener('resize', update);
        update();

        if (back) back.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
        });
    })();
    </script>
</body>
</html>
