<?php
const IMAGE_UPLOAD_MAX_BYTES = 5 * 1024 * 1024;

/** Admin-only diagnostics: no credentials, paths, or uploaded content. */
function imageUploadEnvironment(): array {
    $folder = dirname(__DIR__) . '/uploads/administration';
    return [
        'php_version' => PHP_VERSION,
        'extensions' => [
            'mbstring' => function_exists('mb_strlen') && function_exists('mb_substr'),
            'fileinfo' => class_exists('finfo') && function_exists('finfo_open'),
            'gd' => function_exists('imagecreatetruecolor'),
            'curl' => function_exists('curl_init'),
            'imagick' => class_exists('Imagick'),
        ],
        'limits' => ['upload_max_filesize'=>ini_get('upload_max_filesize'), 'post_max_size'=>ini_get('post_max_size'), 'memory_limit'=>ini_get('memory_limit'), 'max_execution_time'=>ini_get('max_execution_time')],
        'administration_upload_writable' => is_dir($folder) ? is_writable($folder) : is_writable(dirname($folder)),
    ];
}

/** Optimize large raster photos in place, retaining format, alpha and animation. */
function imageUploadOptimizePath(string $path): void {
    clearstatcache(true, $path);
    if (filesize($path) <= IMAGE_UPLOAD_MAX_BYTES) return;
    if (!class_exists('Imagick')) throw new RuntimeException('Automatic image optimization is unavailable. Please use an image up to 5 MB.');
    $info = @getimagesize($path);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
        throw new InvalidArgumentException('Use a JPG, PNG, WebP or GIF photo for automatic optimization. Other images must be up to 5 MB.');
    }
    $probe = new Imagick();
    $probe->pingImage($path);
    $frames = $probe->getNumberImages();
    $probe->clear();
    if ($frames > 100 || $info[0] * $info[1] * $frames > 80000000 || max($info[0], $info[1]) > 20000) {
        throw new InvalidArgumentException('This image is too large to optimize. Please use a smaller photo.');
    }
    $image = new Imagick($path);
    try {
        if ($frames > 1) {
            $coalesced = $image->coalesceImages();
            $image->clear();
            $image = $coalesced;
        }
        foreach ($image as $frame) {
            $frame->autoOrient();
            $frame->setImagePage(0, 0, 0, 0);
        }
        for ($attempt = 0; $attempt < 12; $attempt++) {
            foreach ($image as $frame) {
                $frame->setImageCompressionQuality(max(65, 92 - $attempt * 7));
                $frame->setOption('png:compression-level', '9');
                if ($attempt >= 3) {
                    $frame->resizeImage(max(1, (int)round($frame->getImageWidth() * .8)), max(1, (int)round($frame->getImageHeight() * .8)), Imagick::FILTER_LANCZOS, 1);
                    $frame->setImagePage(0, 0, 0, 0);
                }
            }
            $image->setFirstIterator();
            $bytes = $image->getImagesBlob();
            if (strlen($bytes) <= IMAGE_UPLOAD_MAX_BYTES) {
                if (file_put_contents($path, $bytes, LOCK_EX) !== strlen($bytes)) throw new RuntimeException('Could not optimize the uploaded image.');
                clearstatcache(true, $path);
                return;
            }
        }
        throw new InvalidArgumentException('This photo could not be optimized to 5 MB. Please use a smaller image.');
    } finally {
        $image->clear();
    }
}

/** All storage handlers call this before enforcing the common saved-image cap. */
function imageUploadPrepare(?array $file): ?array {
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return $file;
    $path = $file['tmp_name'] ?? '';
    if (!is_string($path) || !is_uploaded_file($path)) return $file;
    if (!class_exists('finfo')) throw new RuntimeException('Photo uploads require the PHP Fileinfo extension. Enable fileinfo in your hosting PHP settings.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    $file['size'] = filesize($path);
    $file['type'] = $mime;
    if (!str_starts_with($mime, 'image/')) return $file;
    if ($file['size'] > IMAGE_UPLOAD_MAX_BYTES) {
        $originalHash = hash_file('sha256', $path);
        imageUploadOptimizePath($path);
        $newHash = hash_file('sha256', $path);
        $GLOBALS['imageUploadOriginalHashes'][$newHash] = $originalHash;
        $file['size'] = filesize($path);
    }
    return $file;
}
