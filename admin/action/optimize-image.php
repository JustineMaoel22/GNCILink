<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../admin-functions.php';
initSession();
header('Cache-Control: no-store');
try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isLoggedIn()) {
        http_response_code(403);
        throw new RuntimeException('Log in before uploading photos.');
    }
    if (empty($_POST) && empty($_FILES) && (int)($_SERVER['CONTENT_LENGTH'] ?? 0)>0) {
        http_response_code(413);
        throw new InvalidArgumentException('The original photo exceeds the hosting request limit (post_max_size: '.ini_get('post_max_size').'). The maximum saved image size is 5 MB. Choose a smaller original or ask your hosting provider to increase the request limit.');
    }
    if (!verifyCSRFToken((string)($_POST['csrf_token'] ?? ''))) {
        http_response_code(403);
        throw new RuntimeException('Your session has expired. Refresh the page and try again.');
    }
    // Image processing does not mutate session data; let other editor requests proceed.
    session_write_close();
    $file = $_FILES['image'] ?? null;
    if ($file && in_array($file['error'], [UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE], true)) throw new InvalidArgumentException('The original photo exceeds the hosting upload limit (upload_max_filesize: '.ini_get('upload_max_filesize').'). The maximum saved image size is 5 MB. Choose a smaller original or ask your hosting provider to increase the upload limit.');
    if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) throw new InvalidArgumentException('The photo could not be uploaded for optimization. The maximum saved image size is 5 MB. Please choose a smaller image.');
    $file = imageUploadPrepare($file);
    if (!in_array($file['type'], ['image/jpeg', 'image/png', 'image/webp', 'image/gif'], true) || $file['size'] > IMAGE_UPLOAD_MAX_BYTES || !@getimagesize($file['tmp_name'])) throw new InvalidArgumentException('Choose a JPG, PNG, WebP or GIF photo. Saved images must be up to 5 MB.');
    header('Content-Type: ' . $file['type']);
    header('X-Content-Type-Options: nosniff');
    readfile($file['tmp_name']);
} catch (Throwable $error) {
    if (http_response_code() < 400) http_response_code(422);
    header('Content-Type: application/json');
    echo json_encode(['error' => $error instanceof ImagickException ? 'This photo could not be optimized. Please try a smaller image.' : $error->getMessage()]);
}
