<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/text-layout.php';

function cleanUrlCheck(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}

foreach ([
    ['/pages/academics/college/program?slug=beed', '/pages/academics/college/program.php?slug=beed'],
    ['/pages/academics/college/program/beed?ce_preview=1', '/pages/academics/college/program.php?slug=beed'],
    ['/pages/academics/college/program/beed?slug=other', '/pages/academics/college/program.php?slug=beed'],
    ['/pages/student%20life/athletics-sports?ce_preview=1', '/pages/student life/athletics-sports.php'],
    ['/index', '/'],
] as [$clean, $canonical]) {
    cleanUrlCheck(textLayoutRoute($clean) === $canonical, 'Clean URL must keep its existing placement key');
}
foreach (['/admin/settings', '/pages/../config', '//evil.example/pages/a', 'https://evil.example/pages/a', '/pages/a.css'] as $bad) {
    try {
        textLayoutRoute($bad);
        throw new RuntimeException('Invalid route accepted');
    } catch (InvalidArgumentException $expected) {}
}

// The shared partial runs in the caller's scope, including its escaping closure.
// Use an unsupported route to exercise the exception without a database connection.
class CleanUrlLayoutDb extends PDO {
    public function __construct() {}
    public function prepare(string $query, array $options = []): PDOStatement|false {
        return new CleanUrlLayoutStatement();
    }
}
class CleanUrlLayoutStatement extends PDOStatement {
    public function __construct() {}
    public function execute(?array $params = null): bool { return true; }
    public function fetchColumn(int $column = 0): mixed { return false; }
}
// Inject the stub only in the partial, retaining its real route validation and catch.
$partial = file_get_contents(__DIR__ . '/../components/text-layout-assets.php');
$partial = str_replace('getDB()', '(new CleanUrlLayoutDb())', $partial);
$partial = str_replace("__DIR__ . '/../includes/text-layout.php'", "'" . str_replace('\\', '/', __DIR__ . '/../includes/text-layout.php') . "'", $partial);
foreach (['/admin/settings.php', '/pages/academics/college/program?slug=beed'] as $uri) {
    $_SERVER['REQUEST_URI'] = $uri;
    $e = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    $escape = $e;
    ob_start();
    try { eval('?>' . $partial); } finally { ob_end_clean(); }
    cleanUrlCheck($e === $escape && $e('<Program>') === '&lt;Program&gt;', 'Shared partial must preserve the page escaping helper');
}
echo "PASS: clean URL placement keys, invalid route rejection, and shared escaping helper preservation.\n";
