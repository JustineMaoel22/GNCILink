<?php
/**
 * POST /api/track-visit.php
 * Called by the footer script when a page is opened (at most once per page per
 * 30 minutes per browser session). Records the unique visitor and the page view,
 * and returns the visitor counts as JSON.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../components/page-analytics.php';
require_once __DIR__ . '/../includes/privacy.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false]);
    exit;
}

// Enforce the choice server-side too; bypassing the footer must not set an ID
// or record visits before an opt-in. Preference cookies are not authorization.
if (!gncPrivacyAllows('analytics')) { echo json_encode(['ok'=>false,'tracking'=>false]); exit; }
try {
    $db = getDB();
    $pageTracked = false;
    $pageKey     = null;

    // Ignore bots; just return the current numbers.
    if (!gncVisitorIsBot($_SERVER['HTTP_USER_AGENT'] ?? '')) {
        // Anonymous random ID in a first-party cookie (1 year).
        $cookieName = 'gnc_vid';
        $visitorId  = $_COOKIE[$cookieName] ?? '';
        if (!preg_match('/^[a-f0-9]{32}$/', $visitorId)) {
            $visitorId = bin2hex(random_bytes(16));
            setcookie($cookieName, $visitorId, [
                'expires'  => time() + 365 * 24 * 60 * 60,
                'path'     => '/',
                'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
        gncVisitorRecord($db, $visitorId);

        // Page view (which page was opened). A failure here must not break the visitor counter.
        $pagePath  = (string) ($_POST['path']  ?? '');
        $pageTitle = (string) ($_POST['title'] ?? '');
        if ($pagePath !== '') {
            try {
                $pageTracked = gncPageRecord($db, $pagePath, $pageTitle);
                $pageKey     = gncPageNormalizePath($pagePath);
            } catch (Throwable $e) {
                error_log('Page analytics error: ' . $e->getMessage());
            }
        }
    }

    $stats = gncVisitorStats($db);
    echo json_encode([
        'ok' => true, 'today' => $stats['today'], 'total' => $stats['total'],
        'page_tracked' => $pageTracked, 'page' => $pageKey,
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok' => false]);
}
