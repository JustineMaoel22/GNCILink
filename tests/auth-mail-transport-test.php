<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Disable the built-in transport before defining our recording stub: never send mail.
if (!in_array('mail', array_map('trim', explode(',', ini_get('disable_functions'))), true)) {
    throw new RuntimeException('Run with php -d disable_functions=mail tests/auth-mail-transport-test.php');
}
$sent = []; $accepted = true;
$auditRows = [];
function getDB() {
    return new class {
        public function prepare($sql) { return new class {
            public function execute($values) { $GLOBALS['auditRows'][]=$values; return true; }
        }; }
    };
}
function mail($to, $subject, $body, $headers = '', $params = ''): bool {
    global $sent, $accepted;
    $sent[] = compact('to','subject','body','headers');
    return $accepted;
}
define('MAIL_TRANSPORT', 'mail');
define('MAIL_HOST', ''); define('MAIL_PORT', 587);
define('MAIL_USERNAME', ''); define('MAIL_PASSWORD', '');
define('MAIL_ENCRYPTION', 'tls');
define('MAIL_FROM_ADDRESS', 'no-reply@example.test');
define('MAIL_FROM_NAME', 'GNCI Mail');
require_once __DIR__ . '/../includes/auth-security.php';
function transportCheck(bool $ok, string $message): void {
    if (!$ok) throw new RuntimeException($message);
}
transportCheck(authSendMail('admin@example.test','Admin','OTP','Code 012345',null,'<p>012345</p>'), 'Local mail failed without SMTP credentials.');
transportCheck(count($sent)===1, 'Expected one local transport call.');
transportCheck(str_contains($sent[0]['headers'], 'multipart/alternative'), 'HTML/plain alternatives missing.');
transportCheck(str_contains($sent[0]['headers'], '<no-reply@example.test>'), 'Configured sender missing.');
transportCheck(authSendMail('admin@example.test','Admin','Plain','Plain text'), 'Plain mail failed.');
transportCheck(base64_decode(trim($sent[1]['body']))==='Plain text', 'Plain message encoding failed.');
foreach ([
    ['bad-address','Subject','no-reply@example.test','GNCI'],
    ['admin@example.test',"Subject\r\nBcc: attacker@example.test",'no-reply@example.test','GNCI'],
    ['admin@example.test','Subject',"no-reply@example.test\r\nBcc: attacker@example.test",'GNCI'],
    ['admin@example.test','Subject','no-reply@example.test',"GNCI\r\nBcc: attacker@example.test"],
] as [$to,$subject,$from,$name]) {
    try { authLocalMailMessage($to,$subject,'Text',$from,$name); throw new RuntimeException('Unsafe header accepted.'); }
    catch (InvalidArgumentException $expected) {}
}
transportCheck(!class_exists(\PHPMailer\PHPMailer\PHPMailer::class,false), 'Native mail must not load PHPMailer.');
$before=count($sent);
transportCheck(!authSendMail("admin@example.test\r\nBcc: attacker@example.test",'Admin','Subject','Text'), 'Unsafe recipient accepted.');
transportCheck(count($sent)===$before, 'Invalid recipient reached transport.');
$accepted=false;
transportCheck(!authSendMail('admin@example.test','Admin','Subject','Text'), 'Rejected local delivery reported success.');
transportCheck(count($auditRows)===2 && $auditRows[1][1]==='AUTH_MAIL_FAILED', 'Delivery failures must be audited.');
echo "PASS: cPanel/native mail uses no SMTP credentials or PHPMailer; multipart/plain messages and header validation pass. No emails sent.\n";
