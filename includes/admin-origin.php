<?php
/** Fixed admin deployment hosts. Never build authentication targets from arbitrary Host headers. */
function gncAdminPwaOrigin(): ?string {
    $value=trim((string)(getenv('ADMIN_PWA_ORIGIN') ?: ($_ENV['ADMIN_PWA_ORIGIN']??'')));
    if (preg_match('~^https://admin\.gncipampanga\.com/?$~iD',$value)) return 'https://admin.gncipampanga.com';
    // Reserved loopback hostname for testing independent origins without editing production settings.
    if (preg_match('~^http://admin\.localhost(?::([0-9]{1,5}))?/?$~iD',$value,$match)
        && (!isset($match[1]) || ((int)$match[1]>0 && (int)$match[1]<=65535))) return rtrim(strtolower($value),'/');
    return null; // Opt in only after DNS, HTTPS and the document root are ready.
}

function gncAdminRequestOrigin(): ?string {
    $host=strtolower((string)($_SERVER['HTTP_HOST']??''));
    if (in_array($host,['admin.gncipampanga.com','admin.gncipampanga.com:443'],true)) return 'https://admin.gncipampanga.com';
    if (preg_match('~^admin\.localhost(?::[0-9]{1,5})?$~D',$host)) return 'http://'.$host;
    return null;
}

function gncAdminOriginRedirect(string $request,string $method): ?string {
    if (!in_array($method,['GET','HEAD'],true)) return null; // Never move submitted credentials, uploads or approvals between origins.
    $parts=parse_url($request);
    if (!$parts || isset($parts['host']) || isset($parts['scheme'])) return null;
    parse_str($parts['query']??'',$query);
    if (isset($query['ce_preview'])) return null; // The CMS must keep authenticated preview frames on the editor's origin.
    $path=$parts['path']??'/';
    if (gncAdminRequestOrigin()!==null) {
        if (in_array($path,['/','/index.php','/index','/admin','/admin/'],true)) return '/admin/login'.(isset($parts['query'])?'?'.$parts['query']:'');
        return null;
    }
    $origin=gncAdminPwaOrigin();
    $host=(string)($_SERVER['HTTP_HOST']??'');
    if (!$origin || !preg_match('~^(?:(?:www\.)?gncipampanga\.com|localhost|127\.0\.0\.1)(?::[0-9]+)?$~iD',$host)) return null;
    $clean=gncBrowserUrl($request);
    $cleanPath=(string)parse_url($clean,PHP_URL_PATH);
    $adminFiles=array_filter(gncBrowserRoutes(),static fn($file)=>str_starts_with($file,'/admin/'));
    $legacyPath=(string)parse_url(gncBrowserLegacyUrl($clean),PHP_URL_PATH);
    if (in_array($legacyPath,$adminFiles,true)) return $origin.$clean;
    if (in_array($path,['/admin','/admin/'],true)) return $origin.'/admin/login';
    return null;
}
