<?php
/**
 * GNC Public Site - Individual Announcement
 *
 * Opened from a "Read more" link:   /announcement-article.php?slug=the-announcement-slug
 * (an older/fallback link also works: /announcement-article.php?id=12)
 *
 * Place this file in the SITE ROOT (same folder as index.php).
 * If it lives somewhere else, fix the two require_once paths below.
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/admin/admin-functions.php';

if (function_exists('initSession')) { initSession(); }

/* ───────────────────────── Easy-to-change settings ───────────────────────── */
const NEWS_LIST_URL    = '/pages/news-and-announcement.php'; // fallback for "Back" when the visitor didn't come from the site
const ANN_ARTICLE_URL = '/announcement-article.php';     // this file
// Which menu item the shared navbar should highlight (same idea as 'about' on the History page).
// If "News & Events" doesn't light up, change this to the value your navbar.php expects.
$activeSection = 'news';

/* ───────────────────────── Helpers ───────────────────────── */
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/**
 * The article body is HTML written in the admin's rich-text editor (Quill).
 * Only a safe list of tags/attributes is let through, so a bad paste can
 * never run a script on the public site.
 */
function announcementSanitizeHtml(string $html): string {
    if (trim($html) === '') return '';
    // Fallback if the server has no DOM extension: strip to a basic safe tag list, no attributes except link/image URLs.
    if (!class_exists('DOMDocument')) {
        $html = preg_replace('#<(script|style|iframe|object|embed|form|svg)\b.*?</\1>#is', '', $html);
        $html = strip_tags($html, '<p><br><strong><b><em><i><u><s><a><ul><ol><li><h1><h2><h3><h4><blockquote><img><hr>');
        $html = preg_replace_callback('#<(a|img)\b([^>]*)>#i', function ($m) {
            $attr = strtolower($m[1]) === 'a' ? 'href' : 'src';
            if (preg_match('#\b' . $attr . '\s*=\s*("|\')(.*?)\1#is', $m[2], $u)
                && preg_match('#^(https?://|/(?!/))#i', trim($u[2]))) {
                return '<' . strtolower($m[1]) . ' ' . $attr . '="' . htmlspecialchars(trim($u[2]), ENT_QUOTES) . '">';
            }
            return '<' . strtolower($m[1]) . '>';
        }, $html);
        return preg_replace('#<(?!/?(a|img|p|br|strong|b|em|i|u|s|ul|ol|li|h[1-4]|blockquote|hr)\b)[^>]*>#i', '', preg_replace('#<(p|strong|b|em|i|u|s|ul|ol|li|h[1-4]|blockquote)\b[^>]*>#i', '<$1>', $html));
    }
    $allowedTags = ['p','br','strong','b','em','i','u','s','strike','a','ul','ol','li','h1','h2','h3','h4','h5','h6',
                    'blockquote','img','span','pre','code','hr','sub','sup','table','thead','tbody','tr','th','td','figure','figcaption','div'];
    $dropWithContent = ['script','style','iframe','object','embed','form','input','button','textarea','select','link','meta','svg','math','noscript'];

    $prev = libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->loadHTML('<?xml encoding="utf-8"?><div id="gnc-root">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($prev);

    $safeUrl = function (string $u, bool $img = false): bool {
        $u = trim($u);
        if ($u === '') return false;
        if (preg_match('#^(https?:)?//#i', $u) || preg_match('#^/(?!/)#', $u)) return true;
        if (!$img && preg_match('#^(mailto:|tel:)#i', $u)) return true;
        return !preg_match('#^[a-z][a-z0-9+.-]*:#i', $u); // plain relative path
    };

    $clean = function (DOMNode $node) use (&$clean, $allowedTags, $dropWithContent, $safeUrl) {
        for ($i = $node->childNodes->length - 1; $i >= 0; $i--) {
            $child = $node->childNodes->item($i);
            if ($child->nodeType === XML_COMMENT_NODE) { $node->removeChild($child); continue; }
            if ($child->nodeType !== XML_ELEMENT_NODE) continue;
            $tag = strtolower($child->nodeName);

            if (in_array($tag, $dropWithContent, true)) { $node->removeChild($child); continue; }
            $clean($child);
            if (!in_array($tag, $allowedTags, true)) {      // unknown tag: keep its text, drop the tag
                while ($child->firstChild) $node->insertBefore($child->firstChild, $child);
                $node->removeChild($child);
                continue;
            }
            // attributes
            for ($a = $child->attributes->length - 1; $a >= 0; $a--) {
                $attr = $child->attributes->item($a);
                $name = strtolower($attr->nodeName);
                $val  = $attr->nodeValue;
                $ok = false;
                if ($tag === 'a' && $name === 'href')                      $ok = $safeUrl($val);
                elseif ($tag === 'img' && $name === 'src')                 $ok = $safeUrl($val, true);
                elseif ($tag === 'img' && in_array($name, ['alt','width','height'], true)) $ok = true;
                elseif ($name === 'class')                                 $ok = (bool)preg_match('/^(ql-[a-z0-9 _-]+)$/i', trim($val));
                elseif ($tag === 'li' && $name === 'data-list')            $ok = in_array($val, ['bullet','ordered','checked','unchecked'], true);
                elseif (in_array($tag, ['td','th'], true) && in_array($name, ['colspan','rowspan'], true)) $ok = ctype_digit($val);
                if (!$ok) $child->removeAttribute($attr->nodeName);
            }
            if ($tag === 'a') {
                $child->setAttribute('rel', 'noopener noreferrer');
                if (preg_match('#^(https?:)?//#i', $child->getAttribute('href'))) $child->setAttribute('target', '_blank');
            }
            if ($tag === 'img') $child->setAttribute('loading', 'lazy');
        }
    };
    $root = $doc->getElementById('gnc-root');
    if (!$root) return '';
    $clean($root);
    $out = '';
    foreach ($root->childNodes as $c) $out .= $doc->saveHTML($c);
    return $out;
}

function announcementBaseUrl(): string {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (!preg_match('/^[a-z0-9.\-]+(:\d+)?$/i', $host)) return '';
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    return ($https ? 'https://' : 'http://') . $host;
}

/**
 * Dates are stored in UTC (database time_zone = +00:00).
 * Show them in local (Philippine) time instead.
 */
function announcementLocalTime($raw, string $format): string {
    try {
        $d = new DateTime((string)$raw, new DateTimeZone('UTC'));
        $d->setTimezone(new DateTimeZone('Asia/Manila'));
        return $d->format($format);
    } catch (Exception $e) { return ''; }
}

/* ───────────────────────── Load the announcement ───────────────────────── */
const ANN_SELECT = "
    SELECT a.*, m.file_path AS image_path, u.first_name, u.last_name,
           c.category_name, d.dept_name
    FROM announcements a
    LEFT JOIN media_library m ON a.featured_image = m.media_id
    LEFT JOIN users u         ON a.user_id = u.user_id
    LEFT JOIN categories c    ON a.category_id = c.category_id
    LEFT JOIN departments d   ON a.dept_id = d.dept_id
";

$article   = null;
$isPreview = false;
$slug = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';
$id   = isset($_GET['id'])   ? (int)$_GET['id'] : 0;

try {
    if ($slug !== '') {
        $stmt = getDB()->prepare(ANN_SELECT . " WHERE a.slug = ? AND a.status = 'published' LIMIT 1");
        $stmt->execute([$slug]);
        $article = $stmt->fetch() ?: null;
    } elseif ($id > 0) {
        $stmt = getDB()->prepare(ANN_SELECT . " WHERE a.announcement_id = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) {
            $canPreview = function_exists('isLoggedIn') && isLoggedIn()
                && (hasPermission('edit_announcements') || hasPermission('manage_announcements') || hasPermission('edit_own_announcements'));
            if (($row['status'] ?? '') === 'published') {
                $article = $row;
            } elseif ($canPreview) {          // admins can look at drafts before publishing
                $article = $row;
                $isPreview = true;
            }
        }
    }
} catch (Throwable $ex) { error_log('Announcement load error: ' . $ex->getMessage()); }

if (!$article) { http_response_code(404); }

$gallery = $related = [];
if ($article) {
    // Extra photos added in the admin (needs the announcement_images table; skipped quietly if it is not there yet)
    try {
        $stmt = getDB()->prepare("
            SELECT m.file_path FROM announcement_images ai
            JOIN media_library m ON ai.media_id = m.media_id
            WHERE ai.announcement_id = ? ORDER BY ai.display_order, ai.image_id
        ");
        $stmt->execute([(int)$article['announcement_id']]);
        $featured = $article['image_path'] ?? '';
        foreach ($stmt->fetchAll() as $g) {
            if (!empty($g['file_path']) && $g['file_path'] !== $featured) $gallery[] = $g['file_path'];
        }
    } catch (Throwable $ex) { /* table not created yet */ }

    // "More Announcements": latest 5, mixing website announcements and Facebook posts, newest first
    try {
        $stmt = getDB()->prepare("
            SELECT a.announcement_id, a.title, a.slug, COALESCE(a.published_at, a.created_at) AS sort_date, m.file_path AS image_path
            FROM announcements a
            LEFT JOIN media_library m ON a.featured_image = m.media_id
            WHERE a.status = 'published' AND a.announcement_id <> ?
            ORDER BY sort_date DESC
            LIMIT 5
        ");
        $stmt->execute([(int)$article['announcement_id']]);
        foreach ($stmt->fetchAll() as $row) {
            $related[] = [
                'title'    => $row['title'],
                'date'     => $row['sort_date'],
                'image'    => $row['image_path'],
                'url'      => ANN_ARTICLE_URL . (!empty($row['slug']) ? '?slug=' . rawurlencode($row['slug']) : '?id=' . (int)$row['announcement_id']),
                'external' => false,
            ];
        }
    } catch (Throwable $ex) { error_log('Related announcements error: ' . $ex->getMessage()); }

    try {
        $fb = getDB()->query("
            SELECT f.fb_post_id, f.message, f.permalink_url, f.created_time, m.file_path AS image_path
            FROM fb_posts_cache f
            LEFT JOIN media_library m ON f.image_media_id = m.media_id
            ORDER BY f.created_time DESC
            LIMIT 5
        ")->fetchAll();
        foreach ($fb as $row) {
            $msg   = trim(preg_replace('/\s+/', ' ', strip_tags((string)$row['message'])));
            $first = trim(strtok((string)preg_replace('/\s*\R\s*/u', "\n", trim(strip_tags((string)$row['message']))), "\n"));
            $label = $first !== '' ? $first : $msg;
            if (mb_strlen($label) > 90) $label = mb_substr($label, 0, 87) . '…';
            $related[] = [
                'title'    => $label !== '' ? $label : 'Facebook post',
                'date'     => $row['created_time'],
                'image'    => $row['image_path'],
                'url'      => $row['permalink_url'] ?: 'https://www.facebook.com/' . rawurlencode($row['fb_post_id']),
                'external' => true,
            ];
        }
    } catch (Throwable $ex) { error_log('Facebook posts error: ' . $ex->getMessage()); }

    usort($related, function ($x, $y) { return strtotime($y['date']) <=> strtotime($x['date']); });
    $related = array_slice($related, 0, 5);
}

/* Values used in the page */
$title     = $article['title'] ?? 'Announcement not found';
$bodyHtml  = $article ? announcementSanitizeHtml((string)$article['content']) : '';
$dateRaw   = $article['published_at'] ?? $article['created_at'] ?? null;
$dateText  = $dateRaw ? announcementLocalTime($dateRaw, 'F j, Y') : '';
$category  = trim((string)($article['category_name'] ?? ''));
if ($category === '' && !empty($article['dept_name'])) $category = $article['dept_name'];
if ($category === '' && !empty($article['program']) && $article['program'] !== 'ALL') $category = $article['program'];
if ($category === '') $category = 'Announcement';
$byline    = trim(($article['first_name'] ?? '') . ' ' . ($article['last_name'] ?? ''));
$plain     = trim(preg_replace('/\s+/', ' ', strip_tags($bodyHtml)));
$words     = $plain === '' ? 0 : count(preg_split('/\s+/', $plain));
$desc      = mb_strlen($plain) > 160 ? mb_substr($plain, 0, 157) . '…' : $plain;
$base      = announcementBaseUrl();
$selfUrl   = $article ? $base . ANN_ARTICLE_URL . '?slug=' . rawurlencode($article['slug'] ?: '') : '';
if ($article && empty($article['slug'])) $selfUrl = $base . ANN_ARTICLE_URL . '?id=' . (int)$article['announcement_id'];
$ogImage   = (!empty($article['image_path']) && $base) ? $base . $article['image_path'] : '';
$bannerTitle = 'Announcements';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($title) ?> — Guagua National Colleges</title>
<meta name="description" content="<?= e($desc) ?>">
<?php if ($article && !$isPreview): ?>
<meta property="og:type" content="article">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($desc) ?>">
<?php if ($selfUrl): ?><meta property="og:url" content="<?= e($selfUrl) ?>"><?php endif; ?>
<?php if ($ogImage): ?><meta property="og:image" content="<?= e($ogImage) ?>"><?php endif; ?>
<?php else: ?>
<meta name="robots" content="noindex">
<?php endif; ?>
<link href="/assets/css/bootstrap.min.css" rel="stylesheet">
<link href="/assets/css/base-style.css" rel="stylesheet">
<link href="/assets/css/navbar-style.css" rel="stylesheet">
<link href="/assets/css/footer-style.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
<!-- news-announcement-article.css holds the header (banner, curve, logo) and all article styles -->
<link href="/assets/css/article.css" rel="stylesheet">
<link href="/assets/css/skeleton-style.css" rel="stylesheet">
<link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>

<?php if ($isPreview): ?>
<div class="preview-bar"><i class="bi bi-eye"></i> Preview — this announcement is not published yet. Visitors cannot see it.</div>
<?php endif; ?>

<?php if (file_exists(__DIR__ . '/components/navbar.php')) { include_once __DIR__ . '/components/navbar.php'; } ?>

<main>
    <!-- Hero banner: copied from the History of GNC page -->
    <section class="history-hero">
        <div class="hero-bg" aria-hidden="true"></div>
        <div class="hero-dust" aria-hidden="true"><i></i><i></i><i></i><i></i><i></i><i></i><i></i><i></i></div>
        <div class="hero-content">
            <h1 class="hero-title"><?= e($bannerTitle) ?></h1>
            <span class="hero-rule" aria-hidden="true"></span>
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
</main>


<section class="article-wrap">
<?php if (!$article): ?>
    <div class="notfound">
        <i class="bi bi-megaphone"></i>
        <h1 class="headline headline--tight">We couldn't find that announcement</h1>
        <p class="notfound-text">It may have been moved, hidden or removed.</p>
        <a class="btn-soft btn-green" data-back href="<?= e(NEWS_LIST_URL) ?>"><i class="bi bi-arrow-left"></i> Back</a>
    </div>
<?php else: ?>
    <article>
        <h1 class="headline"><?= e($title) ?></h1>

        <div class="meta">
            <?php if ($category): ?><span class="pill"><?= e($category) ?></span><?php endif; ?>
            <?php if ($dateText): ?><span><i class="bi bi-calendar3"></i> <time datetime="<?= e(announcementLocalTime($dateRaw, 'Y-m-d')) ?>"><?= e($dateText) ?></time></span><?php endif; ?>
            <?php if ($byline !== ''): ?><span><i class="bi bi-person"></i> <?= e($byline) ?></span><?php endif; ?>
            <?php if ($dateRaw): ?><span><i class="bi bi-clock"></i> <time datetime="<?= e(announcementLocalTime($dateRaw, 'c')) ?>"><?= e(announcementLocalTime($dateRaw, 'g:i A')) ?></time></span><?php endif; ?>
        </div>

        <?php if (!empty($article['image_path'])): ?>
        <figure class="cover"><img src="<?= e($article['image_path']) ?>" alt="<?= e($title) ?>" data-lightbox></figure>
        <?php endif; ?>

        <div class="prose"><?= $bodyHtml ?></div>

        <?php if ($gallery): ?>
        <h2 class="gallery-title">Photo Gallery</h2>
        <div class="gallery">
            <?php foreach ($gallery as $i => $src): ?>
            <button type="button" aria-label="Open photo <?= $i + 1 ?> of <?= count($gallery) ?>"><img src="<?= e($src) ?>" alt="" loading="lazy" data-lightbox></button>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($selfUrl && !$isPreview): ?>
        <div class="share">
            <strong>Share:</strong>
            <a class="btn-soft" target="_blank" rel="noopener" href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode($selfUrl) ?>"><i class="bi bi-facebook"></i> Facebook</a>
            <button type="button" class="btn-soft" id="copyLink" data-url="<?= e($selfUrl) ?>"><i class="bi bi-link-45deg"></i> <span>Copy link</span></button>
        </div>
        <?php endif; ?>

        <div class="back"><a class="btn-soft btn-green" data-back href="<?= e(NEWS_LIST_URL) ?>"><i class="bi bi-arrow-left"></i> Back</a></div>
    </article>

    <?php if ($related): ?>
    <h2 class="related-title">More Announcements</h2>
    <?php $relStatic = count($related) < 3; ?>
    <div class="related<?= $relStatic ? ' is-static' : '' ?>" style="--rel-time: <?= max(20, count($related) * 7) ?>s">
        <div class="related-track">
        <?php for ($pass = 0; $pass < ($relStatic ? 1 : 2); $pass++): ?>
            <div class="related-group"<?= $pass === 1 ? ' aria-hidden="true"' : '' ?>>
            <?php foreach ($related as $r):
                $rDate = announcementLocalTime($r['date'], 'M j, Y'); ?>
                <a class="rel-card" href="<?= e($r['url']) ?>"<?= $r['external'] ? ' target="_blank" rel="noopener noreferrer"' : '' ?><?= $pass === 1 ? ' tabindex="-1"' : '' ?>>
                    <?php if (!empty($r['image'])): ?>
                        <div class="rel-img" style="background-image:url('<?= e(str_replace("'", '%27', $r['image'])) ?>')" role="img" aria-label=""></div>
                    <?php else: ?>
                        <div class="rel-img"><i class="bi bi-image"></i></div>
                    <?php endif; ?>
                    <div class="rel-body"><small><?= e($rDate) ?><?= $r['external'] ? ' · <i class="bi bi-facebook"></i> Facebook' : '' ?></small><h3><?= e($r['title']) ?></h3></div>
                </a>
            <?php endforeach; ?>
            </div>
        <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>
</section>

<?php if (file_exists(__DIR__ . '/components/footer.php')) { include_once __DIR__ . '/components/footer.php'; } ?>
<?php if (!empty($_SERVER['DOCUMENT_ROOT']) && file_exists($_SERVER['DOCUMENT_ROOT'] . '/chatbot/widget.php')) { include_once $_SERVER['DOCUMENT_ROOT'] . '/chatbot/widget.php'; } ?>

<div class="lightbox" id="lightbox" role="dialog" aria-modal="true" aria-label="Photo viewer">
    <button type="button" class="lb-btn lb-close" aria-label="Close"><i class="bi bi-x-lg"></i></button>
    <button type="button" class="lb-btn lb-prev" aria-label="Previous photo"><i class="bi bi-chevron-left"></i></button>
    <img src="" alt="">
    <button type="button" class="lb-btn lb-next" aria-label="Next photo"><i class="bi bi-chevron-right"></i></button>
</div>

<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous"></script>
<script>
(function () {
    // "Back": return to the page the visitor came from (list, filters and page number stay as they were)
    document.querySelectorAll('a[data-back]').forEach(function (link) {
        link.addEventListener('click', function (e) {
            try {
                if (document.referrer && new URL(document.referrer).origin === location.origin && history.length > 1) {
                    e.preventDefault(); history.back();
                }
            } catch (err) {}
        });
    });

    // Copy link
    var copyBtn = document.getElementById('copyLink');
    if (copyBtn) copyBtn.addEventListener('click', function () {
        var url = copyBtn.dataset.url, label = copyBtn.querySelector('span');
        var done = function () { label.textContent = 'Copied!'; setTimeout(function () { label.textContent = 'Copy link'; }, 2000); };
        if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(url).then(done); }
        else { var t = document.createElement('input'); t.value = url; document.body.appendChild(t); t.select(); try { document.execCommand('copy'); done(); } catch (e) {} t.remove(); }
    });

    // Photo viewer for the cover, in-text pictures and gallery
    var imgs = Array.prototype.slice.call(document.querySelectorAll('[data-lightbox], .prose img'));
    var lb = document.getElementById('lightbox'), lbImg = lb.querySelector('img'), cur = 0;
    function show(i) { cur = (i + imgs.length) % imgs.length; lbImg.src = imgs[cur].currentSrc || imgs[cur].src; lbImg.alt = imgs[cur].alt || ''; }
    function openLb(i) { show(i); lb.classList.add('open'); document.body.style.overflow = 'hidden'; lb.querySelector('.lb-close').focus(); }
    function closeLb() { lb.classList.remove('open'); document.body.style.overflow = ''; lbImg.src = ''; }
    imgs.forEach(function (im, i) { im.addEventListener('click', function () { openLb(i); }); });
    lb.querySelector('.lb-close').addEventListener('click', closeLb);
    lb.querySelector('.lb-prev').addEventListener('click', function () { show(cur - 1); });
    lb.querySelector('.lb-next').addEventListener('click', function () { show(cur + 1); });
    lb.addEventListener('click', function (e) { if (e.target === lb) closeLb(); });
    if (imgs.length < 2) { lb.querySelector('.lb-prev').style.display = lb.querySelector('.lb-next').style.display = 'none'; }
    document.addEventListener('keydown', function (e) {
        if (!lb.classList.contains('open')) return;
        if (e.key === 'Escape') closeLb(); else if (e.key === 'ArrowLeft') show(cur - 1); else if (e.key === 'ArrowRight') show(cur + 1);
    });
    // Swipe on touch screens
    var sx = null;
    lb.addEventListener('touchstart', function (e) { sx = e.touches[0].clientX; }, { passive: true });
    lb.addEventListener('touchend', function (e) {
        if (sx === null) return; var dx = e.changedTouches[0].clientX - sx; sx = null;
        if (Math.abs(dx) > 50) show(cur + (dx < 0 ? 1 : -1));
    });
})();
</script>
</body>
</html>