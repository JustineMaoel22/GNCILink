<?php
require_once __DIR__ . '/config/config.php';

// ---------------------------------------------------------------
// Fetch the latest published admin announcements
// ---------------------------------------------------------------
try {
    $db = getDB();
    $stmt = $db->query("
        SELECT a.announcement_id, a.title, a.slug, a.content, a.published_at, a.created_at,
            c.category_name, m.file_path as image_path
        FROM announcements a
        LEFT JOIN categories c ON a.category_id = c.category_id
        LEFT JOIN media_library m ON a.featured_image = m.media_id
        WHERE a.status = 'published'
        ORDER BY COALESCE(a.published_at, a.created_at) DESC
        LIMIT 6
    ");
    $dbAnnouncements = $stmt->fetchAll();
} catch (Exception $e) {
    $dbAnnouncements = [];
}

// Normalize DB rows into the shared feed shape
$publicAnnouncements = [];
foreach ($dbAnnouncements as $ann) {
    $excerpt = strip_tags($ann['content']);
    $excerpt = mb_strlen($excerpt) > 110 ? mb_substr($excerpt, 0, 110) . '…' : $excerpt;
    $publicAnnouncements[] = [
        'source'     => 'admin',
        'title'      => $ann['title'],
        'excerpt'    => $excerpt,
        'image_path' => $ann['image_path'],
        'date'       => $ann['published_at'] ?? $ann['created_at'],
        'link'       => 'announcement.php?slug=' . urlencode($ann['slug']),
    ];
}

// ---------------------------------------------------------------
// Fetch the latest Facebook Page posts (cached to avoid rate limits)
// ---------------------------------------------------------------
function getFacebookPosts(): array {
    $pageId      = '403969556396264';      // define these in config.php
    $accessToken = 'EAAjxjZAhVjfsBR8Fe3JZBECx60dvOxCco6EroVvHAiwmDXEKwFTZA3wlzM1CObBjx1bf3ZCtC4t6r48UjU9zYtGWGFk2Ff79lGbDqGBAZBlZAWxmyMDT4IrtdeA8YqghPZAKguoZAFDcuAZBt4j4NZAOZCFGe5E6jtxV1loT2Elr2IhOfo5k7oyuOswV8RPuvam';
    $cacheFile   = __DIR__ . '/cache/fb_posts_cache.json';
    $cacheTtl    = 600; // 10 minutes

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTtl)) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if ($cached !== null) return $cached;
    }

    $url = "https://graph.facebook.com/v19.0/{$pageId}/posts"
         . "?fields=message,created_time,permalink_url,full_picture&limit=6"
         . "&access_token={$accessToken}";

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $raw = curl_exec($ch);
    $curlErr = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        error_log("Facebook fetch failed: $curlErr");
        return file_exists($cacheFile) ? (json_decode(file_get_contents($cacheFile), true) ?? []) : [];
    }

    $response = json_decode($raw, true);

    if (isset($response['error'])) {
        error_log("Facebook API error: " . $response['error']['message']);
        return file_exists($cacheFile) ? (json_decode(file_get_contents($cacheFile), true) ?? []) : [];
    }

    $posts = [];
    if (!empty($response['data'])) {
        foreach ($response['data'] as $post) {
            if (empty($post['message'])) continue; // skip posts with no text (photo-only, etc.)

            $excerpt = mb_strlen($post['message']) > 110 ? mb_substr($post['message'], 0, 110) . '…' : $post['message'];

            $posts[] = [
                'source'     => 'facebook',
                'title'      => null,
                'excerpt'    => $excerpt,
                'image_path' => $post['full_picture'] ?? null,
                'date'       => $post['created_time'],
                'link'       => $post['permalink_url'] ?? 'https://facebook.com/' . FB_PAGE_ID,
            ];
        }
    }

    $cacheDir = dirname($cacheFile);
    if (!is_dir($cacheDir)) mkdir($cacheDir, 0755, true);
    file_put_contents($cacheFile, json_encode($posts));

    return $posts;
}

$facebookPosts = getFacebookPosts();

