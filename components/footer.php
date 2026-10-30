<?php require_once __DIR__ . '/../includes/privacy.php'; ?>
<?php include_once __DIR__ . '/image-framing-assets.php'; ?>
<link rel="stylesheet" href="/assets/css/content-rich-text.css?v=<?= filemtime(__DIR__.'/../assets/css/content-rich-text.css') ?>">
<?php include_once __DIR__ . '/office-map-modal.php'; ?>
<?php
// Visitor counter: read current numbers (recording happens via api/track-visit.php)
$gncVisitorStats = ['today' => 0, 'total' => 0];
try {
    if (!function_exists('getDB')) {
        require_once __DIR__ . '/../config/config.php';
    }
    require_once __DIR__ . '/visitor-counter.php';
    $gncVisitorStats = gncVisitorStats(getDB());
} catch (Throwable $e) {
    // Footer still renders with zeros if the database is unavailable.
}

/**
 * Turn a stored page_url into a link that actually exists on this server.
 * Tries the path as-is, with .php added, and as a folder with index.php/index.html.
 * Returns null when nothing matches, so the footer never shows a dead (NOT FOUND) link.
 */
if (!function_exists('gncResolvePopularUrl')) {
    function gncResolvePopularUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') return null;

        $parts = parse_url($url);
        if ($parts === false) return null;

        // Full external URLs are left untouched.
        if (!empty($parts['host'])) return $url;

        $path  = rawurldecode((string) ($parts['path'] ?? ''));
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?' . $parts['query'] : '';
        if ($path === '' || strpos($path, '..') !== false) return null;

        // Always root-relative so it works from any folder depth.
        $path = '/' . ltrim($path, '/');

        $root = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
        if ($root === '') return $path . $query; // can't verify, use as-is

        $trimmed = rtrim($path, '/');
        $candidates = [
            $path                    => $path,
            $trimmed . '.php'        => $trimmed . '.php',
            $trimmed . '/index.php'  => ($trimmed === '' ? '/' : $trimmed . '/'),
            $trimmed . '/index.html' => ($trimmed === '' ? '/' : $trimmed . '/'),
        ];
        foreach ($candidates as $file => $link) {
            if (is_file($root . $file)) {
                return implode('/', array_map('rawurlencode', explode('/', $link))) . $query;
            }
        }
        return null;
    }
}

