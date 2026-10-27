<?php
// Run with: docker compose exec -T --user www-data web php tests/image-upload-http-test.php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
$path = tempnam(sys_get_temp_dir(), 'gnc-upload-http-');
$sessionId = 'gnc-upload-test-' . bin2hex(random_bytes(12));
$token = bin2hex(random_bytes(32));
session_id($sessionId);
session_start();
// This isolated session exercises upload validation; no account or content is changed.
$admin = getDB()->query("SELECT user_id,credential_version FROM users WHERE role='Super Admin' AND status='active' LIMIT 1")->fetch();
if (!$admin) throw new RuntimeException('An active local Super Admin is required.');
$_SESSION = ['user_id' => (int)$admin['user_id'], 'credential_version'=>(int)$admin['credential_version'], 'user_role' => 'Super Admin', '_initiated' => true, 'last_activity'=>time(), 'csrf_token' => $token];
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
    uploadHttpCheck(strlen($bytes) <= IMAGE_UPLOAD_MAX_BYTES && getimagesizefromstring($bytes) !== false, 'Optimized result is a valid image under 5 MB');
    $small = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1cAAAAASUVORK5CYII=');
    file_put_contents($path,$small);
    [$status,$type,$bytes] = uploadHttpRequest($path,$cookie,$token);
    uploadHttpCheck($status===200 && $type==='image/png' && $bytes===$small, 'Valid image retains original bytes');
    file_put_contents($path, 'not an image');
    [$status] = uploadHttpRequest($path, $cookie, $token);
    uploadHttpCheck($status === 422, 'Renamed non-image refused');
} finally {
    if (dirname($path) === sys_get_temp_dir() && str_starts_with(basename($path), 'gnc-upload-http-')) @unlink($path);
    session_id($sessionId); session_start(); $_SESSION = []; session_destroy();
}
echo "PASS: oversized original optimized to 5 MB; small image bytes preserved; anonymous requests, invalid CSRF tokens and renamed non-images rejected.\n";
