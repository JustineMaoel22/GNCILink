<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/fb-categorizer.php';

// Content Editor preview (?ce_preview=1): a signed-in editor sees this same page with
// unpublished slides and small editing markers. Everyone else gets the normal page.
// The shared functions are always loaded: the homepage headings and program cards below are
// editable in the Content Editor and read through homeText() (original wording until edited).
require_once __DIR__ . '/admin/admin-functions.php';
cePreviewActive();   // decide preview mode now, before any output (it needs the session)

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
        'id'         => (int)($ann['announcement_id'] ?? 0),
        'source'     => 'admin',
        'title'      => $ann['title'],
        'excerpt'    => $excerpt,
        'image_path' => $ann['image_path'],
        'date'       => $ann['published_at'] ?? $ann['created_at'],
        'link'       => '/announcement-article.php?slug=' . urlencode($ann['slug']),
    ];
}

// ---------------------------------------------------------------
// Fetch the latest published News articles
// ---------------------------------------------------------------
try {
    $stmt = $db->query("
        SELECT n.news_id, n.title, n.slug, n.content, n.author, n.published_at, n.created_at,
            m.file_path as image_path
        FROM news n
        LEFT JOIN media_library m ON n.featured_image = m.media_id
        WHERE n.status = 'published'
        ORDER BY COALESCE(n.published_at, n.created_at) DESC
        LIMIT 6
    ");
    $dbNews = $stmt->fetchAll();
} catch (Exception $e) {
    $dbNews = [];
}

// Normalize DB rows into the shared feed shape — same shape as
// $publicAnnouncements so index.php can merge/sort them together.
$publicNews = [];
foreach ($dbNews as $item) {
    $excerpt = strip_tags($item['content']);
    $excerpt = mb_strlen($excerpt) > 110 ? mb_substr($excerpt, 0, 110) . '…' : $excerpt;
    $publicNews[] = [
        'id'         => (int)($item['news_id'] ?? 0),
        'source'     => 'news',
        'title'      => $item['title'],
        'excerpt'    => $excerpt,
        'image_path' => $item['image_path'],
        'date'       => $item['published_at'] ?? $item['created_at'],
        'link'       => '/news-article.php?slug=' . urlencode($item['slug']),
        'author'     => $item['author'] ?? null,
    ];
}

// ---------------------------------------------------------------
// Fetch the latest Facebook Page posts (cached in the database to
// avoid rate limits). Images are downloaded once and registered in
// media_library — same as every other file in this project — so we
// never depend on Facebook's short-lived signed CDN URLs at render time.
// ---------------------------------------------------------------
function getFacebookPosts(): array {
    $db       = getDB();
    $cacheTtl = 600; // 10 minutes

    $lastFetchRow = $db->query("SELECT MAX(fetched_at) AS last_fetch FROM fb_posts_cache")->fetch();
    $isFresh = !empty($lastFetchRow['last_fetch'])
        && (time() - strtotime($lastFetchRow['last_fetch']) < $cacheTtl);

    if ($isFresh) {
        return fetchCachedFbPostsFromDb($db);
    }

    // ---------------------------------------------------------------
    // Rate limiting: a hard cap on how many real Facebook calls we make
    // per hour, tracked in fb_api_call_log (created once, manually — see
    // setup note). This keeps us well under Facebook's own rate limits
    // no matter how busy the site gets, using just two lightweight
    // queries instead of anything that creates tables or takes locks on
    // every request.
    // ---------------------------------------------------------------
    $maxCallsPerHour = 60;

    $callsThisHour = (int) $db->query("
        SELECT COUNT(*) FROM fb_api_call_log WHERE called_at > NOW() - INTERVAL 1 HOUR
    ")->fetchColumn();

    if ($callsThisHour >= $maxCallsPerHour) {
        error_log("Facebook fetch skipped: hourly rate limit ($maxCallsPerHour/hr) reached");
        return fetchCachedFbPostsFromDb($db);
    }

    $db->exec("INSERT INTO fb_api_call_log (called_at) VALUES (NOW())");
    $db->exec("DELETE FROM fb_api_call_log WHERE called_at <= NOW() - INTERVAL 1 HOUR"); // keep the table small

    $pageId      = $_ENV['FB_PAGE_ID'];
    $accessToken = $_ENV['FB_ACCESS_TOKEN'];

    $url = "https://graph.facebook.com/v19.0/{$pageId}/posts"
         . "?fields=id,message,created_time,permalink_url,full_picture,picture,"
         . "attachments{media{image{src}},subattachments{media{image{src}}}}&limit=6"
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
        return fetchCachedFbPostsFromDb($db); // serve whatever's already cached, even if stale
    }

    $response = json_decode($raw, true);

    if (isset($response['error'])) {
        error_log("Facebook API error: " . $response['error']['message']);
        return fetchCachedFbPostsFromDb($db);
    }

    if (!empty($response['data'])) {
        $existingStmt = $db->prepare("
            SELECT f.image_media_id, m.file_path
            FROM fb_posts_cache f
            LEFT JOIN media_library m ON f.image_media_id = m.media_id
            WHERE f.fb_post_id = ?
        ");

        $categoryMap = gncLoadCategoryMap($db);

        $upsertStmt = $db->prepare("
            INSERT INTO fb_posts_cache (fb_post_id, message, image_media_id, category_id, permalink_url, created_time, fetched_at)
            VALUES (:fb_post_id, :message, :image_media_id, :category_id, :permalink_url, :created_time, NOW())
            ON DUPLICATE KEY UPDATE
                message         = VALUES(message),
                permalink_url   = VALUES(permalink_url),
                created_time    = VALUES(created_time),
                fetched_at      = NOW(),
                image_media_id  = COALESCE(VALUES(image_media_id), image_media_id),
                category_id     = IF(category_locked = 1, category_id, VALUES(category_id))
        ");

        foreach ($response['data'] as $post) {
            if (empty($post['message']) || empty($post['id'])) continue; // skip photo-only / malformed posts

            // Reuse the existing media_library row for this post if we already
            // downloaded it AND the file is still there. Otherwise (never
            // downloaded, earlier download failed, or file/row went missing)
            // try again with every image URL Facebook gave us.
            $existingStmt->execute([$post['id']]);
            $existing     = $existingStmt->fetch() ?: [];
            $imageMediaId = null;

            if (!empty($existing['image_media_id']) && gncFbImageIsUsable($existing['file_path'] ?? null)) {
                $imageMediaId = (int) $existing['image_media_id'];
            } else {
                $candidates = gncPickFbImageUrls($post);
                foreach ($candidates as $candidateUrl) {
                    $imageMediaId = downloadAndStoreFbImageAsMedia($db, $post['id'], $candidateUrl);
                    if ($imageMediaId) break;
                }
                if (!$imageMediaId) {
                    error_log("Facebook post {$post['id']}: no usable image (" . count($candidates) . " candidate URL(s) tried)");
                }
            }

            $upsertStmt->execute([
                ':fb_post_id'     => $post['id'],
                ':message'        => $post['message'],
                ':image_media_id' => $imageMediaId,
                ':category_id'    => gncCategoryIdForMessage($post['message'], $categoryMap), // auto-detected
                ':permalink_url'  => $post['permalink_url'] ?? null,
                ':created_time'   => date('Y-m-d H:i:s', strtotime($post['created_time'])),
            ]);
        }
    }

    return fetchCachedFbPostsFromDb($db);
}

/**
 * Read cached FB posts back out of the database, joined against
 * media_library for the image path — same shape as the admin
 * announcements feed so index.php can merge/sort them together.
 */
function fetchCachedFbPostsFromDb(PDO $db): array {
    $rows = $db->query("
        SELECT f.message, f.permalink_url, f.created_time, m.file_path
        FROM fb_posts_cache f
        LEFT JOIN media_library m ON f.image_media_id = m.media_id
        ORDER BY f.created_time DESC
        LIMIT 6
    ")->fetchAll();

    $posts = [];
    foreach ($rows as $row) {
        $excerpt = mb_strlen($row['message']) > 110 ? mb_substr($row['message'], 0, 110) . '…' : $row['message'];
        $posts[] = [
            'source'     => 'facebook',
            'title'      => null,
            'excerpt'    => $excerpt,
            'image_path' => $row['file_path'], // e.g. /uploads/facebook/fb_12345.jpg, or null
            'date'       => $row['created_time'],
            'link'       => $row['permalink_url'] ?? 'https://facebook.com/' . FB_PAGE_ID,
        ];
    }
    return $posts;
}

/**
 * Collect every image URL Facebook returned for a post, best first.
 * full_picture is empty for some post types (shared links, albums,
 * videos), so fall back to the attachment images and finally the
 * small "picture" thumbnail.
 */
function gncPickFbImageUrls(array $post): array {
    $urls = [];
    if (!empty($post['full_picture'])) $urls[] = $post['full_picture'];

    foreach (($post['attachments']['data'] ?? []) as $att) {
        if (!empty($att['media']['image']['src'])) $urls[] = $att['media']['image']['src'];
        foreach (($att['subattachments']['data'] ?? []) as $sub) {
            if (!empty($sub['media']['image']['src'])) $urls[] = $sub['media']['image']['src'];
        }
    }

    if (!empty($post['picture'])) $urls[] = $post['picture'];

    return array_values(array_unique($urls));
}

/**
 * True when a cached image row points at a file that still exists.
 * Lets us re-download images whose media_library row or file was lost.
 */
function gncFbImageIsUsable(?string $publicPath): bool {
    if (empty($publicPath)) return false;
    if (strpos($publicPath, '/uploads/facebook/') === 0) {
        return is_file(UPLOAD_PATH . 'facebook/' . basename($publicPath));
    }
    return true; // not one of ours: assume it's fine
}

/**
 * Download a Facebook post image once and register it in media_library,
 * exactly like every other uploaded file in this project. Returns the
 * new media_id, or null if the download/insert failed.
 */
function downloadAndStoreFbImageAsMedia(PDO $db, string $postId, string $remoteUrl): ?int {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $remoteUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);          // CDN links can redirect
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; GNCWebsite/1.0)');
    curl_setopt($ch, CURLOPT_ENCODING, '');                   // accept gzip etc.
    $imageData = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr   = curl_error($ch);
    curl_close($ch);

    if ($imageData === false || $httpCode < 200 || $httpCode >= 300 || $imageData === '') {
        error_log("Failed to download FB image for post $postId (HTTP $httpCode) $curlErr");
        return null;
    }

    // Make sure what we got is really an image (not an HTML error page)
    $info = @getimagesizefromstring($imageData);
    if ($info === false) {
        error_log("FB image for post $postId is not a valid image");
        return null;
    }
    $mimeToExt = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
    $ext = $mimeToExt[$info['mime']] ?? null;
    if ($ext === null) {
        error_log("FB image for post $postId has unsupported type {$info['mime']}");
        return null;
    }

    $safeId   = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $postId);
    $filename = 'fb_' . $safeId . '.' . $ext;

    $destDir = UPLOAD_PATH . 'facebook/';
    if (!is_dir($destDir)) mkdir($destDir, 0755, true);

    $saved = file_put_contents($destDir . $filename, $imageData);
    if ($saved === false) {
        error_log("Failed to save FB image for post $postId to disk");
        return null;
    }

    $publicPath = '/uploads/facebook/' . $filename;

    try {
        $stmt = $db->prepare("
            INSERT INTO media_library (file_name, file_type, file_size, file_path, created_by, created_at)
            VALUES (?, ?, ?, ?, NULL, NOW())
        ");
        $stmt->execute([$filename, $ext, $saved, $publicPath]);
        return (int) $db->lastInsertId();
    } catch (Exception $e) {
        error_log("Failed to insert media_library row for FB post $postId: " . $e->getMessage());
        return null;
    }
}

$facebookPosts = getFacebookPosts();

// ---------------------------------------------------------------
// Pinned announcements/news (managed from Admin > Announcements /
// Admin > News, via the pin toggle — up to GNC_PIN_LIMIT items combined).
// These power the small featured slideshow above the News & Announcements
// grid below.
// ---------------------------------------------------------------
const GNC_PIN_LIMIT = 5;   // most pinned items shown on the homepage (was 3)
$pinnedFeedItems = [];

/**
 * Split announcement/news HTML into an intro sentence and up to 4 checklist
 * points (taken from <li> items, or from following paragraphs/lines).
 */
function gncPinnedParse(string $html): array {
    $points = [];
    if (preg_match_all('/<li[^>]*>(.*?)<\/li>/is', $html, $m)) {
        foreach ($m[1] as $li) {
            $t = trim(html_entity_decode(strip_tags($li), ENT_QUOTES, 'UTF-8'));
            if ($t !== '') $points[] = $t;
        }
        $html = preg_replace('/<(ul|ol)[^>]*>.*?<\/\1>/is', '', $html);
    }
    $text  = html_entity_decode(strip_tags(preg_replace('/<\/(p|div|br|h[1-6])>|<br\s*\/?>/i', "\n", $html)), ENT_QUOTES, 'UTF-8');
    $lines = array_values(array_filter(array_map('trim', preg_split('/\R+/', $text)), fn($l) => $l !== ''));
    $intro = $lines[0] ?? '';
    if (!$points) {
        $points = array_slice($lines, 1, 4);
    }
    $cut = fn($s, $n) => mb_strlen($s) > $n ? mb_substr($s, 0, $n) . '…' : $s;
    return [$cut($intro, 160), array_map(fn($s) => $cut($s, 110), array_slice($points, 0, 4))];
}
/**
 * Return the admin's content exactly as typed (paragraphs, line breaks, lists,
 * bold/italic, links) but safe to print: only simple formatting tags are kept
 * and every attribute is dropped (except a safe href on links).
 */
function gncPinnedHtml(string $html): string {
    $html = strip_tags($html, '<p><br><strong><b><em><i><u><ul><ol><li><a><h1><h2><h3><h4><h5><h6>');
    $html = preg_replace_callback('/<(\/?)([a-z0-9]+)([^>]*)>/i', function ($m) {
        $tag = strtolower($m[2]);
        if ($m[1] === '/') return '</' . $tag . '>';
        if ($tag === 'a' && preg_match('/href\s*=\s*([\"\'])(.*?)\1/i', $m[3], $h)
            && preg_match('#^(https?://|/|mailto:)#i', trim($h[2]))) {
            return '<a href="' . htmlspecialchars(trim($h[2]), ENT_QUOTES) . '" target="_blank" rel="noopener">';
        }
        return '<' . $tag . '>';
    }, $html);
    return trim($html);
}
try {
    $stmt = $db->query("
        SELECT a.announcement_id, a.title, a.slug, a.content, a.pinned_at, a.published_at, a.created_at,
            m.file_path AS image_path
        FROM announcements a
        LEFT JOIN media_library m ON a.featured_image = m.media_id
        WHERE a.is_pinned = 1 AND a.status = 'published'
    ");
    foreach ($stmt->fetchAll() as $ann) {
        $excerpt = strip_tags($ann['content']);
        $excerpt = mb_strlen($excerpt) > 130 ? mb_substr($excerpt, 0, 130) . '…' : $excerpt;
        [$pinIntro, $pinPoints] = gncPinnedParse($ann['content']);
        $pinnedFeedItems[] = [
            'intro'      => $pinIntro,
            'points'     => $pinPoints,
            'html'       => gncPinnedHtml((string)$ann['content']),
            'id'         => (int)($ann['announcement_id'] ?? 0),
            'source'     => 'admin',
            'type_label' => 'Announcement',
            'title'      => $ann['title'],
            'excerpt'    => $excerpt,
            'image_path' => $ann['image_path'],
            'date'       => $ann['published_at'] ?? $ann['created_at'],
            'pinned_at'  => $ann['pinned_at'],
            'link'       => '/announcement-article.php?slug=' . urlencode($ann['slug']),
        ];
    }

    $stmt = $db->query("
        SELECT n.news_id, n.title, n.slug, n.content, n.pinned_at, n.published_at, n.created_at,
            m.file_path AS image_path
        FROM news n
        LEFT JOIN media_library m ON n.featured_image = m.media_id
        WHERE n.is_pinned = 1 AND n.status = 'published'
    ");
    foreach ($stmt->fetchAll() as $item) {
        $excerpt = strip_tags($item['content']);
        $excerpt = mb_strlen($excerpt) > 130 ? mb_substr($excerpt, 0, 130) . '…' : $excerpt;
        [$pinIntro, $pinPoints] = gncPinnedParse($item['content']);
        $pinnedFeedItems[] = [
            'intro'      => $pinIntro,
            'points'     => $pinPoints,
            'html'       => gncPinnedHtml((string)$item['content']),
            'id'         => (int)($item['news_id'] ?? 0),
            'source'     => 'news',
            'type_label' => 'News',
            'title'      => $item['title'],
            'excerpt'    => $excerpt,
            'image_path' => $item['image_path'],
            'date'       => $item['published_at'] ?? $item['created_at'],
            'pinned_at'  => $item['pinned_at'],
            'link'       => '/news-article.php?slug=' . urlencode($item['slug']),
        ];
    }

    $stmt = $db->query("
        SELECT f.message, f.permalink_url, f.created_time, f.pinned_at, m.file_path AS image_path
        FROM fb_posts_cache f
        LEFT JOIN media_library m ON f.image_media_id = m.media_id
        WHERE f.is_pinned = 1
    ");
    foreach ($stmt->fetchAll() as $row) {
        $excerpt = mb_strlen($row['message']) > 130 ? mb_substr($row['message'], 0, 130) . '…' : $row['message'];
        $pinnedFeedItems[] = [
            'intro'      => $excerpt,
            'points'     => [],
            'html'       => nl2br(htmlspecialchars((string)$row['message'])),
            'source'     => 'facebook',
            'type_label' => 'Announcement',
            'title'      => null,
            'excerpt'    => $excerpt,
            'image_path' => $row['image_path'],
            'date'       => $row['created_time'],
            'pinned_at'  => $row['pinned_at'],
            'link'       => $row['permalink_url'] ?? ('https://facebook.com/' . FB_PAGE_ID),
        ];
    }

    usort($pinnedFeedItems, fn($a, $b) => strtotime($b['pinned_at']) <=> strtotime($a['pinned_at']));
    $pinnedFeedItems = array_slice($pinnedFeedItems, 0, GNC_PIN_LIMIT);
} catch (Exception $e) {
    $pinnedFeedItems = [];
}

// ---------------------------------------------------------------
// Homepage slideshow (managed via Admin > Section Editor)
// ---------------------------------------------------------------
try {
    $heroSlides = $db->query("
        SELECT * FROM hero_slides
        WHERE status " . (cePreviewActive() ? "IN ('published', 'draft')" : "= 'published'") . "
        ORDER BY display_order ASC, slide_id ASC
    ")->fetchAll();
} catch (Exception $e) {
    $heroSlides = [];
}

// Lightweight mobile detection so slides marked "hide on mobile" are
// simply not rendered on small-screen devices.
$isMobileUA = isset($_SERVER['HTTP_USER_AGENT'])
    && preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $_SERVER['HTTP_USER_AGENT']);
if ($isMobileUA && !cePreviewActive()) {
    $heroSlides = array_values(array_filter($heroSlides, fn($s) => (int)$s['show_on_mobile'] === 1));
}

// ---------------------------------------------------------------
// Merge + sort combined feed, newest first, capped at 3
// ---------------------------------------------------------------
$publicAnnouncements = array_merge($publicAnnouncements, $publicNews, $facebookPosts);
usort($publicAnnouncements, function ($a, $b) {
    return strtotime($b['date']) <=> strtotime($a['date']);
});
$publicAnnouncements = array_slice($publicAnnouncements, 0, 3);

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

    // Overlap condition: include any event that touches this month at all,
    // not just ones that *start* in it — so multi-day events that started
    // last month but run into this one still show up.
    $stmt = $db->prepare("
        SELECT e.event_id, e.title, e.start_date, e.end_date, e.status, c.category_name, c.category_color
        FROM events e
        LEFT JOIN categories c ON e.category_id = c.category_id
        WHERE e.status = 'published'
          AND e.start_date <= ?
          AND COALESCE(e.end_date, e.start_date) >= ?
        ORDER BY e.start_date ASC
    ");
    $stmt->execute([$evtRangeEnd, $evtRangeStart]);
    $publicMonthEvents = $stmt->fetchAll();
} catch (Exception $e) {
    $publicMonthEvents = [];
}

// Expand each event across every day it spans (clamped to this month),
// so a "Jul 18 - Jul 20" event gets a chip on the 18th, 19th, and 20th —
// matching how the admin calendar renders multi-day events.
$publicEventsByDay = [];
$evtMonthStartObj = new DateTime(date('Y-m-d', strtotime($evtRangeStart)));
$evtMonthEndObj   = new DateTime(date('Y-m-d', strtotime($evtRangeEnd)));

foreach ($publicMonthEvents as $ev) {
    $evStartObj = new DateTime(date('Y-m-d', strtotime($ev['start_date'])));
    $evEndObj   = new DateTime(date('Y-m-d', strtotime($ev['end_date'] ?: $ev['start_date'])));
    if ($evEndObj < $evStartObj) $evEndObj = clone $evStartObj; // guard against bad data

    // Clamp the event's span to the days that actually fall within this calendar month
    $cursor  = $evStartObj > $evtMonthStartObj ? clone $evStartObj : clone $evtMonthStartObj;
    $lastDay = $evEndObj   < $evtMonthEndObj   ? clone $evEndObj   : clone $evtMonthEndObj;

    while ($cursor <= $lastDay) {
        $day = (int)$cursor->format('j');
        $publicEventsByDay[$day][] = $ev;
        $cursor->modify('+1 day');
    }
}

// ---------------------------------------------------------------
// Program filter for the "Upcoming Events" card
// ---------------------------------------------------------------
// Canonical code -> full name list, kept identical to getProgramCategories()
// in admin-functions.php so names match everywhere on the site.
$programFullNames = [
    'ALL'     => 'All Programs',
    'BEEd'    => 'Bachelor of Elementary Education',
    'BSEd'    => 'Bachelor of Secondary Education',
    'BAEL'    => 'Bachelor of Arts in English Language',
    'BSCE'    => 'Bachelor of Science in Civil Engineering',
    'BSAIS'   => 'Bachelor of Science in Accounting Information Systems',
    'BSA'     => 'Bachelor of Science in Accountancy',
    'BSBA-FM' => 'Bachelor of Science in Business Administration major in Financial Management',
    'BSCS'    => 'Bachelor of Science in Computer Science',
    'BSIT'    => 'Bachelor of Science in Information Technology',
    'BSHM'    => 'Bachelor of Science in Hospitality Management',
    'BSTM'    => 'Bachelor of Science in Tourism Management',
    'BSMLS'   => 'Bachelor of Science in Medical Laboratory Science',
    'BSPh'    => 'Bachelor of Science in Pharmacy',
    'BSN'     => 'Bachelor of Science in Nursing',
];

// A specific program is "selected" when ?program=CODE is a real, known
// code and isn't the 'ALL' placeholder. Anything unrecognized (missing,
// tampered, typo'd) safely falls back to no filter at all — same
// fail-safe pattern as normalizeProgramCategory() in admin-functions.php.
$selectedProgram = $_GET['program'] ?? null;
if ($selectedProgram === 'ALL' || !isset($programFullNames[$selectedProgram])) {
    $selectedProgram = null;
}

// Time is optional on events; 00:00:00 means "no time set".
function evt_timeLabel($dt) {
    if (empty($dt)) return '';
    $ts = strtotime($dt);
    return date('H:i:s', $ts) !== '00:00:00' ? date('g:i A', $ts) : '';
}

// Upcoming events (next 3 published, from today onward, any month;
// filtered to $selectedProgram when one is chosen)
try {
    if ($selectedProgram !== null) {
        // A specific program is selected: show ONLY events tagged with
        // that exact program code. General ('ALL') / other-program events
        // do not appear here.
        $stmt = $db->prepare("
            SELECT e.event_id, e.title, e.location, e.start_date, e.end_date, e.program, c.category_name, c.category_color
            FROM events e
            LEFT JOIN categories c ON e.category_id = c.category_id
            WHERE e.status = 'published' AND e.start_date >= ? AND e.program = ?
            ORDER BY e.start_date ASC
            LIMIT 3
        ");
        $stmt->execute([date('Y-m-d 00:00:00'), $selectedProgram]);
    } else {
        // No program selected: the general/College Department view,
        // unfiltered, same behavior as before this feature existed.
        $stmt = $db->prepare("
            SELECT e.event_id, e.title, e.location, e.start_date, e.end_date, e.program, c.category_name, c.category_color
            FROM events e
            LEFT JOIN categories c ON e.category_id = c.category_id
            WHERE e.status = 'published' AND e.start_date >= ?
            ORDER BY e.start_date ASC
            LIMIT 3
        ");
        $stmt->execute([date('Y-m-d 00:00:00')]);
    }
    $upcomingEvents = $stmt->fetchAll();
} catch (Exception $e) {
    $upcomingEvents = [];
}

// ---------------------------------------------------------------
// Fetch programs for the homepage "Programs" card
// ---------------------------------------------------------------
try {
    $stmt = $db->query("
        SELECT p.program_id, p.program_code, d.slug AS dept_slug
        FROM programs p
        LEFT JOIN departments d ON d.dept_id = p.dept_id
        ORDER BY p.program_code ASC
    ");
    $homePrograms = $stmt->fetchAll();
} catch (Exception $e) {
    $homePrograms = [];
}

// ---------------------------------------------------------------
// "Message from the President" — managed in the admin Content Editor
// (homepage_president, row id = 1). Any field left empty/missing falls
// back to the original hardcoded text so the section never renders blank.
// ---------------------------------------------------------------
$president = [
    'name'      => 'Geraldine G. Lim, MBA',
    'title'     => 'President',
    'org'       => 'Guagua National Colleges, Inc.',
    'eyebrow'   => 'A Warm Welcome to',
    'heading'   => 'Our New Students',
    'greeting'  => 'Welcome GNCians!',
    'message'   => "\u{201C}Congratulations for choosing Guagua National Colleges, Inc. to take care of you and your education.\n\nGuagua National Colleges, Inc. boast of 100 plus years of giving quality education.\u{201D}",
    'photo_url' => 'assets/images/maam-president.png',
];
try {
    $stmt = $db->query("
        SELECT hp.name, hp.title, hp.org, hp.eyebrow, hp.heading, hp.greeting, hp.message,
               m.file_path AS photo_path
        FROM homepage_president hp
        LEFT JOIN media_library m ON m.media_id = hp.media_id
        WHERE hp.id = 1
    ");
    $hpRow = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
    if ($hpRow) {
        foreach (['name', 'title', 'org', 'eyebrow', 'heading', 'greeting', 'message'] as $k) {
            if (isset($hpRow[$k]) && trim($hpRow[$k]) !== '') {
                $president[$k] = $hpRow[$k];
            }
        }
        $pp = $hpRow['photo_path'] ?? '';
        if ($pp !== '' && strpos($pp, '..') === false && is_file(__DIR__ . $pp)) {
            $president['photo_url'] = ltrim($pp, '/');
        }
    }
} catch (Exception $e) {
    error_log('Homepage president fetch error: ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guagua National Colleges</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/base-style.css" rel="stylesheet">
    <link href="assets/css/navbar-style.css" rel="stylesheet">
    <link href="assets/css/footer-style.css" rel="stylesheet">
    <link href="assets/css/index-style.css?v=12" rel="stylesheet">
    <link href="assets/css/skeleton-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="icon" type="image/x-icon" href="assets/images/logos/gnc-logo-v1.svg">
    <!-- Opening intro: shown once per browser session. Class is set in <head> so the
         overlay covers the page from the first paint. -->
    <script>
        (function () {
            try {
                var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                if (<?= cePreviewActive() ? 'false && ' : '' ?>!reduce && !sessionStorage.getItem('gncIntroSeen')) {
                    document.documentElement.classList.add('gnc-intro-on');
                }
            } catch (e) {}
        })();
    </script>
    <style>
        #gnc-intro { display: none; }
        html.gnc-intro-on, html.gnc-intro-on body { overflow: hidden; }
        html.gnc-intro-on #gnc-intro { display: block; position: fixed; inset: 0; z-index: 99999; cursor: pointer; }

        /* ---- background: 6 vertical panels that wipe away on exit ---- */
        .gnc-intro-panels { position: absolute; inset: 0; display: flex; }
        .gnc-intro-panels i {
            flex: 1; background: #094024; margin-right: -1px;
            transition: transform .9s cubic-bezier(.76, 0, .24, 1);
        }
        .gnc-intro-panels i:nth-child(even) { background: #07351d; }
        .gnc-intro-panels i:nth-child(1) { transition-delay: 0s; }
        .gnc-intro-panels i:nth-child(2) { transition-delay: .07s; }
        .gnc-intro-panels i:nth-child(3) { transition-delay: .14s; }
        .gnc-intro-panels i:nth-child(4) { transition-delay: .21s; }
        .gnc-intro-panels i:nth-child(5) { transition-delay: .28s; }
        .gnc-intro-panels i:nth-child(6) { transition-delay: .35s; }
        #gnc-intro.is-leaving .gnc-intro-panels i { transform: translateY(-101%); }

        /* ---- foreground content ---- */
        .gnc-intro-ui {
            position: absolute; inset: 0; color: #fff;
            padding: clamp(1.2rem, 4vw, 3rem);
            display: flex; flex-direction: column; justify-content: space-between;
            transition: opacity .4s ease, transform .5s ease;
        }
        #gnc-intro.is-leaving .gnc-intro-ui { opacity: 0; transform: translateY(-30px); }

        .gnc-intro-top { display: flex; align-items: center; gap: .8rem; opacity: 0; animation: gncIFade .8s ease .2s forwards; }
        .gnc-intro-top img { width: 46px; height: 46px; object-fit: contain; }
        .gnc-intro-top span {
            font-family: 'Inter', sans-serif; font-size: .72rem; font-weight: 600;
            letter-spacing: .28em; text-transform: uppercase; color: rgba(255, 255, 255, .75);
        }

        .gnc-intro-words { margin: auto 0; padding-left: clamp(0rem, 3vw, 3rem); }
        .gnc-intro-word {
            display: block; overflow: hidden; padding: .02em 0;
            font-family: 'Bebas Neue', 'Anton', Impact, sans-serif;
            font-size: clamp(3.6rem, 15vw, 11rem); line-height: .9; letter-spacing: .01em;
        }
        .gnc-intro-word b {
            display: block; font-weight: 400; transform: translateY(105%);
            animation: gncIRise .9s cubic-bezier(.2, .8, .2, 1) forwards;
        }
        .gnc-intro-word:nth-child(1) b { animation-delay: .35s; }
        .gnc-intro-word:nth-child(2) b { animation-delay: .6s; color: transparent; -webkit-text-stroke: 2px rgba(255, 255, 255, .9); }
        .gnc-intro-word:nth-child(3) b { animation-delay: .85s; color: #EABA3B; }

        .gnc-intro-bottom { display: flex; align-items: flex-end; justify-content: space-between; gap: 1rem; }
        .gnc-intro-name {
            font-family: 'Noto Serif', serif; font-size: clamp(.8rem, 1.6vw, 1.05rem);
            color: rgba(255, 255, 255, .8); margin: 0 0 .6rem;
            opacity: 0; animation: gncIFade .8s ease 1.3s forwards;
        }
        .gnc-intro-count {
            font-family: 'Bebas Neue', 'Anton', Impact, sans-serif; line-height: .8;
            font-size: clamp(3.4rem, 11vw, 8rem); color: #fff; font-variant-numeric: tabular-nums;
        }
        .gnc-intro-count small { font-size: .38em; color: #EABA3B; margin-left: .1em; }

        .gnc-intro-line {
            position: absolute; left: 0; right: 0; bottom: 0; height: 4px; background: #EABA3B;
            transform: scaleX(var(--p, 0)); transform-origin: left;
        }

        @keyframes gncIRise { to { transform: translateY(0); } }
        @keyframes gncIFade { to { opacity: 1; } }
    </style>
</head>
<body>
    <!-- Opening intro overlay (styles/script in <head> and below; click to skip) -->
    <div id="gnc-intro" aria-hidden="true">
        <div class="gnc-intro-panels"><i></i><i></i><i></i><i></i><i></i><i></i></div>
        <div class="gnc-intro-ui">
            <div class="gnc-intro-top">
                <img src="assets/images/logos/gnc-logo-v1.svg" alt="">
                <span>Guagua National Colleges</span>
            </div>
            <div class="gnc-intro-words">
                <span class="gnc-intro-word"><b>Fides</b></span>
                <span class="gnc-intro-word"><b>Scientia</b></span>
                <span class="gnc-intro-word"><b>Patria.</b></span>
            </div>
            <div class="gnc-intro-bottom">
                <p class="gnc-intro-name">Guagua National Colleges, Inc.</p>
                <div class="gnc-intro-count"><span id="gnc-intro-num">0</span><small>%</small></div>
            </div>
        </div>
        <div class="gnc-intro-line" id="gnc-intro-line"></div>
    </div>


    <!-- Scroll progress bar + back-to-top (decorative, pointer-safe) -->
    <div class="gnc-scroll-progress" id="gncScrollProgress" aria-hidden="true"></div>
    <button type="button" class="gnc-backtop" id="gncBackTop" aria-label="Back to top">
        <i class="bi bi-arrow-up"></i>
    </button>

    <?php $activeSection = 'home'; ?>
    <?php include __DIR__ . '/components/navbar.php'; ?>

    <?php if (!empty($heroSlides)): ?>
    <div class="hero-slideshow">
        <div class="hero-aurora" aria-hidden="true"><span></span><span></span><span></span></div>
        <canvas id="heroParticles" class="hero-particles" aria-hidden="true"></canvas>
        <?php foreach ($heroSlides as $i => $slide):
            // Optional database text fields can be NULL, including in editor previews.
            foreach (['title', 'subtitle', 'btn1_text', 'btn1_link', 'btn2_text', 'btn2_link',
                      'media_path', 'media_path_mobile', 'poster_path', 'media_type', 'status'] as $field) {
                $slide[$field] = (string)($slide[$field] ?? '');
            }
            $titleLines = $slide['title'] ? explode('|', $slide['title'], 2) : [];
            if (cePreviewActive()) $titleLines = array_pad($titleLines, 2, '');   // editor needs both lines to click into
            $hasButtons = !empty($slide['btn1_text']) || !empty($slide['btn2_text']) || cePreviewActive();
            $isFirstSlide = $i === 0;
        ?>
        <div class="hero-slide <?= $isFirstSlide ? 'active' : '' ?>" data-slide="<?= $i ?>"<?= ceMark('data-ve-slide="' . (int)$slide['slide_id'] . '" data-ve-status="' . ceAttr($slide['status']) . '"') ?>>
            <?php if ($slide['media_type'] === 'video'): ?>
                <div class="slide-video-wrap"<?= ceMark('data-ve-bg="video"') ?>>
                    <?php if ($isFirstSlide): ?>
                    <video
                        class="slide-video hero-slide-video"
                        src="<?= htmlspecialchars($slide['media_path']) ?>"
                        <?= $slide['poster_path'] ? 'poster="' . htmlspecialchars($slide['poster_path']) . '"' : '' ?>
                        muted loop playsinline preload="auto">
                    </video>
                    <?php else: ?>
                    <!-- Lazy: only slide 1 needs to load right away. Later slides get their
                         src/poster attached by the IntersectionObserver in the script below,
                         right before the carousel is about to bring them into view. -->
                    <video
                        class="slide-video hero-slide-video lazy-video"
                        data-src="<?= htmlspecialchars($slide['media_path']) ?>"
                        <?= $slide['poster_path'] ? 'data-poster="' . htmlspecialchars($slide['poster_path']) . '"' : '' ?>
                        muted loop playsinline preload="none">
                    </video>
                    <?php endif; ?>
                </div>
            <?php else:
                // Desktop and mobile images are rendered as two completely separate
                // layers. Which one is visible is decided purely by the browser's
                // current viewport width (Bootstrap's d-none/d-md-block utilities),
                // never by server-side device detection — so changing one image can
                // never bleed into the other, and there's no UA string to misread.
                $mobileMediaPath = $slide['media_path_mobile'] ?: $slide['media_path'];
            ?>
                <?php if ($isFirstSlide): ?>
                <!-- First slide loads eagerly, so it gets a skeleton shimmer while
                     the real image decodes. skeleton-loader.js preloads data-bg,
                     then swaps it into background-image and drops .skeleton-hero. -->
                <div class="slide-bg skeleton-hero d-none d-md-block" data-bg="<?= htmlspecialchars($slide['media_path']) ?>"<?= ceMark('data-ve-bg="desktop"') ?>></div>
                <div class="slide-bg skeleton-hero d-md-none" data-bg="<?= htmlspecialchars($mobileMediaPath) ?>"<?= ceMark('data-ve-bg="mobile"') ?>></div>
                <?php else: ?>
                <div class="slide-bg lazy-bg d-none d-md-block" data-bg="<?= htmlspecialchars($slide['media_path']) ?>"<?= ceMark('data-ve-bg="desktop"') ?>></div>
                <div class="slide-bg lazy-bg d-md-none" data-bg="<?= htmlspecialchars($mobileMediaPath) ?>"<?= ceMark('data-ve-bg="mobile"') ?>></div>
                <?php endif; ?>
            <?php endif; ?>
            <?php $overlayOn = ($slide['show_gradient'] === null || (int)$slide['show_gradient'] === 1); ?>
            <?php if ($overlayOn || cePreviewActive()): ?>
            <div class="slide-overlay"<?= ceMark('data-ve-overlay="1"' . ($overlayOn ? '' : ' style="display:none"')) ?>></div>
            <?php endif; ?>

            <?php if ($titleLines || $slide['subtitle'] || $hasButtons || cePreviewActive()): ?>
            <div class="container h-100">
                <div class="hero-content">
                    <?php if (!empty($titleLines[0]) || cePreviewActive()): ?>
                        <h1 class="hero-title-line"<?= ceMark('data-ve-field="slide-title1" data-ve-max="50" data-ve-ph="Headline" data-ve-label="Headline"') ?>><?= htmlspecialchars($titleLines[0]) ?></h1>
                    <?php endif; ?>
                    <?php if (!empty($titleLines[1]) || cePreviewActive()): ?>
                        <h1 class="hero-title-line"<?= ceMark('data-ve-field="slide-title2" data-ve-max="50" data-ve-ph="Second line (optional)" data-ve-label="Headline, second line"') ?>><?= htmlspecialchars($titleLines[1]) ?></h1>
                    <?php endif; ?>
                    <?php if (!empty($slide['subtitle']) || cePreviewActive()): ?>
                        <p class="hero-desc"<?= ceMark('data-ve-field="slide-subtitle" data-ve-max="150" data-ve-multiline="1" data-ve-ph="Add a short description" data-ve-label="Description"') ?>><?= nl2br(htmlspecialchars($slide['subtitle'])) ?></p>
                    <?php endif; ?>
                    <?php if ($hasButtons): ?>
                    <div class="hero-ctas">
                        <?php if (!empty($slide['btn1_text']) || cePreviewActive()): ?>
                            <a href="<?= htmlspecialchars($slide['btn1_link'] ?: '#') ?>" class="btn-enroll<?= (cePreviewActive() && empty($slide['btn1_text'])) ? ' ve-ghost' : '' ?>"<?= ceMark('data-ve-btn="1" data-ve-link="' . ceAttr($slide['btn1_link']) . '"') ?>>
                                <?php if (cePreviewActive()): ?><span data-ve-field="slide-btn1" data-ve-max="30" data-ve-ph="Add a button" data-ve-label="Button text"><?= htmlspecialchars($slide['btn1_text']) ?></span><?php else: ?><?= htmlspecialchars($slide['btn1_text']) ?><?php endif; ?>
                                <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" fill="currentColor" viewBox="0 0 16 16">
                                    <path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8z"/>
                                </svg>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($slide['btn2_text']) || cePreviewActive()): ?>
                            <a href="<?= htmlspecialchars($slide['btn2_link'] ?: '#') ?>" class="btn-explore<?= (cePreviewActive() && empty($slide['btn2_text'])) ? ' ve-ghost' : '' ?>"<?= ceMark('data-ve-btn="2" data-ve-link="' . ceAttr($slide['btn2_link']) . '"') ?>>
                                <?php if (cePreviewActive()): ?><span data-ve-field="slide-btn2" data-ve-max="30" data-ve-ph="Add a second button" data-ve-label="Button text"><?= htmlspecialchars($slide['btn2_text']) ?></span><?php else: ?><?= htmlspecialchars($slide['btn2_text']) ?><?php endif; ?>
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

<?php
    // The three GNC pillars. Rendered as a looping marquee (2 identical groups,
    // each holding the set twice, so the -50% loop is seamless on wide screens).
    $gncPillars = [
        ['name' => 'FIDES',    'bg' => 'fides-bg',    'icon' => 'assets/images/svg/church.svg', 'desc' => "Faith in God and One's Self."],
        ['name' => 'SCIENTIA', 'bg' => 'scientia-bg', 'icon' => 'assets/images/svg/book.svg',   'desc' => 'Search for Truth and Knowledge.'],
        ['name' => 'PATRIA',   'bg' => 'patria-bg',   'icon' => 'assets/images/svg/swords.svg', 'desc' => 'Love for Country.'],
    ];
?>
    <div class="gnc-pillars">
        <div class="pillar-marquee gnc-reveal" id="pillarMarquee" role="group" aria-label="Our pillars: Fides, Scientia, Patria">
            <div class="pillar-viewport">
                <div class="pillar-track">
                    <?php for ($g = 0; $g < 2; $g++): ?>
                    <ul class="pillar-group">
                        <?php for ($rep = 0; $rep < 2; $rep++): foreach ($gncPillars as $p):
                            $dup = ($g > 0 || $rep > 0); ?>
                        <li class="pillar-word<?= $dup ? ' is-dup' : '' ?>"<?= $dup ? ' aria-hidden="true"' : '' ?> title="<?= htmlspecialchars($p['desc']) ?>">
                            <span class="pillar-badge <?= $p['bg'] ?>">
                                <img src="<?= $p['icon'] ?>" alt="" class="pillar-icon-img" loading="lazy" decoding="async">
                            </span>
                            <span class="pillar-label">
                                <span class="pillar-text"><?= $p['name'] ?></span>
                                <span class="pillar-desc"><?= htmlspecialchars($p['desc']) ?></span>
                            </span>
                        </li>
                        <?php endforeach; endfor; ?>
                    </ul>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
    </div>

    <section class="gnc-news" id="announcements">
        <div class="container gnc-news-wrap">
            <div class="gnc-news-head gnc-reveal">
                <h2 class="gnc-news-heading"><i class="bi <?= homeIconClass('news_icon') ?> gnc-news-icon"<?= homeIconMark('news_icon') ?>></i><?= homeTextHtml('news_heading') ?><span<?= homeTextMark('news_subheading', true) ?>><?= homeTextBr('news_subheading') ?></span></h2>
                <p class="gnc-news-sub"<?= homeTextMark('news_intro') ?>><?= htmlspecialchars(homeText('news_intro')) ?></p>
            </div>

            <?php if (!empty($pinnedFeedItems)): ?>
            <?php $pinCount = count($pinnedFeedItems); ?>
            <div class="gnc-pin" id="gncPin" data-count="<?= $pinCount ?>" data-interval="7000" role="region" aria-roledescription="carousel" aria-label="Pinned news and announcements">
                <div class="gnc-pin-stage">
                    <?php foreach ($pinnedFeedItems as $i => $pin):
                        $isFbPin = $pin['source'] === 'facebook';
                        $pinTitle = trim((string)($pin['title'] ?? ''));
                        if ($pinTitle === '') { $pinTitle = $isFbPin ? 'Facebook Update' : 'Announcement'; }
                        $isNewsPin = !$isFbPin && $pin['type_label'] === 'News';
                        $pinKind   = $isFbPin ? 'facebook' : ($isNewsPin ? 'news' : 'announcement');
                        $pinKindLabel = $isFbPin ? 'Facebook Post' : ($isNewsPin ? 'News' : 'Announcement');
                        $pinKindIcon  = $isFbPin ? 'bi-facebook' : ($isNewsPin ? 'bi-newspaper' : 'bi-megaphone-fill');
                        // plain-text description (CSS cuts it off with "..." when it is too long)
                        $pinDesc = trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(preg_replace('/<\/(p|div|li|h[1-6])>|<br\s*\/?>/i', ' ', (string)($pin['html'] ?? ''))), ENT_QUOTES, 'UTF-8')));
                    ?>
                    <article class="gnc-pin-slide<?= $i === 0 ? ' is-active' : '' ?>" data-index="<?= $i ?>" aria-label="<?= ($i + 1) ?> of <?= $pinCount ?>"<?= (!$isFbPin && !empty($pin['id'])) ? ceManageMark('data-ve-post="' . $pinKind . '" data-ve-id="' . (int)$pin['id'] . '" data-ve-label="' . htmlspecialchars($pinTitle, ENT_QUOTES) . '"') : '' ?>>
                        <?php if (!empty($pin['image_path'])): ?>
                        <img class="gnc-pin-img" src="<?= htmlspecialchars($pin['image_path']) ?>" alt="" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>" decoding="async">
                        <?php else: ?>
                        <div class="gnc-pin-fallback">
                            <i class="bi <?= $isFbPin ? 'bi-facebook' : ($pin['type_label'] === 'News' ? 'bi-newspaper' : 'bi-megaphone') ?>"></i>
                        </div>
                        <?php endif; ?>
                        <div class="gnc-pin-caption">
                            <span class="gnc-pin-kind gnc-pin-kind--<?= $pinKind ?>"><i class="bi <?= $pinKindIcon ?>"></i> <?= $pinKindLabel ?></span>
                            <h3 class="gnc-pin-title"><?= htmlspecialchars($pinTitle) ?></h3>
                            <?php if ($pinDesc !== ''): ?>
                            <p class="gnc-pin-desc"><?= htmlspecialchars($pinDesc) ?></p>
                            <?php endif; ?>
                            <a href="<?= htmlspecialchars($pin['link']) ?>"<?= $isFbPin ? ' target="_blank" rel="noopener"' : '' ?> class="gnc-pin-more">
                                Learn more <span aria-hidden="true">&#9655;</span>
                            </a>
                        </div>
                    </article>
                    <?php endforeach; ?>

                    <?php if ($pinCount > 1): ?>
                    <button type="button" class="gnc-pin-arrow gnc-pin-prev" aria-label="Previous"><i class="bi bi-arrow-left"></i></button>
                    <button type="button" class="gnc-pin-arrow gnc-pin-next" aria-label="Next"><i class="bi bi-arrow-right"></i></button>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (empty($publicAnnouncements)): ?>
                <p class="gnc-news-empty">No announcements have been posted yet. Please check back soon.</p>
            <?php else: ?>
                <?php foreach (array_values($publicAnnouncements) as $idx => $item):
                    $isFacebook   = $item['source'] === 'facebook';
                    $isNews       = $item['source'] === 'news';
                    $linkTarget   = $isFacebook ? ' target="_blank" rel="noopener"' : '';
                    $fallbackIcon = $isFacebook ? 'bi-facebook' : ($isNews ? 'bi-newspaper' : 'bi-megaphone');
                    $typeLabel    = $isFacebook ? 'From Facebook' : ($isNews ? 'News' : 'Announcement');

                    // Title (shown in a single gold colour)
                    $rawTitle = trim((string)($item['title'] ?? ''));
                    if ($rawTitle === '') { $rawTitle = $isFacebook ? 'Facebook Update' : 'Announcement'; }
                ?>
                <div class="gnc-news-row<?= $idx % 2 === 1 ? ' gnc-news-row--reverse' : '' ?> gnc-reveal"<?= (!$isFacebook && !empty($item['id'])) ? ceManageMark('data-ve-post="' . ($isNews ? 'news' : 'announcement') . '" data-ve-id="' . (int)$item['id'] . '" data-ve-label="' . htmlspecialchars($rawTitle, ENT_QUOTES) . '"') : '' ?>>
                    <div class="gnc-news-img skeleton-wrap">
                        <?php if (!empty($item['image_path'])): ?>
                        <img src="<?= htmlspecialchars($item['image_path']) ?>"
                             alt="<?= htmlspecialchars($item['title'] ?? 'Facebook post') ?>"
                             loading="lazy" decoding="async">
                        <?php else: ?>
                        <div class="gnc-news-img-fallback"><i class="bi <?= $fallbackIcon ?>"></i></div>
                        <?php endif; ?>
                        <span class="gnc-news-shine" aria-hidden="true"></span>
                    </div>
                    <div class="gnc-news-text">
                        <div class="gnc-news-meta">
                            <span class="gnc-news-type"><i class="bi <?= $fallbackIcon ?>"></i> <?= $typeLabel ?></span>
                            <span class="gnc-news-date"><?= date('F d, Y', strtotime($item['date'])) ?></span>
                        </div>
                        <h3 class="gnc-news-title"><?= htmlspecialchars($rawTitle) ?></h3>
                        <?php if ($isNews && !empty($item['author'] ?? null)): ?>
                        <div class="gnc-news-author">By <?= htmlspecialchars($item['author']) ?></div>
                        <?php endif; ?>
                        <p class="gnc-news-desc"><?= htmlspecialchars($item['excerpt']) ?></p>
                        <a href="<?= htmlspecialchars($item['link']) ?>"<?= $linkTarget ?> class="gnc-news-btn">
                            <?= $isFacebook ? 'View on Facebook' : 'Read More' ?>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>

                <div class="gnc-news-all gnc-reveal">
                    <a href="pages/news-and-announcement.php" class="gnc-news-btn">View All Announcements &amp; News</a>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <section class="gnc-cal-sec" id="events">
        <div class="container">
            <div class="gnc-cal-head gnc-reveal">
                <span class="gnc-cal-kicker"><i class="bi <?= homeIconClass('calendar_icon') ?>"<?= homeIconMark('calendar_icon') ?>></i> <?= homeTextHtml('calendar_kicker') ?></span>
                <h2 class="gnc-cal-heading"><?= homeTextHtml('calendar_heading') ?> <span<?= homeTextMark('calendar_accent') ?>><?= htmlspecialchars(homeText('calendar_accent')) ?></span></h2>
                <p class="gnc-cal-sub"<?= homeTextMark('calendar_intro') ?>><?= htmlspecialchars(homeText('calendar_intro')) ?></p>
            </div>

            <div class="gnc-evs gnc-reveal">
                <div class="gnc-evs-head">
                    <span class="gnc-evs-title"><i class="bi bi-calendar-event-fill"></i> <?= homeTextHtml('events_label') ?></span>
                    <?php if ($selectedProgram !== null): ?>
                        <a href="?#events" class="gnc-programs-count text-decoration-none">
                            <?= htmlspecialchars($selectedProgram) ?> &times;
                        </a>
                    <?php endif; ?>
                </div>

                <?php if (empty($upcomingEvents)): ?>
                    <p class="gnc-evs-empty">
                        <?php if ($selectedProgram !== null): ?>
                            No upcoming events for <?= htmlspecialchars($selectedProgram) ?> at this time.
                        <?php else: ?>
                            No upcoming events at this time.
                        <?php endif; ?>
                    </p>
                <?php else: ?>
                <div class="gnc-evs-grid">
                    <?php foreach ($upcomingEvents as $ev):
                        $evStartTs = strtotime($ev['start_date']);
                        $evEndTs   = !empty($ev['end_date']) ? strtotime($ev['end_date']) : null;
                        $stLabel   = evt_timeLabel($ev['start_date']);
                        $enLabel   = evt_timeLabel($ev['end_date'] ?? null);
                        $multiDay  = $evEndTs !== null && date('Y-m-d', $evEndTs) !== date('Y-m-d', $evStartTs);

                        if ($multiDay) {
                            $whenLabel = date('M j', $evStartTs) . ' – ' . date('M j', $evEndTs);
                        } elseif ($stLabel !== '') {
                            $whenLabel = $stLabel . ($enLabel !== '' ? ' - ' . $enLabel : '');
                        } else {
                            $whenLabel = 'All Day';
                        }
                    ?>
                    <div class="gnc-evs-item" style="--evt-color: <?= evt_categoryColor($ev['category_color'] ?? null, $ev['category_name'] ?? null) ?>;"<?= ceManageMark('data-ve-post="event" data-ve-id="' . (int)($ev['event_id'] ?? 0) . '" data-ve-date="' . htmlspecialchars(date('Y-m', $evStartTs), ENT_QUOTES) . '" data-ve-label="' . htmlspecialchars((string)($ev['title'] ?? 'Event'), ENT_QUOTES) . '"') ?>>
                        <div class="gnc-datebox">
                            <span class="gnc-datebox-day"><?= date('j', $evStartTs) ?></span>
                            <span class="gnc-datebox-mon"><?= strtoupper(date('M', $evStartTs)) ?></span>
                        </div>
                        <div class="gnc-evs-info">
                            <div class="gnc-evs-name"><?= htmlspecialchars($ev['title']) ?></div>
                            <div class="gnc-evs-meta"><i class="bi bi-clock"></i> <?= htmlspecialchars($whenLabel) ?></div>
                            <?php if (!empty($ev['location'])): ?>
                            <div class="gnc-evs-meta"><i class="bi bi-geo-alt"></i> <?= htmlspecialchars($ev['location']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="row g-4">
                <div class="col-lg-7 gnc-reveal">
                    <div class="gnc-cal-card">
                        <div class="gnc-cal-header">
                            <a href="?evt_month=<?= $evtPrevMonth ?>&evt_year=<?= $evtPrevYear ?>#events" class="gnc-cal-nav">
                                <i class="bi bi-chevron-left"></i>
                            </a>
                            <span class="gnc-cal-title"><?= htmlspecialchars(date('F', $evtFirstOfMonth)) ?> <span><?= htmlspecialchars(date('Y', $evtFirstOfMonth)) ?></span></span>
                            <a href="?evt_month=<?= $evtNextMonth ?>&evt_year=<?= $evtNextYear ?>#events" class="gnc-cal-nav">
                                <i class="bi bi-chevron-right"></i>
                            </a>
                        </div>

                        <div class="gnc-cal-grid">
                            <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dow): ?>
                                <div class="gnc-cal-dow"><?= $dow ?></div>
                            <?php endforeach; ?>

                            <?php for ($i = 0; $i < $evtStartWeekday; $i++): ?>
                                <div class="gnc-cal-cell gnc-cal-empty"></div>
                            <?php endfor; ?>

                            <?php for ($day = 1; $day <= $evtDaysInMonth; $day++):
                                $isToday = ($day == (int)date('j') && $evtMonth == (int)date('n') && $evtYear == (int)date('Y'));
                                $dayEvents = $publicEventsByDay[$day] ?? [];
                                $dayHasEvent = !empty($dayEvents);
                                $dayTitles = $dayHasEvent ? implode("\n", array_map(function ($e) {
                                    $t = evt_timeLabel($e['start_date']);
                                    return $e['title'] . ($t !== '' ? ' (' . $t . ')' : '');
                                }, $dayEvents)) : '';
                                $visibleEvents = array_slice($dayEvents, 0, 2);
                                $extraCount = count($dayEvents) - count($visibleEvents);
                            ?>
                                <div class="gnc-cal-cell<?= $isToday ? ' gnc-cal-today' : '' ?><?= $dayHasEvent ? ' gnc-cal-hasevent' : '' ?>"
                                     <?= $dayHasEvent ? 'title="' . htmlspecialchars($dayTitles) . '"' : '' ?>>
                                    <span class="gnc-cal-daynum"><?= $day ?></span>
                                    <?php if ($dayHasEvent): ?>
                                        <div class="gnc-cal-events">
                                            <?php foreach ($visibleEvents as $e):
                                                $c = evt_categoryColor($e['category_color'] ?? null, $e['category_name'] ?? null);
                                            ?>
                                                <span class="gnc-cal-event-chip" style="background:<?= $c ?>22; color:<?= $c ?>;">
                                                    <?= htmlspecialchars($e['title']) ?>
                                                </span>
                                            <?php endforeach; ?>
                                            <?php if ($extraCount > 0): ?>
                                                <span class="gnc-cal-event-more">+<?= $extraCount ?> more</span>
                                            <?php endif; ?>
                                        </div>
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

                <div class="col-lg-5 gnc-reveal">
                    <div class="gnc-events-side-stack">
                    <div class="gnc-upcoming-card">
                        <div class="gnc-upcoming-header d-flex align-items-center justify-content-between">
                            <span><i class="bi bi-mortarboard-fill"></i> Programs</span>
                            <?php if (!empty($homePrograms)): ?>
                                <span class="gnc-programs-count"><?= count($homePrograms) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="gnc-programs-list">
                            <?php if (empty($homePrograms)): ?>
                                <p class="text-muted px-3 py-4 mb-0">No programs available at this time.</p>
                            <?php else: foreach ($homePrograms as $prog):
                                $code      = $prog['program_code'];
                                $full      = $programFullNames[$code] ?? null;
                                $isActive  = ($selectedProgram === $code);
                                // Clicking a program filters the Upcoming Events card above to
                                // that exact program (?program=CODE), on this same page — no
                                // redirect anywhere. Clicking an already-active program clears
                                // the filter back to the general view.
                                $progUrl = $isActive ? '?#events' : '?program=' . rawurlencode($code) . '#events';
                            ?>
                                <a href="<?= htmlspecialchars($progUrl) ?>" class="gnc-programs-list-item text-decoration-none<?= $isActive ? ' gnc-programs-list-item-active' : '' ?>">
                                    <span class="gnc-programs-code"><?= htmlspecialchars($code) ?></span>
                                    <?php if ($full): ?>
                                        <span class="gnc-programs-list-fullname"><?= htmlspecialchars($full) ?></span>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; endif; ?>
                        </div>
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
        <div class="container gnc-prog-wrap">
            <div class="gnc-prog-head gnc-reveal">
                <h2 class="gnc-prog-heading"><i class="bi <?= homeIconClass('programs_icon') ?> gnc-prog-icon"<?= homeIconMark('programs_icon') ?>></i><?= homeTextHtml('programs_heading') ?><span<?= homeTextMark('programs_subheading', true) ?>><?= homeTextBr('programs_subheading') ?></span></h2>
                <p class="gnc-prog-sub"<?= homeTextMark('programs_intro') ?>><?= htmlspecialchars(homeText('programs_intro')) ?></p>
            </div>

            <div class="gnc-prog-row gnc-reveal">
                <div class="gnc-prog-img lazy-bg" data-bg="<?= htmlspecialchars(homeText('programs_1_image')) ?>" role="img" aria-label="<?= htmlspecialchars(homeText('programs_1_title')) ?>"<?= ceMark('data-ve-img="home-image" data-ve-key="programs_1_image" data-ve-label="Program 1 picture"') ?>></div>
                <div class="gnc-prog-text">
                    <h3 class="gnc-prog-title"<?= homeTextMark('programs_1_title') ?>><?= htmlspecialchars(homeText('programs_1_title')) ?></h3>
                    <p class="gnc-prog-desc"<?= homeTextMark('programs_1_text') ?>><?= htmlspecialchars(homeText('programs_1_text')) ?></p>
                    <a href="<?= htmlspecialchars(homeText('programs_1_link')) ?>" class="gnc-prog-btn"<?= ceMark('data-ve-btn="1" data-ve-linkkey="programs_1_link" data-ve-link="' . ceAttr(homeText('programs_1_link')) . '"') ?>><?= homeTextHtml('programs_1_button') ?></a>
                </div>
            </div>

            <div class="gnc-prog-row gnc-prog-row--reverse gnc-reveal">
                <div class="gnc-prog-img lazy-bg" data-bg="<?= htmlspecialchars(homeText('programs_2_image')) ?>" role="img" aria-label="<?= htmlspecialchars(homeText('programs_2_title')) ?>"<?= ceMark('data-ve-img="home-image" data-ve-key="programs_2_image" data-ve-label="Program 2 picture"') ?>></div>
                <div class="gnc-prog-text">
                    <h3 class="gnc-prog-title"<?= homeTextMark('programs_2_title') ?>><?= htmlspecialchars(homeText('programs_2_title')) ?></h3>
                    <p class="gnc-prog-desc"<?= homeTextMark('programs_2_text') ?>><?= htmlspecialchars(homeText('programs_2_text')) ?></p>
                    <a href="<?= htmlspecialchars(homeText('programs_2_link')) ?>" class="gnc-prog-btn"<?= ceMark('data-ve-btn="1" data-ve-linkkey="programs_2_link" data-ve-link="' . ceAttr(homeText('programs_2_link')) . '"') ?>><?= homeTextHtml('programs_2_button') ?></a>
                </div>
            </div>

            <div class="gnc-prog-row gnc-reveal">
                <div class="gnc-prog-img lazy-bg" data-bg="<?= htmlspecialchars(homeText('programs_3_image')) ?>" role="img" aria-label="<?= htmlspecialchars(homeText('programs_3_title')) ?>"<?= ceMark('data-ve-img="home-image" data-ve-key="programs_3_image" data-ve-label="Program 3 picture"') ?>></div>
                <div class="gnc-prog-text">
                    <h3 class="gnc-prog-title"<?= homeTextMark('programs_3_title') ?>><?= htmlspecialchars(homeText('programs_3_title')) ?></h3>
                    <p class="gnc-prog-desc"<?= homeTextMark('programs_3_text') ?>><?= htmlspecialchars(homeText('programs_3_text')) ?></p>
                    <a href="<?= htmlspecialchars(homeText('programs_3_link')) ?>" class="gnc-prog-btn"<?= ceMark('data-ve-btn="1" data-ve-linkkey="programs_3_link" data-ve-link="' . ceAttr(homeText('programs_3_link')) . '"') ?>><?= homeTextHtml('programs_3_button') ?></a>
                </div>
            </div>
        </div>
    </section>

    <section class="gnc-president py-5" id="president">
        <div class="container">
            <div class="row align-items-center g-5">
                <div class="col-lg-5 gnc-reveal">
                    <div class="gnc-president-leaves"></div>

                    <div class="gnc-president-label">
                        <span class="gnc-label-bar"></span>
                        <?= homeTextHtml('president_label') ?>
                    </div>

                    <div class="gnc-president-card">
                        <div class="gnc-president-photo-wrap">
                            <div class="gnc-president-seal"></div>
                            <img src="<?= htmlspecialchars($president['photo_url']) ?>" alt="<?= htmlspecialchars($president['name']) ?>" class="gnc-president-photo" loading="lazy" decoding="async"<?= ceMark('data-ve-img="pres-photo" data-ve-label="President photo"') ?>>
                        </div>
                        <div class="gnc-president-nameplate">
                            <p class="gnc-president-name"<?= ceMark('data-ve-field="pres-name" data-ve-max="255" data-ve-ph="Name" data-ve-label="Name"') ?>><?= htmlspecialchars($president['name']) ?></p>
                            <p class="gnc-president-role"<?= ceMark('data-ve-field="pres-title" data-ve-max="255" data-ve-ph="Title" data-ve-label="Title"') ?>><?= htmlspecialchars($president['title']) ?></p>
                            <p class="gnc-president-org"<?= ceMark('data-ve-field="pres-org" data-ve-max="255" data-ve-ph="Organization" data-ve-label="Organization"') ?>><?= htmlspecialchars($president['org']) ?></p>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7 gnc-reveal">
                    <p class="gnc-president-eyebrow" style="color: #1F5E2C;"<?= ceMark('data-ve-field="pres-eyebrow" data-ve-max="255" data-ve-ph="Small heading" data-ve-label="Small heading"') ?>><?= htmlspecialchars($president['eyebrow']) ?></p>
                    <h2 class="gnc-president-title" style="color: #1F5E2C;"<?= ceMark('data-ve-field="pres-heading" data-ve-max="255" data-ve-ph="Heading" data-ve-label="Heading"') ?>><?= htmlspecialchars($president['heading']) ?></h2>

                    <p class="gnc-president-greeting"<?= ceMark('data-ve-field="pres-greeting" data-ve-max="255" data-ve-ph="Greeting" data-ve-label="Greeting"') ?>><?= htmlspecialchars($president['greeting']) ?></p>

                    <?php if (cePreviewActive()): ?><div data-ve-field="pres-message" data-ve-blocks="1" data-ve-multiline="1" data-ve-max="5000" data-ve-ph="Write the message" data-ve-label="Message"><?php endif; ?>
                    <?php foreach (preg_split('/\R{2,}/', trim($president['message'])) as $para): ?>
                    <p class="gnc-president-quote"><?= nl2br(htmlspecialchars(trim($para))) ?></p>
                    <?php endforeach; ?>
                    <?php if (cePreviewActive()): ?></div><?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="gnc-campus-map py-5" id="campus-map">
        <div class="container">
            <div class="gnc-programs-header lazy-bg gnc-reveal">
                <h2 class="gnc-section-title" style="color: #1F5E2C;"<?= homeTextMark('campus_heading') ?>><?= htmlspecialchars(homeText('campus_heading')) ?></h2>
                <p class="text-muted mb-0"<?= homeTextMark('campus_intro') ?>><?= htmlspecialchars(homeText('campus_intro')) ?></p>
            </div>

            <iframe src="campus_navigator.html"
                    class="gnc-map-frame gnc-reveal"
                    title="GNC Campus Navigator"
                    loading="lazy">
            </iframe>
        </div>
    </section>
    <?php include __DIR__ . '/components/footer.php'; ?>
    <?php include __DIR__ . '/chatbot/widget.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous"></script>
    <?php if (cePreviewActive()): ?>
    <script>
        /* Content Editor preview: stop the slideshow auto-advancing so the slide being edited stays put. */
        (function () { var si = window.setInterval; window.setInterval = function (fn, ms) { return (ms >= 5000) ? 0 : si.apply(window, arguments); }; })();
    </script>
    <?php endif; ?>
    <script src="assets/js/index.js"></script>
    <script src="assets/js/skeleton-loader.js"></script>
    <script>
        /* Opening intro: counter climbs for ~2.6s, finishes when the page has loaded (5s cap), then the panels wipe up. */
        (function () {
            var root = document.documentElement;
            var el = document.getElementById('gnc-intro');
            if (!el || !root.classList.contains('gnc-intro-on')) return;
            try { sessionStorage.setItem('gncIntroSeen', '1'); } catch (e) {}
            var num = document.getElementById('gnc-intro-num');
            var line = document.getElementById('gnc-intro-line');
            var MIN = 2600, CAP = 5000, t0 = performance.now();
            var loaded = document.readyState === 'complete', shown = 0, done = false;
            if (!loaded) window.addEventListener('load', function () { loaded = true; });
            function leave() {
                if (done) return; done = true;
                el.classList.add('is-leaving');
                setTimeout(function () { root.classList.remove('gnc-intro-on'); el.remove(); }, 1500);
            }
            function tick(now) {
                if (done) return;
                var t = now - t0;
                var ready = (loaded && t >= MIN) || t >= CAP;
                var target = ready ? 100 : 92 * (1 - Math.pow(1 - Math.min(t / MIN, 1), 3));
                shown += (target - shown) * (ready ? 0.3 : 0.2);
                if (ready && shown > 99.4) shown = 100;
                num.textContent = Math.round(shown);
                line.style.setProperty('--p', (shown / 100).toFixed(3));
                if (shown >= 100) { setTimeout(leave, 250); return; }
                requestAnimationFrame(tick);
            }
            requestAnimationFrame(tick);
            el.addEventListener('click', leave);   // click to skip
        })();
    </script>

    <script>
    // Make the WHOLE card clickable (not only the "Read More" button).
    // Real links/buttons keep working, and selecting text does not open the card.
    (function () {
        function go(link) {
            if (link.target === '_blank') { window.open(link.href, '_blank', 'noopener'); }
            else { window.location.href = link.href; }
        }
        function wire(cardSelector, linkSelector) {
            document.querySelectorAll(cardSelector).forEach(function (card) {
                var link = card.querySelector(linkSelector);
                if (!link) return;
                card.style.cursor = 'pointer';
                card.addEventListener('click', function (e) {
                    if (e.target.closest('a, button, input, select, textarea, label')) return;
                    if (window.getSelection && window.getSelection().toString()) return;
                    go(link);
                });
            });
        }
        wire('.gnc-news-row', 'a.gnc-news-btn');
        wire('.gnc-pinned-card', 'a.gnc-pinned-btn');
    })();
    </script>

    <script>
    // Pinned news: centre-mode slider (active slide in the middle, neighbours peeking).
    (function () {
        var root = document.getElementById('gncPin');
        if (!root) return;
        var slides = Array.prototype.slice.call(root.querySelectorAll('.gnc-pin-slide'));
        var n = slides.length, active = 0, timer = null;
        var interval = parseInt(root.getAttribute('data-interval'), 10) || 7000;
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        function render() {
            slides.forEach(function (el, i) {
                var o = ((i - active) % n + n) % n;
                if (o > n / 2) o -= n;
                el.style.setProperty('--o', o);
                el.classList.toggle('is-active', o === 0);
                el.classList.toggle('is-hidden', Math.abs(o) > 1);
                el.setAttribute('aria-hidden', o === 0 ? 'false' : 'true');
            });
        }
        function go(i) { active = ((i % n) + n) % n; render(); }
        function start() { if (n > 1 && !reduce) { stop(); timer = setInterval(function () { go(active + 1); }, interval); } }
        function stop() { if (timer) { clearInterval(timer); timer = null; } }

        render();
        if (n < 2) return;

        root.querySelector('.gnc-pin-prev').addEventListener('click', function () { go(active - 1); start(); });
        root.querySelector('.gnc-pin-next').addEventListener('click', function () { go(active + 1); start(); });
        root.addEventListener('mouseenter', stop);
        root.addEventListener('mouseleave', start);
        root.addEventListener('focusin', stop);
        root.addEventListener('focusout', start);
        root.setAttribute('tabindex', '0');
        root.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowLeft') { go(active - 1); start(); }
            if (e.key === 'ArrowRight') { go(active + 1); start(); }
        });

        // click: neighbour -> bring to centre; centre -> open the article
        slides.forEach(function (el, i) {
            el.addEventListener('click', function (e) {
                if (i !== active) { go(i); start(); return; }
                if (e.target.closest('a')) return;
                var link = el.querySelector('a.gnc-pin-more');
                if (!link) return;
                if (link.target === '_blank') window.open(link.href, '_blank', 'noopener');
                else window.location.href = link.href;
            });
        });

        // swipe
        var x0 = null;
        root.addEventListener('touchstart', function (e) { x0 = e.touches[0].clientX; stop(); }, { passive: true });
        root.addEventListener('touchend', function (e) {
            if (x0 === null) return;
            var dx = e.changedTouches[0].clientX - x0; x0 = null;
            if (Math.abs(dx) > 40) go(active + (dx < 0 ? 1 : -1));
            start();
        });
        start();
    })();
    </script>




    <script>
    /* ============================================================
       HOMEPAGE POLISH — decorative animation layer only.
       Everything is feature-detected and wrapped so that a failure
       here can never affect the page's real functionality.
       Respects prefers-reduced-motion throughout.
       ============================================================ */
    (function () {
        'use strict';
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var canHover = window.matchMedia && window.matchMedia('(hover: hover)').matches;
        // Lag-fix build: keep the slideshow, but disable the heaviest hero
        // decorations (canvas particles + scroll parallax). They look nice,
        // but combined with a full-screen video they cause stutter.
        var heroDecorEnabled = false;

        /* ---- 1. Gold-dust particle field over the hero ---- */
        (function () {
            var canvas = document.getElementById('heroParticles');
            if (!canvas || reduce || !heroDecorEnabled) return;
            var ctx = canvas.getContext('2d');
            if (!ctx) return;
            var hero = canvas.parentElement;
            var particles = [], W = 0, H = 0, raf = null;

            function resize() {
                W = canvas.width = hero.offsetWidth;
                H = canvas.height = hero.offsetHeight;
            }
            function spawn() {
                var n = Math.min(70, Math.floor(W * H / 22000));
                particles = [];
                for (var i = 0; i < n; i++) {
                    particles.push({
                        x: Math.random() * W, y: Math.random() * H,
                        r: Math.random() * 2.2 + 0.6,
                        s: Math.random() * 0.45 + 0.12,
                        d: (Math.random() - 0.5) * 0.3,
                        a: Math.random() * 0.5 + 0.15,
                        t: Math.random() * Math.PI * 2
                    });
                }
            }
            function tick() {
                ctx.clearRect(0, 0, W, H);
                for (var i = 0; i < particles.length; i++) {
                    var p = particles[i];
                    p.y -= p.s; p.x += p.d; p.t += 0.02;
                    if (p.y < -10) { p.y = H + 10; p.x = Math.random() * W; }
                    if (p.x < -10) p.x = W + 10; else if (p.x > W + 10) p.x = -10;
                    ctx.beginPath();
                    ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
                    ctx.fillStyle = 'rgba(234,186,59,' + (p.a * (0.6 + 0.4 * Math.sin(p.t))).toFixed(3) + ')';
                    ctx.fill();
                }
                raf = requestAnimationFrame(tick);
            }
            resize(); spawn(); tick();
            window.addEventListener('resize', function () { resize(); spawn(); });
            document.addEventListener('visibilitychange', function () {
                if (document.hidden) { if (raf) cancelAnimationFrame(raf); raf = null; }
                else if (!raf) { tick(); }
            });
        })();

        /* ---- 2. Hero content parallax drift on scroll ---- */
        (function () {
            if (reduce || !heroDecorEnabled) return;
            var hero = document.querySelector('.hero-slideshow');
            if (!hero) return;
            var ticking = false;
            function update() {
                ticking = false;
                var y = window.scrollY;
                if (y >= hero.offsetHeight) return;
                var content = hero.querySelector('.hero-slide.active .hero-content');
                if (!content) return;
                content.style.transform = 'translateY(' + (y * 0.25) + 'px)';
                content.style.opacity = Math.max(0, 1 - y / (hero.offsetHeight * 0.85));
            }
            window.addEventListener('scroll', function () {
                if (!ticking) { ticking = true; requestAnimationFrame(update); }
            }, { passive: true });
        })();

        /* ---- 3. Magnetic hero buttons ---- */
        (function () {
            if (reduce || !canHover) return;
            document.querySelectorAll('.btn-enroll, .btn-explore').forEach(function (btn) {
                btn.addEventListener('mousemove', function (e) {
                    var r = btn.getBoundingClientRect();
                    var x = e.clientX - r.left - r.width / 2;
                    var y = e.clientY - r.top - r.height / 2;
                    btn.style.transform = 'translate(' + (x * 0.18) + 'px,' + (y * 0.3) + 'px)';
                });
                btn.addEventListener('mouseleave', function () { btn.style.transform = ''; });
            });
        })();

        /* ---- 4. Click ripple on CTA-style buttons ---- */
        document.addEventListener('click', function (e) {
            if (reduce) return;
            var btn = e.target.closest('.gnc-news-btn, .gnc-prog-btn, .gnc-pin-more, .btn-enroll, .btn-explore');
            if (!btn) return;
            var rect = btn.getBoundingClientRect();
            var d = Math.max(rect.width, rect.height);
            var span = document.createElement('span');
            span.className = 'gnc-ripple';
            span.style.width = span.style.height = d + 'px';
            span.style.left = (e.clientX - rect.left - d / 2) + 'px';
            span.style.top = (e.clientY - rect.top - d / 2) + 'px';
            btn.appendChild(span);
            setTimeout(function () { span.remove(); }, 700);
        });

        /* ---- 5. Subtle 3D tilt on large cards (desktop only) ---- */
        (function () {
            if (reduce || !canHover) return;
            function tilt(el, max) {
                el.addEventListener('mousemove', function (e) {
                    var r = el.getBoundingClientRect();
                    var px = (e.clientX - r.left) / r.width - 0.5;
                    var py = (e.clientY - r.top) / r.height - 0.5;
                    el.style.transform = 'perspective(900px) rotateX(' + (-py * max) + 'deg) rotateY(' + (px * max) + 'deg) translateY(-4px)';
                });
                el.addEventListener('mouseleave', function () { el.style.transform = ''; });
            }
            /* tilt hover effect removed */
        })();

        /* ---- 6. Count-up animation for the Programs counter ---- */
        (function () {
            if (reduce || !('IntersectionObserver' in window)) return;
            var el = document.querySelector('.gnc-upcoming-header .gnc-programs-count');
            if (!el) return;
            var target = parseInt(el.textContent, 10);
            if (isNaN(target) || target < 2) return;
            var done = false;
            var io = new IntersectionObserver(function (entries) {
                entries.forEach(function (en) {
                    if (!en.isIntersecting || done) return;
                    done = true; io.disconnect();
                    var start = null, dur = 1200;
                    function step(ts) {
                        if (!start) start = ts;
                        var p = Math.min((ts - start) / dur, 1);
                        var eased = 1 - Math.pow(1 - p, 3);
                        el.textContent = Math.round(eased * target);
                        if (p < 1) requestAnimationFrame(step); else el.textContent = target;
                    }
                    requestAnimationFrame(step);
                });
            }, { threshold: 0.6 });
            io.observe(el);
        })();

        /* ---- 7. Scroll progress bar ---- */
        (function () {
            var bar = document.getElementById('gncScrollProgress');
            if (!bar) return;
            function update() {
                var h = document.documentElement;
                var max = h.scrollHeight - h.clientHeight;
                bar.style.width = (max > 0 ? (h.scrollTop / max) * 100 : 0) + '%';
            }
            window.addEventListener('scroll', update, { passive: true });
            window.addEventListener('resize', update);
            update();
        })();

        /* ---- 8. Back-to-top button ---- */
        (function () {
            var btn = document.getElementById('gncBackTop');
            if (!btn) return;
            window.addEventListener('scroll', function () {
                btn.classList.toggle('is-show', window.scrollY > 600);
            }, { passive: true });
            btn.addEventListener('click', function () {
                window.scrollTo({ top: 0, behavior: reduce ? 'auto' : 'smooth' });
            });
        })();
    })();
    </script>


    <script>
    /* ============================================================
       HERO VIDEO PERFORMANCE PATCH
       Keeps the Ken Burns effect off video slides (handled in CSS)
       and keeps hero videos from decoding while the slideshow is
       offscreen. It plays only the active slide video, hydrates at
       most one slide away, pauses all other videos, and pauses the
       whole hero when scrolled out of view. Runs after index.js and
       only reacts to the existing .hero-slide.active class, so it
       does not change slideshow timing, dots, arrows, or lazy-bg.
       ============================================================ */
    (function () {
        'use strict';
        var heroRoot = document.querySelector('.hero-slideshow');
        var heroInView = true;

        function heroSlides() {
            return Array.prototype.slice.call(document.querySelectorAll('.hero-slide'));
        }
        function activeIndex(slides) {
            for (var i = 0; i < slides.length; i++) {
                if (slides[i].classList.contains('active')) return i;
            }
            return 0;
        }
        function circularDistance(i, active, n) {
            var d = Math.abs(i - active) % n;
            return Math.min(d, n - d);
        }
        function hydrateVideo(slide) {
            var v = slide.querySelector('video.hero-slide-video');
            if (!v) return null;
            if (!v.getAttribute('src') && v.getAttribute('data-src')) {
                if (v.getAttribute('data-poster')) v.poster = v.getAttribute('data-poster');
                v.src = v.getAttribute('data-src');
            }
            return v;
        }
        function pauseAllHeroVideos() {
            heroSlides().forEach(function (slide) {
                var v = slide.querySelector('video.hero-slide-video');
                if (v) v.pause();
            });
        }
        function syncHeroVideos() {
            if (!heroInView) return;
            var slides = heroSlides();
            if (!slides.length) return;
            var n = slides.length;
            var active = activeIndex(slides);
            slides.forEach(function (slide, i) {
                // Hydrate at most one slide away so prev/next are ready,
                // but never load the whole deck at once.
                if (circularDistance(i, active, n) > 1) {
                    var off = slide.querySelector('video.hero-slide-video');
                    if (off) off.pause();
                    return;
                }
                var v = hydrateVideo(slide);
                if (!v) return;
                if (i === active) {
                    v.muted = true;
                    var pr = v.play();
                    if (pr && pr.catch) pr.catch(function () {});
                } else {
                    v.pause();
                }
            });
        }

        // Pause the whole slideshow when it scrolls offscreen; resume when
        // it comes back. This is the main fix for "lags after scrolling
        // down and back up" because the active video was still decoding
        // while hidden and then stutters on re-entry.
        if (heroRoot && 'IntersectionObserver' in window) {
            var heroIo = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    heroInView = !!entry.isIntersecting;
                    if (heroInView) syncHeroVideos();
                    else pauseAllHeroVideos();
                });
            }, { threshold: 0 });
            heroIo.observe(heroRoot);
        }

        if (heroRoot && 'MutationObserver' in window) {
            var mo = new MutationObserver(function () { syncHeroVideos(); });
            heroSlides().forEach(function (slide) {
                mo.observe(slide, { attributes: true, attributeFilter: ['class'] });
            });
        }
        document.addEventListener('visibilitychange', function () {
            if (document.hidden) pauseAllHeroVideos();
            else syncHeroVideos();
        });
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', syncHeroVideos);
        } else {
            syncHeroVideos();
        }
    })();
    </script>
</body>
</html>
