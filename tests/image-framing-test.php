<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/image-framing.php';
function frameCheck(bool $test, string $message): void { if (!$test) throw new RuntimeException($message); }
$path = '/assets/images/admission-header.png';
$absolute = dirname(__DIR__) . $path;
$hash = hash_file('sha256', $absolute);
frameCheck(imageFramingPath($path) === $path, 'Bundled images can have display settings');
foreach (['/uploads/../config/config.php', '/assets/images/../../.env', 'https://example.com/a.jpg', '//example.com/a.jpg', '/uploads/x.png?bad', '/uploads/%2e%2e/x.png'] as $bad) frameCheck(imageFramingPath($bad) === null, 'Unsafe or external image paths are rejected');
foreach ([['zoom' => 0], ['zoom' => 4.1], ['x' => -1], ['y' => 101], ['x' => '40'], ['zoom' => INF]] as $bad) {
    $rejected = false; try { imageFramingNormalize($bad); } catch (InvalidArgumentException $e) { $rejected = true; }
    frameCheck($rejected, 'Invalid position values are rejected');
}
$db = getDB(); $db->beginTransaction();
try {
    imageFramingSave($path, ['x' => 23.5, 'y' => 78.25, 'zoom' => 1.75], null);
    $frame = imageFramingAll()[$path];
    frameCheck($frame === ['x' => 23.5, 'y' => 78.25, 'zoom' => 1.75], 'Settings round-trip through the database');
    $_POST['image_framing'] = json_encode([$hash => ['x' => 70, 'y' => 10, 'zoom' => 2]]);
    imageFramingSaveUpload($path, $absolute);
    frameCheck(imageFramingAll()[$path] === ['x' => 70.0, 'y' => 10.0, 'zoom' => 2.0], 'Upload framing is matched to original file bytes');
    frameCheck(hash_file('sha256', $absolute) === $hash, 'Saving display metadata preserves the original image bytes');
} finally { $db->rollBack(); }
echo "PASS: metadata storage, upload association, original file preservation, bounds and safe paths; database writes rolled back.\n";
