<?php
const IMAGE_UPLOAD_MAX_BYTES = 5 * 1024 * 1024;

/** Inspect bytes server-side; raster uploads can work on hosts without Fileinfo. */
function imageUploadMime(string $path): string {
    if (class_exists('finfo') && function_exists('finfo_open')) {
        return (string)(new finfo(FILEINFO_MIME_TYPE))->file($path);
    }
    $info = @getimagesize($path);
    $types = [IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_PNG => 'image/png',
        IMAGETYPE_WEBP => 'image/webp', IMAGETYPE_GIF => 'image/gif'];
    if ($info && $info[0] > 0 && $info[1] > 0 && isset($types[$info[2]])) return $types[$info[2]];
    throw new RuntimeException('Choose a valid JPG, PNG, WebP or GIF image. Other upload types require the PHP Fileinfo extension in your hosting PHP settings.');
}

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
        $resize = static function (Imagick $frames, float $scale): void {
            foreach ($frames as $frame) {
                $frame->resizeImage(max(1, (int)round($frame->getImageWidth() * $scale)), max(1, (int)round($frame->getImageHeight() * $scale)), Imagick::FILTER_LANCZOS, 1);
                $frame->setImagePage(0, 0, 0, 0);
            }
        };
        // PNG compression is lossless: lowering JPEG-style quality does not shrink its pixels.
        // Size these formats before encoding instead of recompressing the same full-size image.
        if (in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_GIF], true)) {
            $scale = min(.9, sqrt(IMAGE_UPLOAD_MAX_BYTES * .9 / filesize($path)));
            $resize($image, max(.25, $scale));
        }
        for ($attempt = 0; $attempt < 6; $attempt++) {
            foreach ($image as $frame) {
                $frame->setImageCompressionQuality(max(65, 92 - $attempt * 7));
                $frame->setOption('png:compression-level', '4');
            }
            $image->setFirstIterator();
            $bytes = $image->getImagesBlob();
            if (strlen($bytes) <= IMAGE_UPLOAD_MAX_BYTES) {
                if (file_put_contents($path, $bytes, LOCK_EX) !== strlen($bytes)) throw new RuntimeException('Could not optimize the uploaded image.');
                clearstatcache(true, $path);
                return;
            }
            // Every retry reduces dimensions, using the actual encoded size to converge quickly.
            $resize($image, max(.25, min(.85, sqrt(IMAGE_UPLOAD_MAX_BYTES * .9 / strlen($bytes)))));
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
    $mime = imageUploadMime($path);
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
