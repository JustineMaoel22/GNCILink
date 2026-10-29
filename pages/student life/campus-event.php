<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/facebook-post-url.php';
require_once __DIR__ . '/../../admin/admin-functions.php';
require_once __DIR__ . '/../../components/calendar-filters.php';
$calendarFilters = publicCalendarFilters($_GET);
$calendarDepartments = $calendarFilters['departments'];
$programFullNames = $calendarFilters['programs'];
$selectedDepartment = $calendarFilters['department'];
$selectedProgram = $calendarFilters['program'];
$calendarFilterLabel = $calendarFilters['label'];
$calendarFilterSql = $calendarFilters['condition'];
try {
    $homePrograms = getDB()->query('SELECT program_code FROM programs ORDER BY program_code ASC')->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $homePrograms = [];
}
$calendarCategory = 'School Event';
require __DIR__ . '/../../components/calendar-month.php';
require __DIR__ . '/../../components/calendar-upcoming-data.php';
$calendarUpcomingHeading = 'Upcoming Events';
require_once __DIR__ . '/../../includes/campus-event-posts.php';
$schoolPosts = ['items' => [], 'page' => 1, 'pages' => 1, 'total' => 0];
try {
    $schoolPosts = campusEventPosts(getDB(), $_GET);
} catch (Exception $e) {
    error_log('Campus event posts unavailable: ' . $e->getMessage());
}
$campusH = static fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$calendarScopeQuery = array_filter(['department' => $selectedDepartment, 'program' => $selectedProgram], static fn($value) => $value !== null);
$calendarNavigationParams = array_merge($calendarScopeQuery, ['event_page' => $schoolPosts['page']]);
$calendarFilterBaseQuery = [
    'evt_month' => $evtMonth,
    'evt_year' => $evtYear,
    'event_page' => $schoolPosts['page'],
];
$campusPageUrl = static fn($page) => '?' . http_build_query(array_merge($calendarScopeQuery, [
    'evt_month' => $evtMonth,
    'evt_year' => $evtYear,
    'event_page' => $page,
])) . '#recent-events';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campus Events</title>
    <link href="/assets/css/bootstrap.min.css" rel="stylesheet">
    <link href="/assets/css/navbar-style.css" rel="stylesheet">
    <link href="/assets/css/footer-style.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link
        href="/assets/css/campus-event-style.css?v=<?= filemtime(__DIR__ . '/../../assets/css/campus-event-style.css') ?>"
        rel="stylesheet">
    <link
        href="/assets/css/calendar-program-widget.css?v=<?= filemtime(__DIR__ . '/../../assets/css/calendar-program-widget.css') ?>"
        rel="stylesheet">
    <link href="/assets/css/skeleton-style.css" rel="stylesheet">
    <link rel="icon" type="image/x-icon" href="/assets/images/logos/gnc-logo-v1.svg">
</head>

