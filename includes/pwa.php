<?php
/** PWA metadata; offline document storage remains public-only. */
function gncPwaPublicPaths(): array {
    $paths = ['/', '/news', '/news-article', '/announcement', '/announcement-article'];
    foreach (require __DIR__ . '/browser-routes.php' as $alias => $file) {
        if (str_starts_with($file, '/pages/')) $paths[] = $alias;
    }
    return array_values(array_unique($paths));
}

function gncPwaPublicUrl(string $url): bool {
    $path = rawurldecode((string)parse_url($url, PHP_URL_PATH));
    $path = rtrim($path, '/') ?: '/';
    return in_array($path, gncPwaPublicPaths(), true)
        || (bool)preg_match('~^/(programs|departments)/[a-z0-9-]+$~D', $path);
}

function gncPwaVersion(): string {
    static $version;
    if ($version !== null) return $version;
    $root = dirname(__DIR__);
    $hash = hash_init('sha256');
    foreach (['version.txt','manifest.json','admin-manifest.json','offline.html','service-worker.php','assets/js/pwa-worker.js','assets/js/pwa.js','assets/css/pwa.css','includes/pwa.php','includes/browser-routes.php','assets/images/pwa/icon-192.png','assets/images/pwa/icon-512.png','assets/images/pwa/maskable-512.png','assets/images/pwa/apple-touch-icon.png'] as $file) {
        hash_update_file($hash, $root . '/' . $file);
    }
    foreach (glob($root.'/assets/images/pwa/admin-*.png') as $file) hash_update_file($hash,$file);
    hash_update_file($hash,$root.'/admin/service-worker.php');
    // Also invalidate offline assets when styles/scripts are deployed without a version bump.
    foreach (array_merge(glob($root.'/assets/css/*.css'), glob($root.'/assets/js/*.js')) as $file) {
        hash_update($hash, basename($file) . ':' . filemtime($file) . ':' . filesize($file));
    }
    return $version = substr(hash_final($hash), 0, 20);
}

function gncPwaHtml(string $html, string $legacyPath): string {
    $publicUrl = gncBrowserUrl($legacyPath);
    if (!gncPwaPublicUrl($publicUrl) || isset($_GET['ce_preview'])
        || !in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET','HEAD'], true)
        || http_response_code() >= 300 || stripos($html, '</head>') === false) return $html;

    // Only session-free public documents explicitly opt into offline storage.
    // Contact forms, auth cookies, previews, tokens and private/no-store responses never do.
    $cacheable = session_status() !== PHP_SESSION_ACTIVE && !isset($_COOKIE[session_name()])
        && !isset($_SESSION['user_id']) && !str_contains($publicUrl, 'contact-us')
        && !preg_match('~data-ve-(?:field|section|person|slide)=|name=["\'](?:csrf|csrf_token|token)["\']~i', $html);
    foreach (headers_list() as $header) {
        if (preg_match('~^Cache-Control:.*(?:private|no-store)~i', $header)) $cacheable = false;
    }
    if (!headers_sent()) {
        if ($cacheable) header('X-GNCI-PWA-Cache: public');
        // Always revalidate public HTML; a service worker handles offline copies explicitly.
        if ($cacheable) header('Cache-Control: no-cache, must-revalidate');
    }
    return preg_replace('~</head\s*>~i', gncPwaHead().'</head>', $html, 1);
}

function gncPwaHead(bool $admin = false): string {
    if (isset($_GET['ce_preview']) || ($_GET['embed'] ?? '') === '1'
        || strtolower($_SERVER['HTTP_SEC_FETCH_DEST'] ?? '') === 'iframe') return '';
    $version = gncPwaVersion();
    $name = $admin ? 'GNCILink Admin' : 'GNCILink';
    return '<link rel="manifest" href="/'.($admin ? 'admin-manifest.json' : 'manifest.json').'">'
        . ($admin ? '<meta name="gnci-pwa-area" content="admin">' : '')
        . ($admin && function_exists('isLoggedIn') && isLoggedIn() ? '<meta name="gnci-pwa-authenticated" content="true">' : '')
        . '<meta name="theme-color" content="#094024">'
        . '<meta name="application-name" content="'.$name.'">'
        . '<meta name="mobile-web-app-capable" content="yes">'
        . '<meta name="apple-mobile-web-app-capable" content="yes">'
        . '<meta name="apple-mobile-web-app-title" content="'.$name.'">'
        . '<meta name="apple-mobile-web-app-status-bar-style" content="default">'
        . '<link rel="apple-touch-icon" href="/assets/images/pwa/'.($admin ? 'admin-apple-touch-icon.png' : 'apple-touch-icon.png').'">'
        . '<link rel="stylesheet" href="/assets/css/pwa.css?v='.$version.'">'
        . '<script src="/assets/js/pwa.js?v='.$version.'" defer></script>';
}
