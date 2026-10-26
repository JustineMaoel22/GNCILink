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

// "Popular Pages": the most visited pages from page_visits (page-analytics.php).
// The homepage and the page being viewed are left out. Empty list = section hidden.
$gncPopularPages = [];
try {
    require_once __DIR__ . '/page-analytics.php';
    $gncExclude  = ['/'];
    $gncCurrent  = gncPageNormalizePath((string) ($_SERVER['REQUEST_URI'] ?? ''));
    if ($gncCurrent !== null) $gncExclude[] = $gncCurrent;
    // Ask for extra rows so pages dropped for dead links can be replaced.
    foreach (gncPopularPages(getDB(), 12, $gncExclude) as $p) {
        if (count($gncPopularPages) >= 4) break;
        if ((int) $p['visit_count'] <= 0 || $p['page_title'] === '') continue;
        $href = gncResolvePopularUrl((string) $p['page_url']);
        if ($href === null) continue;
        $p['page_url'] = $href;
        $gncPopularPages[] = $p;
    }
} catch (Throwable $e) {
    // Footer still renders without the section if analytics is unavailable.
}
?>
<?php include_once __DIR__ . '/recommendations.php'; ?>
<footer class="gnc-footer">
    <div class="gnc-footer-banner">
        <div class="gnc-footer-banner-overlay"></div>
        <div class="gnc-footer-seal-wrap">
            <img src="/assets/images/logos/gnc-logo-v1.svg" alt="Guagua National Colleges Seal" class="gnc-footer-seal">
        </div>
    </div>

    <div class="gnc-footer-top">
        <div class="container">
            <?php if ($gncPopularPages): ?>
            <nav class="gnc-footer-popular" aria-label="Popular pages">
                <div class="gnc-footer-popular-head">
                    <i class="bi bi-fire" aria-hidden="true"></i>
                    <h6>Popular Pages</h6>
                </div>
                <div class="gnc-footer-popular-grid" data-count="<?= count($gncPopularPages) ?>">
                    <?php foreach ($gncPopularPages as $i => $gncPage): ?>
                    <a href="<?= htmlspecialchars($gncPage['page_url']) ?>" class="gnc-popular-link">
                        <span class="gnc-popular-rank"><?= $i + 1 ?></span>
                        <span class="gnc-popular-title"><?= htmlspecialchars($gncPage['page_title']) ?></span>
                        <i class="bi bi-arrow-right" aria-hidden="true"></i>
                    </a>
                    <?php endforeach; ?>
                </div>
            </nav>
            <?php endif; ?>

            <div class="row gy-5">
                <div class="col-lg-3 col-md-6">
                    <h5 class="gnc-footer-brand">Guagua National Colleges</h5>
                    <p class="gnc-footer-tagline">Fides, Scientia et. Patria</p>
                    <p class="gnc-footer-desc">
                        "Our graduates will be globally competitive citizens. They will uphold our founding values. Fides – faith in God, Scientia – search for truth &amp; knowledge, Patria – love for country."
                    </p>

                    <h6 class="gnc-footer-heading">Contact Us</h6>
                    <ul class="gnc-footer-contact-list">
                        <li>
                            <i class="bi bi-geo-alt-fill"></i>
                            <span>Rep. Eligio Lagman St, Sta. Filomena Rd, Guagua, Pampanga</span>
                        </li>
                        <li>
                            <i class="bi bi-telephone-fill"></i>
                            <span>(045) 123-4567</span>
                        </li>
                        <li>
                            <i class="bi bi-envelope-fill"></i>
                            <span>info@gnc.edu.ph</span>
                        </li>
                        <li>
                            <i class="bi bi-clock-fill"></i>
                            <span>Monday - Friday<br>8:00AM - 5:00PM</span>
                        </li>
                    </ul>

                    <div class="gnc-footer-socials">
                        <a href="https://www.facebook.com/gnc.edu.ph" aria-label="Facebook" target="_blank"><i class="bi bi-facebook"></i></a>
                        <a href="https://www.instagram.com/gnci1918/" aria-label="Instagram" target="_blank"><i class="bi bi-instagram"></i></a>
                        <a href="https://www.linkedin.com/school/guagua-national-colleges/" aria-label="LinkedIn" target="_blank"><i class="bi bi-linkedin"></i></a>
                    </div>
                </div>

                <div class="col-lg-2 col-md-6">
                    <h6 class="gnc-footer-heading">Visitor Counter</h6>
                    <div class="gnc-visitor-stats">
                        <div class="gnc-visitor-stat">
                            <i class="bi bi-person-check-fill"></i>
                            <div>
                                <span class="gnc-visitor-num" id="gncVisitorsToday"><?= number_format($gncVisitorStats['today']) ?></span>
                                <span class="gnc-visitor-label">Today's Visitors</span>
                            </div>
                        </div>
                        <div class="gnc-visitor-stat">
                            <i class="bi bi-people-fill"></i>
                            <div>
                                <span class="gnc-visitor-num" id="gncVisitorsTotal"><?= number_format($gncVisitorStats['total']) ?></span>
                                <span class="gnc-visitor-label">Total Visitors</span>
                            </div>
                        </div>
                    </div>

                    <h6 class="gnc-footer-heading gnc-footer-heading-spaced">Student Resources</h6>
                    <ul class="gnc-footer-links">
                        <li><a href="http://library.gncipampanga.com/" target="_blank" rel="noopener noreferrer">Libraries <i class="bi bi-chevron-right"></i></a></li>
                        <li><a href="/index.php#events">Calendar <i class="bi bi-chevron-right"></i></a></li>
                        <li><a href="https://login.microsoftonline.com/" target="_blank" rel="noopener noreferrer">Access Student Email <i class="bi bi-chevron-right" aria-hidden="true"></i></a></li>
                    </ul>
                </div>

                <div class="col-lg-7">
                    <div class="gnc-footer-map">
                        <a href="https://www.google.com/maps/search/?api=1&query=Guagua+National+Colleges,+Pampanga"
                        target="_blank" rel="noopener" class="gnc-footer-map-link">
                            View on Google Maps
                        </a>
                        <iframe
                            src="https://maps.google.com/maps?q=Guagua+National+Colleges,+Pampanga&t=&z=16&ie=UTF8&iwloc=&output=embed"
                            width="100%"
                            height="100%"
                            style="border:0; min-height: 300px;"
                            allowfullscreen=""
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade">
                        </iframe>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="gnc-footer-bottom">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-between align-items-center">
                <p class="mb-0">&copy; 2026 Guagua National Colleges, Inc. All rights reserved.</p>
                <p class="mb-0">
                    <a href="/coming-soon.php">Privacy Policy</a> &nbsp;|&nbsp; <a href="/coming-soon.php">Terms of Use</a>
                </p>
            </div>
        </div>
    </div>
</footer>

<?php include_once __DIR__ . '/../chatbot/widget.php'; ?>

<script>
(function () {
    if (window.top !== window.self || new URLSearchParams(location.search).has('ce_preview')) return;
    var todayEl = document.getElementById('gncVisitorsToday');
    var totalEl = document.getElementById('gncVisitorsTotal');

    // Count this page view at most once per page per 30 minutes (per browser tab session)
    var path = location.pathname;
    var pageParams = new URLSearchParams(location.search), entityParams = new URLSearchParams();
    var entityKeys = /\/(program|college-department)\.php$/.test(path) ? ['slug'] : /\/(news-article|announcement-article|event)\.php$/.test(path) ? ['id', 'slug'] : [];
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
    .catch(function () {});
})();
</script>
