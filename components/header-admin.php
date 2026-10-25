<?php
/**
 * GNC Admin Panel - Shared Header / Layout Top
 * Include at the top of every admin page after requireLogin()
 */

// Ensure user is logged in before rendering
requireLogin();
$currentUser = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$csrfToken   = generateCSRFToken();

// Embedded mode: the Visual Editor opens the News / Announcements / Events editors in its side panel. There the page
// must show only the editor itself (no sidebar or top bar). The browser tells us it is a frame (Sec-Fetch-Dest), so this
// also holds after a save redirects; ?embed=1 is the fallback. Everything else about the page is unchanged.
$gncEmbedded = (($_GET['embed'] ?? '') === '1')
    || strtolower((string)($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '')) === 'iframe';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <?php require_once __DIR__ . '/loading-feedback.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : '' ?>GNC Admin Panel</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/header-admin-style.css?v=<?= (int)filemtime(__DIR__ . '/../assets/css/header-admin-style.css') ?>">
    <link rel="stylesheet" href="/assets/css/admin-dialogs.css?v=<?= (int)filemtime(__DIR__ . '/../assets/css/admin-dialogs.css') ?>">
    <script src="/assets/js/admin-dialogs.js?v=<?= (int)filemtime(__DIR__ . '/../assets/js/admin-dialogs.js') ?>"></script>
    <?php include_once __DIR__ . '/image-framing-assets.php'; ?>
    <meta name="cms-csrf" content="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="/assets/css/content-completeness.css?v=<?= (int)filemtime(__DIR__ . '/../assets/css/content-completeness.css') ?>">
    <script src="/assets/js/content-completeness.js?v=<?= (int)filemtime(__DIR__ . '/../assets/js/content-completeness.js') ?>"></script>
    <style>
        /* Sidebar brand: show the GNC logo instead of the yellow "G" box */
        .sidebar-brand-logo { background: transparent !important; padding: 0 !important; box-shadow: none !important; overflow: visible; }
        .sidebar-brand-logo img { width: 100%; height: 100%; object-fit: contain; display: block; }
    </style>
    <?php if ($gncEmbedded): ?>
    <style>
        body.gnc-embedded .gnc-sidebar, body.gnc-embedded .gnc-topbar, body.gnc-embedded .sidebar-overlay { display: none !important; }
        body.gnc-embedded .gnc-main { margin-left: 0 !important; padding-top: 0 !important; width: 100% !important; max-width: none !important; }
        body.gnc-embedded { padding-top: 0 !important; }
    </style>
    <?php endif; ?>
    <link rel="icon" type="image/x-icon" href="../assets/images/logos/gnc-logo-v1.svg">
</head>
<body<?= $gncEmbedded ? ' class="gnc-embedded"' : '' ?>>

<!-- ============================================================
     SIDEBAR OVERLAY (mobile)
============================================================ -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<!-- ============================================================
     SIDEBAR
