<?php
require_once __DIR__ . '/../config/config.php';

// ---------------------------------------------------------------
// Read filter/search/pagination params from the query string
// ---------------------------------------------------------------
$activeType   = $_GET['type'] ?? 'all';                 // all | announcement | news
$activeType   = in_array($activeType, ['all', 'announcement', 'news'], true) ? $activeType : 'all';
$searchTerm   = trim($_GET['q'] ?? '');

// ---------------------------------------------------------------
// Program filter (replaces the old shared-categories dropdown).
// Values match the announcements.program codes used in the admin panel
// (see getProgramCategories() in admin-functions.php). 'ALL' / '' both
// mean "no restriction" — show every item regardless of program.
// ---------------------------------------------------------------
$programOptions = [
    'ALL'     => 'All Programs',
    'NONE'    => 'None',
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
$programFilter = $_GET['program'] ?? 'NONE';
$programFilter = isset($programOptions[$programFilter]) ? $programFilter : 'NONE';

// ---------------------------------------------------------------
// Sort order (newest-first is the default)
// ---------------------------------------------------------------
$sortOrder = $_GET['sort'] ?? 'newest';
$sortOrder = in_array($sortOrder, ['newest', 'oldest'], true) ? $sortOrder : 'newest';

$currentPage  = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
const ITEMS_PER_PAGE = 15;

try {
    $db = getDB();
} catch (Exception $e) {
    $db = null;
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
                'image_path'    => $ann['image_path'],
                'date'          => $ann['published_at'] ?? $ann['created_at'],
                'link'          => 'announcement.php?slug=' . urlencode($ann['slug']),
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
                n.category_id, c.category_name, m.file_path as image_path
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
                'image_path'    => $item['image_path'],
                'date'          => $item['published_at'] ?? $item['created_at'],
                'link'          => 'news.php?slug=' . urlencode($item['slug']),
                'category_id'   => $item['category_id'],
                'category_name' => $item['category_name'],
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
            SELECT f.message, f.permalink_url, f.created_time, m.file_path
            FROM fb_posts_cache f
            LEFT JOIN media_library m ON f.image_media_id = m.media_id
        ")->fetchAll();

        foreach ($rows as $row) {
            $excerpt = mb_strlen($row['message']) > 140 ? mb_substr($row['message'], 0, 140) . '…' : $row['message'];
            $feedItems[] = [
                'source'        => 'facebook',
                'type_key'      => 'announcement',
                'type_label'    => 'Announcement',
                'title'         => null,
                'excerpt'       => $excerpt,
                'image_path'    => $row['file_path'],
                'date'          => $row['created_time'],
                'link'          => $row['permalink_url'] ?? ('https://facebook.com/' . ($_ENV['FB_PAGE_ID'] ?? '')),
                'category_id'   => null,
                'category_name' => null,
                'program'       => null, // Facebook posts aren't tagged to a specific program
                'author'        => null,
            ];
        }
    } catch (Exception $e) {
        // leave $feedItems as-is
    }
}

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
$filteredItems = array_filter($feedItems, function ($item) use ($activeType, $searchTerm, $programFilter) {
    if ($activeType !== 'all' && $item['type_key'] !== $activeType) {
        return false;
    }
    if ($programFilter !== '' && $programFilter !== 'ALL') {
        $itemProgram = $item['program'] ?? null;
        if ($programFilter === 'NONE') {
            // "None" = items with no program tag at all (News, Facebook posts).
            if ($itemProgram !== null) {
                return false;
            }
        } else {
            // Show items tagged with this exact program, or tagged 'ALL'
            // (applies to every program). Items with no program (news,
            // Facebook posts) are excluded once a specific program is chosen.
            if ($itemProgram !== $programFilter && $itemProgram !== 'ALL') {
                return false;
            }
        }
    }
    if ($searchTerm !== '') {
        $haystack = strtolower(($item['title'] ?? '') . ' ' . $item['excerpt']);
        if (strpos($haystack, strtolower($searchTerm)) === false) {
            return false;
        }
    }
    return true;
});
$filteredItems = array_values($filteredItems);

// ---------------------------------------------------------------
// Paginate
// ---------------------------------------------------------------
$totalItems = count($filteredItems);
$totalPages = max(1, (int)ceil($totalItems / ITEMS_PER_PAGE));
$currentPage = min($currentPage, $totalPages);
$pageItems = array_slice($filteredItems, ($currentPage - 1) * ITEMS_PER_PAGE, ITEMS_PER_PAGE);