?>
<?php include_once __DIR__ . '/visitor-feedback.php'; ?>
<?php include_once __DIR__ . '/recommendations.php'; ?>
<?php if (!defined('GNC_RECOMMENDATIONS_RENDERED')): ?>
<script src="/assets/js/recommendation-engine.js?v=1" defer></script>
<?php endif; ?>
<script src="/assets/js/footer-suggestions.js?v=<?= filemtime(__DIR__.'/../assets/js/footer-suggestions.js') ?>" defer></script>
<footer class="gnc-footer">
    <div class="gnc-footer-top">
        <div class="container">
            <div class="gnc-footer-intro">
                <div>
                    <h2 class="gnc-footer-brand">Guagua National Colleges</h2>
                    <p class="gnc-footer-tagline">Fides, Scientia et. Patria</p>
                </div>
                <p class="gnc-footer-desc">
                    "Our graduates will be globally competitive citizens. They will uphold our founding values. Fides – faith in God, Scientia – search for truth &amp; knowledge, Patria – love for country."
                </p>
            </div>

            <nav class="gnc-footer-suggestions" id="gncFooterSuggestions" aria-label="Suggested pages" hidden>
                <div class="gnc-footer-suggestions-head">
                    <i class="bi bi-compass" aria-hidden="true"></i>
                    <h6>Suggested Pages</h6>
                </div>
                <div class="gnc-footer-suggestions-grid" id="gncFooterSuggestionLinks"></div>
            </nav>

            <div class="gnc-footer-grid">
                <div class="gnc-footer-contact">
                    <h6 class="gnc-footer-heading">Contact Us</h6>
                    <ul class="gnc-footer-contact-list">
                        <li>
                            <span class="gnc-footer-contact-icon" aria-hidden="true"><i class="bi bi-geo-alt-fill"></i></span>
                            <span class="gnc-footer-contact-text">
                                <a href="https://www.google.com/maps/search/?api=1&amp;query=Guagua+National+Colleges,+Pampanga" target="_blank" rel="noopener noreferrer">Rep. Eligio Lagman St, Sta. Filomena Rd, Guagua, Pampanga</a>
                            </span>
                        </li>
                        <li>
                            <span class="gnc-footer-contact-icon" aria-hidden="true"><i class="bi bi-telephone-fill"></i></span>
                            <span class="gnc-footer-contact-text"><a href="tel:0451234567">(045) 123-4567</a></span>
                        </li>
                        <li>
                            <span class="gnc-footer-contact-icon" aria-hidden="true"><i class="bi bi-envelope-fill"></i></span>
                            <span class="gnc-footer-contact-text"><a href="mailto:info@gnc.edu.ph">info@gnc.edu.ph</a></span>
                        </li>
                        <li>
                            <span class="gnc-footer-contact-icon" aria-hidden="true"><i class="bi bi-clock-fill"></i></span>
                            <span class="gnc-footer-contact-text">Monday - Friday<br>8:00AM - 5:00PM</span>
                        </li>
                    </ul>

                    <div class="gnc-footer-socials">
                        <a href="https://www.facebook.com/gnc.edu.ph" aria-label="Facebook" target="_blank" rel="noopener noreferrer"><i class="bi bi-facebook"></i></a>
                        <a href="https://www.instagram.com/gnci1918/" aria-label="Instagram" target="_blank" rel="noopener noreferrer"><i class="bi bi-instagram"></i></a>
                        <a href="https://www.linkedin.com/school/guagua-national-colleges/" aria-label="LinkedIn" target="_blank" rel="noopener noreferrer"><i class="bi bi-linkedin"></i></a>
                    </div>
                </div>

                <div class="gnc-footer-resources">
                    <h6 class="gnc-footer-heading">Student Resources</h6>
                    <ul class="gnc-footer-links">
                        <li><a href="http://library.gncipampanga.com/" target="_blank" rel="noopener noreferrer">GNCI Library <i class="bi bi-chevron-right" aria-hidden="true"></i></a></li>
                        <li><a href="/index.php#events">Calendar <i class="bi bi-chevron-right" aria-hidden="true"></i></a></li>
                        <li><a href="/coming-soon.php" target="_blank" rel="noopener noreferrer">Student Portal <i class="bi bi-chevron-right" aria-hidden="true"></i></a></li>
                        <li><a href="https://osa.gncipampanga.com/" target="_blank" rel="noopener noreferrer">OSA Student Grievance <i class="bi bi-chevron-right" aria-hidden="true"></i></a></li>
                        <li><a href="/pages/faq.php">Frequently Asked Questions <i class="bi bi-chevron-right" aria-hidden="true"></i></a></li>
                    </ul>

                    <h6 class="gnc-footer-heading gnc-footer-heading-spaced">Visitor Counter</h6>
                    <div class="gnc-visitor-stats">
                        <div class="gnc-visitor-stat">
                            <i class="bi bi-person-check-fill" aria-hidden="true"></i>
                            <div>
                                <span class="gnc-visitor-num" id="gncVisitorsToday"><?= number_format($gncVisitorStats['today']) ?></span>
                                <span class="gnc-visitor-label">Today's Visitors</span>
                            </div>
                        </div>
                        <div class="gnc-visitor-stat">
                            <i class="bi bi-people-fill" aria-hidden="true"></i>
                            <div>
                                <span class="gnc-visitor-num" id="gncVisitorsTotal"><?= number_format($gncVisitorStats['total']) ?></span>
                                <span class="gnc-visitor-label">Total Visitors</span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="gnc-footer-campus">
                    <h6 class="gnc-footer-heading">Visit Our Campus</h6>
                    <div class="gnc-footer-map">
                        <a href="https://www.google.com/maps/search/?api=1&amp;query=Guagua+National+Colleges,+Pampanga"
                        target="_blank" rel="noopener noreferrer" class="gnc-footer-map-link">
                            <i class="bi bi-geo-alt-fill" aria-hidden="true"></i> View on Google Maps
                        </a>
                        <iframe
                            title="Map showing Guagua National Colleges"
                            <?= gncPrivacyFrameSource('https://maps.google.com/maps?q=Guagua+National+Colleges,+Pampanga&t=&z=16&ie=UTF8&iwloc=&output=embed') ?>
                            allowfullscreen=""
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade">
                        </iframe>
                    </div>
                    <p class="gnc-footer-campus-note">Guagua, Pampanga <span aria-hidden="true">&middot;</span> Philippines</p>
                </div>
            </div>


        </div>
    </div>

    <div class="gnc-footer-bottom">
        <div class="container">
            <div class="gnc-footer-bottom-inner">
                <p class="gnc-footer-copy">&copy; <?= date('Y') ?> Guagua National Colleges, Inc. All rights reserved.</p>
                <nav class="gnc-footer-legal" aria-label="Legal">
                    <?php if (gncLegalPublished()): ?>
                    <a href="/pages/legal/privacy-policy.php">Privacy Policy</a>
                    <span aria-hidden="true">&bull;</span>
                    <a href="/pages/legal/terms-of-use.php">Terms of Use</a>
                    <span aria-hidden="true">&bull;</span>
                    <a href="/pages/legal/cookie-policy.php">Cookie Policy</a>
                    <?php endif; ?>
                    <button type="button" class="gnc-privacy-settings" data-privacy-action="manage">Privacy &amp; cookie settings</button>
                </nav>
                <a href="#" class="gnc-footer-totop" id="gncBackToTop" aria-label="Back to top"><i class="bi bi-arrow-up" aria-hidden="true"></i></a>
            </div>
        </div>
    </div>
