<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/facebook-publishing.php';
// Only integration-domain dependencies are stubbed; all delivery SQL runs on MySQL.
$allowed = true;
$fixture = ['title' => 'Test announcement', 'content' => '<p>Details <a href="https://example.com/details">here</a></p>', 'status' => 'published'];
function hasPermission(string $permission): bool { return $GLOBALS['allowed']; }
function getAnnouncementById(int $id): array { return $GLOBALS['fixture']; }
function getAnnouncementImages(int $id): array { return $GLOBALS['gallery'] ?? []; }
function logActivity(...$args): bool { return true; }
function check(bool $ok, string $label): void { if (!$ok) throw new RuntimeException($label); }
function rejected(callable $work, string $contains): void {
    try { $work(); } catch (FacebookPublishingError $e) { check(str_contains($e->getMessage(), $contains), 'Wrong error: ' . $e->getMessage()); return; }
    throw new RuntimeException('Expected rejection: ' . $contains);
}
foreach (['PAGE_ID' => '123456', 'PAGE_TOKEN' => 'test-token-never-sent', 'APP_ID' => '789', 'APP_SECRET' => 'test-secret', 'ENVIRONMENT' => 'test', 'GRAPH_VERSION' => 'v26.0'] as $key => $value) putenv('FB_PUBLISH_' . $key . '=' . $value);
$_SESSION['user_id'] = 1;
$db = getDB();
$migration = file_get_contents(__DIR__ . '/../sql/facebook-publishing-migration.sql');
$migration = preg_replace('/CREATE TABLE IF NOT EXISTS/', 'CREATE TEMPORARY TABLE', $migration);
$migration = preg_replace('/^--.*$/m', '', $migration);
foreach (explode(';', $migration) as $sql) { if (trim($sql) !== '') $db->exec($sql); }
$db->exec("INSERT INTO facebook_publishing_connection VALUES(1,'123456','Test Page','test',1,1,NOW())");
$calls = [];
$behavior = 'success';
$graph = function ($method, $path, $params, $auth = null) use (&$calls, &$behavior) {
    $calls[] = [$method, $path, $params];
    if ($path === 'debug_token') {
        return ['data' => ['is_valid' => $behavior !== 'invalid', 'type' => $behavior === 'user-token' ? 'USER' : 'PAGE', 'app_id' => $behavior === 'wrong-app' ? '999' : ($behavior === 'numeric-app' ? 789 : '789'), 'expires_at' => $behavior === 'expired' ? time() - 100 : 0,
            'scopes' => $behavior === 'missing-scope' ? ['pages_show_list'] : ['pages_show_list', 'pages_read_engagement', 'pages_manage_posts']]];
    }
    if ($path === 'me') return ['id' => $behavior === 'wrong-page' ? '999' : '123456', 'name' => 'Test Page'];
    if ($path === '123456_555') {
        if ($behavior === 'link-unavailable') throw new FacebookPublishingError('Link lookup unavailable');
        return ['id' => '123456_555', 'permalink_url' => 'https://www.facebook.com/888/posts/555'];
    }
    if ($path === '123456/photos') {
        check($params['published'] === 'false' && $params['source'] instanceof CURLFile, 'Photo preparation is unpublished and local');
        return ['id' => '444'];
    }
    check($path === '123456/feed', 'Writes only to test Page');
    if ($behavior === 'timeout') throw new FacebookPublishingError('Meta did not confirm the result.', true);
    if ($behavior === 'rejected') throw new FacebookPublishingError('Facebook rejected the request (code 100).');
    return ['id' => '123456_555'];
};
$state = function ($id) use ($db) { $q=$db->prepare('SELECT * FROM announcement_facebook_deliveries WHERE announcement_id=?');$q->execute([$id]);return $q->fetch(); };
check(facebookPublishingReady(), 'Migration tables available');
check(str_contains(facebookPublishingMessage($fixture), 'here (https://example.com/details)'), 'Inline link retained');
check(facebookPublishingPostUrl('https://www.facebook.com/123456_555','123456_555')==='https://www.facebook.com/permalink.php?story_fbid=555&id=123456','Old composite-ID link gets a real story URL');
check(facebookPublishingPostUrl('https://evil.example/post','123456_555')==='https://www.facebook.com/permalink.php?story_fbid=555&id=123456','Unsafe permalink rejected');
check(facebookPublishingPostUrl('https://www.facebook.com/school/posts/pfbidExample','123456_555')==='https://www.facebook.com/school/posts/pfbidExample','Opaque canonical permalink preserved');
check(facebookPublishingViewPostUrl('https://www.facebook.com/888/posts/555','123456_555')==='https://www.facebook.com/permalink.php?story_fbid=555&id=123456','Admin view uses shared story/Page link handling for matching post identity');
check(facebookPublishingViewPostUrl('https://www.facebook.com/school/posts/pfbidExample','123456_555')==='https://www.facebook.com/school/posts/pfbidExample','Admin view preserves opaque Meta permalinks');
check(facebookPublishingViewPostUrl('https://www.facebook.com/888/posts/999','123456_555')==='https://www.facebook.com/888/posts/999','Mismatched identity cannot rewrite a different story');
rejected(fn() => facebookPublishingLink('javascript:alert(1)'), 'valid http');
rejected(fn() => facebookPublishAnnouncement(1, '', '999', $graph), 'Connect and authorize');
$allowed = false;
rejected(fn() => facebookPublishAnnouncement(1, '', '123456', $graph), 'permission');
$allowed = true;
foreach (['invalid' => 'invalid or expired', 'wrong-app' => 'different Meta app', 'user-token' => 'contains a User Access Token', 'expired' => 'expired', 'missing-scope' => 'Missing Facebook permission', 'wrong-page' => 'does not match'] as $behavior => $message) {
    rejected(fn() => facebookPublishAnnouncement(1, '', '123456', $graph), $message);
}
$behavior = 'numeric-app';
check(facebookValidatePage($graph)['id'] === '123456', 'Numeric Meta app ID matches configured string ID');
$behavior = 'success';
facebookPublishAnnouncement(1, 'https://example.com', '123456', $graph);
check($state(1)['state'] === 'published' && $state(1)['post_id'] === '123456_555', 'Confirmed post persisted');
check($state(1)['post_url'] === 'https://www.facebook.com/888/posts/555', 'Meta canonical permalink persisted even when owner differs from Graph ID');
$feedCalls = array_values(array_filter($calls, fn($call) => $call[1] === '123456/feed'));
check(end($feedCalls)[2]['link'] === 'https://example.com', 'Text/link post attachment');
$count = count($calls);
facebookPublishAnnouncement(1, '', '123456', $graph);
check(count($calls) === $count, 'Successful post is never sent twice');
$behavior = 'timeout';
rejected(fn() => facebookPublishAnnouncement(2, '', '123456', $graph), 'did not confirm');
check($state(2)['state'] === 'unknown', 'Timeout is uncertain');
$count = count($calls);
rejected(fn() => facebookPublishAnnouncement(2, '', '123456', $graph), 'needs review');
check(count($calls) === $count, 'Unknown delivery cannot repeat');
$behavior = 'rejected';
rejected(fn() => facebookPublishAnnouncement(3, '', '123456', $graph), 'rejected');
check($state(3)['state'] === 'failed', 'Explicit rejection can retry');
$behavior = 'success';
facebookPublishAnnouncement(3, '', '123456', $graph);
check($state(3)['state'] === 'published', 'Rejected request retry succeeds');
$db->exec("INSERT INTO announcement_facebook_deliveries VALUES(4,'123456','sending',NULL,NULL,NULL,1,NOW())");
rejected(fn() => facebookPublishAnnouncement(4, '', '123456', $graph), 'needs review');
$db->exec("UPDATE facebook_publishing_connection SET page_id='999' WHERE connection_id=1");
putenv('FB_PUBLISH_PAGE_ID=999');
$count = count($calls);
facebookPublishAnnouncement(3, '', '999', $graph);
check(count($calls) === $count, 'Switching Pages cannot repost a successful delivery');
$db->exec("INSERT INTO announcement_facebook_deliveries VALUES(5,'123456','failed',NULL,NULL,NULL,1,NOW())");
rejected(fn() => facebookPublishAnnouncement(5, '', '999', $graph), 'different Page');
putenv('FB_PUBLISH_PAGE_ID=123456');
$db->exec("UPDATE facebook_publishing_connection SET page_id='123456',enabled=0 WHERE connection_id=1");
rejected(fn() => facebookPublishAnnouncement(6, '', '123456', $graph), 'Connect and authorize');
$db->exec('UPDATE facebook_publishing_connection SET enabled=1 WHERE connection_id=1');
$behavior='link-unavailable';
facebookPublishAnnouncement(8,'','123456',$graph);
check($state(8)['state']==='published'&&$state(8)['post_url']==='https://www.facebook.com/permalink.php?story_fbid=555&id=123456','Permalink lookup failure preserves confirmed publication and safe fallback');
$count=count($calls);facebookPublishAnnouncement(8,'','123456',$graph);
check(count($calls)===$count,'Failed link lookup cannot cause a duplicate feed post');
$behavior='success';
$fixture['status'] = 'pending';
rejected(fn() => facebookPublishAnnouncement(6, '', '123456', $graph), 'Approve');
$fixture['status'] = 'draft'; // Facebook Only retains an editable, non-public record.
$directory = __DIR__ . '/../uploads/announcements';
if (!is_dir($directory)) mkdir($directory, 0755, true);
$filename = 'facebook-test-' . bin2hex(random_bytes(8)) . '.png';
$photoFile = $directory . '/' . $filename;
try {
    file_put_contents($photoFile, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aAVsAAAAASUVORK5CYII='));
    $fixture['image_path'] = '/uploads/announcements/' . $filename;
    facebookPublishAnnouncement(6, 'https://example.com/photo', '123456', $graph);
    $feedCalls = array_values(array_filter($calls, fn($call) => $call[1] === '123456/feed'));
    $params = end($feedCalls)[2];
    check(isset($params['attached_media[0]']) && !isset($params['link']), 'Photo attached to one feed post');
    check(str_contains($params['message'], 'https://example.com/photo'), 'Photo post retains link in text');
    $fixture['image_path'] = '/config/config.php';
    rejected(fn() => facebookPublishAnnouncement(7, '', '123456', $graph), 'outside announcement');
    $fixture['image_path'] = '/uploads/announcements/' . $filename;
    $gallery = array_map(fn($n) => ['file_path' => '/uploads/announcements/photo-' . $n . '.png'], range(1, 10));
    rejected(fn() => facebookPublishAnnouncement(7, '', '123456', $graph), 'up to 10');
} finally { if (is_file($photoFile)) unlink($photoFile); }
$key = str_repeat('a', 64);
$receipt = $db->prepare('INSERT INTO facebook_publishing_requests VALUES(?,1,1,NOW())');
$receipt->execute([$key]);
try { $receipt->execute([$key]); throw new RuntimeException('Duplicate form request was accepted'); }
catch (PDOException $e) { check($e->getCode() === '23000', 'Request key has a uniqueness constraint'); }
echo "PASS: temporary MySQL schema, authorization, expiry/scopes/Page identity, destination confirmation, successful/uncertain/crashed/failed delivery retry rules, Page switching, disconnect, approval, local photos, links and duplicate request receipts. No live Meta calls or persisted site writes.\n";
