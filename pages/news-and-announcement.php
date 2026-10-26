<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/facebook-post-url.php';
require_once __DIR__ . '/../admin/admin-functions.php';
require_once __DIR__ . '/../components/calendar-filters.php';
// Resume an authorized Visual Editor session before the document sends output.
cePreviewActive();

// ---------------------------------------------------------------
// Search helpers
// Facebook posts often use "fancy font" Unicode letters (e.g. styled
// ADVISORY) that look like normal text but aren't, so a plain
// strpos() can't find them. Normalise everything to plain lowercase.
// ---------------------------------------------------------------
if (!function_exists('gncNormalizeForSearch')) {
    function gncNormalizeForSearch(string $text): string {
        if (class_exists('Normalizer')) {
            $n = Normalizer::normalize($text, Normalizer::FORM_KC);
            if ($n !== false) $text = $n;
        }
        // Fallback: map U+1D400-U+1D7FF styled letters/digits to ASCII
        $text = preg_replace_callback('/[\x{1D400}-\x{1D7FF}]/u', function ($m) {
            $cp = mb_ord($m[0], 'UTF-8');
            if ($cp >= 0x1D7CE) return (string)(($cp - 0x1D7CE) % 10);
            if ($cp <= 0x1D6A3) {
                $i = ($cp - 0x1D400) % 52;
                return $i < 26 ? chr(65 + $i) : chr(97 + $i - 26);
            }
            return $m[0];
        }, $text) ?? $text;
        return mb_strtolower($text, 'UTF-8');
    }
}
if (!function_exists('gncSearchText')) {
    /** Build the full searchable text for one feed item (not truncated). */
    function gncSearchText(array $parts): string {
        $text = implode(' ', array_map(fn($p) => (string)($p ?? ''), $parts));
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return gncNormalizeForSearch(preg_replace('/\s+/u', ' ', $text));
    }
}

// ---------------------------------------------------------------
// Read filter/search/pagination params from the query string
// ---------------------------------------------------------------
$activeType   = $_GET['type'] ?? 'all';
$activeType   = in_array($activeType, ['all', 'announcement', 'news'], true) ? $activeType : 'all';
$searchTerm   = trim($_GET['q'] ?? '');

// ---------------------------------------------------------------
// Program filter (replaces the old shared-categories dropdown).
// Values match the announcements.program codes used in the admin panel
// (see getProgramCategories() in admin-functions.php). 'ALL' / '' both
// mean "no restriction" — show every item regardless of program.
// ---------------------------------------------------------------
$newsFilters = publicCalendarFilters($_GET);
$programOptions = $newsFilters['programs'];
$programFullNames = $programOptions;
$calendarDepartments = $newsFilters['departments'];
$selectedDepartment = $newsFilters['department'];
$calendarFilterLabel = $newsFilters['label'];
$programFilter = $newsFilters['program'] ?? 'ALL';

// ---------------------------------------------------------------
// Sort order (newest-first is the default)
// ---------------------------------------------------------------
$sortOrder = $_GET['sort'] ?? 'newest';
$sortOrder = in_array($sortOrder, ['newest', 'oldest'], true) ? $sortOrder : 'newest';

$currentPage  = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
const ITEMS_PER_PAGE = 12; // 2 cards per row

try {
    $db = getDB();
} catch (Exception $e) {
    $db = null;
}

