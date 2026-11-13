<?php
require_once __DIR__.'/admin-origin.php';
/** Short browser addresses with the original routes retained for CMS/RBAC code. */
function gncBrowserRoutes(): array {
    static $routes;
    return $routes ??= require __DIR__ . '/browser-routes.php';
}

function gncBrowserUrl(string $url): string {
    $parts = parse_url($url);
    if ($parts === false || isset($parts['scheme']) || isset($parts['host']) || !str_starts_with($parts['path'] ?? '', '/')) return $url;
    $path = rawurldecode($parts['path']);
    $query = $parts['query'] ?? '';
    // The visual editor uses the original URLs to identify editable page types.
    parse_str($query, $params);
    if (isset($params['ce_preview'])) return $url;
    $clean = null;
    if ($path === '/index.php' || $path === '/index') $clean = '/';
    if (preg_match('~^/pages/academics/college/(program|college-department)/([a-z0-9-]+)/?$~D', $path, $m)) {
        $clean = ($m[1] === 'program' ? '/programs/' : '/departments/') . $m[2];
    } else {
        foreach (gncBrowserRoutes() as $alias => $file) {
            $admin = str_starts_with($file, '/admin/');
            if ($path === $file || $path === substr($file, 0, -4)
                || ($admin && (rtrim($path,'/') === $alias || rtrim($path,'/') === '/admin'.$alias))) {
                // The pre-login verification page has a directory-free address.
                $clean = $admin && $file !== '/admin/auth/verify-otp.php' ? '/admin'.$alias : $alias; break;
            }
        }
    }
    if ($clean === null) return $url;
    return $clean . ($query !== '' ? '?' . $query : '') . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
}

function gncBrowserLegacyUrl(string $url): string {
    $parts = parse_url($url);
    if ($parts === false || isset($parts['scheme']) || isset($parts['host'])) return $url;
    $path = $parts['path'] ?? '';
    $legacy = gncBrowserRoutes()[rtrim($path, '/')] ?? null;
    if ($legacy === null && str_starts_with($path,'/admin/')) {
        $candidate = gncBrowserRoutes()[substr(rtrim($path,'/'),6)] ?? null;
        if ($candidate !== null && str_starts_with($candidate,'/admin/')) $legacy = $candidate;
    }
    if (preg_match('~^/(programs|departments)/([a-z0-9-]+)/?$~D', $path, $m)) {
        $legacy = '/pages/academics/college/' . ($m[1] === 'programs' ? 'program/' : 'college-department/') . $m[2];
    }
    if ($legacy === null) return $url;
    return $legacy . (isset($parts['query']) ? '?' . $parts['query'] : '') . (isset($parts['fragment']) ? '#' . $parts['fragment'] : '');
}

function gncBrowserHtml(string $html, string $legacyPath): string {
    if (stripos($html, '<html') === false) return $html;
    foreach (headers_list() as $header) {
        if (stripos($header, 'Content-Type:') === 0 && stripos($header, 'text/html') === false) return $html;
    }
    require_once __DIR__ . '/pwa.php';
    $html = gncPwaHtml($html, $legacyPath);
    if (strpos($html, '/assets/js/request-guard.js') === false) {
        $guard='<script src="/assets/js/request-guard.js?v='.(int)filemtime(__DIR__.'/../assets/js/request-guard.js').'"></script>';
        $html=preg_replace('~(<head\b[^>]*>)~i','$1'.$guard,$html,1);
    }
    // Preserve relative assets and form actions at their original filesystem depth.
    $base = rtrim(str_replace('\\', '/', dirname($legacyPath)), '/') . '/';
    $hasBase = stripos($html, '<base ') !== false;
    return preg_replace_callback('~<script\b[^>]*>.*?</script\s*>|<style\b[^>]*>.*?</style\s*>|<!--.*?-->|<head\b[^>]*>|<a\b[^>]*>~is', static function ($match) use ($base, $hasBase, $legacyPath) {
        $tag = $match[0];
        if (preg_match('~^<head\b~i', $tag)) return $hasBase ? $tag : $tag . '<base href="' . htmlspecialchars($legacyPath, ENT_QUOTES, 'UTF-8') . '">';
        if (!preg_match('~^<a\b~i', $tag)) return $tag;
        return preg_replace_callback('~(?<![a-z0-9_:-])href\s*=\s*(["\'])(.*?)\1~is', static function ($href) use ($base) {
            $original = html_entity_decode($href[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($original === '' || preg_match('~^(?:[a-z][a-z0-9+.-]*:|//|[?#])~i', $original)) return $href[0];
            $resolved = $original;
            if ($original[0] !== '/') {
                $segments = [];
                foreach (explode('/', $base . $original) as $segment) {
                    if ($segment === '..') array_pop($segments);
                    elseif ($segment !== '' && $segment !== '.') $segments[] = $segment;
                }
                $resolved = '/' . implode('/', $segments);
            }
            $clean = gncBrowserUrl($resolved);
            if (gncAdminRequestOrigin()!==null && gncPwaPublicUrl($clean)) $clean='https://gncipampanga.com'.$clean;
            if ($clean === $resolved) return $href[0];
            return 'href=' . $href[1] . htmlspecialchars($clean, ENT_QUOTES, 'UTF-8') . $href[1];
        }, $tag);
    }, $html);
}

function gncBrowserRoutingStart(): void {
    static $started = false;
    if ($started) return;
    $started = true;
    if (PHP_SAPI === 'cli') return;
    $host = $_SERVER['HTTP_HOST'] ?? '';
    if (gncAdminRequestOrigin()===null && !preg_match('~^(?:(?:www\.)?gncipampanga\.com|localhost|127\.0\.0\.1)(?::[0-9]+)?$~iD', $host)) return;
    $request = $_SERVER['REQUEST_URI'] ?? '/';
    $adminTarget=gncAdminOriginRedirect($request,$_SERVER['REQUEST_METHOD']??'GET');
    if ($adminTarget!==null) {header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');header('Location: '.$adminTarget,true,302);exit;}
    $clean = gncBrowserUrl($request);
    if ($clean !== $request && in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD'], true)) {
        header('Location: ' . $clean, true, 302);
        exit;
    }
    $legacy = gncBrowserLegacyUrl($request);
    $legacyPath = (string)parse_url($legacy, PHP_URL_PATH);
    // Never change SCRIPT_FILENAME: authorization still checks the real PHP entry point.
    $_SERVER['REQUEST_URI'] = $legacy;
    header_register_callback(static function () {
        foreach (headers_list() as $header) {
            if (stripos($header, 'Location: ') === 0) {
                $target = substr($header, 10);
                $short = gncBrowserUrl($target);
                if ($short !== $target) header('Location: ' . $short, true, http_response_code());
            }
        }
    });
    if (isset($_GET['ce_preview']) || str_contains($legacyPath, '/admin/action/') || str_contains($legacyPath, '/admin/auth/')) return;
    ob_start(static fn($html) => gncBrowserHtml($html, $legacyPath));
}
