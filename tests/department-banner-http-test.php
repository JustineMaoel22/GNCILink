<?php
// Local integration check: creates and deletes its own department and uploads.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin/admin-functions.php';
function bannerCheck(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
function bannerRequest(array $fields, string $cookie): array {
    $curl = curl_init('http://localhost/admin/action/page-content-handler.php');
    curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIE => $cookie, CURLOPT_TIMEOUT => 30, CURLOPT_POSTFIELDS => $fields]);
    $body = curl_exec($curl);
    if ($body === false) throw new RuntimeException(curl_error($curl));
    $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    clearstatcache();
    return [$status, json_decode($body, true)];
}
$user = getDB()->query("SELECT * FROM users WHERE status = 'active' AND role = 'Super Admin' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
bannerCheck((bool)$user, 'An active local admin is required');
$session = 'gnc-banner-test-' . bin2hex(random_bytes(12));
$token = bin2hex(random_bytes(32));
session_id($session); session_start();
$_SESSION = ['user_id' => (int)$user['user_id'], 'user_role' => 'Super Admin', 'credential_version' => (int)($user['credential_version'] ?? 0), '_initiated' => true, 'last_activity' => time(), 'csrf_token' => $token];
$cookie = session_name() . '=' . $session;
session_write_close();
$file = tempnam(sys_get_temp_dir(), 'gnc-banner-');
$id = null;
try {
    $dept = saveDepartmentDetails(['dept_id' => 0, 'dept_name' => 'College of Banner check ' . bin2hex(random_bytes(6)), 'theme_color' => '#1465A0']);
    $id = (int)$dept['dept_id'];
    $base = ['action' => 'save_department_banner', 'dept_id' => $id, 'csrf_token' => $token];
    [$status] = bannerRequest($base, '');
    bannerCheck(in_array($status, [302, 403], true) && departmentBanner($dept) === null, 'Anonymous writes refused');
    [$status, $result] = bannerRequest(array_replace($base, ['csrf_token' => 'invalid']), $cookie);
    bannerCheck($status === 403, 'Invalid CSRF refused: HTTP ' . $status . ' ' . json_encode($result));
    file_put_contents($file, 'This is not an image');
    [$status] = bannerRequest($base + ['banner' => new CURLFile($file, 'image/png', 'fake.png')], $cookie);
    bannerCheck($status === 422 && departmentBanner($dept) === null, 'Fake image rejected without saving');
    file_put_contents($file, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a3ioAAAAASUVORK5CYII='));
    $previous = null;
    for ($i = 0; $i < 2; $i++) {
        [$status, $result] = bannerRequest($base + ['banner' => new CURLFile($file, 'image/png', 'header.png')], $cookie);
        bannerCheck($status === 200 && !empty($result['success']), 'Banner upload/replace succeeds: ' . json_encode($result));
        $media = departmentBanner($dept);
        bannerCheck($media && $result['department']['banner_url'] === $media['file_path'], 'Public and editor read the same persisted image');
        bannerCheck(!deleteMediaIfUnreferenced((int)$media['media_id']), 'Active banner is protected from media cleanup');
        if ($previous) bannerCheck(!departmentFileExists($previous), 'Replaced upload is cleaned up');
        $previous = $media['file_path'];
    }
    $html = file_get_contents('http://localhost/pages/academics/college/college-department.php?slug=' . rawurlencode($dept['slug']));
    bannerCheck(str_contains($html, 'data-dept-banner-image') && str_contains($html, htmlspecialchars($previous, ENT_QUOTES, 'UTF-8')), 'Public page renders the uploaded background');
    bannerCheck(!str_contains($html, 'class="dept-hero" hidden') && substr_count($html, '<h1 ') === 1, 'Single visible heading stays above the background');
    bannerCheck(str_contains($html, 'dept-hero-copy') && str_contains(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html))), $dept['dept_name']), 'Department title remains live text');
    bannerCheck(str_contains($html, '<span class="dept-hero-prefix">College of </span>'), 'College of label is split from the stored full department name');
    bannerCheck(str_contains($html, '--gnc-template:#1465A0;'), 'Saved department color drives the landing header');
    preg_match('/<section class="dept-intro.*?<\/section>/s', $html, $header);
    bannerCheck(!str_contains(str_replace($header[0], '', $html), '--gnc-template:#1465A0;'), 'Header theme variables do not leak to other sections');
    [$status, $result] = bannerRequest($base + ['remove_banner' => '1'], $cookie);
    bannerCheck($status === 200 && $result['success'] && departmentBanner($dept) === null, 'Remove clears the saved banner');
    bannerCheck(!departmentFileExists($previous), 'Removed upload is cleaned up');
    $html = file_get_contents('http://localhost/pages/academics/college/college-department.php?slug=' . rawurlencode($dept['slug']));
    bannerCheck(str_contains($html, 'data-dept-banner-image') && !str_contains($html, htmlspecialchars($previous, ENT_QUOTES, 'UTF-8')) && !str_contains($html, 'class="dept-hero" hidden'), 'Removing the background preserves the live header');
} finally {
    if ($id) {
        saveDepartmentBanner($id, null, true);
        deleteDepartment($id);
    }
    @unlink($file);
    session_id($session); session_start(); $_SESSION = []; session_destroy();
}
echo "PASS: authorized upload, replacement, removal, public rendering, media cleanup, fake image rejection and CSRF/authentication checks.\n";
