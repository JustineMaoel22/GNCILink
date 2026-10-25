<?php
// Run with: docker compose exec -T --user www-data web php tests/image-upload-http-test.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
$path = tempnam(sys_get_temp_dir(), 'gnc-upload-http-');
$sessionId = 'gnc-upload-test-' . bin2hex(random_bytes(12));
$token = bin2hex(random_bytes(32));
session_id($sessionId);
session_start();
// This isolated session exercises only the optimizer; no real account or content is changed.
$_SESSION = ['user_id' => 0, 'user_role' => 'Super Admin', '_initiated' => true, 'csrf_token' => $token];
$cookie = session_name() . '=' . $sessionId;
session_write_close();
function uploadHttpCheck(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function uploadHttpRequest(string $path, string $cookie, string $token): array {
    $curl = curl_init('http://localhost/admin/action/optimize-image.php');
    curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIE => $cookie, CURLOPT_TIMEOUT => 60,
        CURLOPT_POSTFIELDS => ['csrf_token' => $token, 'image' => new CURLFile($path, 'image/jpeg', 'large.jpg')]]);
    $body = curl_exec($curl);
    if ($body === false) throw new RuntimeException(curl_error($curl));
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $type = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
    curl_close($curl);
    return [$status, $type, $body];
}
try {
    $image = new Imagick();
    $image->readImageBlob("P6\n2600 2100\n255\n" . random_bytes(2600 * 2100 * 3));
    $image->setImageFormat('jpeg'); $image->setImageCompressionQuality(100);
    $image->writeImage($path); $image->clear();
    uploadHttpCheck(filesize($path) > IMAGE_UPLOAD_MAX_BYTES, 'Original exceeds 5 MB');
    [$status] = uploadHttpRequest($path, '', $token);
    uploadHttpCheck($status === 403, 'Anonymous upload refused');
    [$status, , $errorBody] = uploadHttpRequest($path, $cookie, 'invalid-token');
    uploadHttpCheck($status === 403, 'Invalid CSRF token refused: HTTP ' . $status . ' ' . $errorBody);
    [$status, $type, $bytes] = uploadHttpRequest($path, $cookie, $token);
    uploadHttpCheck($status === 200 && $type === 'image/jpeg', 'Authenticated multipart photo optimized');
    uploadHttpCheck(strlen($bytes) <= IMAGE_UPLOAD_MAX_BYTES && getimagesizefromstring($bytes) !== false, 'HTTP result is a valid image under 5 MB');
    file_put_contents($path, 'not an image');
    [$status] = uploadHttpRequest($path, $cookie, $token);
    uploadHttpCheck($status === 422, 'Renamed non-image refused');
} finally {
    if (dirname($path) === sys_get_temp_dir() && str_starts_with(basename($path), 'gnc-upload-http-')) @unlink($path);
    session_id($sessionId); session_start(); $_SESSION = []; session_destroy();
}
echo "PASS: real multipart photo optimized below 5 MB; anonymous requests, invalid CSRF tokens and renamed non-images rejected.\n";
