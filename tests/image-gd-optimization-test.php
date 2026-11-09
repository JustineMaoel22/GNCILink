<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/image-upload.php';
if (!function_exists('imagecreatetruecolor')) throw new RuntimeException('GD is required.');
$files = []; $checks = 0;
function gdVerify(bool $condition, string $message): void {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function gdFixture(int $width, int $height, string $format): string {
    global $files;
    $path = tempnam(sys_get_temp_dir(), 'gnci-gd-test-'); $files[] = $path;
    $image = imagecreatetruecolor($width, $height);
    imagealphablending($image, false); imagesavealpha($image, true);
    imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, imagecolorallocatealpha($image, 20, 60, 100, 127));
    ('image' . $format)($image, $path);
    unset($image);
    return $path;
}
function gdOptimize(string $path): void {
    // Exercise the fallback directly even when the test host also has Imagick.
    imageUploadOptimizeGd($path, getimagesize($path), filesize($path));
}
try {
    foreach (['jpeg', 'png', 'webp'] as $format) {
        if (!function_exists('image' . $format)) continue;
        $path = gdFixture(3840, 2160, $format);
        if ($format === 'jpeg') {
            $bytes = file_get_contents($path);
            $comment = 'AI generated fixture';
            file_put_contents($path, substr($bytes, 0, 2) . "\xff\xfe" . pack('n', strlen($comment) + 2) . $comment . substr($bytes, 2));
            unset($bytes);
        }
        gdOptimize($path); $info = getimagesize($path);
        gdVerify($info[0] === 1920 && $info[1] === 1080, "$format fits within 1080p.");
        gdVerify($info[2] === ['jpeg' => IMAGETYPE_JPEG, 'png' => IMAGETYPE_PNG, 'webp' => IMAGETYPE_WEBP][$format], 'Format retained.');
        if ($format === 'jpeg') gdVerify(str_contains(file_get_contents($path), 'AI generated fixture'), 'JPEG provenance retained.');
        else {
            $image = ('imagecreatefrom' . $format)($path);
            gdVerify((imagecolorat($image, 0, 0) >> 24) === 127, "$format transparency retained.");
            unset($image);
        }
        $hash = hash_file('sha256', $path); gdOptimize($path);
        gdVerify(hash_file('sha256', $path) === $hash, 'Fitted photos remain unchanged.');
    }
    $path = gdFixture(4016, 6016, 'jpeg');
    gdOptimize($path); $info = getimagesize($path);
    gdVerify($info[0] === 721 && $info[1] === 1080, 'Live-size portrait fits without cropping under the configured memory limit.');

    $path = gdFixture(2200, 1200, 'png');
    $bytes = file_get_contents($path);
    $text = "Comment\0AI generated fixture";
    $chunk = pack('N', strlen($text)) . 'tEXt' . $text . pack('N', crc32('tEXt' . $text));
    file_put_contents($path, substr($bytes, 0, 33) . $chunk . substr($bytes, 33)); unset($bytes);
    gdOptimize($path);
    gdVerify(str_contains(file_get_contents($path), $text), 'PNG provenance retained.');

    if (function_exists('imagewebp')) {
        $path = gdFixture(2200, 1200, 'webp'); $bytes = file_get_contents($path);
        $text = 'AI generated WebP fixture';
        $body = substr($bytes, 12) . 'XMP ' . pack('V', strlen($text)) . $text . (strlen($text) % 2 ? "\0" : '');
        $body[8] = chr(ord($body[8]) | 4);
        file_put_contents($path, 'RIFF' . pack('V', strlen($body) + 4) . 'WEBP' . $body); unset($bytes, $body);
        gdOptimize($path); $image = imagecreatefromwebp($path);
        gdVerify($image !== false && imagesx($image) === 1920 && str_contains(file_get_contents($path), $text), 'WebP metadata survives in a decodable image.'); unset($image);
    }

    $path = gdFixture(1600, 900, 'jpeg');
    $bytes = file_get_contents($path);
    $exif = "Exif\0\0II" . pack('vVv', 42, 8, 1) . pack('vvVV', 0x0112, 3, 1, 6) . pack('V', 0);
    file_put_contents($path, substr($bytes, 0, 2) . "\xff\xe1" . pack('n', strlen($exif) + 2) . $exif . substr($bytes, 2)); unset($bytes);
    gdOptimize($path); $info = getimagesize($path);
    gdVerify($info[0] === 1080 && $info[1] === 608 && exif_read_data($path)['Orientation'] === 6, 'Sensor pixels fit and retained EXIF displays a 608x1080 portrait.');

    // Animated PNG must never be silently flattened.
    $path = gdFixture(2200, 1200, 'png'); $bytes = file_get_contents($path);
    $animation = pack('NN', 2, 0);
    $chunk = pack('N', 8) . 'acTL' . $animation . pack('N', crc32('acTL' . $animation));
    file_put_contents($path, substr($bytes, 0, 33) . $chunk . substr($bytes, 33)); unset($bytes);
    $hash = hash_file('sha256', $path); $rejected = false;
    try { gdOptimize($path); } catch (InvalidArgumentException $error) { $rejected = str_contains($error->getMessage(), 'animated'); }
    gdVerify($rejected && hash_file('sha256', $path) === $hash, 'Animation rejected with original bytes intact.');

    // Noisy PNG pixels exceed 5 MB even at 1080p; retry must shrink dimensions.
    $path = gdFixture(1, 1, 'png');
    $rows = '';
    for ($row = 0; $row < 1080; $row++) $rows .= "\0" . random_bytes(1920 * 3);
    $pngChunk = static fn(string $type, string $data): string => pack('N', strlen($data)) . $type . $data . pack('N', crc32($type . $data));
    file_put_contents($path, "\x89PNG\r\n\x1a\n" . $pngChunk('IHDR', pack('NNCCCCC', 1920, 1080, 8, 2, 0, 0, 0)) . $pngChunk('IDAT', gzcompress($rows)) . $pngChunk('IEND', '')); unset($rows);
    clearstatcache(true, $path);
    gdVerify(filesize($path) > IMAGE_UPLOAD_MAX_BYTES, 'Noisy PNG exceeds the saved size limit.');
    gdOptimize($path);
    gdVerify(filesize($path) <= IMAGE_UPLOAD_MAX_BYTES && getimagesize($path)[2] === IMAGETYPE_PNG, 'Retry fits noisy PNG within 5 MB while retaining its format.');

    $path = gdFixture(2200, 1200, 'jpeg'); $hash = hash_file('sha256', $path);
    gc_mem_caches();
    $oldLimit = ini_get('memory_limit'); ini_set('memory_limit', '32M'); $rejected = false;
    try { gdOptimize($path); } catch (InvalidArgumentException $error) { $rejected = str_contains($error->getMessage(), 'memory'); }
    finally { ini_set('memory_limit', $oldLimit); }
    gdVerify($rejected && hash_file('sha256', $path) === $hash, 'Memory budget rejected safely before decoding.');

    if (!class_exists('Imagick')) {
        $path = gdFixture(3840, 2160, 'jpeg'); imageUploadOptimizePath($path);
        gdVerify(getimagesize($path)[0] === 1920, 'Shared upload entry point selects GD without Imagick.');
    }
    echo "Passed $checks GD image optimization checks.\n";
} finally {
    foreach ($files as $path) if (is_file($path)) unlink($path);
}
