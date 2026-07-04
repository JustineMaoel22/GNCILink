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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) . ' — ' : '' ?>GNC Admin Panel</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/header-admin-style.css">
    <link rel="icon" type="image/x-icon" href="../assets/images/logos/gnc-logo-v1.svg">
</head>
<body>

<!-- ============================================================
     SIDEBAR OVERLAY (mobile)
============================================================ -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<!-- ============================================================
     SIDEBAR
============================================================ -->
<aside class="gnc-sidebar" id="gnc-sidebar">
    <a class="sidebar-brand" href="/admin/dashboard/">
        <div class="sidebar-brand-logo">G</div>
        <div class="sidebar-brand-text">
            <span class="sidebar-brand-name">GNC Admin Panel</span>
            <span class="sidebar-brand-sub">Guagua National Colleges</span>
        </div>
    </a>

    <!-- Main Navigation -->
    <div class="sidebar-section-label">Main</div>
    <ul class="sidebar-nav">
        <li>
            <a href="/admin/admin-dash.php" class="sidebar-link <?= $currentPage === 'admin-dash' ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2-fill"></i> Dashboard
            </a>
        </li>
    </ul>

    <!-- Content -->
    <div class="sidebar-section-label">Content</div>
    <ul class="sidebar-nav">
        <li>
            <a href="/admin/announcement.php" class="sidebar-link <?= $currentPage === 'announcement' ? 'active' : '' ?>">
                <i class="bi bi-megaphone-fill"></i> Announcements
            </a>
        </li>
        <li>
            <a href="/admin/news/" class="sidebar-link <?= strpos($_SERVER['REQUEST_URI'], '/admin/news') !== false ? 'active' : '' ?>">
                <i class="bi bi-newspaper"></i> News
            </a>
        </li>
        <li>
            <a href="/admin/events.php" class="sidebar-link <?= $currentPage === 'events' ? 'active' : '' ?>">
                <i class="bi bi-calendar-event-fill"></i> Events
            </a>
        </li>
        <li>
            <a href="/admin/section-edit.php" class="sidebar-link <?= $currentPage === 'section-edit' ? 'active' : '' ?>">
                <i class="bi bi-grid-1x2"></i> Section Editor
            </a>
        </li>
    </ul>

    <!-- Workflow -->
    <?php if (hasPermission('manage_approvals')): ?>
    <div class="sidebar-section-label">Workflow</div>
    <ul class="sidebar-nav">
        <li>
            <a href="/admin/approvals/" class="sidebar-link <?= strpos($_SERVER['REQUEST_URI'], '/admin/approvals') !== false ? 'active' : '' ?>">
                <i class="bi bi-check2-circle"></i> Approvals
                <?php
                // Pending count badge
                try {
                    $db = getDB();
                    $pendingCount = $db->query("
                        SELECT COUNT(*) FROM (
                            SELECT announcement_id FROM announcements WHERE status = 'pending'
                            UNION ALL SELECT news_id FROM news WHERE status = 'pending'
                            UNION ALL SELECT event_id FROM events WHERE status = 'pending'
                        ) x
                    ")->fetchColumn();
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
            <a href="/admin/users/" class="sidebar-link <?= strpos($_SERVER['REQUEST_URI'], '/admin/users') !== false ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i> Users
            </a>
        </li>
        <?php endif; ?>
        <?php if (hasPermission('view_logs')): ?>
        <li>
            <a href="/admin/logs/" class="sidebar-link <?= strpos($_SERVER['REQUEST_URI'], '/admin/logs') !== false ? 'active' : '' ?>">
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
        <a href="/admin/dashboard/" style="text-decoration:none;color:inherit">Admin</a>
        <span>›</span>
        <?= htmlspecialchars($pageTitle ?? 'Dashboard') ?>
    </div>

    <div class="topbar-right">
        <?php if (hasPermission('manage_approvals')): ?>
        <a href="/admin/approvals/" class="topbar-icon-btn" title="Pending Approvals" style="text-decoration:none">
            <i class="bi bi-bell-fill"></i>
            <?php if (($pendingCount ?? 0) > 0): ?>
                <span class="topbar-notif-dot"></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>
        <a href="/" target="_blank" class="topbar-icon-btn" title="View Public Site" style="text-decoration:none">
            <i class="bi bi-box-arrow-up-right"></i>
        </a>
        <a href="/admin/auth/logout.php" class="topbar-icon-btn" title="Logout" style="text-decoration:none;color:#dc3545">
            <i class="bi bi-box-arrow-right"></i>
        </a>
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
</script>