<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../includes/auth-email-templates.php';
$checks = 0;
function emailCheck(bool $condition, string $message): void {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
$name = '<img src=x onerror=alert(1)> & "Admin"';
$otp = authBuildOtpEmailHtml($name, '012345', 5);
$url = 'https://example.com/admin/reset-password.php?token='.str_repeat('a', 64).'&source=email';
$reset = authBuildResetEmailHtml($name, $url);
$changed = authBuildPasswordChangedEmailHtml($name);
foreach ([$otp, $reset, $changed] as $html) {
    emailCheck(strpos($html, '<img src=x') === false && strpos($html, '&lt;img') !== false, 'Recipient names must be escaped.');
    emailCheck(strpos($html, 'role="presentation"') !== false && strpos($html, '<script') === false, 'Email uses presentation tables without scripts.');
    emailCheck(strpos($html, 'GNCILink') !== false, 'Shared GNCI branding is present.');
}
emailCheck(strpos($otp, '>012345</p>') !== false, 'Code retains leading zero and contiguous digits.');
emailCheck(strpos($otp, '5 minutes') !== false, 'OTP shows the configured expiration.');
emailCheck(substr_count($reset, 'href="'.authEmailEscape($url).'"') === 2, 'Button and fallback link preserve the reset URL.');
emailCheck(strpos($reset, '15 minutes') !== false && strpos($reset, 'only be used once') !== false, 'Reset expiration and single-use guidance remain.');
emailCheck(strpos($changed, 'contact your Super Admin immediately') !== false, 'Security notification includes recovery advice.');
emailCheck(strpos(authBuildPasswordChangedEmailHtml(''), 'Hello Administrator,') !== false, 'Missing name has a fallback.');
foreach (['javascript:alert(1)', 'data:text/html,test', '/relative-reset'] as $invalid) {
    try { authBuildResetEmailHtml('Admin', $invalid); throw new RuntimeException('Unsafe reset link accepted.'); }
    catch (InvalidArgumentException $expected) { $checks++; }
}
$plain = "Sample plain text\n012345";
$mime = authEmailAlternativeBody($plain, $otp, 'test_boundary');
preg_match_all('/Content-Transfer-Encoding: base64\r\n\r\n(.*?)\r\n--test_boundary/s', $mime, $parts);
emailCheck(count($parts[1]) === 2, 'Local transport contains both alternatives.');
emailCheck(base64_decode($parts[1][0]) === $plain && base64_decode($parts[1][1]) === $otp, 'Both MIME alternatives round-trip intact.');
emailCheck(str_ends_with($mime, "--test_boundary--\r\n"), 'Multipart message closes its boundary.');
if (in_array('--preview', $argv, true)) {
    $previews = [
        'otp' => authBuildOtpEmailHtml('Alex', '012345', 5),
        'reset' => authBuildResetEmailHtml('Alex', 'https://example.com/admin/reset-password.php?token='.str_repeat('a', 64)),
        'changed' => authBuildPasswordChangedEmailHtml('Alex'),
    ];
    foreach ($previews as $kind => $html) file_put_contents(__DIR__.'/../cache/auth-email-'.$kind.'.html', $html);
}
echo "$checks email template checks passed. No emails sent.\n";
