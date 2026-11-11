<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../includes/auth-security.php';
$db=getDB();$checks=0;
function loginLockCheck(bool $ok,string $message): void {
    global $checks;
    if(!$ok)throw new RuntimeException($message);
    $checks++;
}
// Connection-local tables shadow real tables; no persistent account is changed.
$db->exec('CREATE TEMPORARY TABLE users(user_id INT PRIMARY KEY,email VARCHAR(254),password VARCHAR(255),status VARCHAR(20),login_attempts INT,locked_until DATETIME NULL) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE auth_rate_limits(bucket_hash CHAR(64) PRIMARY KEY,attempts INT,expires_at DATETIME) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE auth_security_logs(user_id INT,action VARCHAR(100),ip_address VARCHAR(45),description VARCHAR(255))');
$email='lockout@example.test';$password=bin2hex(random_bytes(16));
$db->prepare("INSERT INTO users VALUES(1,?,?,'active',0,NULL)")->execute([$email,password_hash($password,PASSWORD_DEFAULT)]);
for($i=1;$i<=9;$i++){
    loginLockCheck(authCheckCredentials($email,'wrong',$retry)===null&&$retry===0,"Failure $i must not lock the account.");
}
loginLockCheck(authCheckCredentials($email,'wrong',$retry)===null&&$retry===900,'The tenth failure starts a 15-minute lock.');
$lockedUntil=$db->query('SELECT locked_until FROM users')->fetchColumn();
loginLockCheck(authCheckCredentials(strtoupper($email),$password,$retry)===null&&$retry>0,'Correct passwords and email case changes cannot bypass the lock.');
loginLockCheck($db->query('SELECT locked_until FROM users')->fetchColumn()===$lockedUntil,'Blocked attempts must not extend the lock.');
$db->exec('UPDATE users SET locked_until=DATE_SUB(NOW(),INTERVAL 1 SECOND)');
loginLockCheck(authCheckCredentials($email,'wrong',$retry)===null&&$retry===0,'An expired lock allows another attempt.');
loginLockCheck((int)$db->query('SELECT login_attempts FROM users')->fetchColumn()===1,'The first failure after expiry restarts the count.');
loginLockCheck(authCheckCredentials($email,$password,$retry)!==null&&$retry===0,'Correct credentials succeed after expiry.');
loginLockCheck((int)$db->query('SELECT login_attempts FROM users')->fetchColumn()===0,'Successful verification clears failures.');
loginLockCheck(authCheckCredentials('missing@example.test','wrong',$retry)===null&&$retry===0,'Unknown accounts retain the generic failure.');
loginLockCheck((int)$db->query("SELECT COUNT(*) FROM auth_security_logs WHERE action='LOGIN_LOCKOUT'")->fetchColumn()===1,'The lock is audited once.');
echo "$checks login lockout checks passed. No persistent records changed.\n";