// ---------------------------------------------------------------
// Programs for the sidebar (same source as the homepage "Programs" card)
// ---------------------------------------------------------------
try {
    $homePrograms = $db ? $db->query("
        SELECT p.program_id, p.program_code, p.dept_id, d.slug AS dept_slug
        FROM programs p
        LEFT JOIN departments d ON d.dept_id = p.dept_id
        ORDER BY p.program_code ASC
    ")->fetchAll() : [];
} catch (Exception $e) {
    $homePrograms = [];
}
$selectedProgram = ($programFilter !== 'ALL') ? $programFilter : null;
$programDepartmentIds = array_column($homePrograms, 'dept_id', 'program_code');
$departmentPrograms = [];
if ($selectedDepartment !== null) {
    foreach ($homePrograms as $program) {
        if ($program['dept_slug'] === $selectedDepartment) $departmentPrograms[] = $program['program_code'];
    }
}

// ---------------------------------------------------------------
// Categories for the sidebar (Emergency, Holiday, Reminder, School Event…)
// ---------------------------------------------------------------
try {
    $sidebarCategories = $db ? $db->query("
        SELECT category_id, category_name, category_color
        FROM categories
        ORDER BY category_name ASC
    ")->fetchAll() : [];
} catch (Exception $e) {
    $sidebarCategories = [];
}
$selectedCategory = null;
$selectedCategoryName = '';
$newsCategoryLabels = getNewsCategories();
$newsSidebarCategories = array_diff_key($newsCategoryLabels, array_flip(['Updates', 'Job Opportunities', 'Scholarship']));
$categoryColors = [
    'Holiday'=>'#C99114', 'Job Opportunities'=>'#2878A5', 'Reminder'=>'#D46B28',
    'Scholarship'=>'#8851AD', 'School Event'=>'#26713A', 'Suspension'=>'#BC3030',
    'School News'=>'#236C91', 'Academic'=>'#6452A3', 'Achievements'=>'#B88616',
    'Student Life'=>'#B34880', 'Community Extension'=>'#28856F',
    'Athletics and Sports'=>'#D0652B', 'Others'=>'#657681',
];
foreach ($sidebarCategories as &$category) {
    $category['category_color'] = $categoryColors[$category['category_name']] ?? $category['category_color'];
}
unset($category);
$categoryGroups = [
    'announcement' => ['label'=>'Announcements', 'categories'=>$sidebarCategories],
    'news' => ['label'=>'News', 'categories'=>array_map(static fn($code,$label)=>['category_id'=>$code,'category_name'=>$label,'category_color'=>$categoryColors[$code] ?? '#657681'], array_keys($newsSidebarCategories), array_values($newsSidebarCategories))],
];
if ($activeType === 'news' && is_string($_GET['category'] ?? null) && isset($newsCategoryLabels[$_GET['category']])) {
    $selectedCategory = $_GET['category'];
    $selectedCategoryName = $newsCategoryLabels[$selectedCategory];
} elseif (isset($_GET['category']) && is_scalar($_GET['category']) && ctype_digit((string)$_GET['category'])) {
    foreach ($sidebarCategories as $cat) {
        if ((int)$cat['category_id'] === (int)$_GET['category']) {
            $selectedCategory = (int)$cat['category_id'];
            $selectedCategoryName = $cat['category_name'];
            break;
        }
    }
}

// ---------------------------------------------------------------
// Fetch every published admin announcement
// ---------------------------------------------------------------
$feedItems = [];

if ($db) {
    try {
        $stmt = $db->query("
            SELECT a.announcement_id, a.title, a.slug, a.content, a.published_at, a.created_at,
                a.category_id, a.program, c.category_name, m.file_path as image_path
            FROM announcements a
            LEFT JOIN categories c ON a.category_id = c.category_id
            LEFT JOIN media_library m ON a.featured_image = m.media_id
            WHERE a.status = 'published'
        ");
        foreach ($stmt->fetchAll() as $ann) {
            $excerpt = strip_tags($ann['content']);
            $excerpt = mb_strlen($excerpt) > 140 ? mb_substr($excerpt, 0, 140) . '…' : $excerpt;
            $feedItems[] = [
                'source'        => 'admin',
                'type_key'      => 'announcement',
                'type_label'    => 'Announcement',
                'title'         => $ann['title'],
                'excerpt'       => $excerpt,
                'search_text'   => gncSearchText([$ann['title'], $ann['content'], $ann['category_name'], $ann['program']]),
                'image_path'    => $ann['image_path'],
                'date'          => $ann['published_at'] ?? $ann['created_at'],
                'link'          => '/announcement-article.php?slug=' . urlencode($ann['slug']),
                'category_id'   => $ann['category_id'],
                'category_name' => $ann['category_name'],
                'program'       => $ann['program'],
                'author'        => null,
            ];
        }
    } catch (Exception $e) {
        // leave $feedItems as-is
    }

    // -----------------------------------------------------------
    // Fetch every published News article
    // -----------------------------------------------------------
    try {
        $stmt = $db->query("
            SELECT n.news_id, n.title, n.slug, n.content, n.author, n.published_at, n.created_at,
                n.category_id, n.category AS news_category, c.category_name, m.file_path as image_path
            FROM news n
            LEFT JOIN categories c ON n.category_id = c.category_id
            LEFT JOIN media_library m ON n.featured_image = m.media_id
            WHERE n.status = 'published'
        ");
        foreach ($stmt->fetchAll() as $item) {
            $excerpt = strip_tags($item['content']);
            $excerpt = mb_strlen($excerpt) > 140 ? mb_substr($excerpt, 0, 140) . '…' : $excerpt;
            $feedItems[] = [
                'source'        => 'news',
                'type_key'      => 'news',
                'type_label'    => 'News',
                'title'         => $item['title'],
                'excerpt'       => $excerpt,
                'search_text'   => gncSearchText([$item['title'], $item['content'], $newsCategoryLabels[$item['news_category']] ?? $item['news_category'], $item['author'] ?? '']),
                'image_path'    => $item['image_path'],
                'date'          => $item['published_at'] ?? $item['created_at'],
                'link'          => '/news-article.php?slug=' . urlencode($item['slug']),
                'category_id'   => $item['category_id'],
                'category_name' => $newsCategoryLabels[$item['news_category']] ?? $item['news_category'],
                'news_category' => $item['news_category'],
                'program'       => null, // news isn't tagged to a specific program
                'author'        => $item['author'] ?? null,
            ];
        }
    } catch (Exception $e) {
        // leave $feedItems as-is
    }

    // -----------------------------------------------------------
    // Fetch cached Facebook posts (grouped under "Announcement")
    // -----------------------------------------------------------
    try {
        $rows = $db->query("
            SELECT f.fb_post_id, f.message, f.permalink_url, f.created_time, f.category_id, c.category_name, m.file_path
            FROM fb_posts_cache f
            LEFT JOIN media_library m ON f.image_media_id = m.media_id
            LEFT JOIN categories c ON f.category_id = c.category_id
        ")->fetchAll();

        foreach ($rows as $row) {
            $excerpt = mb_strlen($row['message']) > 140 ? mb_substr($row['message'], 0, 140) . '…' : $row['message'];
            $feedItems[] = [
                'source'        => 'facebook',
                'type_key'      => 'announcement',
                'type_label'    => 'Announcement',
                'title'         => null,
                'excerpt'       => $excerpt,
                'search_text'   => gncSearchText([$row['message']]),
                'image_path'    => $row['file_path'],
                'date'          => $row['created_time'],
                'link'          => gncFacebookPostUrl($row['permalink_url'], $row['fb_post_id'], FB_PAGE_ID),
                'category_id'   => $row['category_id'],
                'category_name' => $row['category_name'],
                'program'       => null, // Facebook posts aren't tagged to a specific program
                'author'        => null,
            ];
        }
    } catch (Exception $e) {
        // leave $feedItems as-is
    }
}

// ---------------------------------------------------------------
// Pinned announcements/news (up to 3, managed via the pin toggle in admin)
// ---------------------------------------------------------------
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
if ($db) {
    try {
        $stmt = $db->query("
            SELECT a.title, a.slug, a.content, a.pinned_at, a.published_at, a.created_at,
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
            SELECT n.title, n.slug, n.content, n.pinned_at, n.published_at, n.created_at,
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
            SELECT f.fb_post_id, f.message, f.permalink_url, f.created_time, f.pinned_at, m.file_path AS image_path
            FROM fb_posts_cache f
            LEFT JOIN media_library m ON f.image_media_id = m.media_id
            WHERE f.is_pinned = 1
        ");
        foreach ($stmt->fetchAll() as $row) {
            $excerpt = mb_strlen($row['message']) > 130 ? mb_substr($row['message'], 0, 130) . '…' : $row['message'];
            $pinnedFeedItems[] = [
                'intro'      => $excerpt,
                'points'     => [],
                'source'     => 'facebook',
                'type_label' => 'Announcement',
                'title'      => null,
                'excerpt'    => $excerpt,
                'image_path' => $row['image_path'],
                'date'       => $row['created_time'],
                'pinned_at'  => $row['pinned_at'],
                'link'       => gncFacebookPostUrl($row['permalink_url'], $row['fb_post_id'], FB_PAGE_ID),
            ];
        }

        usort($pinnedFeedItems, fn($a, $b) => strtotime($b['pinned_at']) <=> strtotime($a['pinned_at']));
        $pinnedFeedItems = array_slice($pinnedFeedItems, 0, 3);
    } catch (Exception $e) {
        $pinnedFeedItems = [];
    }
}

// Only show the pinned slideshow on the first page when no search is active
$showPinned = !empty($pinnedFeedItems) && $currentPage === 1 && $searchTerm === '' && $selectedCategory === null;

// ---------------------------------------------------------------
// Sort by date according to the selected sort order
// ---------------------------------------------------------------
usort($feedItems, function ($a, $b) use ($sortOrder) {
    $cmp = strtotime($b['date']) <=> strtotime($a['date']); // newest-first baseline
    return $sortOrder === 'oldest' ? -$cmp : $cmp;
});

// ---------------------------------------------------------------
// Apply filters: type tab, search box, program dropdown
// ---------------------------------------------------------------
$searchWords = $searchTerm === '' ? [] : array_values(array_filter(preg_split('/\s+/u', gncNormalizeForSearch($searchTerm)), 'strlen'));
$matchesFilters = function ($item, $skipCategory = false, $skipType = false) use ($activeType, $searchWords, $programFilter, $selectedCategory, $selectedDepartment, $departmentPrograms, $calendarDepartments, $programDepartmentIds) {
    if (!$skipType && $activeType !== 'all' && $item['type_key'] !== $activeType) {
        return false;
    }
    if (!$skipCategory && $selectedCategory !== null) {
        $itemCategory = is_string($selectedCategory) ? ($item['news_category'] ?? null) : (int)($item['category_id'] ?? 0);
        if ($itemCategory !== $selectedCategory) return false;
    }
    if ($selectedDepartment !== null) {
        if (!empty($item['dept_id']) && (int)$item['dept_id'] !== (int)$calendarDepartments[$selectedDepartment]['dept_id']) return false;
        $itemProgram = $item['program'] ?? null;
        if ($itemProgram !== 'ALL' && !in_array($itemProgram, $departmentPrograms, true)) return false;
    } elseif ($programFilter !== '' && $programFilter !== 'ALL') {
        if (!empty($item['dept_id']) && (int)$item['dept_id'] !== (int)($programDepartmentIds[$programFilter] ?? 0)) return false;
        // Show items tagged with this exact program, or tagged 'ALL'
        // (applies to every program). Items with no program (news,
        // Facebook posts) are excluded once a specific program is chosen.
        $itemProgram = $item['program'] ?? null;
        if ($itemProgram !== $programFilter && $itemProgram !== 'ALL') {
            return false;
        }
    }
    foreach ($searchWords as $w) {
        // Every word typed must appear somewhere in the item's full text
        // (title, whole content, category, program, author) — not just the excerpt.
        if (mb_strpos($item['search_text'] ?? '', $w) === false) {
            return false;
        }
    }
    return true;
};
$filteredItems = array_values(array_filter($feedItems, fn($it) => $matchesFilters($it)));

// Each group's counts match its destination type, keeping search/audience filters.
$catCounts = [];
foreach ($feedItems as $fi) {
    if (!$matchesFilters($fi, true, true)) continue;
    $group = $fi['type_key'];
    $cid = $group === 'news' ? ($fi['news_category'] ?? '') : (string)($fi['category_id'] ?? '');
    if ($cid !== '') $catCounts[$group][$cid] = ($catCounts[$group][$cid] ?? 0) + 1;
}

// ---------------------------------------------------------------
// Paginate
// ---------------------------------------------------------------
$totalItems = count($filteredItems);
$totalPages = max(1, (int)ceil($totalItems / ITEMS_PER_PAGE));
$currentPage = min($currentPage, $totalPages);
$pageItems = array_slice($filteredItems, ($currentPage - 1) * ITEMS_PER_PAGE, ITEMS_PER_PAGE);

// Helper to rebuild querystring while overriding a param
function na_url(array $overrides = []): string {
    global $programFilter, $selectedCategory, $selectedDepartment;
    $params = array_merge([
        'type'     => $_GET['type'] ?? 'all',
        'q'        => $_GET['q'] ?? '',
        'department' => $selectedDepartment ?? '',
        'program'  => $programFilter !== 'ALL' ? $programFilter : '',
        'category' => $selectedCategory ?? '',
        'sort'     => $_GET['sort'] ?? 'newest',
        'page'     => $_GET['page'] ?? '1',
    ], $overrides);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return 'news-and-announcement.php' . (!empty($params) ? '?' . http_build_query($params) : '') . '#na-results';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News &amp; Announcements</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/base-style.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="/assets/css/news-announcement-style.css?v=2" rel="stylesheet">
    <link href="/assets/css/skeleton-style.css?v=2" rel="stylesheet">
    <link href="/assets/css/calendar-program-widget.css?v=<?= filemtime(__DIR__ . '/../assets/css/calendar-program-widget.css') ?>" rel="stylesheet">
    <style>
        /* Programs sidebar (same look as the homepage card) */
        .na-side-stack{display:flex;flex-direction:column;gap:1.25rem}
        .na-side-stack>*{flex-shrink:0}
        /* Always sticky on desktop. If the sidebar is taller than the screen, it scrolls inside itself instead of being cut off. */
        @media (min-width:992px){.na-side-stack{position:sticky;top:90px;max-height:calc(100vh - 110px);overflow-y:auto;scrollbar-width:none;-ms-overflow-style:none;padding-right:2px}}
        .na-sidebar .gnc-upcoming-card{background:#fff;border:1px solid rgba(9,64,36,.12);border-radius:0;overflow:hidden}
        .na-events-card{background:#fff;border:1px solid rgba(9,64,36,.12);border-radius:0;padding:1.4rem 1.4rem 1.1rem}
        .na-events-title{font-family:'Noto Serif',Georgia,serif;font-weight:700;font-size:1.35rem;color:#0d4a26;margin:0 0 1.1rem}
        .na-event-item{display:flex;align-items:flex-start;gap:1rem;margin-bottom:1.1rem}
        .na-event-badge{flex:0 0 62px;border:1px solid rgba(0,0,0,.08);border-radius:0;overflow:hidden;text-align:center;background:#fff}
        .na-event-month{display:block;background:#094024;color:#fff;font-size:.72rem;font-weight:700;letter-spacing:.04em;padding:.25rem 0}
        .na-event-day{display:block;font-size:1.5rem;font-weight:700;color:#1a1a1a;padding:.2rem 0}
        .na-event-name{font-family:'Noto Serif',Georgia,serif;font-weight:700;color:#0d4a26;line-height:1.3}
        .na-event-date{font-size:.85rem;color:#777;margin-top:.2rem}
        .na-events-link{display:block;text-align:center;font-family:'Noto Serif',Georgia,serif;font-weight:700;color:#0d4a26;text-decoration:none;padding-top:.3rem}
        .na-events-link:hover{color:#094024;text-decoration:underline}
        .na-sidebar .gnc-upcoming-header{background:#094024;color:#fff;padding:.85rem 1.1rem;font-weight:600}
        .na-sidebar .gnc-upcoming-header i{margin-right:.4rem}
        .na-sidebar .gnc-programs-count{background:rgba(255,255,255,.18);color:#fff;border-radius:0;padding:.1rem .6rem;font-size:.78rem}
        .na-sidebar .gnc-programs-list{max-height:340px;overflow-y:auto}
        .na-sidebar .gnc-programs-list{scrollbar-width:none;-ms-overflow-style:none}
        /* Hide the scrollbars in the sidebar (it still scrolls with the mouse wheel / touch) */
        .na-side-stack::-webkit-scrollbar,.na-sidebar .gnc-programs-list::-webkit-scrollbar{display:none;width:0;height:0}
        .na-sidebar .gnc-programs-list-item{display:flex;flex-direction:column;gap:.1rem;padding:.7rem 1.1rem;border-bottom:1px solid rgba(0,0,0,.06);color:#1a1a1a;border-left:4px solid transparent}
        .na-sidebar .gnc-programs-list-item:hover{background:#eaf5ee}
        .na-sidebar .gnc-programs-list-item-active{background:#eaf5ee;border-left-color:#EABA3B}
        .na-sidebar .gnc-programs-code{font-weight:700;color:#094024}
        .na-sidebar .gnc-programs-list-fullname{font-size:.8rem;color:#555}
        .na-sidebar .gnc-cat-item{display:flex;align-items:center;gap:.6rem;padding:.7rem 1.1rem;border-bottom:1px solid rgba(0,0,0,.06);color:#1a1a1a;border-left:4px solid transparent}
        .na-sidebar .gnc-cat-item:last-child{border-bottom:0}
        .na-sidebar .gnc-cat-item:hover{background:#eaf5ee}
        .na-sidebar .gnc-cat-item-active{background:#eaf5ee;border-left-color:#EABA3B}
        .na-sidebar .gnc-cat-dot{width:10px;height:10px;border-radius:0;flex:0 0 10px}
        .na-sidebar .gnc-cat-name{font-weight:600;color:#094024;flex:1}
        .na-sidebar .gnc-cat-count{font-size:.78rem;color:#666;background:rgba(9,64,36,.08);border-radius:0;padding:.05rem .55rem}
        /* Keep the whole sticky sidebar (Programs + Categories + Events) within the
           viewport so Upcoming Events is always visible, not only at the page end.
           The Programs list takes whatever height is left and scrolls inside itself. */
        .na-sidebar .gnc-programs-list{max-height:clamp(150px, calc(100vh - 690px), 340px)}
        .na-sidebar .gnc-cat-item{padding:.5rem 1.1rem}
        .na-sidebar .gnc-upcoming-header{padding:.7rem 1.1rem}
        .na-head .na-toolbar{flex-wrap:wrap;gap:.75rem}
        .na-head .na-filters{flex-wrap:wrap}

        /* --- UX additions --- */
        #na-results{scroll-margin-top:90px}
        /* Keep the sort dropdown above the cards (cards are positioned for the click-anywhere link) */
        .na-head{position:relative;z-index:30}
        .na-head .gnc-dropdown-menu,.na-head .dropdown-menu{z-index:1050}
        .na-card{position:relative}
        /* Whole card is clickable */
        .na-card,.gnc-pinned-card{cursor:pointer}
        .na-card:focus-within{box-shadow:0 0 0 3px rgba(234,186,59,.55)}
        .na-tab:focus-visible,.na-page-link:focus-visible,.na-read-more:focus-visible,.na-filter-btn:focus-visible,
        .gnc-programs-list-item:focus-visible,.gnc-cat-item:focus-visible,.na-chip:focus-visible{outline:3px solid #EABA3B;outline-offset:2px}
        .na-badge-new{display:inline-block;background:#EABA3B;color:#094024;font-size:.68rem;font-weight:700;letter-spacing:.04em;text-transform:uppercase;border-radius:0;padding:.1rem .5rem;margin-left:.5rem;vertical-align:middle}
        .na-summary{display:flex;flex-wrap:wrap;align-items:center;gap:.6rem 1rem;margin-bottom:1rem;font-size:.9rem;color:#555}
        .na-chips{display:flex;flex-wrap:wrap;align-items:center;gap:.4rem}
        .na-chip{display:inline-flex;align-items:center;gap:.4rem;background:#eaf5ee;color:#094024;border:1px solid rgba(9,64,36,.18);border-radius:0;padding:.2rem .7rem;font-size:.8rem;font-weight:600;text-decoration:none}
        .na-chip:hover{background:#d9eee0;color:#094024}
        .na-chip i{font-size:.65rem}
        .na-chip-clear{font-size:.8rem;color:#b3312d;text-decoration:none;font-weight:600;margin-left:.2rem}
        .na-chip-clear:hover{text-decoration:underline}
        .na-note{display:flex;gap:.5rem;align-items:flex-start;background:#fff8e1;border:1px solid rgba(234,186,59,.5);border-radius:0;padding:.6rem .85rem;margin-bottom:1rem;font-size:.85rem;color:#5c4a00}
        .na-note i{margin-top:.15rem}
        .na-empty{text-align:center;padding:3rem 1rem;color:#666}
        .na-empty i{font-size:2.6rem;color:#094024;opacity:.55;display:block;margin-bottom:.6rem}
        .na-empty h3{font-family:'Noto Serif',Georgia,serif;font-size:1.3rem;color:#0d4a26;margin-bottom:.4rem}
        .na-empty-btn{display:inline-block;margin-top:.5rem;background:#094024;color:#fff;border-radius:0;padding:.5rem 1.2rem;text-decoration:none;font-weight:600}
        .na-empty-btn:hover{background:#0d4a26;color:#fff}
        .na-page-gap{align-self:center;padding:0 .3rem;color:#888}
        @media (prefers-reduced-motion:reduce){.na-card{animation:none!important}}
    </style>
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>
    <?php $activeSection = 'news-and-announcement'; ?>
    <?php include_once __DIR__ . '/../components/navbar.php'; ?>

    <section class="newsannouncement-intro">
        <div class="newsannouncement-hero">
            <div class="newsannouncement-hero-inner">
                <p>Latest News and Announcements</p>
                <h1>Stay Updated, Stay Informed</h1>
                <p class="newsannouncement-desc">Get the latest news, events, and important updates from our official Facebook page.</p>
            </div>
        </div>
    </section>

    <section class="na-feed py-5">
        <div class="container">

            <?php if ($showPinned): ?>
            <div class="gnc-pinned-label"><i class="bi bi-pin-angle-fill"></i> Pinned</div>
            <div id="gncPinnedCarousel" class="carousel slide gnc-pinned-carousel mb-5" data-bs-ride="carousel" data-bs-interval="7000">
                <div class="carousel-inner">
                    <?php foreach ($pinnedFeedItems as $i => $pin):
                        $isFbPin = $pin['source'] === 'facebook';
                        $badge   = $isFbPin ? 'From Facebook' : ($pin['type_label'] === 'News' ? 'Featured News' : 'Important Update');
                    ?>
                    <div class="carousel-item <?= $i === 0 ? 'active' : '' ?>">
                        <article class="gnc-pinned-card">
                            <div class="gnc-pinned-media skeleton-wrap">
                                <?php if (!empty($pin['image_path'])): ?>
                                <img src="<?= htmlspecialchars($pin['image_path']) ?>" alt="<?= htmlspecialchars($pin['title'] ?? 'Facebook post') ?>" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>" decoding="async">
                                <?php else: ?>
                                <div class="gnc-pinned-slide-fallback">
                                    <i class="bi <?= $isFbPin ? 'bi-facebook' : ($pin['type_label'] === 'News' ? 'bi-newspaper' : 'bi-megaphone') ?>"></i>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="gnc-pinned-body">
                                <div class="gnc-pinned-content">
                                    <div class="gnc-pinned-tags">
                                        <span class="gnc-pinned-badge"><?= htmlspecialchars($badge) ?></span>
                                    </div>
                                    <?php if (!empty($pin['title'])): ?>
                                    <h3 class="gnc-pinned-title"><?= htmlspecialchars($pin['title']) ?></h3>
                                    <?php endif; ?>
                                    <div class="gnc-pinned-date"><?= date('F d, Y', strtotime($pin['date'])) ?></div>

                                    <?php if (!empty($pin['intro'])): ?>
                                    <p class="gnc-pinned-intro"><?= htmlspecialchars($pin['intro']) ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($pin['points'])): ?>
                                    <ul class="gnc-pinned-list">
                                        <?php foreach ($pin['points'] as $pt): ?>
                                        <li><i class="bi bi-check-circle-fill"></i><span><?= htmlspecialchars($pt) ?></span></li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php endif; ?>
                                </div>

                                <div class="gnc-pinned-footer">
                                    <a href="<?= htmlspecialchars($pin['link']) ?>"<?= $isFbPin ? ' target="_blank" rel="noopener"' : '' ?> class="gnc-pinned-btn">
                                        <?= $isFbPin ? 'View on Facebook' : ($pin['type_label'] === 'News' ? 'Read Full Story' : 'View Full Announcement') ?>
                                        <i class="bi bi-arrow-right"></i>
                                    </a>
                                    <?php if (count($pinnedFeedItems) > 1): ?>
                                    <div class="gnc-pinned-nav">
                                        <button type="button" data-bs-target="#gncPinnedCarousel" data-bs-slide="prev" aria-label="Previous"><i class="bi bi-chevron-left"></i></button>
                                        <button type="button" data-bs-target="#gncPinnedCarousel" data-bs-slide="next" aria-label="Next"><i class="bi bi-chevron-right"></i></button>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="row na-head">
            <div class="col-lg-8">
            <h2 class="na-feed-title"><?= ['all' => 'Latest News &amp; Announcements', 'announcement' => 'Latest Announcements', 'news' => 'Latest News'][$activeType] ?></h2>

            <div class="na-toolbar" id="na-results">
                <div class="na-tabs" role="tablist">
                    <a href="<?= htmlspecialchars(na_url(['type' => 'all', 'category'=>'', 'page' => 1])) ?>"
                       class="na-tab <?= $activeType === 'all' ? 'active' : '' ?>">
                        <i class="bi bi-grid-fill"></i> All
                    </a>
                    <a href="<?= htmlspecialchars(na_url(['type' => 'announcement', 'category'=>'', 'page' => 1])) ?>"
                       class="na-tab <?= $activeType === 'announcement' ? 'active' : '' ?>">
                        <i class="bi bi-megaphone"></i> Announcement
                    </a>
                    <a href="<?= htmlspecialchars(na_url(['type' => 'news', 'category'=>'', 'page' => 1])) ?>"
                       class="na-tab <?= $activeType === 'news' ? 'active' : '' ?>">
                        <i class="bi bi-file-earmark-text"></i> News
                    </a>
                </div>

                <form class="na-filters" method="get" action="news-and-announcement.php#na-results">
                    <input type="hidden" name="type" value="<?= htmlspecialchars($activeType) ?>">
                    <?php if ($selectedDepartment !== null): ?>
                    <input type="hidden" name="department" value="<?= htmlspecialchars($selectedDepartment) ?>">
                    <?php endif; ?>

                    <div class="na-search">
                        <i class="bi bi-search"></i>
                        <input type="search" name="q" id="na-search" placeholder="Search news &amp; announcements" aria-label="Search news and announcements" autocomplete="off" value="<?= htmlspecialchars($searchTerm) ?>">
                    </div>
                    <button type="submit" class="visually-hidden">Search</button>

                    <?php if ($programFilter !== 'ALL'): ?>
                    <input type="hidden" name="program" value="<?= htmlspecialchars($programFilter) ?>">
                    <?php endif; ?>

                    <?php if ($selectedCategory !== null): ?>
                    <input type="hidden" name="category" value="<?= htmlspecialchars((string)$selectedCategory) ?>">
                    <?php endif; ?>

                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sortOrder) ?>">

                    <div class="dropdown na-filter-group">
                        <button type="button" class="na-filter-btn dropdown-toggle"
                                data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-funnel"></i> Sort: <?= $sortOrder === 'oldest' ? 'Oldest' : 'Newest' ?>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end gnc-dropdown-menu">
                            <li><h6 class="dropdown-header">Sort By</h6></li>
                            <li>
                                <button type="submit" name="sort" value="newest"
                                        class="dropdown-item <?= $sortOrder === 'newest' ? 'active' : '' ?>">
                                    <i class="bi bi-sort-down"></i> Newest to Oldest
                                </button>
                            </li>
                            <li>
                                <button type="submit" name="sort" value="oldest"
                                        class="dropdown-item <?= $sortOrder === 'oldest' ? 'active' : '' ?>">
                                    <i class="bi bi-sort-up"></i> Oldest to Newest
                                </button>
                            </li>
                        </ul>
                    </div>
                </form>
            </div>

            </div>
            </div>

            <div class="row g-4 na-layout">
            <div class="col-lg-8">
            <?php
                $rangeFrom = $totalItems ? ($currentPage - 1) * ITEMS_PER_PAGE + 1 : 0;
                $rangeTo   = min($totalItems, $currentPage * ITEMS_PER_PAGE);
                $activeChips = [];
                if ($searchTerm !== '')          $activeChips[] = ['Search: “' . $searchTerm . '”', na_url(['q' => '', 'page' => 1])];
                if ($selectedProgram !== null)   $activeChips[] = ['Program: ' . $selectedProgram, na_url(['program' => '', 'page' => 1])];
                if ($selectedCategory !== null)  $activeChips[] = ['Category: ' . $selectedCategoryName, na_url(['category' => '', 'page' => 1])];
                if ($selectedDepartment !== null) $activeChips[] = ['Department: ' . $calendarFilterLabel, na_url(['department' => '', 'page' => 1])];
                $clearAllUrl = na_url(['department' => '', 'type' => 'all', 'q' => '', 'program' => '', 'category' => '', 'page' => 1]);
            ?>
            <?php if ($totalItems > 0 || $activeChips): ?>
            <div class="na-summary" aria-live="polite">
                <?php if ($totalItems > 0): ?>
                <div class="na-summary-count">Showing <strong><?= $rangeFrom ?>–<?= $rangeTo ?></strong> of <strong><?= $totalItems ?></strong> <?= $totalItems === 1 ? 'result' : 'results' ?></div>
                <?php endif; ?>
                <?php if ($activeChips): ?>
                <div class="na-chips">
                    <?php foreach ($activeChips as [$chipLabel, $chipUrl]): ?>
                    <a href="<?= htmlspecialchars($chipUrl) ?>" class="na-chip" title="Remove this filter"><?= htmlspecialchars($chipLabel) ?> <i class="bi bi-x-lg" aria-hidden="true"></i></a>
                    <?php endforeach; ?>
                    <a href="<?= htmlspecialchars($clearAllUrl) ?>" class="na-chip-clear">Clear all</a>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if ($calendarFilterLabel !== null): ?>
            <div class="na-note"><i class="bi bi-info-circle"></i> Showing posts for <?= htmlspecialchars($calendarFilterLabel) ?> and posts for all programs. News articles and Facebook posts are not tied to a program, so they are hidden while a department or program is selected.</div>
            <?php endif; ?>

            <?php if (empty($pageItems)): ?>
                <div class="na-empty">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <h3>Nothing found</h3>
                    <p><?= ($activeChips || $activeType !== 'all') ? 'Try a different word, or remove some of the filters.' : 'There are no posts yet. Please check back soon.' ?></p>
                    <?php if ($activeChips || $activeType !== 'all'): ?>
                    <a href="<?= htmlspecialchars($clearAllUrl) ?>" class="na-empty-btn">Show everything</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($pageItems as $index => $item):
                        $isFacebook = $item['source'] === 'facebook';
                        $isNews     = $item['source'] === 'news';
                        $linkTarget = $isFacebook ? ' target="_blank" rel="noopener"' : '';
                        $fallbackIcon = $isFacebook ? 'bi-facebook' : ($isNews ? 'bi-newspaper' : 'bi-megaphone');
                        $ribbonClass = $item['type_key'] === 'news' ? 'na-ribbon-news' : 'na-ribbon-announcement';
                        $hasImage = !empty($item['image_path']);
                    ?>
                    <div class="col-md-6 col-xl-4">
                        <!-- Applied inline style to stagger the entrance animation -->
                        <div class="na-card skeleton-group" style="animation-delay: <?= 0.1 + ($index * 0.08) ?>s;">

                            <?php if ($hasImage): ?>
                            <div class="skeleton-wrap na-card-img-wrap">
                                <img src="<?= htmlspecialchars($item['image_path']) ?>"
                                     alt="<?= htmlspecialchars($item['title'] ?? 'Facebook post') ?>"
                                     class="na-card-img"
                                     loading="lazy" decoding="async">
                            </div>
                            <?php else: ?>
                            <div class="na-card-img-wrap na-card-img-fallback">
                                <i class="bi <?= $fallbackIcon ?>"></i>
                            </div>
                            <?php endif; ?>

                            <div class="na-card-body">
                                <?php if ($hasImage): ?>
                                    <div class="skeleton-text-lines">
                                        <?php if (!empty($item['title'])): ?>
                                            <span class="skeleton-line skeleton-line--title skeleton-line--80"></span>
                                        <?php endif; ?>
                                        <span class="skeleton-line skeleton-line--60"></span>
                                        <span class="skeleton-line skeleton-line--100"></span>
                                        <span class="skeleton-line skeleton-line--100"></span>
                                        <span class="skeleton-line skeleton-line--80" style="margin-bottom:1em;"></span>
                                    </div>
                                <?php endif; ?>

                                <div class="<?= $hasImage ? 'skeleton-real-content' : '' ?>">
                                    <?php if (!empty($item['title'])): ?>
                                        <h5 class="na-card-title"><?= htmlspecialchars($item['title']) ?></h5>
                                    <?php endif; ?>

                                    <div class="na-card-meta">
                                        <span class="na-card-date"><i class="bi bi-calendar3"></i> <?= date('F d, Y', strtotime($item['date'])) ?></span>
                                    <?php if ($isFacebook): ?>
                                        <span class="na-badge na-badge-fb"><i class="bi bi-facebook"></i> From Facebook</span>
                                    <?php elseif ($isNews): ?>
                                        <span class="na-badge na-badge-news"><i class="bi bi-newspaper"></i> News</span>
                                    <?php else: ?>
                                        <span class="na-badge na-badge-ann"><i class="bi bi-megaphone"></i> Announcement</span>
                                    <?php endif; ?>
                                    </div>

                                    <?php if ($isNews && !empty($item['author'])): ?>
                                        <div class="na-card-author">By <?= htmlspecialchars($item['author']) ?></div>
                                    <?php endif; ?>

                                    <p class="na-card-excerpt"><?= htmlspecialchars($item['excerpt']) ?></p>

                                    <a href="<?= htmlspecialchars($item['link']) ?>"<?= $linkTarget ?> class="na-read-more stretched-link">
                                        <?= $isFacebook ? 'View on Facebook' : 'Read More' ?>
                                        <i class="bi <?= $isFacebook ? 'bi-box-arrow-up-right' : 'bi-arrow-right' ?>"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                <nav class="na-pagination" aria-label="News and announcements pagination">
                    <?php if ($currentPage > 1): ?>
                        <a href="<?= htmlspecialchars(na_url(['page' => $currentPage - 1])) ?>" class="na-page-link na-page-prev" aria-label="Previous page" rel="prev">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    <?php endif; ?>

                    <?php
                        // Show first, last and the pages around the current one; fold the rest into "…"
                        $pageList = [];
                        for ($p = 1; $p <= $totalPages; $p++) {
                            if ($p === 1 || $p === $totalPages || abs($p - $currentPage) <= 1) {
                                $pageList[] = $p;
                            } elseif (end($pageList) !== '…') {
                                $pageList[] = '…';
                            }
                        }
                    ?>
                    <?php foreach ($pageList as $p): ?>
                        <?php if ($p === '…'): ?>
                        <span class="na-page-gap" aria-hidden="true">…</span>
                        <?php else: ?>
                        <a href="<?= htmlspecialchars(na_url(['page' => $p])) ?>"
                           class="na-page-link <?= $p === $currentPage ? 'active' : '' ?>"
                           aria-label="Page <?= $p ?>"<?= $p === $currentPage ? ' aria-current="page"' : '' ?>><?= $p ?></a>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <?php if ($currentPage < $totalPages): ?>
                        <a href="<?= htmlspecialchars(na_url(['page' => $currentPage + 1])) ?>" class="na-page-link na-page-next" aria-label="Next page" rel="next">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </nav>
                <?php endif; ?>
            <?php endif; ?>
            </div>

            <aside class="col-lg-4 na-sidebar gnc-program-filter-scope">
              <div class="na-side-stack">
                <?php
                $calendarFilterSection = 'na-results';
                $calendarFilterTitle = 'News Categories';
                $calendarFilterUrl = static fn(array $filter) => na_url(array_merge(['department' => '', 'program' => '', 'page' => 1], $filter));
                include __DIR__ . '/../components/calendar-program-filter.php';
                ?>

                <?php if (!empty($categoryGroups)): ?>
                <div class="gnc-upcoming-card">
                    <div class="gnc-upcoming-header d-flex align-items-center justify-content-between">
                        <span><i class="bi bi-tags-fill"></i> Categories</span>
                        <?php if ($selectedCategory !== null): ?>
                            <a href="<?= htmlspecialchars(na_url(['category' => '', 'page' => 1])) ?>" class="gnc-programs-count text-decoration-none">
                                Clear &times;
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php foreach ($categoryGroups as $groupType=>$group): ?>
                    <h3 class="gnc-calendar-filter-heading mb-0" id="na-category-<?= $groupType ?>"><?= htmlspecialchars($group['label']) ?></h3>
                    <div class="gnc-cat-list" role="group" aria-labelledby="na-category-<?= $groupType ?>">
                        <?php foreach ($group['categories'] as $cat):
                            $cid      = $cat['category_id'];
                            $isActive = ($activeType === $groupType && $selectedCategory === $cid);
                            // Clicking a category filters the feed; clicking the active one clears it.
                            $catUrl   = na_url(['type'=>$groupType, 'category' => $isActive ? '' : $cid, 'page' => 1]);
                        ?>
                        <a href="<?= htmlspecialchars($catUrl) ?>" class="gnc-cat-item text-decoration-none<?= $isActive ? ' gnc-cat-item-active' : '' ?>">
                            <span class="gnc-cat-dot" style="background:<?= htmlspecialchars($cat['category_color'] ?: '#094024') ?>"></span>
                            <span class="gnc-cat-name"><?= htmlspecialchars($cat['category_name']) ?></span>
                            <span class="gnc-cat-count"><?= (int)($catCounts[$groupType][$cid] ?? 0) ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

            </div>
            </aside>
            </div>
        </div>
    </section>

    <?php include __DIR__ . '/../components/footer.php'; ?>
    <?php include_once $_SERVER['DOCUMENT_ROOT'] . '/chatbot/widget.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="/assets/js/skeleton-loader.js"></script>
    <script src="/assets/js/calendar-program-widget.js?v=<?= filemtime(__DIR__ . '/../assets/js/calendar-program-widget.js') ?>" defer></script>
    <script>
    // Live search: results update a moment after the visitor stops typing (Enter still works too).
    (function () {
        var input = document.getElementById('na-search');
        if (!input) return;
        var form = input.form, timer = null, last = input.value;
        input.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(function () {
                if (input.value.trim() === last.trim()) return;
                last = input.value;
                try { sessionStorage.setItem('naSearchFocus', '1'); } catch (e) {}
                form.submit();
            }, 500);
        });
        // After the page reloads with results, put the cursor back at the end of what they typed
        try {
            if (sessionStorage.getItem('naSearchFocus') === '1') {
                sessionStorage.removeItem('naSearchFocus');
                input.focus();
                var end = input.value.length;
                input.setSelectionRange(end, end);
            }
        } catch (e) {}
    })();
    </script>
    <script>
    // Make the WHOLE card clickable (not only the "Read More" button).
    // Clicks on real links/buttons keep working normally, and selecting text does not trigger it.
    (function () {
        function go(link) {
            if (link.target === '_blank') { window.open(link.href, '_blank', 'noopener'); }
            else { window.location.href = link.href; }
        }
        function wire(cardSelector, linkSelector) {
            document.querySelectorAll(cardSelector).forEach(function (card) {
                var link = card.querySelector(linkSelector);
                if (!link) return;
                card.addEventListener('click', function (e) {
                    if (e.target.closest('a, button, input, select, textarea, label')) return;
                    var sel = window.getSelection && window.getSelection().toString();
                    if (sel) return;
                    go(link);
                });
            });
        }
        wire('.na-card', 'a.na-read-more');
        wire('.gnc-pinned-card', 'a.gnc-pinned-btn');
    })();
    </script>
</body>
</html>
