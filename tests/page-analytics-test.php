<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../components/page-analytics.php';

function analyticsCheck(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}

// A read-only fixture exercises aggregation without accessing the site's database.
class AnalyticsStatement extends PDOStatement {
    private array $rows;
    public function __construct(array $rows) { $this->rows = $rows; }
    public function execute(?array $params = null): bool { return true; }
    public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return $this->rows; }
    public function fetchColumn(int $column = 0): mixed { return 7; }
}
class AnalyticsDatabase extends PDO {
    public array $pages = [];
    public array $daily = [];
    public function __construct() {}
    public function query(string $query, ?int $fetchMode = null, mixed ...$args): PDOStatement|false {
        return new AnalyticsStatement(strpos($query, 'site_visitors') !== false ? [] : $this->pages);
    }
    public function prepare(string $query, array $options = []): PDOStatement|false {
        analyticsCheck(strpos($query, 'INNER JOIN page_visits') !== false, 'Daily counts must identify their page');
        return new AnalyticsStatement($this->daily);
    }
}

$db = new AnalyticsDatabase();
foreach (['/', '/index.php', '/index', '/index.html?x=1', '/INDEX.PHP/'] as $home) {
    analyticsCheck(gncPageNormalizePath($home) === null, 'Home must be excluded: ' . $home);
    analyticsCheck(!gncPageRecord($db, $home, 'Guagua National Colleges'), 'Home must never be written');
}
$canonical = '/pages/academics/college/college-department.php?slug=cased';
foreach (['/pages/academics/college/departments/cased.php', '/pages/academics/college/departments/cased', '/pages/academics/college/college-department/cased', $canonical] as $url) {
    analyticsCheck(gncPageNormalizePath($url) === $canonical, 'CASED aliases must share an identity');
}
analyticsCheck(gncPageNormalizePath('/pages/about/history.php') === gncPageNormalizePath('/pages/about/history'), 'Static PHP and clean URLs must match');
analyticsCheck(gncPageNormalizePath('/pages/academics/college/program/beed') !== gncPageNormalizePath('/pages/academics/college/program/bsn'), 'Separate programs must remain separate');

$today = gncVisitorToday();
foreach ([
    ['/', 'Home', 670, '08:00:00'],
    ['/index.php', 'Home', 12, '08:00:00'],
    ['/pages/academics/college/departments/cased.php', 'CASED', 83, '08:00:00'],
    ['/pages/academics/college/college-department/cased', 'CASED updated', 45, '09:00:00'],
    ['/pages/about/history.php', 'History', 72, '08:00:00'],
    ['/pages/about/history', 'History', 62, '09:00:00'],
] as [$url, $title, $count, $time]) {
    $db->pages[] = ['page_url'=>$url, 'page_title'=>$title, 'visit_count'=>$count, 'last_visited'=>"$today $time"];
    $db->daily[] = ['visit_date'=>$today, 'page_url'=>$url, 'views'=>$count];
}
$popular = gncPopularPages($db, 10);
analyticsCheck(count($popular) === 2, 'Only two distinct non-Home pages should remain');
analyticsCheck($popular[0]['visit_count'] === 134 && $popular[1]['visit_count'] === 128, 'Combine counts before ranking');
analyticsCheck($popular[1]['page_title'] === 'CASED updated' && $popular[1]['last_visited'] === "$today 09:00:00", 'Use latest title and visit');
analyticsCheck(count(gncPopularPages($db, 10, ['/pages/about/history.php'])) === 1, 'Normalize exclusions too');
$summary = gncAnalyticsSummary($db);
analyticsCheck($summary['total_views'] === 262 && $summary['today_views'] === 262, 'Summary must exclude historical Home counts');
analyticsCheck($summary['unique_visitors'] === 7, 'Unique visitor counter remains independent');
analyticsCheck(gncViewsByDate($db, 2)[$today] === 262, 'Daily chart must exclude Home and include aliases');
analyticsCheck(count(gncViewsByDate($db, 2)) === 2, 'Keep zero-filled dates');

// Hosted databases also contain shared department URLs recorded before the
// tracker preserved query parameters. Reproduce the two CASED rows shown live.
$db->pages = [
    ['page_url'=>$canonical, 'page_title'=>'College of Arts and Sciences, and Education', 'visit_count'=>99, 'last_visited'=>"$today 09:00:00"],
    ['page_url'=>'/pages/academics/college/college-department.php', 'page_title'=>'College of Arts and Sciences, and Education', 'visit_count'=>55, 'last_visited'=>"$today 08:00:00"],
];
$popular = gncPopularPages($db, 10);
analyticsCheck(count($popular) === 1 && $popular[0]['visit_count'] === 154 && $popular[0]['page_url'] === $canonical, 'Hosted slugless CASED history must combine with identified CASED');
// A title alone must never combine two explicitly identified departments.
$db->pages[] = ['page_url'=>'/pages/academics/college/college-department.php?slug=another-department', 'page_title'=>'College of Arts and Sciences, and Education', 'visit_count'=>3, 'last_visited'=>"$today 07:00:00"];
analyticsCheck(count(gncPopularPages($db, 10)) === 3, 'Ambiguous title matches must preserve separate identities');
$db->pages[] = ['page_url'=>'/some-other-page', 'page_title'=>'College of Arts and Sciences, and Education', 'visit_count'=>2, 'last_visited'=>"$today 07:00:00"];
analyticsCheck(count(gncPopularPages($db, 10)) === 4, 'Unrelated pages sharing a title must stay separate');
echo "PASS: URL aliases, historical aggregation, Home recording exclusion, summary, daily charts and visitor counter.\n";
