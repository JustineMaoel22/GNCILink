<?php
// CLI-only regression checks; no database, SMTP, or real account is touched.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function otpCheck(bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}

$root = dirname(__DIR__);
$config = file_get_contents($root . '/config/config.php');
otpCheck((bool) preg_match("/define\('OTP_EXPIRY', (.*?)\);/s", $config, $match), 'Find OTP lifetime configuration');
$expression = $match[1];
foreach ([null, '', 'invalid', '0', '-1', '1.5', '300', '600'] as $value) {
    unset($_ENV['OTP_EXPIRY']);
    if ($value !== null) { $_ENV['OTP_EXPIRY'] = $value; }
    $actual = eval('return ' . $expression . ';');
    $expected = in_array($value, ['300', '600'], true) ? (int) $value : 300;
    otpCheck($actual === $expected, 'Missing/invalid lifetime must not immediately expire the code');
}

$handler = file_get_contents($root . '/admin/auth/auth_handler.php');
$page = file_get_contents($root . '/admin/auth/verify-otp.php');
// Guard every issuance path: PHP's local date must never be compared with MySQL NOW().
otpCheck(substr_count($handler, 'otp_expires_at = TIMESTAMPADD(SECOND, ?, NOW())') === 2,
    'Login and API resend must issue codes using the database clock');
otpCheck(substr_count($page, 'otp_expires_at = TIMESTAMPADD(SECOND, ?, NOW())') === 1,
    'Page resend must use the same database clock');
otpCheck(!str_contains($handler, "strtotime(\$user['otp_expires_at'])"), 'API verification must not interpret database dates in PHP timezone');
otpCheck(str_contains($handler, '(otp_expires_at > NOW()) AS otp_is_current') &&
    str_contains($handler, "empty(\$user['otp_is_current'])"), 'API verification must enforce database expiry');
otpCheck(str_contains($page, 'otp_code = ? AND otp_expires_at > NOW()'), 'Page verification must enforce database expiry');
otpCheck(str_contains($page, 'TIMESTAMPDIFF(SECOND, NOW(), otp_expires_at)') &&
    !str_contains($page, 'let seconds = 300'), 'Countdown must reflect remaining database lifetime');
echo "OTP expiry regression checks passed.\n";
