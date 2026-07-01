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

include __DIR__ . '/../components/header-admin.php';
?>

<!-- ── Page Header ─────────────────────────────────────────────── -->
<div class="page-header">
    <div>
        <h1>Dashboard</h1>
        <p>Welcome back, <?= htmlspecialchars($currentUser['first_name'] ?? 'Admin') ?>! Here's what's happening.</p>
    </div>
</div>

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
        </div>
    </div>

    <!-- Pending -->
    <div class="col-6 col-md-4 col-xl-2">
        <div class="stat-card h-100">
            <div class="d-flex align-items-start justify-content-between mb-2">
                <div class="stat-icon gold"><i class="bi bi-hourglass-split"></i></div>
            </div>
            <div class="stat-value"><?= number_format($stats['pending_approvals'] ?? 0) ?></div>
            <div class="stat-label">Pending Approval</div>
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
            <div class="stat-label">Published</div>
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
            <div class="stat-label">Total Users</div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- ── Recent Content + Activity Log ───────────────────────────── -->
<div class="row g-3">
    <!-- Recent Content -->
    <div class="col-lg-7">
        <div class="data-card h-100">
            <div class="data-card-header">
                <span class="data-card-title"><i class="bi bi-clock-history me-1"></i> Recent Content</span>
                <div class="ms-auto d-flex gap-2">
                    <a href="/admin/announcement.php/" class="btn btn-sm btn-outline-secondary btn-action">View All</a>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Name</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentContent)): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No content yet.</td></tr>
                        <?php else: foreach ($recentContent as $item): ?>
                        <tr>
                            <td style="max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                <?= htmlspecialchars($item['title']) ?>
                            </td>
                            <td>
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
                            <td><span class="status-badge <?= $item['status'] ?>"><?= ucfirst($item['status']) ?></span></td>
                            <td style="font-size:.8rem"><?= htmlspecialchars($item['author']) ?></td>
                            <td style="font-size:.78rem;color:#888"><?= date('M d', strtotime($item['created_at'])) ?></td>
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
                <span class="data-card-title"><i class="bi bi-activity me-1"></i> Recent Activity</span>
                <?php if (hasPermission('view_logs')): ?>
                <a href="/admin/logs/" class="btn btn-sm btn-outline-secondary btn-action ms-auto">View All</a>
                <?php endif; ?>
            </div>
            <div style="padding:.5rem 0">
                <?php if (empty($logs)): ?>
                <div class="empty-state"><i class="bi bi-journal-x"></i><p>No activity yet.</p></div>
                <?php else: foreach ($logs as $log): ?>
                <div style="display:flex;gap:.75rem;align-items:flex-start;padding:.6rem 1.25rem;border-bottom:1px solid #f5f5f5">
                    <?php
                    $actionColors = [
                        'LOGIN'=>'success','LOGOUT'=>'secondary','CREATE'=>'primary',
                        'UPDATE'=>'warning','DELETE'=>'danger','PUBLISH'=>'info',
                        'APPROVE'=>'success','REJECT'=>'danger','OTP_SENT'=>'secondary'
                    ];
                    $actionColor = $actionColors[$log['action']] ?? 'secondary';
                    ?>
                    <span class="badge bg-<?= $actionColor ?>" style="font-size:.65rem;margin-top:2px;flex-shrink:0">
                        <?= htmlspecialchars($log['action']) ?>
                    </span>
                    <div style="flex:1;min-width:0">
                        <div style="font-size:.82rem;color:#333;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                            <?= htmlspecialchars($log['description'] ?? '-') ?>
                        </div>
                        <div style="font-size:.72rem;color:#aaa">
                            <?= htmlspecialchars(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? '')) ?>
                            · <?= date('M d H:i', strtotime($log['created_at'])) ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../components/footer-admin.php'; ?>