============================================================ -->
<aside class="gnc-sidebar" id="gnc-sidebar">
    <a class="sidebar-brand" href="<?= htmlspecialchars(rbacHome()) ?>">
        <div class="sidebar-brand-logo"><img src="/assets/images/logos/gnc-logo-v1.svg" alt="GNC logo"></div>
        <div class="sidebar-brand-text">
            <span class="sidebar-brand-name">GNC Admin Panel</span>
            <span class="sidebar-brand-sub">Guagua National Colleges</span>
        </div>
    </a>

    <?php if (rbacRole() !== 'The Mirror'): ?>
    <!-- Main Navigation -->
    <div class="sidebar-section-label">Main</div>
    <ul class="sidebar-nav">
        <li>
            <a href="/admin/admin-dash.php" class="sidebar-link <?= $currentPage === 'admin-dash' ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2-fill"></i> Dashboard
            </a>
        </li>
    </ul>
    <?php endif; ?>

    <!-- Content -->
    <div class="sidebar-section-label">Content</div>
    <ul class="sidebar-nav">
        <li>
            <a href="/admin/announcement.php" class="sidebar-link <?= $currentPage === 'announcement' ? 'active' : '' ?>">
                <i class="bi bi-megaphone-fill"></i> Announcements
            </a>
        </li>
        <?php if (rbacRole() !== 'The Mirror'): ?><li>
            <a href="/admin/facebook-posts.php" class="sidebar-link <?= $currentPage === 'facebook-posts' ? 'active' : '' ?>">
                <i class="bi bi-facebook"></i> Facebook Posts
            </a>
        </li>
        <?php endif; ?>
        <li>
            <a href="/admin/news.php" class="sidebar-link <?= strpos($_SERVER['REQUEST_URI'], '/admin/news') !== false ? 'active' : '' ?>">
                <i class="bi bi-newspaper"></i> News
            </a>
        </li>
        <?php if (rbacRole() !== 'The Mirror'): ?><li>
            <a href="/admin/events.php" class="sidebar-link <?= $currentPage === 'events' ? 'active' : '' ?>">
                <i class="bi bi-calendar-event-fill"></i> Events
            </a>
        </li>
        <li>
            <?php
            // Page text that editors sent for approval (needs sql/page-approval-migration.sql)
            $cePending = 0;
            if (hasPermission('approve_content') && function_exists('pageReviewReady') && pageReviewReady()) {
                try { $cePending = (int)getDB()->query("SELECT COUNT(*) FROM page_sections WHERE review_status = 'pending'")->fetchColumn(); } catch (Exception $e) {}
            }
            ?>
            <a href="/admin/content-edit.php<?= $cePending > 0 ? '?page=review' : '' ?>" class="sidebar-link <?= $currentPage === 'content-edit' ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2"></i> Content Editor
                <?php if ($cePending > 0): ?><span class="sidebar-badge"><?= $cePending ?></span><?php endif; ?>
            </a>
        </li>
        <?php endif; ?>
    </ul>

    <!-- Workflow -->
    <?php if (hasPermission('manage_approvals') || hasPermission('view_own_submissions')): ?>
    <div class="sidebar-section-label">Workflow</div>
    <ul class="sidebar-nav">
        <li>
            <a href="/admin/submissions.php" class="sidebar-link <?= $currentPage === 'submissions' ? 'active' : '' ?>">
                <i class="bi bi-check2-circle"></i> <?= rbacIsSuper() ? 'Approvals' : 'My Submissions' ?>
                <?php
                // Pending count badge
                try {
                    $db = getDB();
                    $pendingCount = rbacIsSuper() && function_exists('rbacSubmissionsReady') && rbacSubmissionsReady() ? $db->query("SELECT COUNT(*) FROM rbac_submissions WHERE status='pending'")->fetchColumn() : 0;
                    /* Legacy content remains in the existing review panels.
                    $pendingCount = $db->query("
                        SELECT COUNT(*) FROM (
                            SELECT announcement_id FROM announcements WHERE status = 'pending'
                            UNION ALL SELECT news_id FROM news WHERE status = 'pending'
                            UNION ALL SELECT event_id FROM events WHERE status = 'pending'
                        ) x
                    ")->fetchColumn(); */
                    if ($pendingCount > 0) echo '<span class="sidebar-badge">' . $pendingCount . '</span>';
                } catch (Exception $e) {}
                ?>
            </a>
        </li>
    </ul>
    <?php endif; ?>

    <!-- Administration -->
    <?php if (hasPermission('manage_users') || hasPermission('view_logs')): ?>
    <div class="sidebar-section-label">Administration</div>
    <ul class="sidebar-nav">
        <?php if (hasPermission('manage_users')): ?>
        <li>
            <a href="/admin/users.php" class="sidebar-link <?= $currentPage === 'users' ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i> Users
            </a>
        </li>
        <?php endif; ?>
        <?php if (hasPermission('view_logs')): ?>
        <li>
            <a href="/admin/logs.php" class="sidebar-link <?= $currentPage === 'logs' ? 'active' : '' ?>">
                <i class="bi bi-clock-history"></i> Activity Logs
            </a>
        </li>
        <?php endif; ?>
    </ul>
    <?php endif; ?>

    <!-- User Footer -->
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-avatar">
                <?= strtoupper(substr($currentUser['first_name'] ?? 'A', 0, 1)) ?>
            </div>
            <div>
                <div class="sidebar-user-name"><?= htmlspecialchars(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? '')) ?></div>
                <div class="sidebar-user-role"><?= htmlspecialchars($currentUser['role'] ?? '') ?></div>
            </div>
            <a href="/admin/auth/logout.php" class="sidebar-logout" title="Logout">
                <i class="bi bi-box-arrow-right"></i>
            </a>
        </div>
    </div>