// ---------------------------------------------------------------
// Homepage slideshow (managed via Admin > Section Editor)
// ---------------------------------------------------------------
try {
    $heroSlides = $db->query("
        SELECT * FROM hero_slides
        WHERE status = 'published'
        ORDER BY display_order ASC, slide_id ASC
    ")->fetchAll();
} catch (Exception $e) {
    $heroSlides = [];
}

// Lightweight mobile detection so slides marked "hide on mobile" are
// simply not rendered on small-screen devices.
$isMobileUA = isset($_SERVER['HTTP_USER_AGENT'])
    && preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $_SERVER['HTTP_USER_AGENT']);
if ($isMobileUA) {
    $heroSlides = array_values(array_filter($heroSlides, fn($s) => (int)$s['show_on_mobile'] === 1));
}

// ---------------------------------------------------------------
// Merge + sort combined feed, newest first, capped at 6
// ---------------------------------------------------------------
$publicAnnouncements = array_merge($publicAnnouncements, $facebookPosts);
usort($publicAnnouncements, function ($a, $b) {
    return strtotime($b['date']) <=> strtotime($a['date']);
});
$publicAnnouncements = array_slice($publicAnnouncements, 0, 6);

// ---------------------------------------------------------------
// Events calendar (public, published only)
// ---------------------------------------------------------------
$evtMonth = isset($_GET['evt_month']) ? max(1, min(12, (int)$_GET['evt_month'])) : (int)date('n');
$evtYear  = isset($_GET['evt_year'])  ? (int)$_GET['evt_year']                  : (int)date('Y');

$evtFirstOfMonth = mktime(0, 0, 0, $evtMonth, 1, $evtYear);
$evtDaysInMonth  = (int)date('t', $evtFirstOfMonth);
$evtStartWeekday = (int)date('w', $evtFirstOfMonth); // 0 = Sunday
$evtMonthLabel   = date('F Y', $evtFirstOfMonth);

$evtPrevMonth = $evtMonth - 1; $evtPrevYear = $evtYear;
if ($evtPrevMonth < 1) { $evtPrevMonth = 12; $evtPrevYear--; }
$evtNextMonth = $evtMonth + 1; $evtNextYear = $evtYear;
if ($evtNextMonth > 12) { $evtNextMonth = 1; $evtNextYear++; }

// ---------------------------------------------------------------
// Category colors
// Holiday = yellow, School Event = green, Emergency = red
// ---------------------------------------------------------------
function evt_categoryColor(?string $categoryColor, ?string $categoryName = null): string {
    if (!empty($categoryColor)) return $categoryColor;
    $name = strtolower(trim($categoryName ?? ''));
    if (strpos($name, 'holiday') !== false)   return '#EABA3B';
    if (strpos($name, 'emergency') !== false || strpos($name, 'suspension') !== false) return '#B3312D';
    if (strpos($name, 'school') !== false)    return '#1F5E2C';
    return '#6c757d';
}

