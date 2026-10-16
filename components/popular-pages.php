<?php
/**
 * Popular Pages ("Suggested Pages") section.
 *
 * Usage (before the footer include):
 *     <?php include __DIR__ . '/components/popular-pages.php'; ?>
 *
 * Optional settings, set before the include:
 *     $popularPagesLimit  = 4;          // how many cards
 *     $popularPagesMin    = 3;          // hide the section until at least this many pages qualify
 *     $popularPagesLayout = 'container' // 'container' = Bootstrap .container (homepage)
 *                                       // 'narrow'    = 1200px wrapper (department pages)
 *
 * Data comes from the page_visits table (see page-analytics.php). The homepage,
 * the page being viewed, and pages that no longer exist are never suggested.
 */
$popularPagesLimit  = $popularPagesLimit  ?? 4;
$popularPagesMin    = $popularPagesMin    ?? 3;
$popularPagesLayout = $popularPagesLayout ?? 'container';

$gncPopular = [];
try {
    if (!function_exists('getDB')) {
        require_once __DIR__ . '/../config/config.php';
    }
    require_once __DIR__ . '/page-analytics.php';
    $gncPopular = gncSuggestedPages(
        getDB(),
        (int) $popularPagesLimit,
        gncPageNormalizePath($_SERVER['REQUEST_URI'] ?? '/')
    );
} catch (Throwable $e) {
    $gncPopular = [];
}

if (count($gncPopular) >= (int) $popularPagesMin):
?>
<?php if (!defined('GNC_POPULAR_PAGES_CSS')): define('GNC_POPULAR_PAGES_CSS', true); ?>
<style>
.gnc-popular {
    background: #fff;
    border-top: 1px solid #e6e1d8;
    padding: 4rem 0;
    font-family: 'Inter', sans-serif;
}
.gnc-popular-inner { max-width: 1200px; margin: 0 auto; padding: 0 15px; }
.gnc-popular-eyebrow {
    display: block;
    color: #d3a63a;
    font-weight: 700;
    font-size: .8rem;
    letter-spacing: 2px;
    text-transform: uppercase;
    margin-bottom: .5rem;
}
.gnc-popular-title {
    font-family: 'Noto Serif', serif;
    color: #1F5E2C;
    font-weight: 800;
    font-size: clamp(24px, 3vw, 32px);
    line-height: 1.15;
    margin: 0 0 .5rem;
}
.gnc-popular-sub { color: #6b6b66; font-size: .95rem; margin: 0 0 2rem; max-width: 60ch; }
.gnc-popular-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
}
.gnc-popular-card {
    display: flex;
    align-items: center;
    gap: 14px;
    min-width: 0;
    padding: 18px;
    background: #fff;
    border: 1px solid #e6e1d8;
    border-radius: 14px;
    box-shadow: 0 4px 18px rgba(9, 64, 36, 0.08);
    text-decoration: none;
    transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
}
.gnc-popular-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 10px 26px rgba(9, 64, 36, 0.14);
    border-color: #d3a63a;
}
.gnc-popular-icon {
    flex-shrink: 0;
    width: 44px;
    height: 44px;
    border-radius: 50%;
    background: #eaf5ee;
    color: #1F5E2C;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.1rem;
}
.gnc-popular-text { flex: 1; min-width: 0; }
.gnc-popular-name {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    font-family: 'Noto Serif', serif;
    font-weight: 700;
    font-size: .95rem;
    line-height: 1.3;
    color: #1F5E2C;
}
.gnc-popular-section {
    display: block;
    margin-top: 2px;
    font-size: .72rem;
    color: #6b6b66;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.gnc-popular-arrow { flex-shrink: 0; color: #d3a63a; font-size: .85rem; }

@media (max-width: 991.98px) {
    .gnc-popular-grid { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 575.98px) {
    .gnc-popular { padding: 2.75rem 0; }
    .gnc-popular-grid { grid-template-columns: 1fr; gap: 12px; }
}
</style>
<?php endif; ?>

<section class="gnc-popular" aria-labelledby="gncPopularTitle">
    <div class="<?= $popularPagesLayout === 'narrow' ? 'gnc-popular-inner' : 'container' ?>">
        <span class="gnc-popular-eyebrow">Suggested for You</span>
        <h2 class="gnc-popular-title" id="gncPopularTitle">Popular Pages</h2>
        <p class="gnc-popular-sub">Pages our visitors are exploring the most right now.</p>

        <div class="gnc-popular-grid">
            <?php foreach ($gncPopular as $pg): ?>
            <a class="gnc-popular-card" href="<?= htmlspecialchars($pg['url'], ENT_QUOTES) ?>">
                <span class="gnc-popular-icon" aria-hidden="true"><i class="bi bi-fire"></i></span>
                <span class="gnc-popular-text">
                    <span class="gnc-popular-name"><?= htmlspecialchars($pg['title']) ?></span>
                    <?php if ($pg['section'] !== ''): ?>
                    <span class="gnc-popular-section"><?= htmlspecialchars($pg['section']) ?></span>
                    <?php endif; ?>
                </span>
                <i class="bi bi-arrow-right gnc-popular-arrow" aria-hidden="true"></i>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>