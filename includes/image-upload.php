<?php
const IMAGE_UPLOAD_MAX_BYTES = 5 * 1024 * 1024;
const IMAGE_UPLOAD_MAX_WIDTH = 1920;
const IMAGE_UPLOAD_MAX_HEIGHT = 1080;

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

/** Fit raster uploads within 1080p without cropping/upscaling; preserve format, alpha and animation. */
function imageUploadOptimizePath(string $path): void {
    clearstatcache(true, $path);
    $originalSize = filesize($path);
    $info = @getimagesize($path);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
        if ($originalSize <= IMAGE_UPLOAD_MAX_BYTES) return;
        throw new InvalidArgumentException('Use a JPG, PNG, WebP or GIF photo for automatic optimization. Other images must be up to 5 MB.');
    }
    $needsResize = $info[0] > IMAGE_UPLOAD_MAX_WIDTH || $info[1] > IMAGE_UPLOAD_MAX_HEIGHT;
    if (!class_exists('Imagick') || !method_exists('Imagick', 'pingImage')) {
        imageUploadOptimizeGd($path, $info, $originalSize);
        return;
    }
    $probe = new Imagick();
    $probe->pingImage($path);
    $frames = $probe->getNumberImages();
    // A portrait may fit before EXIF rotation but exceed the height limit afterwards.
    if (in_array($probe->getImageOrientation(), [5, 6, 7, 8], true)) {
        $needsResize = $info[1] > IMAGE_UPLOAD_MAX_WIDTH || $info[0] > IMAGE_UPLOAD_MAX_HEIGHT;
    }
    $probe->clear();
    if (!$needsResize && $originalSize <= IMAGE_UPLOAD_MAX_BYTES) return;
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
            $scale = min(1, IMAGE_UPLOAD_MAX_WIDTH / $frame->getImageWidth(), IMAGE_UPLOAD_MAX_HEIGHT / $frame->getImageHeight());
            if ($scale < 1) {
                $frame->resizeImage(max(1, (int)round($frame->getImageWidth() * $scale)), max(1, (int)round($frame->getImageHeight() * $scale)), Imagick::FILTER_LANCZOS, 1);
            }
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
        if (!$needsResize && in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_GIF], true)) {
            $scale = min(.9, sqrt(IMAGE_UPLOAD_MAX_BYTES * .9 / filesize($path)));
            $resize($image, max(.25, $scale));
        }
        for ($attempt = 0; $attempt < 6; $attempt++) {
            foreach ($image as $frame) {
                $frame->setImageCompressionQuality(max(65, 82 - $attempt * 5));
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

/** Keep metadata (including photo-check provenance) without decoding entire files. */
function imageUploadGdMetadata(string $path, int $type): array {
    $stream = fopen($path, 'rb');
    if (!$stream) throw new RuntimeException('Could not read the uploaded image.');
    $metadata = ''; $animated = $type === IMAGETYPE_GIF;
    try {
        fseek($stream, $type === IMAGETYPE_JPEG ? 2 : ($type === IMAGETYPE_PNG ? 8 : 12));
        while (!feof($stream)) {
            if ($type === IMAGETYPE_JPEG) {
                $marker = fread($stream, 2);
                if (strlen($marker) !== 2 || $marker[0] !== "\xff" || in_array(ord($marker[1]), [0xda, 0xd9], true)) break;
                $lengthBytes = fread($stream, 2);
                if (strlen($lengthBytes) !== 2) break;
                $length = unpack('n', $lengthBytes)[1] - 2;
                if ($length < 0) break;
                $payload = $length ? fread($stream, $length) : '';
                if (strlen($payload) !== $length) break;
                $code = ord($marker[1]);
                if (($code >= 0xe1 && $code <= 0xef) || $code === 0xfe) $metadata .= $marker . $lengthBytes . $payload;
            } elseif (in_array($type, [IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
                $header = fread($stream, 8);
                if (strlen($header) !== 8) break;
                $png = $type === IMAGETYPE_PNG;
                $length = unpack($png ? 'N' : 'V', substr($header, $png ? 0 : 4, 4))[1];
                $chunk = substr($header, $png ? 4 : 0, 4);
                $extra = $png ? 4 : ($length % 2);
                if ($length > filesize($path) - ftell($stream) - $extra) break;
                if (in_array($chunk, ['acTL', 'ANIM', 'ANMF'], true)) $animated = true;
                if (in_array($chunk, ['tEXt', 'zTXt', 'iTXt', 'eXIf', 'iCCP', 'caBX', 'EXIF', 'XMP ', 'ICCP'], true)) {
                    $metadata .= $header . fread($stream, $length + $extra);
                } else {
                    fseek($stream, $length + $extra, SEEK_CUR);
                }
            } else break;
        }
    } finally { fclose($stream); }
    return [$metadata, $animated];
}

/** GD fallback for static photos; never flatten animations or silently accept large dimensions. */
function imageUploadOptimizeGd(string $path, array $info, int $originalSize): void {
    $orientation = 1;
    if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($path);
        $orientation = (int)($exif['Orientation'] ?? 1);
    }
    // Keep EXIF and sensor orientation together; browsers apply the retained orientation.
    $rotated = in_array($orientation, [5, 6, 7, 8], true);
    $maxWidth = $rotated ? IMAGE_UPLOAD_MAX_HEIGHT : IMAGE_UPLOAD_MAX_WIDTH;
    $maxHeight = $rotated ? IMAGE_UPLOAD_MAX_WIDTH : IMAGE_UPLOAD_MAX_HEIGHT;
    [$metadata, $animated] = imageUploadGdMetadata($path, $info[2]);
    if ($info[2] === IMAGETYPE_JPEG && !function_exists('exif_read_data') && str_contains($metadata, "Exif\0\0")) {
        // Without EXIF support, fit both axes within 1080 so any retained rotation fits.
        $maxWidth = $maxHeight = IMAGE_UPLOAD_MAX_HEIGHT;
    }
    $scale = min(1, $maxWidth / $info[0], $maxHeight / $info[1]);
    if ($scale === 1 && $originalSize <= IMAGE_UPLOAD_MAX_BYTES) return;
    if ($animated) throw new InvalidArgumentException('Resizing GIF or animated images requires Imagick. Please upload an image already within 1920 x 1080 and 5 MB, or use a static JPG, PNG or WebP.');
    $decoder = [IMAGETYPE_JPEG => 'imagecreatefromjpeg', IMAGETYPE_PNG => 'imagecreatefrompng', IMAGETYPE_WEBP => 'imagecreatefromwebp'][$info[2]] ?? '';
    $encoder = [IMAGETYPE_JPEG => 'imagejpeg', IMAGETYPE_PNG => 'imagepng', IMAGETYPE_WEBP => 'imagewebp'][$info[2]] ?? '';
    if (!$decoder || !function_exists($decoder) || !function_exists($encoder)) {
        throw new RuntimeException('Automatic resizing is unavailable for this image format. Please upload a photo already within 1920 x 1080 and 5 MB.');
    }
    $width = max(1, (int)round($info[0] * $scale));
    $height = max(1, (int)round($info[1] * $scale));
    $limitText = trim(ini_get('memory_limit'));
    $limit = (float)$limitText * match (strtolower(substr($limitText, -1))) { 'g' => 1073741824, 'm' => 1048576, 'k' => 1024, default => 1 };
    $estimate = $info[0] * $info[1] * 4 + $width * $height * 8 + 16 * 1024 * 1024;
    if (max($info[0], $info[1]) > 20000 || $info[0] * $info[1] > 80000000 || ($limit > 0 && memory_get_usage() + $estimate > $limit)) {
        throw new InvalidArgumentException('This photo needs more memory than the server has available. Please resize it to 1920 x 1080 or smaller before uploading.');
    }
    $source = @$decoder($path);
    if (!$source) throw new InvalidArgumentException('This photo could not be decoded. Please choose a valid JPG, PNG or WebP.');
    try {
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $output = imagecreatetruecolor($width, $height);
            if (!$output) throw new RuntimeException('Could not allocate the resized image.');
            try {
                imagealphablending($output, false);
                imagesavealpha($output, true);
                if (!imagecopyresampled($output, $source, 0, 0, 0, 0, $width, $height, $info[0], $info[1])) throw new RuntimeException('Could not resize the photo.');
                ob_start();
                try {
                    $written = $encoder($output, null, $info[2] === IMAGETYPE_PNG ? 6 : max(65, 82 - $attempt * 5));
                    $bytes = ob_get_contents();
                } finally { ob_end_clean(); }
                if (!$written || !$bytes) throw new RuntimeException('Could not encode the resized photo.');
            } finally { unset($output); }
            if ($metadata !== '') {
                if ($info[2] === IMAGETYPE_JPEG) $bytes = substr($bytes, 0, 2) . $metadata . substr($bytes, 2);
                elseif ($info[2] === IMAGETYPE_PNG) $bytes = substr($bytes, 0, 33) . $metadata . substr($bytes, 33);
                else {
                    // Extended WebP is required when retaining EXIF, XMP or ICC chunks.
                    $flags = 0; $icc = ''; $other = '';
                    for ($offset = 0; $offset + 8 <= strlen($metadata);) {
                        $chunk = substr($metadata, $offset, 4);
                        $flags |= ['ICCP' => 32, 'EXIF' => 8, 'XMP ' => 4][$chunk] ?? 0;
                        $length = unpack('V', substr($metadata, $offset + 4, 4))[1];
                        $raw = substr($metadata, $offset, 8 + $length + $length % 2);
                        if ($chunk === 'ICCP') $icc .= $raw;
                        else $other .= $raw;
                        $offset += 8 + $length + $length % 2;
                    }
                    $body = substr($bytes, 12);
                    if (substr($body, 0, 4) === 'VP8X') {
                        $body[8] = chr(ord($body[8]) | $flags);
                        $body = substr($body, 0, 18) . $icc . substr($body, 18);
                    } else {
                        $alpha = substr($body, 0, 4) === 'VP8L' && (ord($body[12]) & 16) ? 16 : 0;
                        $body = 'VP8X' . pack('V', 10) . chr($flags | $alpha) . "\0\0\0" . substr(pack('V', $width - 1), 0, 3) . substr(pack('V', $height - 1), 0, 3) . $icc . $body;
                    }
                    $body .= $other;
                    $bytes = 'RIFF' . pack('V', strlen($body) + 4) . 'WEBP' . $body;
                }
            }
            if (strlen($bytes) <= IMAGE_UPLOAD_MAX_BYTES) {
                if (file_put_contents($path, $bytes, LOCK_EX) !== strlen($bytes)) throw new RuntimeException('Could not save the optimized photo.');
                clearstatcache(true, $path);
                return;
            }
            $retryScale = max(.25, min(.85, sqrt(IMAGE_UPLOAD_MAX_BYTES * .9 / strlen($bytes))));
            $width = max(1, (int)round($width * $retryScale));
            $height = max(1, (int)round($height * $retryScale));
        }
        throw new InvalidArgumentException('This photo could not be optimized to 5 MB. Please use a smaller image.');
    } finally { unset($source); }
}

/** Optimize large dimensions as well as oversized files before validating saved uploads. */
function imageUploadPrepare(?array $file): ?array {
    if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return $file;
    $path = $file['tmp_name'] ?? '';
    if (!is_string($path) || !is_uploaded_file($path)) return $file;
    $mime = imageUploadMime($path);
    $file['size'] = filesize($path);
    $file['type'] = $mime;
    if (!str_starts_with($mime, 'image/')) return $file;
    $info = @getimagesize($path);
    if ($info) {
        $originalHash = hash_file('sha256', $path);
        imageUploadOptimizePath($path);
        $newHash = hash_file('sha256', $path);
        $GLOBALS['imageUploadOriginalHashes'][$newHash] = $GLOBALS['imageUploadOriginalHashes'][$originalHash] ?? $originalHash;
        $file['size'] = filesize($path);
    }
    return $file;
}
