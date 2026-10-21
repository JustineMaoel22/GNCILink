const assert = require('node:assert/strict');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const { DatabaseSync } = require('node:sqlite');
const db = new DatabaseSync(':memory:');
db.exec(`
    CREATE TABLE categories (category_id INTEGER, category_name TEXT);
    CREATE TABLE media_library (media_id INTEGER, file_path TEXT);
    CREATE TABLE announcements (announcement_id INTEGER, title TEXT, slug TEXT, published_at TEXT, created_at TEXT, featured_image INTEGER, category_id INTEGER, status TEXT);
    CREATE TABLE news (news_id INTEGER, title TEXT, slug TEXT, published_at TEXT, created_at TEXT, featured_image INTEGER, category_id INTEGER, status TEXT);
    CREATE TABLE fb_posts_cache (fb_post_id TEXT, message TEXT, created_time TEXT, image_media_id INTEGER, category_id INTEGER, permalink_url TEXT, category_locked INTEGER);
    INSERT INTO categories VALUES (1,'School Event'),(2,'Holiday');
    INSERT INTO media_library VALUES (1,'/uploads/school.jpg');
    INSERT INTO announcements VALUES
        (1,'School announcement','school-announcement','2026-10-20', '2026-10-01',1,1,'published'),
        (2,'Holiday announcement','holiday',NULL,'2026-10-21',NULL,2,'published'),
        (3,'Draft announcement','draft',NULL,'2026-10-21',NULL,1,'draft'),
        (4,'Old school announcement','old',NULL,'2025-01-01',NULL,1,'published');
    INSERT INTO news VALUES
        (1,'School news','school-news',NULL,'2026-10-19',NULL,1,'published'),
        (2,'Holiday news','holiday-news',NULL,'2026-10-21',NULL,2,'published'),
        (3,'Archived news','archived',NULL,'2026-10-21',NULL,1,'archived'),
        (4,'Uncategorized news','uncategorized',NULL,'2026-10-21',NULL,NULL,'published');
    INSERT INTO fb_posts_cache VALUES
        ('page_school','School lecture series','2026-10-18',1,1,'https://www.facebook.com/posts/school',0),
        ('page_locked','Manually categorized school event','2026-10-17',NULL,1,'https://www.facebook.com/posts/locked',1),
        ('page_holiday','Holiday Facebook post','2026-10-21',NULL,2,NULL,0),
        ('page_uncategorized','Uncategorized Facebook post','2026-10-21',NULL,NULL,NULL,0);
`);
for (let id = 5; id <= 10; id++) {
    db.prepare('INSERT INTO news VALUES (?,?,?,NULL,?,NULL,1,?)').run(id, 'School news ' + id, 'school-' + id, '2026-09-01', 'published');
}
const harness = `
    $input = json_decode(stream_get_contents(STDIN), true);
    class CapturedStatement extends PDOStatement {
        public function __construct(private $owner, private string $sql) {}
        public function execute(?array $params = null): bool { $this->owner->queries[] = ['sql'=>$this->sql, 'params'=>$params]; return true; }
        public function fetchColumn(int $column = 0): mixed { return $this->owner->total; }
        public function fetchAll(int $mode = PDO::FETCH_DEFAULT, mixed ...$args): array { return []; }
    }
    class CapturedDB extends PDO {
        public array $queries = [];
        public function __construct(public int $total) {}
        public function prepare(string $query, array $options = []): PDOStatement|false { return new CapturedStatement($this, $query); }
    }
    require getenv('CAMPUS_TEST_ROOT').'/includes/campus-event-posts.php';
    $db = new CapturedDB($input['total']);
    $result = campusEventPosts($db, $input['query']);
    echo json_encode(['queries'=>$db->queries, 'result'=>$result]);
`;
function listing(total, query = {}) {
    const result = spawnSync(process.env.PHP_BINARY || 'C:/php/php.exe', ['-r', harness], {
        input: JSON.stringify({total, query}), encoding: 'utf8', windowsHide: true,
        env: {...process.env, CAMPUS_TEST_ROOT:path.resolve(__dirname, '..').replaceAll('\\', '/')},
    });
    if (result.error) throw result.error;
    assert.equal(result.status, 0, result.stderr);
    assert.equal(result.stderr, '');
    return JSON.parse(result.stdout);
}
const initial = listing(0);
const count = initial.queries[0];
const total = db.prepare(count.sql).get(...count.params)['COUNT(*)'];
assert.equal(total, 11, 'Published School Event articles and categorized Facebook posts are counted');
const all = [];
for (const page of [1, 2]) {
    const result = listing(total, {event_page:page, evt_month:1, evt_year:2050, category:'Holiday'});
    assert.equal(result.result.pages, 2);
    const query = result.queries[1];
    const rows = db.prepare(query.sql).all(...query.params);
    assert.equal(rows.length, page === 1 ? 6 : 5);
    all.push(...rows);
}
assert.equal(all[0].title, 'School announcement', 'Posts sort by publication date');
assert.equal(all[0].image_path, '/uploads/school.jpg');
assert.equal(all[1].post_type, 'news');
assert.equal(all[1].image_path, null, 'Posts without featured images remain visible');
assert.equal(all[2].post_type, 'facebook', 'Imported school announcements appear with articles');
assert.equal(all[2].permalink_url, 'https://www.facebook.com/posts/school');
assert.equal(all[2].image_path, '/uploads/school.jpg');
assert.equal(all[3].title, 'Manually categorized school event', 'Manual category assignments are honored');
assert.equal(all.at(-1).title, 'Old school announcement', 'Older posts remain accessible across calendar months');
assert.equal(new Set(all.map(row => row.post_type + ':' + row.post_id)).size, total, 'Pagination has no duplicates or omissions');
assert.equal(listing(total, {event_page:999}).result.page, 2);
for (const value of [-1, 'invalid', ['2']]) assert.equal(listing(total, {event_page:value}).result.page, 1);
assert.equal(listing(0).result.pages, 1);
console.log('PASS: combined School Event feed, published visibility, date sorting, images, pagination, calendar independence, and invalid page inputs. No website database changes.');
db.close();
