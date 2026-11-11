<?php
namespace PHPMailer\PHPMailer {
    #[\AllowDynamicProperties]
    class PHPMailer {
        const ENCRYPTION_SMTPS='ssl'; const ENCRYPTION_STARTTLS='tls';
        public static array $messages=[];
        public function __construct($exceptions=true) {}
        public function isSMTP() { $this->smtp=true; }
        public function setFrom($address,$name) { $this->from=$address; }
        public function addAddress($address,$name) { $this->to=$address; }
        public function isHTML($enabled) { $this->html=$enabled; }
        public function send() { self::$messages[]=clone $this; return true; }
    }
}
namespace {
    if (PHP_SAPI!=='cli') { http_response_code(404); exit; }
    define('APP_URL','https://example.test');
    define('MAIL_TRANSPORT','smtp');
    define('MAIL_HOST','mail.example.test');
    define('MAIL_PORT', (int)($argv[1] ?? 465));
    define('MAIL_ENCRYPTION', MAIL_PORT===465?'ssl':'tls');
    define('MAIL_USERNAME','sender@example.test');
    define('MAIL_PASSWORD',' dummy mailbox password with spaces ');
    define('MAIL_FROM_ADDRESS','sender@example.test');
    define('MAIL_FROM_NAME','GNCI Mail');
    require_once __DIR__.'/../includes/auth-security.php';
    if (!authSendMail('recipient@example.test','Admin','OTP','Plain text',null,'<p>OTP</p>')) throw new \RuntimeException('SMTP transport failed.');
    $messages=\PHPMailer\PHPMailer\PHPMailer::$messages;
    if (count($messages)!==1) throw new \RuntimeException('Expected one delivery attempt.');
    $mail=$messages[0];
    if ($mail->Host!==MAIL_HOST || $mail->Port!==MAIL_PORT || $mail->SMTPSecure!==MAIL_ENCRYPTION || !$mail->SMTPAuth) throw new \RuntimeException('SMTP server/TLS configuration changed.');
    if ($mail->Password!==MAIL_PASSWORD || $mail->Username!==MAIL_USERNAME) throw new \RuntimeException('Mailbox credentials changed.');
    if ($mail->from!==MAIL_FROM_ADDRESS || $mail->Body!=='<p>OTP</p>' || $mail->AltBody!=='Plain text') throw new \RuntimeException('Sender or message alternatives changed.');
    if (isset($mail->SMTPOptions)) throw new \RuntimeException('Hosted SMTP must retain certificate verification.');
    echo "PASS: configured SMTP host, port, TLS, exact mailbox password, sender and HTML/plain text preserved. No emails sent.\n";
}
