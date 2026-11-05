<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/image-upload.php';
if (!class_exists('Imagick')) { fwrite(STDERR, "Imagick is required for these checks.\n"); exit(1); }
$files = [];
$checks = 0;
function verifyImage(bool $valid, string $message): void {
    global $checks;
    if (!$valid) throw new RuntimeException($message);
    $checks++;
}
function temporaryImage(): string {
    global $files;
    $path = tempnam(sys_get_temp_dir(), 'gnci-optimizer-test-');
    $files[] = $path;
    return $path;
}
try {
    // A highly compressed 4K image is still resized even though it is under 5 MB.
    $path = temporaryImage();
    $image = new Imagick();
    $image->newImage(3840, 2160, 'navy', 'jpeg');
    $image->setImageCompressionQuality(95);
    // Preserve AI-related metadata so optimization cannot bypass the photo checker.
    $image->setImageProperty('comment', 'AI generated fixture');
    $image->writeImage($path); $image->clear();
    verifyImage(filesize($path) < IMAGE_UPLOAD_MAX_BYTES, 'Fixture should be below 5 MB.');
    imageUploadOptimizePath($path);
    $info = getimagesize($path);
    verifyImage($info[0] === 1920 && $info[1] === 1080 && $info[2] === IMAGETYPE_JPEG, '4K JPEG should become 1920x1080 JPEG.');
    $image = new Imagick($path);
    verifyImage(str_contains($image->getImageProperty('comment'), 'AI generated'), 'AI metadata must survive optimization.');
    $image->clear();
    $hash = hash_file('sha256', $path);
    imageUploadOptimizePath($path);
    verifyImage(hash_file('sha256', $path) === $hash, 'Repeated optimization should not recompress an already fitted image.');

    $path = temporaryImage();
    $image = new Imagick(); $image->newImage(2400, 3600, 'green', 'jpeg');
    $image->writeImage($path); $image->clear();
    imageUploadOptimizePath($path); $info = getimagesize($path);
    verifyImage($info[0] === 720 && $info[1] === 1080, 'Portrait aspect ratio must be preserved.');

    $path = temporaryImage();
    $image = new Imagick(); $image->newImage(2560, 1440, 'transparent', 'png');
    $image->writeImage($path); $image->clear();
    imageUploadOptimizePath($path);
    $image = new Imagick($path); $color = $image->getImagePixelColor(0, 0)->getColor(true);
    verifyImage($image->getImageWidth() === 1920 && $image->getImageHeight() === 1080 && $color['a'] < .01, 'PNG dimensions and transparency must be preserved.');
    $image->clear();

    // EXIF orientation 6 rotates a landscape sensor image into a portrait.
    $path = temporaryImage();
    $image = new Imagick(); $image->newImage(1600, 900, 'red', 'jpeg');
    $image->setImageProfile('exif', "Exif\0\0II" . pack('vVv', 42, 8, 1) . pack('vvVV', 0x0112, 3, 1, 6) . pack('V', 0));
    $image->setImageOrientation(Imagick::ORIENTATION_RIGHTTOP);
    $image->writeImage($path); $image->clear();
    imageUploadOptimizePath($path);
    $image = new Imagick($path);
    verifyImage($image->getImageWidth() === 608 && $image->getImageHeight() === 1080, 'EXIF rotation must be applied before fitting the image.');
    verifyImage(!in_array($image->getImageOrientation(), [5, 6, 7, 8], true), 'Optimized pixels must not be rotated twice.');
    $image->clear();

    $path = temporaryImage();
    $sequence = new Imagick();
    foreach (['red', 'blue'] as $color) {
        $frame = new Imagick(); $frame->newImage(2200, 1200, $color, 'gif');
        $frame->setImageDelay(15); $sequence->addImage($frame); $frame->clear();
    }
    $sequence->setFirstIterator(); $sequence->setImageIterations(0); $sequence->writeImages($path, true); $sequence->clear();
    imageUploadOptimizePath($path);
    $sequence = new Imagick($path);
    verifyImage($sequence->getNumberImages() === 2, 'GIF animation must retain both frames.');
    foreach ($sequence as $frame) verifyImage($frame->getImageWidth() <= 1920 && $frame->getImageHeight() <= 1080 && $frame->getImageDelay() === 15, 'GIF frames must fit and preserve timing.');
    $sequence->clear();

    if (Imagick::queryFormats('WEBP')) {
        $path = temporaryImage(); $image = new Imagick(); $image->newImage(3000, 2000, 'transparent', 'webp');
        $image->writeImage($path); $image->clear(); imageUploadOptimizePath($path);
        $info = getimagesize($path);
        verifyImage($info[0] === 1620 && $info[1] === 1080 && $info[2] === IMAGETYPE_WEBP, 'WebP should retain its format and proportions.');
    }

    // Preserve the pre-existing 5 MB cap even for incompressible raster uploads.
    $path = temporaryImage();
    $image = new Imagick();
    $image->readImageBlob("P6\n1920 1080\n255\n" . random_bytes(1920 * 1080 * 3));
    $image->setImageFormat('png'); $image->writeImage($path); $image->clear();
    verifyImage(filesize($path) > IMAGE_UPLOAD_MAX_BYTES, 'Noisy fixture must exceed the saved file limit.');
    imageUploadOptimizePath($path); clearstatcache(true, $path);
    verifyImage(filesize($path) <= IMAGE_UPLOAD_MAX_BYTES && getimagesize($path)[2] === IMAGETYPE_PNG, 'Oversized uploads must retain format and fit within 5 MB.');

    $path = temporaryImage(); file_put_contents($path, str_repeat('x', IMAGE_UPLOAD_MAX_BYTES + 1));
    $rejected = false;
    try { imageUploadOptimizePath($path); } catch (InvalidArgumentException $error) { $rejected = true; }
    verifyImage($rejected, 'Oversized non-images must be refused.');
    echo "Passed $checks image optimization checks.\n";
} finally {
    foreach ($files as $path) if (is_file($path)) unlink($path);
}
