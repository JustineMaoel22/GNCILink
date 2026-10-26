<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/image-upload.php';
$path = tempnam(sys_get_temp_dir(), 'gnc-mime-');
try {
    // A real image with an arbitrary filename; browser MIME and extension are irrelevant.
    file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1cAAAAASUVORK5CYII='));
    if (imageUploadMime($path) !== 'image/png') throw new RuntimeException('PNG was not recognized.');
    file_put_contents($path, base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'));
    if (imageUploadMime($path) !== 'image/gif') throw new RuntimeException('GIF was not recognized.');
    foreach (['<?php echo "not an image";', '', '<svg xmlns="http://www.w3.org/2000/svg"></svg>', "%PDF-1.4\n"] as $invalid) {
        file_put_contents($path, $invalid);
        try {
            $mime = imageUploadMime($path);
            if (str_starts_with($mime, 'image/') && $mime !== 'image/svg+xml') throw new LogicException('Non-raster content accepted as a raster image.');
            if (!class_exists('finfo')) throw new LogicException('Fallback accepted non-raster content.');
        } catch (RuntimeException $expected) {
            if (class_exists('finfo')) throw $expected;
        }
    }
} finally { @unlink($path); }
echo 'PASS: real image detection and non-raster rejection ' . (class_exists('finfo') ? 'with Fileinfo' : 'without Fileinfo') . ".\n";