<body>
    <?php $activeSection = 'student-life'; ?>
    <?php include_once __DIR__ . '/../../components/navbar.php'; ?>

    <section class="campus-event-intro">
        <div class="campus-event-hero">
            <div class="campus-event-hero-inner">
                <nav class="campus-event-breadcrumb" aria-label="breadcrumb">
                    <a class="gnc-breadcrumb-home" href="/" aria-label="Home" title="Home"><svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true" focusable="false"><path d="M8 1 0 7h2v8h4v-5h4v5h4V7h2L8 1Z"/></svg></a><i class="bi bi-chevron-right" aria-hidden="true"></i>
                    <span>Student Life</span>
                    <i class="bi bi-chevron-right"></i>
                    <span class="campus-event-current">Campus Events</span>
                </nav>
                <h1>Campus Events</h1>
                <p>Discover exciting opportunities to engage with our campus community and enhance your student
                    experience.</p>
            </div>
        </div>
    </section>
    <section class="gnc-cal-sec" id="events" aria-labelledby="school-calendar-heading">
        <div class="container">
            <div class="gnc-cal-heading-row">
                <div class="gnc-cal-head">
                    <span class="gnc-cal-kicker"><i class="bi bi-calendar3" aria-hidden="true"></i> Campus Events</span>
                    <h2 class="gnc-cal-heading" id="school-calendar-heading">School <span>Calendar</span></h2>
                    <p class="gnc-cal-sub">Explore school activities, programs, and events throughout the year.</p>
                </div>
                <?php include __DIR__ . '/../../components/calendar-program-filter.php'; ?>
            </div>
            <div id="campus-calendar-content">
                <?php if ($calendarFilterLabel !== null): ?>
                    <p class="campus-calendar-scope">Showing school events for
                        <strong><?= $campusH($calendarFilterLabel) ?></strong>.
                    </p>
                <?php endif; ?>
                <div class="row g-4 gnc-calendar-layout">
                    <div class="col-lg-8">
                        <?php include __DIR__ . '/../../components/calendar-card.php'; ?>
                    </div>
                    <div class="col-lg-4">
                        <?php include __DIR__ . '/../../components/calendar-upcoming-card.php'; ?>
                    </div>
                </div>
                <?php if (empty($publicMonthEvents)): ?>
                    <p class="campus-calendar-empty">No school events scheduled for <?= htmlspecialchars($evtMonthLabel) ?>.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </section>
    <section class="campus-recent-events" id="recent-events" aria-labelledby="recent-events-heading">
        <div class="container">
            <h2 id="recent-events-heading">Recent Events</h2>
            <?php if (empty($schoolPosts['items'])): ?>
                <p class="campus-recent-empty">No school event news or announcements have been published yet.</p>
            <?php else: ?>
                <div class="campus-recent-grid">
                    <?php foreach ($schoolPosts['items'] as $post):
                        $postDate = strtotime($post['post_date']);
                        $isFacebook = $post['post_type'] === 'facebook';
                        $postType = $post['post_type'] === 'news' ? 'News' : 'Announcement';
                        $postTitle = $post['title'];
                        if ($isFacebook) {
                            $postTitle = preg_split('/\R+/u', trim($postTitle))[0] ?: 'School Event';
                            $postUrl = gncFacebookPostUrl($post['permalink_url'], (string) $post['post_id'], FB_PAGE_ID);
                        } else {
                            $postUrl = ($post['post_type'] === 'news' ? '/news-article.php' : '/announcement-article.php') . '?slug=' . rawurlencode($post['slug']);
                        }
                        $postImage = (string) ($post['image_path'] ?? '');
                        if ($postImage !== '' && !preg_match('~^https?://~i', $postImage))
                            $postImage = '/' . ltrim($postImage, '/');
                        ?>
                        <a class="campus-recent-card" href="<?= $campusH($postUrl) ?>" <?= $isFacebook ? ' target="_blank" rel="noopener"' : '' ?>>
                            <?php if ($postImage !== ''): ?>
                                <img class="campus-recent-image" src="<?= $campusH($postImage) ?>" alt="" loading="lazy"
                                    decoding="async">
                            <?php else: ?>
                                <div class="campus-recent-placeholder" aria-hidden="true"><i class="bi bi-calendar-event"></i></div>
                            <?php endif; ?>
                            <time class="campus-recent-date" datetime="<?= date('Y-m-d', $postDate) ?>"
                                aria-label="<?= date('F j, Y', $postDate) ?>">
                                <span><?= strtoupper(date('M', $postDate)) ?></span>
                                <strong><?= date('d', $postDate) ?></strong>
                                <span><?= date('Y', $postDate) ?></span>
                            </time>
                            <div class="campus-recent-content">
                                <span
                                    class="campus-recent-type"><?= $postType ?><?= $isFacebook ? ' · From Facebook' : '' ?></span>
                                <h3><?= $campusH($postTitle) ?></h3>
                                <span class="campus-recent-meta"><i class="bi bi-calendar3" aria-hidden="true"></i>
                                    <?= date('F j, Y', $postDate) ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php if ($schoolPosts['pages'] > 1): ?>
                    <nav class="campus-recent-pagination" aria-label="Recent events pages">
                        <?php if ($schoolPosts['page'] > 1): ?>
                            <a href="<?= $campusH($campusPageUrl($schoolPosts['page'] - 1)) ?>" aria-label="Previous page"
                                rel="prev"><i class="bi bi-chevron-left" aria-hidden="true"></i></a>
                        <?php endif; ?>
                        <?php for ($p = 1; $p <= $schoolPosts['pages']; $p++): ?>
                            <?php if ($p === 1 || $p === $schoolPosts['pages'] || abs($p - $schoolPosts['page']) <= 1): ?>
                                <a href="<?= $campusH($campusPageUrl($p)) ?>" <?= $p === $schoolPosts['page'] ? ' aria-current="page"' : '' ?> aria-label="Page <?= $p ?>"><?= $p ?></a>
                            <?php elseif ($p === $schoolPosts['page'] - 2 || $p === $schoolPosts['page'] + 2): ?>
                                <span aria-hidden="true">&hellip;</span>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <?php if ($schoolPosts['page'] < $schoolPosts['pages']): ?>
                            <a href="<?= $campusH($campusPageUrl($schoolPosts['page'] + 1)) ?>" aria-label="Next page" rel="next"><i
                                    class="bi bi-chevron-right" aria-hidden="true"></i></a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
    <?php include_once __DIR__ . '/../../components/footer.php'; ?>
    <script src="/assets/js/campus-events.js?v=<?= filemtime(__DIR__ . '/../../assets/js/campus-events.js') ?>"
        defer></script>
    <script
        src="/assets/js/calendar-program-widget.js?v=<?= filemtime(__DIR__ . '/../../assets/js/calendar-program-widget.js') ?>"
        defer></script>
</body>

</html>