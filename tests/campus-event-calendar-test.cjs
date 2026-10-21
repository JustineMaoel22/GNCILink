// Exercise the actual PHP calendar query against an isolated SQLite fixture.
const assert = require('node:assert/strict');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { DatabaseSync } = require('node:sqlite');
const db = new DatabaseSync(':memory:');
db.exec(`
    CREATE TABLE categories (category_id INTEGER, category_name TEXT, category_color TEXT);
    CREATE TABLE events (event_id INTEGER, title TEXT, start_date TEXT, end_date TEXT, status TEXT, category_id INTEGER);
    INSERT INTO categories VALUES (1,'School Event','#1F5E2C'),(2,'Holiday','#EABA3B'),(3,'Emergency','#B3312D');
    INSERT INTO events VALUES
    (1,'School assembly','2026-10-12 08:00:00',NULL,'published',1),
    (2,'Holiday break','2026-10-13 00:00:00',NULL,'published',2),
    (3,'Emergency closure','2026-10-14 00:00:00',NULL,'published',3),
    (4,'Draft assembly','2026-10-15 00:00:00',NULL,'draft',1),
    (5,'Cross-month fair','2026-09-30 00:00:00','2026-10-02 23:59:59','published',1),
    (6,'Next month','2026-11-01 00:00:00',NULL,'published',1),
    (7,'<script>school</script>','2026-10-16 00:00:00',NULL,'published',1);
    ALTER TABLE events ADD COLUMN program TEXT;
    ALTER TABLE events ADD COLUMN location TEXT;
    UPDATE events SET program = CASE WHEN event_id IN (1,2,4) THEN 'CS' ELSE 'ED' END;
    CREATE TABLE programs (program_code TEXT, dept_id INTEGER);
    INSERT INTO programs VALUES ('CS',1),('ED',2);
`);
const root = path.resolve(__dirname, '..').replaceAll('\\', '/');
const php = process.env.PHP_BINARY || 'C:/php/php.exe';
const harness = `
    $input = json_decode(stream_get_contents(STDIN), true);
    $_GET = $input['query'] ?? ['evt_month'=>10, 'evt_year'=>2026];
    function getDepartments() { return [
        ['dept_id'=>1,'slug'=>'computing','dept_name'=>'Computing'],
        ['dept_id'=>2,'slug'=>'education','dept_name'=>'Education'],
        ['dept_id'=>3,'slug'=>'empty','dept_name'=>'Empty'],
    ]; }
    function getProgramCategories() { return ['ALL'=>'All Programs','CS'=>'Computer Science','ED'=>'Education']; }
    require getenv('CALENDAR_TEST_ROOT').'/components/calendar-filters.php';
    $calendarFilters = publicCalendarFilters($_GET);
    $calendarFilterSql = $calendarFilters['condition'];
    $calendarNavigationParams = array_filter(['department'=>$calendarFilters['department'], 'program'=>$calendarFilters['program']], fn($v) => $v !== null);
    if ($input['school']) $calendarCategory = 'School Event';
    $db = new class($input['rows']) {
        public $sql; public $params; public $rows;
        function __construct($rows) { $this->rows = $rows; }
        function prepare($sql) { $this->sql = $sql; return $this; }
        function execute($params) { $this->params = $params; }
        function fetchAll() { return $this->rows; }
    };
    require getenv('CALENDAR_TEST_ROOT').'/components/calendar-month.php';
    $monthSql = $db->sql;
    $monthParams = $db->params;
    ob_start();
    include getenv('CALENDAR_TEST_ROOT').'/components/calendar-card.php';
    $html = ob_get_clean();
    $upcomingHtml = '';
    if ($input['upcoming']) {
        require getenv('CALENDAR_TEST_ROOT').'/components/calendar-upcoming-data.php';
        function ceManageMark($value) { return ''; }
        $calendarUpcomingHeading = 'Upcoming Events';
        $calendarFilterLabel = $calendarFilters['label'];
        $calendarFilterUrl = fn($filter) => '?#events';
        ob_start();
        include getenv('CALENDAR_TEST_ROOT').'/components/calendar-upcoming-card.php';
        $upcomingHtml = ob_get_clean();
    }
    echo json_encode(['sql'=>$monthSql, 'params'=>$monthParams, 'days'=>$publicEventsByDay, 'html'=>$html,
        'upcomingSql'=>$db->sql, 'upcomingParams'=>$db->params, 'upcomingHtml'=>$upcomingHtml]);
`;
function render(school, rows = [], query, upcoming = false) {
    const result = spawnSync(php, ['-r', harness], {
        input: JSON.stringify({ school, rows, query, upcoming }), encoding: 'utf8',
        env: { ...process.env, CALENDAR_TEST_ROOT: root }, windowsHide: true,
    });
    assert.equal(result.status, 0, result.stderr);
    assert.equal(result.stderr, '', 'PHP renders without warnings');
    return JSON.parse(result.stdout);
}
for (const school of [true, false]) {
    const query = render(school);
    const rows = db.prepare(query.sql).all(...query.params);
    assert.deepEqual(rows.map(row => row.event_id), school ? [5, 1, 7] : [5, 1, 2, 3, 7]);
    const result = render(school, rows);
    assert.equal(result.days['1'][0].title, 'Cross-month fair');
    assert.equal(result.days['2'][0].title, 'Cross-month fair');
    assert.equal(result.days['3'], undefined, 'Multi-day events stop at their end date');
    assert.ok(result.html.includes('&lt;script&gt;school&lt;/script&gt;'));
    assert.ok(!result.html.includes('<script>school</script>'));
    assert.equal(result.html.includes('Holiday'), !school);
    assert.equal(result.html.includes('Emergency'), !school);
    assert.ok(result.html.includes('evt_month=11&amp;evt_year=2026#events'));
}
const end = render(true, [], { evt_month: 12, evt_year: 2050 });
assert.ok(end.html.includes('Maximum calendar year 2050 reached'));
assert.ok(!end.html.includes('evt_year=2051'));
const invalid = render(true, [], { evt_month: ['10'], evt_year: ['2026'] });
assert.ok(invalid.html.includes('gnc-cal-card'));
for (const [scope, expected] of [
    [{program:'CS'}, [1]],
    [{department:'computing'}, [1]],
    [{department:'education'}, [5,7]],
    [{department:'empty'}, []],
    [{department:'computing',program:'ED'}, [1]],
    [{department:['computing'],program:['CS']}, [5,1,7]],
]) {
    const result = render(true, [], {evt_month:10,evt_year:2026,...scope});
    assert.deepEqual(db.prepare(result.sql).all(...result.params).map(row=>row.event_id), expected);
    if (typeof scope.department === 'string') assert.ok(result.html.includes('department=' + scope.department), 'Month navigation retains the department');
    else if (typeof scope.program === 'string') assert.ok(result.html.includes('program=' + scope.program), 'Month navigation retains the program');
}
db.exec(`
    UPDATE events SET start_date='2000-01-01 00:00:00', end_date=NULL;
    INSERT INTO events VALUES
    (10,'Future holiday','2099-01-01 00:00:00',NULL,'published',2,'CS',NULL),
    (11,'Future draft','2099-01-02 00:00:00',NULL,'draft',1,'CS',NULL),
    (12,'School fair','2099-01-03 08:30:00','2099-01-03 10:00:00','published',1,'CS','Main Hall'),
    (13,'Education day','2099-01-04 00:00:00',NULL,'published',1,'ED',NULL),
    (14,'School assembly','2099-01-05 00:00:00',NULL,'published',1,'CS',NULL),
    (15,'Later school event','2099-01-06 00:00:00',NULL,'published',1,'CS',NULL);
`);
for (const [school, scope, ids] of [
    [true, {}, [12,13,14]],
    [true, {program:'CS'}, [12,14,15]],
    [true, {department:'computing'}, [12,14,15]],
    [true, {department:'empty'}, []],
    [false, {}, [10,12,13]],
]) {
    const query = {evt_month:10,evt_year:2026,...scope};
    const result = render(school, [], query, true);
    const rows = db.prepare(result.upcomingSql).all(...result.upcomingParams);
    assert.deepEqual(rows.map(row=>row.event_id), ids, 'Upcoming events honor category, visibility, scope, date order, and limit');
    const view = render(school, rows, query, true).upcomingHtml;
    assert.ok(view.includes('Upcoming Events'));
    if (ids.includes(12)) {
        assert.ok(view.includes('Main Hall'));
        assert.ok(view.includes('8:30 AM - 10:00 AM'));
        assert.ok(view.includes('All Day'));
    } else assert.ok(view.includes('No upcoming events'));
}
console.log('PASS: month and upcoming calendars, School Event scope, department/program filters, published visibility, next-three ordering, time/location rendering, and navigation. No website database changes.');
db.close();
