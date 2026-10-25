<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/image-upload.php';
function imageUploadCheck(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
$paths = [];
function imageUploadFixture(string $format, int $width, int $height): Imagick {
    $image = new Imagick();
    $image->readImageBlob("P6\n{$width} {$height}\n255\n" . random_bytes($width * $height * 3));
    $image->setImageFormat($format);
    $image->setImageCompressionQuality(100);
    return $image;
}
try {
    foreach (['jpeg', 'png', 'webp', 'gif'] as $format) {
        $path = tempnam(sys_get_temp_dir(), 'gnc-image-test-'); $paths[] = $path;
        $image = imageUploadFixture($format, 2600, 2100);
        $image->setImageProperty('comment', 'trainedAlgorithmicMedia');
        if ($format === 'png') {
            $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_SET);
            $image->evaluateImage(Imagick::EVALUATE_MULTIPLY, .5, Imagick::CHANNEL_ALPHA);
        }
        if ($format === 'webp') $image->setOption('webp:lossless', 'true');
        if ($format === 'gif') {
            $image->setImageDelay(15);
            $image->addImage(imageUploadFixture('gif', 2600, 2100));
            $image->setImageDelay(30);
        }
        $image->setFirstIterator();
        $image->writeImages($path, true); $image->clear();
        clearstatcache(true, $path);
        imageUploadCheck(filesize($path) > IMAGE_UPLOAD_MAX_BYTES, $format . ' fixture must exceed 5 MB');
        $original = new Imagick($path);
        $originalComment = $original->getImageProperty('comment');
        $original->clear();
        imageUploadOptimizePath($path);
        imageUploadCheck(filesize($path) > 0 && filesize($path) <= IMAGE_UPLOAD_MAX_BYTES, $format . ' output fits 5 MB');
        $output = new Imagick($path);
        imageUploadCheck(strtolower($output->getImageFormat()) === $format, $format . ' retains its format');
        if ($originalComment !== false) imageUploadCheck($output->getImageProperty('comment') === $originalComment, 'Existing provenance metadata retained for photo checks');
        if ($format === 'png') imageUploadCheck($output->getImageAlphaChannel(), 'PNG transparency retained');
        if ($format === 'gif') {
            imageUploadCheck($output->getNumberImages() === 2, 'GIF animation retains both frames');
            $output->setFirstIterator(); imageUploadCheck($output->getImageDelay() === 15, 'First GIF frame timing retained');
            $output->nextImage(); imageUploadCheck($output->getImageDelay() === 30, 'Second GIF frame timing retained');
        }
        $output->clear();
        echo "PASS: oversized {$format} optimized to at most 5 MB.\n";
    }
    $path = tempnam(sys_get_temp_dir(), 'gnc-image-test-'); $paths[] = $path;
    $small = imageUploadFixture('png', 16, 16); $small->writeImage($path); $small->clear();
    file_put_contents($path, str_repeat("\0", IMAGE_UPLOAD_MAX_BYTES - filesize($path)), FILE_APPEND);
    $hash = hash_file('sha256', $path);
    imageUploadOptimizePath($path);
    imageUploadCheck(hash_file('sha256', $path) === $hash, 'Images at exactly 5 MB remain byte-for-byte unchanged');
    echo "PASS: exact 5 MB boundary is accepted without recompression.\n";
} finally {
    foreach ($paths as $path) if (dirname($path) === sys_get_temp_dir() && str_starts_with(basename($path), 'gnc-image-test-')) @unlink($path);
}
