<?php
namespace DepartmentCleanUrlTest;
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../components/page-analytics.php';

// Capture the real request-handling block's redirect without terminating the test.
function header(string $value, bool $replace = true, int $code = 0): void {
    throw new \RuntimeException($value, $code);
}
function check(bool $ok, string $message): void {
    if (!$ok) throw new \LogicException($message);
}
$source = file_get_contents(__DIR__ . '/../pages/academics/college/college-department.php');
$start = strpos($source, '$departmentRequestPath =');
$end = strpos($source, "require __DIR__", $start);
$routing = 'namespace DepartmentCleanUrlTest; ' . substr($source, $start, $end - $start);
foreach ([
    ['/pages/academics/college/college-department/cased', [], 'GET', 'cased', null],
    ['/pages/academics/college/college-department/cased/', ['slug'=>'other'], 'GET', 'cased', null],
    ['/pages/academics/college/college-department.php?slug=cased', ['slug'=>'cased'], 'GET', null, 'Location: /pages/academics/college/college-department/cased'],
    ['/pages/academics/college/college-department?slug=cased&ce_preview=1', ['slug'=>'cased','ce_preview'=>'1'], 'HEAD', null, 'Location: /pages/academics/college/college-department/cased?ce_preview=1'],
    ['/pages/academics/college/college-department.php?slug=cased', ['slug'=>'cased'], 'POST', 'cased', null],
    ['/pages/academics/college/college-department.php?slug[]=bad', ['slug'=>['bad']], 'GET', ['bad'], null],
] as [$uri, $query, $method, $expectedSlug, $redirect]) {
    $_SERVER['REQUEST_URI'] = $uri;
    $_SERVER['REQUEST_METHOD'] = $method;
    $_GET = $query;
    try {
        eval($routing);
        check($redirect === null && ($_GET['slug'] ?? '') === $expectedSlug, 'Incorrect department routing');
    } catch (\RuntimeException $result) {
        check($redirect !== null && $result->getMessage() === $redirect && $result->getCode() === 302, 'Incorrect legacy redirect');
    }
}
$canonical = '/pages/academics/college/college-department.php?slug=cased';
foreach (['/pages/academics/college/college-department/cased', '/pages/academics/college/college-department/cased/?slug=other', '/pages/academics/college/college-department?slug=cased', $canonical] as $url) {
    check(\gncPageNormalizePath($url) === $canonical, 'Department analytics identity must remain stable');
}
echo "PASS: department path routing, legacy redirects, preview parameters, POST preservation, malformed input, and analytics identity.\n";
