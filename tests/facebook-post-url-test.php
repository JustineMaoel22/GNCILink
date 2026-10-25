<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/facebook-post-url.php';

$expected = 'https://www.facebook.com/permalink.php?story_fbid=456&id=123';
$cases = [
    ['https://www.facebook.com/999/posts/456', '123_456', '789', $expected],
    ['https://www.facebook.com/123/posts/456/', '123_456', null, $expected],
    ['https://m.facebook.com/123/posts/456?fbclid=tracking', null, null, $expected],
    ['https://www.facebook.com/123/posts/456', null, null, $expected],
    [null, '123_456', null, $expected],
    ['', '123_456', null, $expected],
    ['   ', '123_456', null, $expected],
    ['javascript:alert(1)', '123_456', null, $expected],
    ['https://facebook.com.evil.example/123/posts/456', '123_456', null, $expected],
    ['https://www.facebook.com@evil.example/123/posts/456', '123_456', null, $expected],
    ['https://user@www.facebook.com/123/posts/456', '123_456', null, $expected],
    ["https://www.facebook.com/123/posts/456\ninvalid", '123_456', null, $expected],
    [null, 'invalid', '789', 'https://www.facebook.com/789'],
    [null, null, 'invalid/redirect', 'https://www.facebook.com/'],
    [null, null, null, 'https://www.facebook.com/'],
    // A stale/mismatched Graph ID must not replace the target of a valid permalink.
    ['https://www.facebook.com/123/posts/456', '789_111', null, 'https://www.facebook.com/123/posts/456'],
    ['https://www.facebook.com/permalink.php?story_fbid=456&amp;id=123', '123_456', null, $expected],
];
foreach ([
    'https://www.facebook.com/reel/987/',
    'https://www.facebook.com/123/videos/987',
    'https://www.facebook.com/watch/?v=987',
    'https://www.facebook.com/photo.php?fbid=987&set=a.654',
    'https://www.facebook.com/123/posts/pfbidAbCd123',
    'https://www.facebook.com/school/posts/456',
    'https://www.facebook.com/share/p/AbCd/',
    'https://www.facebook.com/story.php?story_fbid=456&id=123',
] as $url) {
    $cases[] = [$url, '123_456', null, $url];
}
foreach ($cases as $i => [$url, $id, $page, $want]) {
    $actual = gncFacebookPostUrl($url, $id, $page);
    if ($actual !== $want) throw new RuntimeException('Case ' . $i . ': unexpected target ' . $actual);
}
echo 'PASS: ' . count($cases) . " cases covering numeric post handoff, cached identities, missing/unsafe URLs, mismatched IDs and preserved media/opaque links.\n";
