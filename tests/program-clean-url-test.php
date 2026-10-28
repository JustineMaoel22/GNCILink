<?php
namespace ProgramCleanUrlTest;
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../components/page-analytics.php';

// Capture the real request-handling block's redirect without terminating the test.
function header(string $value, bool $replace = true, int $code = 0): void {
    throw new \RuntimeException($value, $code);
}
function check(bool $ok, string $message): void {
    if (!$ok) throw new \LogicException($message);
}
$source = file_get_contents(__DIR__ . '/../pages/academics/college/program.php');
$start = strpos($source, '$programRequestPath =');
$end = strpos($source, '$program = publicProgramPage($slug);', $start);
$routing = 'namespace ProgramCleanUrlTest; ' . substr($source, $start, $end - $start);
foreach ([
    ['/pages/academics/college/program/beed', [], 'GET', 'beed', null],
    ['/pages/academics/college/program/beed/', ['slug'=>'other'], 'GET', 'beed', null],
    ['/pages/academics/college/program.php?slug=beed', ['slug'=>'beed'], 'GET', null, 'Location: /pages/academics/college/program/beed'],
    ['/pages/academics/college/program?slug=beed&ce_preview=1', ['slug'=>'beed','ce_preview'=>'1'], 'HEAD', null, 'Location: /pages/academics/college/program/beed?ce_preview=1'],
    ['/pages/academics/college/program.php?slug=beed', ['slug'=>'beed'], 'POST', 'beed', null],
    ['/pages/academics/college/program.php?slug[]=bad', ['slug'=>['bad']], 'GET', '', null],
] as [$uri, $query, $method, $expectedSlug, $redirect]) {
    $_SERVER['REQUEST_URI'] = $uri;
    $_SERVER['REQUEST_METHOD'] = $method;
    $_GET = $query;
    try {
        eval($routing);
        check($redirect === null && $slug === $expectedSlug, 'Incorrect program routing');
    } catch (\RuntimeException $result) {
        check($redirect !== null && $result->getMessage() === $redirect && $result->getCode() === 302, 'Incorrect legacy redirect');
    }
}
$canonical = '/pages/academics/college/program.php?slug=beed';
foreach (['/pages/academics/college/program/beed', '/pages/academics/college/program/beed/?slug=other', '/pages/academics/college/program?slug=beed', $canonical] as $url) {
    check(\gncPageNormalizePath($url) === $canonical, 'Program analytics identity must remain stable');
}
echo "PASS: program path routing, legacy redirects, preview parameters, POST preservation, malformed input, and analytics identity.\n";