try {
    $evtRangeStart = sprintf('%04d-%02d-01 00:00:00', $evtYear, $evtMonth);
    $evtRangeEnd   = date('Y-m-d 23:59:59', mktime(0, 0, 0, $evtMonth, $evtDaysInMonth, $evtYear));

    $stmt = $db->prepare("
        SELECT e.event_id, e.title, e.start_date, e.end_date, e.status, c.category_name, c.category_color
        FROM events e
        LEFT JOIN categories c ON e.category_id = c.category_id
        WHERE e.status = 'published' AND e.start_date BETWEEN ? AND ?
        ORDER BY e.start_date ASC
    ");
    $stmt->execute([$evtRangeStart, $evtRangeEnd]);
    $publicMonthEvents = $stmt->fetchAll();
} catch (Exception $e) {
    $publicMonthEvents = [];
}

$publicEventsByDay = [];
foreach ($publicMonthEvents as $ev) {
    $day = (int)date('j', strtotime($ev['start_date']));
    $publicEventsByDay[$day][] = $ev;
}

// Upcoming events (next 3 published, from today onward, any month)
try {
    $stmt = $db->prepare("
        SELECT e.event_id, e.title, e.location, e.start_date, e.end_date, c.category_name, c.category_color
        FROM events e
        LEFT JOIN categories c ON e.category_id = c.category_id
        WHERE e.status = 'published' AND e.start_date >= ?
        ORDER BY e.start_date ASC
        LIMIT 3
    ");
    $stmt->execute([date('Y-m-d 00:00:00')]);
    $upcomingEvents = $stmt->fetchAll();
} catch (Exception $e) {
    $upcomingEvents = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guagua National Colleges</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/index-style.css?v=2" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="icon" type="image/x-icon" href="assets/images/logos/gnc-logo-v1.svg">
</head>
<body>

    <?php include __DIR__ . '/components/index-nav.php'; ?>

    <?php if (!empty($heroSlides)): ?>
    <div class="hero-slideshow">
        <?php foreach ($heroSlides as $i => $slide):
            $titleLines = $slide['title'] ? explode('|', $slide['title'], 2) : [];
            $hasButtons = !empty($slide['btn1_text']) || !empty($slide['btn2_text']);
        ?>
        <div class="hero-slide <?= $i === 0 ? 'active' : '' ?>" data-slide="<?= $i ?>">
            <?php if ($slide['media_type'] === 'video'): ?>
                <div class="slide-video-wrap">
                    <video
                        class="slide-video hero-slide-video"
                        src="<?= htmlspecialchars($slide['media_path']) ?>"
                        <?= $slide['poster_path'] ? 'poster="' . htmlspecialchars($slide['poster_path']) . '"' : '' ?>
                        muted loop playsinline preload="auto">
                    </video>
                </div>
            <?php else: ?>
                <div class="slide-bg" style="background-image: url('<?= htmlspecialchars($slide['media_path']) ?>');"></div>
            <?php endif; ?>
            <div class="slide-overlay"></div>

            <?php if ($titleLines || $slide['subtitle'] || $hasButtons): ?>
            <div class="container h-100">
                <div class="hero-content">
                    <?php if (!empty($titleLines[0])): ?>
                        <h1 class="hero-title-gold mb-0"><?= htmlspecialchars($titleLines[0]) ?></h1>
                    <?php endif; ?>
                    <?php if (!empty($titleLines[1])): ?>
                        <h1 class="hero-title-white"><?= htmlspecialchars($titleLines[1]) ?></h1>
                    <?php endif; ?>
                    <?php if (!empty($slide['subtitle'])): ?>
                        <p class="hero-desc"><?= nl2br(htmlspecialchars($slide['subtitle'])) ?></p>
                    <?php endif; ?>
                    <?php if ($hasButtons): ?>
                    <div class="hero-ctas">
                        <?php if (!empty($slide['btn1_text'])): ?>
                            <a href="<?= htmlspecialchars($slide['btn1_link'] ?: '#') ?>" class="btn-enroll">
                                <?= htmlspecialchars($slide['btn1_text']) ?>
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8z"/>
                                </svg>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($slide['btn2_text'])): ?>
                            <a href="<?= htmlspecialchars($slide['btn2_link'] ?: '#') ?>" class="btn-explore">
                                <?= htmlspecialchars($slide['btn2_text']) ?>
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <?php if (count($heroSlides) > 1): ?>
        <button type="button" class="slide-arrow slide-arrow-prev" id="slide-prev" aria-label="Previous slide">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z"/>
            </svg>
        </button>
        <button type="button" class="slide-arrow slide-arrow-next" id="slide-next" aria-label="Next slide">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="currentColor" viewBox="0 0 16 16">
                <path fill-rule="evenodd" d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/>
            </svg>
        </button>

        <div class="hero-dots">
            <?php foreach ($heroSlides as $i => $slide): ?>
                <span class="<?= $i === 0 ? 'active' : '' ?>" data-target="<?= $i ?>"></span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

<div class="gnc-pillars">
        <div class="container">
            <div class="pillar-card">
                <div class="pillar-wrap">

                    <div class="pillar-item">
                        <div class="pillar-icon-wrap fides-bg">
                            <img src="assets/images/svg/church.svg" alt="Fides" class="pillar-icon-img">
                        </div>
                        <div>
                            <p class="pillar-title fides-color">FIDES</p>
                            <p class="pillar-desc">Faith in God and One's Self.</p>
                        </div>
                    </div>

                    <div class="pillar-item">
                        <div class="pillar-icon-wrap scientia-bg">
                            <img src="assets/images/svg/book.svg" alt="Scientia" class="pillar-icon-img">
                        </div>
                        <div>
                            <p class="pillar-title scientia-color">SCIENTIA</p>
                            <p class="pillar-desc">Search for Truth and Knowledge.</p>
                        </div>
                    </div>

                    <div class="pillar-item">
                        <div class="pillar-icon-wrap patria-bg">
                            <img src="assets/images/svg/swords.svg" alt="Patria" class="pillar-icon-img">
                        </div>
                        <div>
                            <p class="pillar-title patria-color">PATRIA</p>
                            <p class="pillar-desc">Love for Country.</p>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <section class="gnc-announcements py-5" id="announcements">
        <div class="container">
            <div class="gnc-announce-header mb-5" style="background-image: url('assets/images/Section Header.png')">
                <span class="d-inline-block mb-2" style="color:#EABA3B;font-weight:700;font-size:.78rem;letter-spacing:1.5px;text-transform:uppercase;">
                    Latest Announcements
                </span>
                <h2 class="mb-2" style="font-family: 'Noto Serif', serif; color:#094024;font-weight:800;">Stay Updated, Stay Informed</h2>
                <p class="text-muted mb-0">Get the latest news, events, and important updates from our official Facebook page.</p>
            </div>

            <?php if (empty($publicAnnouncements)): ?>
                <p class="text-muted text-center">No announcements have been posted yet. Please check back soon.</p>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($publicAnnouncements as $item):
                        $isFacebook = $item['source'] === 'facebook';
                        $linkTarget = $isFacebook ? ' target="_blank" rel="noopener"' : '';
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="card h-100 border-0 gnc-announcement-card" style="position:relative;">

                            <?php if ($isFacebook): ?>
                            <span style="position:absolute;top:12px;left:12px;z-index:2;background:#1877F2;color:#fff;
                                width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;
                                box-shadow:0 2px 6px rgba(0,0,0,.25);" title="Posted on Facebook">
                                <i class="bi bi-facebook" style="font-size:1.05rem;"></i>
                            </span>
                            <?php endif; ?>

                            <?php if (!empty($item['image_path'])): ?>
                            <img src="<?= htmlspecialchars($item['image_path']) ?>" class="card-img-top" alt="<?= htmlspecialchars($item['title'] ?? 'Facebook post') ?>" style="height:280px;object-fit:cover;">
                            <?php else: ?>
                            <div style="height:280px;background:#eef1ee;display:flex;align-items:center;justify-content:center;color:#c3cbc4;">
                                <i class="bi <?= $isFacebook ? 'bi-facebook' : 'bi-megaphone' ?>" style="font-size:2rem;"></i>
                            </div>
                            <?php endif; ?>

                            <div class="card-body d-flex flex-column">
                                <small class="text-muted mb-2 d-flex align-items-center gap-2">
                                    <i class="bi bi-calendar3"></i> <?= date('F d, Y', strtotime($item['date'])) ?>
                                    <?php if ($isFacebook): ?>
                                        <span class="badge" style="background:#e7f0fe;color:#1877F2;font-weight:600;font-size:.68rem;">
                                            <i class="bi bi-facebook"></i> From Facebook
                                        </span>
                                    <?php endif; ?>
                                </small>

                                <?php if (!empty($item['title'])): ?>
                                <h5 class="card-title" style="font-family: 'Noto Serif', serif; color:#094024;font-weight:800;">
                                    <?= htmlspecialchars($item['title']) ?>
                                </h5>
                                <?php endif; ?>

                                <p class="card-text text-muted flex-grow-1" style="font-family: 'Inter', sans-serif; font-size: .9rem;">
                                    <?= htmlspecialchars($item['excerpt']) ?>
                                </p>

                                <a href="<?= htmlspecialchars($item['link']) ?>"<?= $linkTarget ?> class="gnc-read-more text-decoration-none mt-1">
                                    <?= $isFacebook ? 'View on Facebook' : 'Read More' ?>
                                    <i class="bi <?= $isFacebook ? 'bi-box-arrow-up-right' : 'bi-arrow-right' ?>"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="text-center mt-5">
                    <a href="announcements.php" class="btn gnc-view-all-btn">
                        View All Announcements &amp; News
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="gnc-events-cal py-5" id="events" style="background:#f7f8f6;">
        <div class="container">
            <h2 class="mb-4" style="font-family:'Noto Serif', serif; color:#094024; font-weight:800;">Calendar of Events</h2>

            <div class="row g-4">
                <div class="col-lg-7">
                    <div class="gnc-cal-card">
                        <div class="gnc-cal-header">
                            <a href="?evt_month=<?= $evtPrevMonth ?>&evt_year=<?= $evtPrevYear ?>#events" class="gnc-cal-nav">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                            <span class="gnc-cal-title"><?= htmlspecialchars($evtMonthLabel) ?></span>
                            <a href="?evt_month=<?= $evtNextMonth ?>&evt_year=<?= $evtNextYear ?>#events" class="gnc-cal-nav">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </div>

                        <div class="gnc-cal-grid">
                            <?php foreach (['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $dow): ?>
                                <div class="gnc-cal-dow"><?= $dow ?></div>
                            <?php endforeach; ?>

                            <?php for ($i = 0; $i < $evtStartWeekday; $i++): ?>
                                <div class="gnc-cal-cell gnc-cal-empty"></div>
                            <?php endfor; ?>

                            <?php for ($day = 1; $day <= $evtDaysInMonth; $day++):
                                $isToday = ($day == (int)date('j') && $evtMonth == (int)date('n') && $evtYear == (int)date('Y'));
                                $dayHasEvent = !empty($publicEventsByDay[$day]);
                                $dayTitles = $dayHasEvent ? implode("\n", array_map(fn($e) => $e['title'], $publicEventsByDay[$day])) : '';
                                $dayColors = $dayHasEvent ? array_values(array_unique(array_map(fn($e) => evt_categoryColor($e['category_color'] ?? null, $e['category_name'] ?? null), $publicEventsByDay[$day]))) : [];
                            ?>
                                <div class="gnc-cal-cell<?= $isToday ? ' gnc-cal-today' : '' ?><?= $dayHasEvent ? ' gnc-cal-hasevent' : '' ?>"
                                     <?= $dayHasEvent ? 'title="' . htmlspecialchars($dayTitles) . '"' : '' ?>>
                                    <?= $day ?>
                                    <?php if ($dayHasEvent): ?>
                                        <span class="gnc-cal-dots">
                                            <?php foreach (array_slice($dayColors, 0, 3) as $c): ?>
                                                <span class="gnc-cal-dot" style="background:<?= $c ?>;"></span>
                                            <?php endforeach; ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endfor; ?>
                        </div>

                        <div class="gnc-cal-legend">
                            <span><span class="gnc-cal-dot" style="background:#EABA3B;"></span> Holiday</span>
                            <span><span class="gnc-cal-dot" style="background:#1F5E2C;"></span> School Event</span>
                            <span><span class="gnc-cal-dot" style="background:#B3312D;"></span> Emergency</span>
                        </div>
                    </div>
                </div>

                <div class="col-lg-5">
                    <div class="gnc-upcoming-card">
                        <div class="gnc-upcoming-header">
                            <i class="bi bi-calendar-event-fill"></i> Upcoming Events
                        </div>
                        <div class="gnc-upcoming-list">
                            <?php if (empty($upcomingEvents)): ?>
                                <p class="text-muted px-3 py-4 mb-0">No upcoming events at this time.</p>
                            <?php else: foreach ($upcomingEvents as $ev): ?>
                                <div class="gnc-upcoming-item" style="border-left: 4px solid <?= evt_categoryColor($ev['category_color'] ?? null, $ev['category_name'] ?? null) ?>;">
                                    <i class="bi bi-calendar3 gnc-upcoming-icon" style="color: <?= evt_categoryColor($ev['category_color'] ?? null, $ev['category_name'] ?? null) ?>;"></i>
                                    <div>
                                        <div class="gnc-upcoming-title"><?= htmlspecialchars($ev['title']) ?></div>
                                        <div class="gnc-upcoming-date">
                                            <?= date('F j, Y', strtotime($ev['start_date'])) ?>
                                            <?php if (!empty($ev['location'])): ?>
                                                · <?= htmlspecialchars($ev['location']) ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div class="container">
        <div class="d-flex align-items-center justify-content-between flex-wrap gap-3 my-4 px-4 py-3"
            style="background:#eaf5ee;border:1px solid rgba(9,64,36,0.15);border-radius:10px;">
            <div class="d-flex align-items-center gap-3">
                <span style="background:#094024;color:#fff;width:28px;height:28px;border-radius:50%;
                            display:flex;align-items:center;justify-content:center;flex-shrink:0;">
                    <i class="bi bi-info-lg" style="font-size:0.85rem;"></i>
                </span>
                <span style="font-size:0.88rem;color:#333;">
                    These announcements are posted on our official Facebook page. For more updates and interactions, follow us on Facebook
                </span>
            </div>
            <a href="https://www.facebook.com/gnc.edu.ph" target="_blank" rel="noopener"
            class="d-flex align-items-center gap-2 text-decoration-none flex-shrink-0"
            style="background:#fff;border:1px solid rgba(9,64,36,0.15);border-radius:8px;
                    padding:0.55rem 1.1rem;font-weight:600;font-size:0.85rem;color:#1a1a1a;">
                <i class="bi bi-facebook" style="color:#1877F2;font-size:1rem;"></i>
                Follow us on Facebook
                <i class="bi bi-box-arrow-up-right" style="font-size:0.8rem;color:#555;"></i>
            </a>
        </div>
    </div>

    <section class="gnc-programs py-5" id="programs">
        <div class="container">
            <div class="gnc-programs-header" style="background-image: url('assets/images/Section Header.png');">
                <span class="gnc-eyebrow">Academic Programs</span>
                <h2 class="gnc-section-title">Shaping Minds, Building Futures</h2>
                <p class="text-muted mb-0">
                    Explore the services, personnel, and contact information of every office within Guagua National Colleges.
                </p>
            </div>

            <div class="row g-4 mt-2">
                <div class="col-md-4">
                    <div class="gnc-program-card">
                        <div class="gnc-program-img" style="background-image:url('assets/images/basic-edu.png');"></div>
                        <div class="gnc-program-body">
                            <h5 class="gnc-program-title">Basic Education</h5>
                            <p class="gnc-program-desc">
                                Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
                            </p>
                            <a href="#" class="gnc-program-link">Learn More <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="gnc-program-card">
                        <div class="gnc-program-img" style="background-image:url('assets/images/college-programs.png');"></div>
                        <div class="gnc-program-body">
                            <h5 class="gnc-program-title">College Programs</h5>
                            <p class="gnc-program-desc">
                                Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
                            </p>
                            <a href="#" class="gnc-program-link">Learn More <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="gnc-program-card">
                        <div class="gnc-program-img" style="background-image:url('assets/images/graduate-school.png');"></div>
                        <div class="gnc-program-body">
                            <h5 class="gnc-program-title">Graduate School</h5>
                            <p class="gnc-program-desc">
                                Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.
                            </p>
                            <a href="#" class="gnc-program-link">Learn More <i class="bi bi-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="gnc-president py-5" id="president">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-5">
                    <div class="gnc-president-leaves"></div>

                    <div class="gnc-president-label">
                        <span class="gnc-label-bar"></span>
                        Message from the President
                    </div>

                    <div class="gnc-president-card">
                        <div class="gnc-president-photo-wrap">
                            <div class="gnc-president-seal"></div>
                            <img src="assets/images/maam-president.png" alt="Geraldine G. Lim, MBA" class="gnc-president-photo">
                        </div>
                        <div class="gnc-president-nameplate">
                            <p class="gnc-president-name">Geraldine G. Lim, MBA</p>
                            <p class="gnc-president-role">President</p>
                            <p class="gnc-president-org">Guagua National Colleges, Inc.</p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7">
                    <p class="gnc-president-eyebrow">A Warm Welcome to</p>
                    <h2 class="gnc-president-title">Our New Students</h2>

                    <p class="gnc-president-greeting">Welcome GNCians!</p>

                    <p class="gnc-president-quote">
                        &ldquo;Congratulations for choosing Guagua National Colleges, Inc. to take care of you and your education.
                    </p>
                    <p class="gnc-president-quote">
                        Guagua National Colleges, Inc. boast of 100 plus years of giving quality education.&rdquo;
                    </p>
                </div>
    </section>

    <?php include __DIR__ . '/components/index-footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous"></script>
    <style>

        .gnc-cal-card, .gnc-upcoming-card {
            background:#fff; border-radius:12px;
            box-shadow:0 2px 10px rgba(0,0,0,0.06);
            overflow:hidden; height:100%;
        }
        .gnc-cal-header {
            display:flex; align-items:center; gap:.75rem;
            padding:1rem 1.25rem; border-bottom:1px solid #f0f0f0;
        }
        .gnc-cal-title { font-weight:700; color:#094024; font-size:1.05rem; }
        .gnc-cal-nav {
            width:32px; height:32px; border-radius:8px;
            display:flex; align-items:center; justify-content:center;
            background:#f5f5f5; color:#094024; text-decoration:none;
            transition:background .15s;
        }
        .gnc-cal-nav:hover { background:#e8e8e8; }
        .gnc-cal-nav:last-of-type { margin-left:auto; }
        .gnc-cal-grid {
            display:grid; grid-template-columns:repeat(7,1fr);
            gap:2px; padding:1rem 1.25rem;
        }
        .gnc-cal-dow {
            text-align:center; font-size:.68rem; font-weight:700;
            color:#999; text-transform:uppercase; padding-bottom:6px;
        }
        .gnc-cal-cell {
            aspect-ratio:1; display:flex; flex-direction:column; align-items:center; justify-content:center;
            font-size:.82rem; color:#444; border-radius:6px; position:relative;
        }
        .gnc-cal-empty { background:none; }
        .gnc-cal-today { background:#DDEBFD; font-weight:700; color:#094024; border:1px solid #1877F2; }
        .gnc-cal-hasevent {
            background:#f3f6f3; font-weight:700; cursor:default;
        }
        .gnc-cal-hasevent.gnc-cal-today { background:#DDEBFD; border-color:#1877F2; }
        .gnc-cal-dots { display:flex; gap:3px; margin-top:2px; }
        .gnc-cal-dot {
            display:inline-block; width:6px; height:6px; border-radius:50%;
        }
        .gnc-cal-legend {
            display:flex; flex-wrap:wrap; gap:14px; padding:0 1.25rem 1rem;
            font-size:.78rem; color:#555;
        }
        .gnc-cal-legend .gnc-cal-dot { width:9px; height:9px; margin-right:5px; }

        .gnc-upcoming-header {
            background:#094024; color:#fff; font-weight:700;
            padding:1rem 1.25rem; font-size:.95rem;
        }
        .gnc-upcoming-header i { color:#EABA3B; margin-right:.4rem; }
        .gnc-upcoming-item {
            display:flex; gap:.75rem; align-items:flex-start;
            padding:.9rem 1.25rem; border-bottom:1px solid #f5f5f5;
        }
        .gnc-upcoming-item:last-child { border-bottom:none; }
        .gnc-upcoming-icon { color:#EABA3B; font-size:1.1rem; margin-top:2px; }
        .gnc-upcoming-title { font-weight:700; color:#094024; font-size:.9rem; }
        .gnc-upcoming-date { font-size:.78rem; color:#888; margin-top:2px; }
    </style>
    
    <script src="assets/js/index.js?v=2"></script>
</body>
</html>