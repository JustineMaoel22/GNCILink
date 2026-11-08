<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../includes/browser-urls.php';
$checks=0;
function routeCheck(bool $ok,string $message): void { global $checks; if (!$ok) throw new RuntimeException($message); $checks++; }
foreach (gncBrowserRoutes() as $alias=>$file) {
    $admin=str_starts_with($file,'/admin/');
    $canonical=$admin?'/admin'.$alias:$alias;
    routeCheck(gncBrowserUrl($file)===$canonical,'File canonical URL: '.$file);
    routeCheck(gncBrowserLegacyUrl($canonical)===$file,'Canonical restores original CMS/RBAC identity: '.$canonical);
    if ($admin) {
        routeCheck(str_contains(file_get_contents(__DIR__.'/../.htaccess'),'RewriteRule ^admin/'.substr($alias,1).'/?$ '.ltrim($file,'/').' [END]'),'Apache scoped route matches the registry: '.$alias);
        routeCheck(gncBrowserUrl($alias)===$canonical,'Existing admin bookmark remains usable: '.$alias);
        routeCheck(gncBrowserUrl($canonical)===$canonical,'Canonical URL does not loop: '.$canonical);
        routeCheck(gncBrowserUrl($file.'?page=example#section')===$canonical.'?page=example#section','Queries/fragments are preserved');
    }
}
routeCheck(gncBrowserUrl('/admin/action/page-content-handler.php')==='/admin/action/page-content-handler.php','Action endpoints are preserved');
routeCheck(gncBrowserUrl('/pages/about/vision-mission.php?ce_preview=1')==='/pages/about/vision-mission.php?ce_preview=1','Editor preview identities are preserved');
routeCheck(gncBrowserUrl('/departments/cased')==='/departments/cased','Dynamic public paths are preserved');
$html=gncBrowserHtml('<html><head><title>Admin</title></head><body><a href="/admin/admin-dash.php">Dashboard</a><a href="/settings">Settings</a><a href="/">Public site</a></body></html>','/admin/news.php');
routeCheck(str_contains($html,'href="/admin/dashboard"')&&str_contains($html,'href="/admin/settings"'),'Admin navigation stays scoped');
routeCheck(str_contains($html,'href="/"'),'Public link stays public');
echo 'PASS: '.$checks.' scoped routing and compatibility checks.'.PHP_EOL;
