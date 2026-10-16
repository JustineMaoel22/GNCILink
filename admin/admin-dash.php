<?php
/**
 * GNC Admin Panel – Dashboard
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin/admin-functions.php';

initSession();
requireLogin();

// Get current user for display
$currentUser = getCurrentUser();

$pageTitle = 'Dashboard';
$stats     = getDashboardStats();
$logs      = getActivityLogs(10);

// Website analytics (page visits) - only for roles with the view_analytics permission
$analytics     = null;
$analyticsDays = (int) ($_GET['days'] ?? 14);
if (!in_array($analyticsDays, [7, 14, 30], true)) $analyticsDays = 14;
if (hasPermission('view_analytics')) {
    require_once __DIR__ . '/../components/page-analytics.php';
    try {
        $analyticsDb = getDB();
        $analytics = [
            'summary' => gncAnalyticsSummary($analyticsDb),
            'top'     => gncPopularPages($analyticsDb, 10),
            'by_date' => gncViewsByDate($analyticsDb, $analyticsDays),
        ];
    } catch (Exception $e) {
        error_log('Analytics error: ' . $e->getMessage());
    }
}

// Recent content across all types
try {
    $db = getDB();
    
    // Start with announcements only - simpler and more reliable
    $recentContent = $db->query("
        SELECT 'announcement' AS type, a.announcement_id AS id, a.title, a.status, a.created_at,
            CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) AS author
        FROM announcements a
        LEFT JOIN users u ON a.user_id = u.user_id
        ORDER BY a.created_at DESC
        LIMIT 8
    ")->fetchAll();
    
    // If announcements are empty, try to add news
    if (empty($recentContent)) {
        try {
            $newsContent = $db->query("
                SELECT 'news' AS type, n.news_id AS id, n.title, n.status, n.created_at,
                       CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) AS author
                FROM news n
                LEFT JOIN users u ON n.user_id = u.user_id
                ORDER BY n.created_at DESC
                LIMIT 8
            ")->fetchAll();
            $recentContent = array_merge($recentContent, $newsContent);
        } catch (Exception $e) {
            error_log('Get news error: ' . $e->getMessage());
        }
    }
    
    // Try to add events if table exists
    try {
        $eventContent = $db->query("
            SELECT 'event' AS type, e.event_id AS id, e.title, e.status, e.created_at,
                   CONCAT(COALESCE(u.first_name,''), ' ', COALESCE(u.last_name,'')) AS author
            FROM events e
            LEFT JOIN users u ON e.user_id = u.user_id
            ORDER BY e.created_at DESC
            LIMIT 8
        ")->fetchAll();
        $recentContent = array_merge($recentContent, $eventContent);
    } catch (Exception $e) {
        error_log('Get events error: ' . $e->getMessage());
    }
    
    // Sort combined results by date
    usort($recentContent, function($a, $b) {
        return strtotime($b['created_at']) - strtotime($a['created_at']);
    });
    
    // Limit to 8 results
    $recentContent = array_slice($recentContent, 0, 8);
    
} catch (Exception $e) {
    error_log('Recent content error: ' . $e->getMessage());
    $recentContent = [];
}

// ── Plain-language helpers for the dashboard ─────────────────────
// Current time in the Philippines (Asia/Manila, UTC+8), regardless of server timezone
if (!function_exists('dashNow')) {
    function dashNow(): DateTime {
        return new DateTime('now', new DateTimeZone('Asia/Manila'));
    }
}
if (!function_exists('dashGreeting')) {
    function dashGreeting(): string {
        $h = (int) dashNow()->format('G');
        return ($h >= 5 && $h < 12) ? 'Good morning' : ($h >= 12 && $h < 18 ? 'Good afternoon' : 'Good evening');
    }
}
if (!function_exists('dashTimeAgo')) {
    function dashTimeAgo(?string $dt): string {
        $t = $dt ? strtotime($dt) : false;
        if (!$t) return '';
        $d = time() - $t;
        if ($d < 60)     return 'just now';
        if ($d < 3600)   { $m = (int) floor($d / 60);    return $m . ' minute' . ($m > 1 ? 's' : '') . ' ago'; }
        if ($d < 86400)  { $h = (int) floor($d / 3600);  return $h . ' hour'   . ($h > 1 ? 's' : '') . ' ago'; }
        if ($d < 172800) return 'yesterday';
        if ($d < 604800) { $n = (int) floor($d / 86400); return $n . ' days ago'; }
        return date('M j, Y', $t);
    }
}
// Status words an admin would use (CSS class still uses the raw status)
$statusLabels = [
    'published' => 'Live on website', 'pending' => 'Waiting for approval',
    'draft' => 'Draft', 'rejected' => 'Needs changes', 'archived' => 'Archived',
];
// Log codes -> everyday words
$actionLabels = [
    'LOGIN' => 'Signed in', 'LOGOUT' => 'Signed out', 'CREATE' => 'Added', 'UPDATE' => 'Updated',
    'DELETE' => 'Deleted', 'PUBLISH' => 'Published', 'APPROVE' => 'Approved', 'REJECT' => 'Rejected',
    'OTP-SENT' => 'Code sent',
];
$pendingCount = (int) ($stats['pending_approvals'] ?? 0);

// Shortcut buttons. Edit the URLs here if your pages live somewhere else.
$quickActions = [
    ['Post an Announcement', 'Share a notice with students and staff', '/admin/announcement.php', 'bi-megaphone-fill'],
    ['Write a News Article', 'Publish a story on the website',        '/admin/news.php',         'bi-newspaper'],
    ['Add an Event',         'Put a date on the school calendar',      '/admin/events.php',       'bi-calendar-event-fill'],
];
if (hasPermission('manage_page_content')) {
    $quickActions[] = ['Edit Website Pages', 'Change words and pictures on pages', '/admin/content-edit.php', 'bi-window-desktop'];
}
if (hasPermission('manage_approvals')) {
    $quickActions[] = ['Review Approvals', $pendingCount > 0 ? $pendingCount . ' waiting for you' : 'Nothing waiting right now', '/admin/approvals/', 'bi-check2-circle'];
}

include __DIR__ . '/../components/header-admin.php';
?>

<style>
.dash-section-title { font-size: 1.1rem; font-weight: 700; margin: 1.5rem 0 .75rem; color: #1f2a24; display: flex; align-items: center; gap: .5rem; }
.dash-attention { display: flex; align-items: center; gap: 1rem; flex-wrap: wrap; background: #fff8e1; border: 1px solid #f2dc9b; border-left: 5px solid #e9b93c; border-radius: 12px; padding: 1rem 1.25rem; margin-bottom: 1rem; }
.dash-attention > i { font-size: 1.7rem; color: #c9a227; }
.dash-attention-text { flex: 1; min-width: 220px; font-size: 1rem; line-height: 1.45; color: #4a3b00; }
.dash-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 1rem; margin-bottom: .5rem; }
.dash-action { position: relative; display: flex; flex-direction: column; align-items: flex-start; gap: .9rem; background: #fff; border: 1px solid #e3e8e5; border-bottom: 3px solid var(--gnc-green, #094024); border-radius: 14px; padding: 1.15rem 1.15rem 1.1rem; text-decoration: none; color: #1f2a24; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,.05); transition: box-shadow .2s ease, transform .2s ease, border-color .2s ease; }
.dash-action:hover { border-color: #cfd8d3; border-bottom-color: var(--gnc-green, #094024); box-shadow: 0 10px 22px rgba(9,64,36,.14); transform: translateY(-3px); color: #1f2a24; }
.dash-action:active { transform: translateY(0); box-shadow: 0 2px 4px rgba(0,0,0,.08); }
.dash-action-icon { width: 46px; height: 46px; flex-shrink: 0; border-radius: 12px; background: rgba(9,64,36,.09); color: var(--gnc-green, #094024); display: flex; align-items: center; justify-content: center; font-size: 1.35rem; }
.dash-action-go { position: absolute; top: 1.15rem; right: 1.15rem; width: 30px; height: 30px; border-radius: 50%; background: var(--gnc-green, #094024); color: #fff; display: flex; align-items: center; justify-content: center; font-size: .95rem; transition: transform .2s ease; }
.dash-action:hover .dash-action-go { transform: translateX(3px); }
.dash-action-text { min-width: 0; width: 100%; overflow-wrap: break-word; word-break: normal; hyphens: manual; }
.dash-action strong { display: block; font-size: 1rem; line-height: 1.3; margin-bottom: .2rem; }
.dash-action small { display: block; color: #5d6963; font-size: .85rem; line-height: 1.4; }
.dash-hint { font-size: .8rem; color: #6b756f; margin-top: .1rem; }
.dash-chart-note { font-size: .9rem; color: #444; margin-bottom: 1rem; line-height: 1.5; }
.stat-label { font-size: .9rem; }
.data-card .table { font-size: .92rem; }
.dash-action:focus-visible, .dash-attention a:focus-visible { outline: 3px solid rgba(233,185,60,.9); outline-offset: 2px; }
@media (prefers-reduced-motion: reduce) { .dash-action, .dash-action-go { transition: none; } .dash-action:hover, .dash-action:active, .dash-action:hover .dash-action-go { transform: none; } }

/* ═══════════ Responsive layout ═══════════ */
.page-header h1 { font-size: clamp(1.3rem, 4.5vw, 1.9rem); line-height: 1.25; overflow-wrap: anywhere; }
.page-header p  { overflow-wrap: anywhere; }
.stat-card      { min-width: 0; }
.stat-value     { font-size: clamp(1.4rem, 5vw, 2rem); overflow-wrap: anywhere; }
.data-card      { min-width: 0; }
.data-card-header { flex-wrap: wrap; gap: .5rem; }
.dash-section-row { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .5rem; margin-bottom: .5rem; }
.dash-section-row .dash-section-title { margin-bottom: 0; }
.dash-range .btn { min-height: 36px; display: inline-flex; align-items: center; justify-content: center; }
.dash-page-cell { max-width: 260px; }
.dash-page-cell > div { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 600; }
.dash-post-title { max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.dash-log-row   { display: flex; gap: .75rem; align-items: center; padding: .6rem 1.25rem; border-bottom: 1px solid #f5f5f5; }
.dash-log-badge { font-size: .75rem; flex-shrink: 0; width: 92px; text-align: center; }
.dash-log-text  { flex: 1; min-width: 0; }
.dash-log-desc  { font-size: .9rem; color: #333; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.dash-log-meta  { font-size: .78rem; color: #7a847e; overflow-wrap: anywhere; }
.dash-chart-pad { padding: 1rem 1.25rem 1.1rem; }
.dash-chart-plot { height: 170px; }
.dash-chart-axis-y { position: relative; height: 170px; width: 30px; flex-shrink: 0; font-size: .66rem; color: #aaa; text-align: right; line-height: 1; }
.dash-top-value { font-size: 1.05rem; line-height: 1.3; overflow: hidden; text-overflow: ellipsis; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }

/* Tablets and below */
@media (max-width: 991.98px) {
    .dash-page-cell { max-width: 40vw; }
    .dash-post-title { max-width: 30vw; }
}

/* Large phones / small tablets */
@media (max-width: 767.98px) {
    .dash-section-title { font-size: 1rem; margin-top: 1.25rem; }
    .dash-attention { padding: .9rem 1rem; gap: .75rem; }
    .dash-attention .btn-gnc-gold { width: 100%; justify-content: center; }
    .data-card-header .btn-action,
    .data-card-header .dash-range { flex-shrink: 0; }
    .dash-range { width: 100%; margin-left: 0 !important; }
    .dash-range .btn { flex: 1 1 0; min-height: 40px; }
    .dash-log-row { padding: .6rem 1rem; }
    .dash-chart-pad { padding: .9rem 1rem 1rem; }
    /* Hide the least important table column so rows fit */
    .dash-col-author { display: none; }
}

/* Phones */
@media (max-width: 575.98px) {
    .stat-card { padding: .85rem; }
    .stat-label { font-size: .82rem; }
    .dash-hint  { font-size: .74rem; }
    .dash-log-badge { width: 76px; font-size: .7rem; }
    .dash-log-row { gap: .6rem; }
    .dash-log-desc { white-space: normal; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; }
    .dash-chart-plot, .dash-chart-axis-y { height: 150px; }
    .dash-chart-axis-y { width: 26px; font-size: .6rem; }
    .dash-chart-val { display: none; }          /* numbers above bars get crowded; tooltips still work */
    .dash-col-lastvisit { display: none; }
    .dash-page-cell { max-width: 46vw; }

    /* "Latest Posts" table becomes a stack of cards */
    .dash-posts thead { display: none; }
    .dash-posts, .dash-posts tbody, .dash-posts tr, .dash-posts td { display: block; width: 100%; }
    .dash-posts tr { padding: .75rem 1rem; border-bottom: 1px solid #eee; }
    .dash-posts td { border: 0 !important; padding: .15rem 0 !important; max-width: none !important; white-space: normal !important; }
    .dash-posts td.dash-post-title { font-weight: 600; font-size: 1rem; margin-bottom: .25rem; overflow: visible; text-overflow: clip; }
    .dash-posts td[data-label]::before { content: attr(data-label) ': '; color: #7a847e; font-size: .75rem; font-weight: 600; text-transform: uppercase; letter-spacing: .02em; }
    .dash-posts td.dash-col-author { display: block; }
    .dash-posts td.dash-empty { text-align: center; }
}

/* Very small phones */
@media (max-width: 359.98px) {
    .dash-log-badge { display: none; }
    .data-card .table { font-size: .85rem; }
}

/* Phones: action cards become compact horizontal rows */
@media (max-width: 575px) {
    .dash-actions { grid-template-columns: 1fr; gap: .75rem; }
    .dash-action { flex-direction: row; align-items: center; gap: .8rem; padding: .85rem 1rem; }
    .dash-action-icon { width: 42px; height: 42px; font-size: 1.2rem; }
    .dash-action-text { flex: 1; width: auto; }
    .dash-action-go { position: static; margin-left: auto; width: 28px; height: 28px; flex-shrink: 0; }
}

/* Landscape phones: avoid huge vertical chart */
@media (max-height: 480px) and (orientation: landscape) {
    .dash-chart-plot, .dash-chart-axis-y { height: 130px; }
}
.page-header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
.dash-clock { display: flex; align-items: center; gap: .65rem; background: #fff; border: 1px solid #e5e7eb; border-radius: 12px; padding: .55rem .95rem; box-shadow: 0 1px 3px rgba(0,0,0,.05); }
.dash-clock > i { font-size: 1.4rem; color: #2563eb; }
.dash-clock-time { font-size: 1.25rem; font-weight: 700; line-height: 1.1; font-variant-numeric: tabular-nums; color: #1a1a1a; }
</style>

<!-- ── Page Header ─────────────────────────────────────────────── -->
<div class="page-header">
    <div>
        <h1><span id="dashGreeting"><?= dashGreeting() ?></span>, <?= htmlspecialchars($currentUser['first_name'] ?? 'Admin') ?>!</h1>
        <p><span id="dashDate"><?= dashNow()->format('l, F j, Y') ?></span> &middot; Here is a quick look at your website today.</p>
    </div>
    <div class="dash-clock" title="Philippine Time (PHT, UTC+8)">
        <i class="bi bi-clock"></i>
        <div>
            <div class="dash-clock-time" id="dashClockTime"><?= dashNow()->format('g:i A') ?></div>
        </div>
    </div>
</div>
<script>
(function () {
    var TZ = 'Asia/Manila';
    var timeFmt = new Intl.DateTimeFormat('en-US', { timeZone: TZ, hour: 'numeric', minute: '2-digit', hour12: true });
    var dateFmt = new Intl.DateTimeFormat('en-US', { timeZone: TZ, weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
    var hourFmt = new Intl.DateTimeFormat('en-US', { timeZone: TZ, hour: 'numeric', hour12: false });
    var elTime = document.getElementById('dashClockTime');
    var elDate = document.getElementById('dashDate');
    var elGreet = document.getElementById('dashGreeting');
    function tick() {
        var now = new Date();
        elTime.textContent = timeFmt.format(now);
        elDate.textContent = dateFmt.format(now);
        var h = parseInt(hourFmt.format(now), 10) % 24;
        elGreet.textContent = (h >= 5 && h < 12) ? 'Good morning' : (h >= 12 && h < 18 ? 'Good afternoon' : 'Good evening');
    }
    tick();
    setInterval(tick, 1000);
})();
</script>

<?php if ($pendingCount > 0): ?>
<!-- ── Needs attention ─────────────────────────────────────────── -->
<div class="dash-attention" role="alert">
    <i class="bi bi-bell-fill" aria-hidden="true"></i>
    <div class="dash-attention-text">
        <strong><?= number_format($pendingCount) ?> <?= $pendingCount === 1 ? 'item is' : 'items are' ?> waiting for approval.</strong>
        Nothing goes live on the website until it is approved.
    </div>
    <?php if (hasPermission('manage_approvals')): ?>
    <a href="/admin/approvals/" class="btn-gnc-gold text-decoration-none d-inline-flex align-items-center gap-2" style="min-height:44px;padding:.5rem 1.1rem">
        <i class="bi bi-arrow-right-circle"></i> Review now
    </a>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ── Quick actions ───────────────────────────────────────────── -->
<h2 class="dash-section-title"><i class="bi bi-lightning-charge-fill" aria-hidden="true"></i> What would you like to do?</h2>
<div class="dash-actions">
    <?php foreach ($quickActions as [$qLabel, $qDesc, $qUrl, $qIcon]): ?>
    <a class="dash-action" href="<?= htmlspecialchars($qUrl) ?>">
        <span class="dash-action-icon" aria-hidden="true"><i class="bi <?= $qIcon ?>"></i></span>
        <span class="dash-action-text"><strong><?= htmlspecialchars($qLabel) ?></strong><small><?= htmlspecialchars($qDesc) ?></small></span>
        <span class="dash-action-go" aria-hidden="true"><i class="bi bi-arrow-right"></i></span>
    </a>
    <?php endforeach; ?>
</div>

<h2 class="dash-section-title"><i class="bi bi-grid-fill" aria-hidden="true"></i> Your content at a glance</h2>

<!-- ── Stats Row ───────────────────────────────────────────────── -->
<div class="row g-3 mb-4">
    <!-- Announcements -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card h-100">
            <div class="d-flex align-items-start justify-content-between mb-2">
                <div class="stat-icon green"><i class="bi bi-megaphone-fill"></i></div>
            </div>
            <div class="stat-value"><?= number_format($stats['total_announcements'] ?? 0) ?></div>
            <div class="stat-label">Announcements</div>
            <div class="dash-hint">Notices posted</div>
        </div>
    </div>

    <!-- News -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card h-100">
            <div class="d-flex align-items-start justify-content-between mb-2">
                <div class="stat-icon blue"><i class="bi bi-newspaper"></i></div>
            </div>
            <div class="stat-value"><?= number_format($stats['total_news'] ?? 0) ?></div>
            <div class="stat-label">News Articles</div>
            <div class="dash-hint">Stories posted</div>
        </div>
    </div>

    <!-- Events -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card h-100">
            <div class="d-flex align-items-start justify-content-between mb-2">
                <div class="stat-icon purple"><i class="bi bi-calendar-event-fill"></i></div>
            </div>
            <div class="stat-value"><?= number_format($stats['total_events'] ?? 0) ?></div>
            <div class="stat-label">Events</div>
            <div class="dash-hint">On the calendar</div>
        </div>
    </div>

    <!-- Pending -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card h-100">
            <div class="d-flex align-items-start justify-content-between mb-2">
                <div class="stat-icon gold"><i class="bi bi-hourglass-split"></i></div>
            </div>
            <div class="stat-value"><?= number_format($stats['pending_approvals'] ?? 0) ?></div>
            <div class="stat-label">Waiting for Approval</div>
            <div class="dash-hint">Not live yet</div>
            <?php if (($stats['pending_approvals'] ?? 0) > 0 && hasPermission('manage_approvals')): ?>
            <a href="/admin/approvals/" class="stat-delta up text-decoration-none">
                <i class="bi bi-arrow-right-circle"></i> Review now
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Published -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card h-100">
            <div class="d-flex align-items-start justify-content-between mb-2">
                <div class="stat-icon teal"><i class="bi bi-check-circle-fill"></i></div>
            </div>
            <div class="stat-value"><?= number_format($stats['published_content'] ?? 0) ?></div>
            <div class="stat-label">Live on Website</div>
            <div class="dash-hint">Visitors can see these</div>
        </div>
    </div>

    <!-- Users (Super Admin only) -->
    <?php if (hasPermission('manage_users')): ?>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card h-100">
            <div class="d-flex align-items-start justify-content-between mb-2">
                <div class="stat-icon red"><i class="bi bi-people-fill"></i></div>
            </div>
            <div class="stat-value"><?= number_format($stats['total_users'] ?? 0) ?></div>
            <div class="stat-label">Admin Accounts</div>
            <div class="dash-hint">People who can sign in here</div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php if ($analytics !== null):
    $sum        = $analytics['summary'];
    $topPage    = $sum['most_visited'];
    $byDate     = $analytics['by_date'];
    $maxViews   = max(1, max($byDate ?: [0]));
    $periodSum  = array_sum($byDate);
    $dayCount   = count($byDate);
    // Y-axis: 4 even steps with round numbers (e.g. 0, 2, 4, 6, 8)
    $rawStep    = max(1, $maxViews / 4);
    $mag        = pow(10, floor(log10($rawStep)));
    $norm       = $rawStep / $mag;
    $axisStep   = max(1, (int) round(($norm <= 1 ? 1 : ($norm <= 2 ? 2 : ($norm <= 5 ? 5 : 10))) * $mag));
    $axisMax    = $axisStep * 4;
    $showValues = $dayCount <= 14;                 // numbers above bars
    $barGap     = $dayCount > 14 ? 3 : 8;
    $labelEvery = $dayCount > 14 ? 5 : 1;          // x-axis label spacing
    $i = 0;
    $busiestViews = $byDate ? max($byDate) : 0;
    $busiestDate  = $busiestViews > 0 ? array_search($busiestViews, $byDate) : null;
?>
<!-- ── Website Analytics ───────────────────────────────────────── -->
<div class="dash-section-row">
    <h2 class="dash-section-title"><i class="bi bi-bar-chart-line-fill" aria-hidden="true"></i> Who is visiting your website?</h2>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-xl-3">
        <div class="stat-card h-100">
            <div class="d-flex align-items-start justify-content-between mb-2">
                <div class="stat-icon blue"><i class="bi bi-eye-fill"></i></div>
            </div>
            <div class="stat-value"><?= number_format($sum['total_views']) ?></div>
            <div class="stat-label">Pages Opened</div>
            <div class="dash-hint">All time</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card h-100">
            <div class="d-flex align-items-start justify-content-between mb-2">
                <div class="stat-icon purple"><i class="bi bi-people-fill"></i></div>
            </div>
            <div class="stat-value"><?= number_format($sum['unique_visitors']) ?></div>
            <div class="stat-label">Different Visitors</div>
            <div class="dash-hint">Each person counted once</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card h-100">
            <div class="d-flex align-items-start justify-content-between mb-2">
                <div class="stat-icon green"><i class="bi bi-calendar-day-fill"></i></div>
            </div>
            <div class="stat-value"><?= number_format($sum['today_views']) ?></div>
            <div class="stat-label">Visits Today</div>
            <div class="dash-hint">Since midnight</div>
        </div>
    </div>
    <div class="col-6 col-xl-3">
        <div class="stat-card h-100">
            <div class="d-flex align-items-start justify-content-between mb-2">
                <div class="stat-icon gold"><i class="bi bi-trophy-fill"></i></div>
            </div>
            <div class="stat-value dash-top-value">
                <?= $topPage ? htmlspecialchars($topPage['page_title']) : '—' ?>
            </div>
            <div class="stat-label">
                Most Visited Page<?= $topPage ? ' · ' . number_format($topPage['visit_count']) . ' visits' : '' ?>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <!-- Most Visited Pages -->
    <div class="col-lg-7">
        <div class="data-card h-100">
            <div class="data-card-header">
                <span class="data-card-title"><i class="bi bi-fire me-1"></i> Most Popular Pages</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th style="width:50px">#</th>
                            <th>Page</th>
                            <th class="text-end">Visits</th>
                            <th class="dash-col-lastvisit">Last Visited</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($analytics['top'])): ?>
                        <tr><td colspan="4" class="text-center py-4 text-muted">No page visits recorded yet.</td></tr>
                        <?php else: foreach ($analytics['top'] as $rank => $row): ?>
                        <tr>
                            <td><strong><?= $rank + 1 ?></strong></td>
                            <td class="dash-page-cell">
                                <div title="<?= htmlspecialchars($row['page_url']) ?>">
                                    <?= htmlspecialchars($row['page_title']) ?>
                                </div>
                            </td>
                            <td class="text-end"><strong><?= number_format($row['visit_count']) ?></strong></td>
                            <td class="dash-col-lastvisit" style="font-size:.8rem;white-space:nowrap"><?= htmlspecialchars(gncFormatLastVisited($row['last_visited'])) ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Page visits by date -->
    <div class="col-lg-5">
        <div class="data-card h-100">
            <div class="data-card-header">
                <span class="data-card-title"><i class="bi bi-calendar3 me-1"></i> Visits Each Day</span>
                <div class="ms-auto btn-group btn-group-sm dash-range" role="group" aria-label="Date range">
                    <?php foreach ([7, 14, 30] as $d): ?>
                    <a href="?days=<?= $d ?>" class="btn btn-outline-secondary <?= $analyticsDays === $d ? 'active' : '' ?>">Last <?= $d ?> days</a>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="dash-chart-pad">
                <div class="dash-chart-note">
                    People opened pages <strong><?= number_format($periodSum) ?></strong> times in the last <?= $dayCount ?> days.
                    <?php if ($busiestDate): ?>
                        Busiest day: <strong><?= date('l, M j', strtotime($busiestDate)) ?></strong> (<?= number_format($busiestViews) ?> visits).
                    <?php endif; ?>
                </div>

                <div style="display:flex;gap:8px" role="img" aria-label="Bar chart of page visits per day">
                    <!-- Y axis -->
                    <div class="dash-chart-axis-y">
                        <?php for ($t = 0; $t <= 4; $t++): ?>
                        <span style="position:absolute;right:0;bottom:<?= $t * 25 ?>%;transform:translateY(50%)"><?= number_format($axisStep * $t) ?></span>
                        <?php endfor; ?>
                    </div>

                    <!-- Plot area -->
                    <div style="flex:1;min-width:0">
                        <div class="dash-chart-plot" style="position:relative;border-bottom:1px solid #d9d9d9;border-left:1px solid #d9d9d9">
                            <?php for ($t = 1; $t <= 4; $t++): ?>
                            <div style="position:absolute;left:0;right:0;bottom:<?= $t * 25 ?>%;border-top:1px dashed #ececec"></div>
                            <?php endfor; ?>

                            <div style="position:absolute;top:0;left:0;right:0;bottom:0;display:flex;align-items:flex-end;gap:<?= $barGap ?>px;padding:0 <?= $barGap ?>px">
                                <?php foreach ($byDate as $date => $views): ?>
                                <div style="flex:1;min-width:0;height:100%;display:flex;align-items:flex-end;justify-content:center"
                                     title="<?= htmlspecialchars(date('M j, Y', strtotime($date))) ?>: <?= number_format($views) ?> visits">
                                    <?php if ($views > 0): ?>
                                    <div style="position:relative;width:100%;max-width:38px;height:<?= max(2, round($views / $axisMax * 100, 2)) ?>%;background:var(--gnc-green-light,#1F5E2C);border-radius:4px 4px 0 0">
                                        <?php if ($showValues): ?>
                                        <span class="dash-chart-val" style="position:absolute;bottom:100%;left:50%;transform:translateX(-50%);margin-bottom:2px;font-size:.68rem;font-weight:600;color:#555;white-space:nowrap"><?= number_format($views) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- X axis labels -->
                        <div style="display:flex;gap:<?= $barGap ?>px;padding:0 <?= $barGap ?>px;margin-top:6px">
                            <?php $i = 0; $lastIdx = $dayCount - 1; foreach ($byDate as $date => $views): ?>
                            <div style="flex:1;min-width:0;text-align:center;font-size:.64rem;color:#999;white-space:nowrap;overflow:hidden">
                                <?php if ($dayCount <= 7): ?>
                                    <?= date('M j', strtotime($date)) ?>
                                <?php elseif ($i % $labelEvery === 0 || $i === $lastIdx): ?>
                                    <?= date('j', strtotime($date)) ?>
                                <?php else: ?>&nbsp;<?php endif; ?>
                            </div>
                            <?php $i++; endforeach; ?>
                        </div>
                    </div>
                </div>

                <div style="font-size:.7rem;color:#aaa;text-align:center;margin-top:.6rem">
                    <?= date('M j', strtotime(array_key_first($byDate))) ?> – <?= date('M j, Y', strtotime(array_key_last($byDate))) ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Recent Content + Activity Log ───────────────────────────── -->
<div class="row g-3">
    <!-- Recent Content -->
    <div class="col-lg-7">
        <div class="data-card h-100">
            <div class="data-card-header">
                <span class="data-card-title"><i class="bi bi-clock-history me-1"></i> Latest Posts</span>
                <div class="ms-auto d-flex gap-2 flex-wrap">
                    <a href="/admin/announcement.php/" class="btn btn-sm btn-outline-secondary btn-action">Go to Announcements</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 dash-posts">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th class="dash-col-author">Posted by</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentContent)): ?>
                        <tr><td colspan="5" class="dash-empty text-center py-4 text-muted">Nothing has been posted yet. Use the buttons at the top to post your first announcement, news article or event.</td></tr>
                        <?php else: foreach ($recentContent as $item): ?>
                        <tr>
                            <td class="dash-post-title">
                                <?= htmlspecialchars($item['title']) ?>
                            </td>
                            <td data-label="Type">
                                <?php
                                $typeMap = ['announcement'=>['gold','bi-megaphone','Announcement'],
                                        'news'=>['blue','bi-newspaper','News'],
                                        'event'=>['purple','bi-calendar-event','Event']];
                                [$color,$icon,$label] = $typeMap[$item['type']] ?? ['secondary','bi-file','Content'];
                                ?>
                                <span class="badge bg-<?= $color === 'gold' ? 'warning text-dark' : ($color === 'blue' ? 'primary' : 'info text-dark') ?>" style="font-size:.7rem">
                                    <i class="bi <?= $icon ?>"></i> <?= $label ?>
                                </span>
                            </td>
                            <td data-label="Status"><span class="status-badge <?= $item['status'] ?>"><?= htmlspecialchars($statusLabels[$item['status']] ?? ucfirst($item['status'])) ?></span></td>
                            <td class="dash-col-author" data-label="Posted by" style="font-size:.8rem"><?= htmlspecialchars($item['author']) ?></td>
                            <td data-label="Date" style="font-size:.78rem;color:#888"><?= date('M j', strtotime($item['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Activity Log -->
    <div class="col-lg-5">
        <div class="data-card h-100">
            <div class="data-card-header">
                <span class="data-card-title"><i class="bi bi-activity me-1"></i> What's Been Happening</span>
                <?php if (hasPermission('view_logs')): ?>
                <a href="/admin/logs/" class="btn btn-sm btn-outline-secondary btn-action ms-auto">View All</a>
                <?php endif; ?>
            </div>
            <div style="padding:.5rem 0">
                <?php if (empty($logs)): ?>
                <div class="empty-state"><i class="bi bi-journal-x"></i><p>No activity yet.</p></div>
                <?php else: foreach ($logs as $log): ?>
                <div class="dash-log-row">
                    <?php
                    $actionColors = [
                        'LOGIN'=>'success','LOGOUT'=>'secondary','CREATE'=>'primary',
                        'UPDATE'=>'warning','DELETE'=>'danger','PUBLISH'=>'info',
                        'APPROVE'=>'success','REJECT'=>'danger','OTP-SENT'=>'secondary'
                    ];
                    $actionColor = $actionColors[$log['action']] ?? 'secondary';
                    ?>
                    <span class="badge dash-log-badge bg-<?= $actionColor ?>">
                        <?= htmlspecialchars($actionLabels[$log['action']] ?? ucfirst(strtolower((string) $log['action']))) ?>
                    </span>
                    <div class="dash-log-text">
                        <div class="dash-log-desc">
                            <?= htmlspecialchars($log['description'] ?? '-') ?>
                        </div>
                        <div class="dash-log-meta">
                            <?= htmlspecialchars(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')) ?>
                            · <span title="<?= date('M j, Y g:i A', strtotime($log['created_at'])) ?>"><?= htmlspecialchars(dashTimeAgo($log['created_at'])) ?></span>
                        </div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../components/footer-admin.php'; ?>