</aside>

<!-- ============================================================
     TOPBAR
============================================================ -->
<header class="gnc-topbar">
    <button class="topbar-toggle" id="sidebar-toggle" aria-label="Toggle sidebar">
        <i class="bi bi-list"></i>
    </button>

    <div class="topbar-breadcrumb">
        <a href="<?= htmlspecialchars(rbacHome()) ?>" style="text-decoration:none;color:inherit">Admin</a>
        <span>›</span>
        <?= htmlspecialchars($pageTitle ?? 'Dashboard') ?>
    </div>

    <div class="topbar-right">
        <?php if (hasPermission('manage_approvals')): ?>
        <a href="/admin/submissions.php" class="topbar-icon-btn" title="Pending Approvals" style="text-decoration:none">
            <i class="bi bi-bell-fill"></i>
            <?php if (($pendingCount ?? 0) > 0): ?>
                <span class="topbar-notif-dot"></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>
        <a href="/" target="_blank" class="topbar-icon-btn" title="View Public Site" style="text-decoration:none">
            <i class="bi bi-box-arrow-up-right"></i>
        </a>
        <?php if (hasPermission('manage_settings')): ?>
        <a href="/admin/settings.php" class="topbar-icon-btn" title="Settings" aria-label="Settings" style="text-decoration:none"<?= $currentPage === 'settings' ? ' aria-current="page"' : '' ?>>
            <i class="bi bi-gear-fill"></i>
        </a>
        <?php endif; ?>
    </div>
</header>

<!-- ============================================================
     TOAST CONTAINER
============================================================ -->
<div id="toast-container"></div>

<!-- ============================================================
     MAIN CONTENT WRAPPER START
============================================================ -->
<main class="gnc-main">

<script>
// Sidebar toggle (mobile)
const sidebarToggle  = document.getElementById('sidebar-toggle');
const sidebarEl      = document.getElementById('gnc-sidebar');
const sidebarOverlay = document.getElementById('sidebar-overlay');

sidebarToggle?.addEventListener('click', () => {
    sidebarEl.classList.toggle('open');
    sidebarOverlay.classList.toggle('open');
});
sidebarOverlay?.addEventListener('click', () => {
    sidebarEl.classList.remove('open');
    sidebarOverlay.classList.remove('open');
});

// Toast helper – call showToast('message', 'success|error|warning')
function showToast(message, type = 'success') {
    const iconMap = { success: 'bi-check-circle-fill', error: 'bi-x-circle-fill', warning: 'bi-exclamation-triangle-fill' };
    const container = document.getElementById('toast-container');
    const toast = document.createElement('div');
    toast.className = `gnc-toast ${type}`;
    toast.innerHTML = `<i class="bi ${iconMap[type] || iconMap.success}"></i><span>${message}</span>`;
    container.appendChild(toast);
    setTimeout(() => toast.remove(), 4000);
}

// CSRF token for AJAX
const CSRF_TOKEN = '<?= $csrfToken ?>';

// Confirm logout before ending the session.
let logoutConfirmationPending = false;
document.querySelectorAll('a[href="/admin/auth/logout.php"]').forEach(link => {
    link.addEventListener('click', async event => {
        event.preventDefault();
        if (logoutConfirmationPending) return;
        logoutConfirmationPending = true;
        try {
            const confirmed = await window.AdminDialogs.confirm(
                'Are you sure you want to log out of the admin panel?',
                { title: 'Log out?', confirmText: 'Log out', cancelText: 'Cancel' }
            );
            if (confirmed) window.location.assign(link.href);
        } finally {
            logoutConfirmationPending = false;
        }
    });
});
</script>
