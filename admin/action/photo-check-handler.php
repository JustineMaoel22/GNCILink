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
header('Cache-Control: no-store');

function photoCheckFail(string $message, int $status): void {
    http_response_code($status);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

// Diagnostic: authenticated POST with test=1 and a valid CSRF token.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test'])) {
    if (!isLoggedIn() || !hasPermission('manage_page_content')) photoCheckFail('Log in as an administrator first.', 403);
    if (!verifyCSRFToken((string)($_POST['csrf_token'] ?? ''))) photoCheckFail('Refresh the page and try again.', 403);
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
try { $file = imageUploadPrepare($file); } catch (Throwable $error) { photoCheckFail($error->getMessage(), 422); }
if (!$file || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
    photoCheckFail('The photo could not be read. Please try again.', 422);
}
if ((int)$file['size'] > 5 * 1024 * 1024) {
    photoCheckFail('The photo is too large. Maximum size is 5MB.', 422);
}

$name = $_POST['person_name'] ?? '';
$key = $_POST['profile_key'] ?? '';
$section = $_POST['section_key'] ?? '';
if (!is_string($section) || strlen($section) > 255) photoCheckFail('Invalid page.', 422);
if (!is_string($name) || mb_strlen($name) > 255 || !is_string($key)
    || ($key !== '' && !preg_match('/\A(person|office|dean|president):[0-9]+\z/', $key))) photoCheckFail('Invalid profile.', 422);
$info = @getimagesize($file['tmp_name']);
if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
    || $info[0]*$info[1] > 40000000) photoCheckFail('Please choose a JPG, PNG or WebP portrait up to 40 megapixels.', 422);
// Both optional photo checks honor the shared setting after file validation.
$r = personPhotoCheck($file['tmp_name'], $name, $key, null, $section);
if (($_POST['review_duplicate'] ?? '') === '1') {
    if (!rbacIsSuper()) photoCheckFail('Only Super Admin can review a possible duplicate.', 403);
    if (($r['reason'] ?? '') !== 'disabled') {
        if (($r['reason'] ?? '') !== 'duplicate_face' || empty($r['review_key'])) photoCheckFail('This comparison cannot be reviewed. Check the photo again.', 409);
        if (!logActivity((int)$_SESSION['user_id'], 'UPDATE', null, null,
            'Personnel photo review for ' . $name . ': confirmed reviewed assignment [comparison ' . $r['review_key'] . ']')) photoCheckFail('The review could not be recorded. Please try again.', 503);
        $_SESSION['person_photo_reviews'] = array_filter($_SESSION['person_photo_reviews'] ?? [], static fn($expiry) => $expiry > time());
        $_SESSION['person_photo_reviews'][$r['review_key']] = time() + 900;
        $_SESSION['person_photo_reviews'] = array_slice($_SESSION['person_photo_reviews'], -20, null, true);
        $r['ok'] = true;
        $r['reviewed'] = true;
    }
}
if ($r['ok']) {
    $basic = photoCheckImage($file['tmp_name']);
    if (!$basic['ok']) $r = $basic;
}
unset($r['review_key'], $r['detail']);
echo json_encode(['success'=>true] + $r);
