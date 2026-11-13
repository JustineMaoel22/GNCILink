<?php
// Invalid content must be rejected before any media file or database write.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../admin/admin-functions.php';
$path = tempnam(sys_get_temp_dir(), 'gnci-reject-');
$checks = 0;
try {
    foreach (['%PDF-1.7 invalid picture', '<svg xmlns="http://www.w3.org/2000/svg"></svg>', 'plain text'] as $bytes) {
        file_put_contents($path, $bytes);
        foreach (['jpg', 'png', 'gif', 'webp'] as $extension) {
            $file = ['name'=>'renamed.'.$extension, 'type'=>'image/'.$extension, 'tmp_name'=>$path, 'error'=>UPLOAD_ERR_OK, 'size'=>strlen($bytes)];
            foreach (['uploadAnnouncementImage', 'uploadNewsImage'] as $upload) {
                try {
                    $upload($file);
                    throw new LogicException('Non-image content was accepted.');
                } catch (Exception $error) {
                    if (!in_array($error->getMessage(), [
                        'Invalid image type. Allowed: JPG, PNG, GIF, WEBP.',
                        'Choose a valid JPG, PNG, WebP, GIF, PDF, MP4 or WebM file.',
                    ], true)) throw $error;
                    $checks++;
                }
            }
        }
    }
} finally { unlink($path); }
echo "PASS: $checks server-side rejections of non-image content with allowed filenames. No content changed.\n";
