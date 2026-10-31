<?php
const IMAGE_UPLOAD_MAX_BYTES = 5 * 1024 * 1024;

/** Check PDF header, final cross-reference pointer and EOF without trusting the filename. */
function uploadPdfSignatureValid(string $path): bool {
    $stream = @fopen($path, 'rb');
    if ($stream === false) return false;
    try {
        $size = fstat($stream)['size'];
        if ($size < 32 || !preg_match('/\A%PDF-[12]\.[0-9](?:\r\n|\r|\n)/', fread($stream, 16))) return false;
        if (fseek($stream, max(0, $size - 2048)) !== 0) return false;
        $tail = stream_get_contents($stream);
        if (!preg_match('/startxref\s+([0-9]+)\s+%%EOF[\x00\x09\x0A\x0C\x0D\x20]*\z/', $tail, $match)) return false;
        $offset = (int)$match[1];
        if ($offset < 9 || $offset >= $size || fseek($stream, $offset) !== 0) return false;
        $xref = fread($stream, 2048);
        // PDFs may use a conventional xref table or a PDF 1.5+ cross-reference stream.
        return (bool)preg_match('/\Axref\s+[0-9]+\s+[0-9]+\s/', $xref)
            || (bool)preg_match('/\A[0-9]+\s+[0-9]+\s+obj\s*<<(?:(?!>>).)*\/Type\s*\/XRef\b/s', $xref);
    } finally {
        fclose($stream);
    }
}

/** Inspect bytes server-side; raster and curriculum PDF uploads work without Fileinfo. */
function imageUploadMime(string $path): string {
    if (class_exists('finfo') && function_exists('finfo_open')) {
        return (string)(new finfo(FILEINFO_MIME_TYPE))->file($path);
    }
    $info = @getimagesize($path);
    $types = [IMAGETYPE_JPEG => 'image/jpeg', IMAGETYPE_PNG => 'image/png',
        IMAGETYPE_WEBP => 'image/webp', IMAGETYPE_GIF => 'image/gif'];
    if ($info && $info[0] > 0 && $info[1] > 0 && isset($types[$info[2]])) return $types[$info[2]];
    if (uploadPdfSignatureValid($path)) return 'application/pdf';
    // MP4/WebM by container signature (ISO base media 'ftyp' box, or EBML header).
    $head = (string)@file_get_contents($path, false, null, 0, 16);
    if (strlen($head) >= 12 && substr($head, 4, 4) === 'ftyp') return 'video/mp4';
    if (strncmp($head, "\x1A\x45\xDF\xA3", 4) === 0) return 'video/webm';
    throw new RuntimeException('Choose a valid JPG, PNG, WebP, GIF, PDF, MP4 or WebM file.');
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
    if (!class_exists('Imagick') || !method_exists('Imagick', 'pingImage')) throw new RuntimeException('Automatic image optimization is unavailable. Please use an image up to 5 MB.');
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

/** Optimize oversized originals before enforcing the common saved-image cap. */
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