// Helper to rebuild querystring while overriding a param
function na_url(array $overrides = []): string {
    $params = array_merge([
        'type'     => $_GET['type'] ?? 'all',
        'q'        => $_GET['q'] ?? '',
        'program'  => $_GET['program'] ?? '',
        'sort'     => $_GET['sort'] ?? 'newest',
        'page'     => $_GET['page'] ?? '1',
    ], $overrides);
    $params = array_filter($params, fn($v) => $v !== '' && $v !== null);
    return 'news-and-announcement.php' . (!empty($params) ? '?' . http_build_query($params) : '');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>News & Announcement</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/base-style.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="/assets/css/news-announcement-style.css" rel="stylesheet">
    <link href="/assets/css/skeleton-style.css?v=2" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>
<body>
    <?php $activeSection = 'news-and-announcement'; ?>
    <?php include_once __DIR__ . '/../components/index-nav.php'; ?>

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

            <h2 class="na-feed-title">Latest Announcements</h2>

            <div class="na-toolbar">
                <div class="na-tabs" role="tablist">
                    <a href="<?= htmlspecialchars(na_url(['type' => 'all', 'page' => 1])) ?>"
                       class="na-tab <?= $activeType === 'all' ? 'active' : '' ?>">
                        <i class="bi bi-grid-fill"></i> All
                    </a>
                    <a href="<?= htmlspecialchars(na_url(['type' => 'announcement', 'page' => 1])) ?>"
                       class="na-tab <?= $activeType === 'announcement' ? 'active' : '' ?>">
                        <i class="bi bi-megaphone"></i> Announcement
                    </a>
                    <a href="<?= htmlspecialchars(na_url(['type' => 'news', 'page' => 1])) ?>"
                       class="na-tab <?= $activeType === 'news' ? 'active' : '' ?>">
                        <i class="bi bi-file-earmark-text"></i> News
                    </a>
                </div>

                <form class="na-filters" method="get" action="news-and-announcement.php">
                    <input type="hidden" name="type" value="<?= htmlspecialchars($activeType) ?>">

                    <div class="na-search">
                        <i class="bi bi-search"></i>
                        <input type="text" name="q" placeholder="Search" value="<?= htmlspecialchars($searchTerm) ?>">
                    </div>
                    <button type="submit" class="visually-hidden">Search</button>

                    <div class="na-select">
                        <i class="bi bi-mortarboard"></i>
                        <select name="program" onchange="this.form.submit()">
                            <?php foreach ($programOptions as $code => $label):
                                $optionText = in_array($code, ['ALL', 'NONE'], true) ? $label : "$code - $label";
                            ?>
                                <option value="<?= htmlspecialchars($code) ?>" <?= $programFilter === $code ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($optionText) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sortOrder) ?>">

                    <div class="dropdown na-filter-group">
                        <button type="button" class="na-filter-btn dropdown-toggle"
                                data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-funnel"></i> Filter
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

            <?php if (empty($pageItems)): ?>
                <p class="text-muted text-center py-5">No announcements or news match your search.</p>
            <?php else: ?>
                <div class="row g-4">
                    <?php foreach ($pageItems as $item):
                        $isFacebook = $item['source'] === 'facebook';
                        $isNews     = $item['source'] === 'news';
                        $linkTarget = $isFacebook ? ' target="_blank" rel="noopener"' : '';
                        $fallbackIcon = $isFacebook ? 'bi-facebook' : ($isNews ? 'bi-newspaper' : 'bi-megaphone');
                        $ribbonClass = $item['type_key'] === 'news' ? 'na-ribbon-news' : 'na-ribbon-announcement';
                        $hasImage = !empty($item['image_path']);
                    ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="na-card skeleton-group">
                            <div class="na-ribbon <?= $ribbonClass ?>">
                                <span>|</span> <?= strtoupper($item['type_label']) ?>
                            </div>

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

                                    <div class="na-card-date">
                                        <i class="bi bi-calendar3"></i> <?= date('F d, Y', strtotime($item['date'])) ?>
                                    </div>

                                    <?php if ($isNews && !empty($item['author'])): ?>
                                        <div class="na-card-author">By <?= htmlspecialchars($item['author']) ?></div>
                                    <?php endif; ?>

                                    <p class="na-card-excerpt"><?= htmlspecialchars($item['excerpt']) ?></p>

                                    <hr class="na-card-divider">

                                    <div class="na-card-footer">
                                        <span class="na-card-footer-date"><?= date('F d, Y', strtotime($item['date'])) ?></span>
                                        <?php if ($isFacebook): ?>
                                            <span class="na-badge na-badge-fb"><i class="bi bi-facebook"></i> From Facebook</span>
                                        <?php elseif ($isNews): ?>
                                            <span class="na-badge na-badge-news"><i class="bi bi-newspaper"></i> News</span>
                                        <?php else: ?>
                                            <span class="na-badge na-badge-ann"><i class="bi bi-megaphone"></i> Announcement</span>
                                        <?php endif; ?>
                                    </div>

                                    <a href="<?= htmlspecialchars($item['link']) ?>"<?= $linkTarget ?> class="na-read-more">
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
                        <a href="<?= htmlspecialchars(na_url(['page' => $currentPage - 1])) ?>" class="na-page-link na-page-prev">
                            <i class="bi bi-chevron-left"></i>
                        </a>
                    <?php endif; ?>

                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <a href="<?= htmlspecialchars(na_url(['page' => $p])) ?>"
                        class="na-page-link <?= $p === $currentPage ? 'active' : '' ?>"><?= $p ?></a>
                    <?php endfor; ?>

                    <?php if ($currentPage < $totalPages): ?>
                        <a href="<?= htmlspecialchars(na_url(['page' => $currentPage + 1])) ?>" class="na-page-link na-page-next">
                            <i class="bi bi-chevron-right"></i>
                        </a>
                    <?php endif; ?>
                </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>

    <?php include __DIR__ . '/../components/index-footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" integrity="sha384-I7E8VVD/ismYTF4hNIPjVp/Zjvgyol6VFvRkX/vR+Vc4jQkC+hVqc2pM8ODewa9r" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js" integrity="sha384-G/EV+4j2dNv+tEPo3++6LCgdCROaejBqfUeNjuKAiuXbjrxilcCdDz6ZAVfHWe1Y" crossorigin="anonymous"></script>
    <script src="/assets/js/skeleton-loader.js"></script>
</body>
</html>