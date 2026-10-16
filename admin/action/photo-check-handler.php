<?php
/**
 * AJAX endpoint: checks one photo BEFORE the admin saves it, so the editor can
 * show a popup right away ("not a person" / "AI-generated").
 *
 * POST (multipart): csrf_token, photo (file)
 * Answers JSON: {success, checked, ok, reason, message}
 */
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../admin-functions.php';

initSession();
header('Content-Type: application/json; charset=utf-8');

function photoCheckFail(string $message, int $status): void {
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

// Self-test: open  /admin/action/photo-check-handler.php?test=1  in the browser while logged in.
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['test'])) {
    if (!isLoggedIn() || !hasPermission('manage_page_content')) photoCheckFail('Log in as an administrator first.', 403);
    unset($_SESSION['photo_check_down_until'], $_SESSION['photo_check_down_why']);
    $report = [
        'curl_loaded'   => function_exists('curl_init'),
        'gd_loaded'     => function_exists('imagecreatetruecolor'),
        'api_key_set'   => photoCheckConfigured(),
        'model'         => PHOTO_CHECK_MODEL,
        'block_ai'      => PHOTO_CHECK_BLOCK_AI,
        'expected'      => 'result.checked = true and result.reason = "not_person" (the test image is a plain blue square)',
    ];
    if ($report['gd_loaded']) {
        $im = imagecreatetruecolor(300, 300);
        imagefill($im, 0, 0, imagecolorallocate($im, 30, 80, 200));
        $tmp = tempnam(sys_get_temp_dir(), 'pc');
        imagejpeg($im, $tmp);
        imagedestroy($im);
        $report['result'] = photoCheckImage($tmp);
        @unlink($tmp);
    }
    unset($_SESSION['photo_check']);   // don't keep the test verdict
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') photoCheckFail('Invalid request.', 405);
if (!isLoggedIn()) photoCheckFail('Your session has expired. Please log in again.', 401);
if (!hasPermission('manage_page_content')) photoCheckFail('You do not have permission to do this.', 403);
if (!verifyCSRFToken((string)($_POST['csrf_token'] ?? ''))) photoCheckFail('Your session has expired. Please refresh the page.', 403);

$file = $_FILES['photo'] ?? null;
if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
    photoCheckFail('The photo could not be read. Please try again.', 422);
}
if ((int)$file['size'] > 5 * 1024 * 1024) {
    photoCheckFail('The photo is too large. Maximum size is 5MB.', 422);
}

$r = photoCheckImage($file['tmp_name']);
echo json_encode([
    'success' => true,
    'checked' => $r['checked'],
    'ok'      => $r['ok'],
    'reason'  => $r['reason'],
    'message' => $r['message'],
    'detail'  => $r['detail'] ?? null,   // why the check was skipped (shown in the browser console)
]);