</footer>
<?php include_once __DIR__ . '/privacy-controls.php'; ?>

<?php include_once __DIR__ . '/../chatbot/widget.php'; ?>

<script>
(function () {
    var btn = document.getElementById('gncBackToTop');
    if (!btn) return;
    btn.addEventListener('click', function (ev) {
        ev.preventDefault();
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
    });
})();
</script>

<script>
(function () {
    if (window.top !== window.self || new URLSearchParams(location.search).has('ce_preview')) return;
    var inFlight=false;
    function trackView() {
    if (!window.GNCPrivacy || !GNCPrivacy.allows('analytics') || inFlight) return;
    var todayEl = document.getElementById('gncVisitorsToday');
    var totalEl = document.getElementById('gncVisitorsTotal');

    // Count this page view at most once per page per 30 minutes (per browser tab session)
    var path = location.pathname;
    var pageParams = new URLSearchParams(location.search), entityParams = new URLSearchParams();
    var entityKeys = /\/(program|college-department)(?:\.php)?\/?$/.test(path) ? ['slug'] : /\/(news-article|announcement-article|event)\.php$/.test(path) ? ['id', 'slug'] : [];
    entityKeys.forEach(function (key) { if (pageParams.has(key)) entityParams.set(key, pageParams.get(key)); });
    if (entityParams.toString()) path += '?' + entityParams.toString();
    var key  = 'gnc_pv:' + path;
    var now  = Date.now();
    try {
        var last = parseInt(sessionStorage.getItem(key) || '0', 10);
        if (last && now - last < 30 * 60 * 1000) return;
    } catch (e) {}

    var body = new URLSearchParams();
    body.set('path', path);
    body.set('title', document.title || '');

    inFlight=true;
    fetch('/api/track-visit.php', {
        method: 'POST',
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'fetch' },
        body: body
    })
    .then(function (r) { return r.ok ? r.json() : null; })
    .then(function (d) {
        if (!d || !d.ok) return;
        try { sessionStorage.setItem(key, String(now)); } catch (e) {}
        if (todayEl) todayEl.textContent = Number(d.today).toLocaleString('en-US');
        if (totalEl) totalEl.textContent = Number(d.total).toLocaleString('en-US');
    })
    .catch(function () {}).finally(function(){inFlight=false;});
    }
    window.addEventListener('gnc:privacyready',trackView);
    window.addEventListener('gnc:privacychange',trackView);
    trackView();
})();
</script>
