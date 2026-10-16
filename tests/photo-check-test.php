<?php
// Run with php -n tests/photo-check-test.php (isolated, mocked Gemini transport).
define('GEMINI_API_KEY', 'test-only');
define('PHOTO_CHECK_FALLBACK_MODELS', []);
foreach (['CURLOPT_POST', 'CURLOPT_POSTFIELDS', 'CURLOPT_RETURNTRANSFER', 'CURLOPT_CONNECTTIMEOUT', 'CURLOPT_TIMEOUT', 'CURLOPT_HTTPHEADER', 'CURLINFO_HTTP_CODE'] as $i => $name) {
    define($name, $i + 1);
}
function curl_init($url) { return new stdClass(); }
function curl_setopt_array($handle, $options) { return true; }
function curl_exec($handle) { return $GLOBALS['reply']; }
function curl_getinfo($handle, $option) { return $GLOBALS['status'] ?? 200; }
function curl_error($handle) { return ''; }
function curl_close($handle) {}
require __DIR__ . '/../admin/photo-check.php';

function check($condition, $label) {
    if (!$condition) throw new RuntimeException($label);
    echo "PASS: $label\n";
}
function verdict($person, $score, $finish = 'STOP') {
    $_SESSION = [];
    $GLOBALS['reply'] = json_encode(['candidates' => [[
        'finishReason' => $finish,
        'content' => ['parts' => [
            ['thought' => true, 'text' => 'Internal reasoning'],
            ['text' => json_encode(['is_person_photo' => $person, 'ai_generated_score' => $score])],
        ]],
    ]]]);
}
$path = tempnam(sys_get_temp_dir(), 'photo-test');
try {
    file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    verdict(true, 0.1);
    check(photoCheckImage($path)['ok'], 'Camera portrait accepted, thought part ignored');
    verdict(false, 0.1);
    check(photoCheckImage($path)['reason'] === 'not_person', 'Non-person rejected');
    verdict(true, 0.9);
    check(photoCheckImage($path)['reason'] === 'ai_generated', 'Synthetic portrait rejected');
    verdict(true, null);
    check(!photoCheckImage($path)['ok'], 'Missing score fails closed');
    verdict('false', 0);
    check(!photoCheckImage($path)['checked'], 'Invalid boolean rejected');
    verdict(true, 0.1, 'MAX_TOKENS');
    check(!photoCheckImage($path)['ok'], 'Truncated response fails closed');
    verdict(true, 0.1);
    $GLOBALS['status'] = 403;
    check(!photoCheckImage($path)['ok'], 'API failure fails closed');
    check(!photoCheckImage($path)['ok'], 'Cooldown also fails closed');
    file_put_contents($path, 'not an image');
    check(photoCheckImage($path)['reason'] === 'invalid_image', 'Invalid image rejected');
} finally {
    unlink($path);
}
