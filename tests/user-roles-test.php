<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';require_once __DIR__.'/../admin/admin-functions.php';require_once __DIR__.'/../includes/user-roles.php';
$db=getDB();$checks=0;
function roleCheck(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
function roleRefused(callable $fn):void{try{$fn();throw new LogicException('Role change unexpectedly accepted.');}catch(RuntimeException|InvalidArgumentException $e){global $checks;$checks++;}}
// Temporary tables shadow the real accounts and audits for this connection only.
$db->exec('CREATE TEMPORARY TABLE users(user_id INT PRIMARY KEY,password VARCHAR(255),role VARCHAR(30),status VARCHAR(20),credential_version INT,locked_until DATETIME NULL,remember_token VARCHAR(255),remember_token_expires_at DATETIME NULL)');
$db->exec('CREATE TEMPORARY TABLE auth_rate_limits(bucket_hash CHAR(64) PRIMARY KEY,attempts INT,expires_at DATETIME)');
$db->exec('CREATE TEMPORARY TABLE auth_security_logs(user_id INT,action VARCHAR(100),ip_address VARCHAR(45),description VARCHAR(255))');
$db->exec('CREATE TEMPORARY TABLE activity_logs(user_id INT,action VARCHAR(100),table_name VARCHAR(100),record_id INT,description TEXT,created_at DATETIME)');
$actorPassword='role-test-'.bin2hex(random_bytes(12));$targetPassword='target-'.bin2hex(random_bytes(12));
$q=$db->prepare("INSERT INTO users VALUES(?, ?, ?, 'active',1,NULL,'remember',DATE_ADD(NOW(),INTERVAL 1 DAY))");
$q->execute([1,password_hash($actorPassword,PASSWORD_DEFAULT),'Super Admin']);$q->execute([2,password_hash($targetPassword,PASSWORD_DEFAULT),'Content Editor']);
$_SESSION=['user_id'=>1,'user_role'=>'Super Admin','credential_version'=>1];
roleRefused(fn()=>changeAdminUserRole(2,'The Mirror',''));
roleRefused(fn()=>changeAdminUserRole(2,'The Mirror','incorrect'));
roleRefused(fn()=>changeAdminUserRole(2,'The Mirror',$targetPassword));
roleCheck($db->query('SELECT role FROM users WHERE user_id=2')->fetchColumn()==='Content Editor','Missing, incorrect and target-user passwords leave the role unchanged.');
roleCheck((int)$db->query("SELECT COUNT(*) FROM auth_security_logs WHERE action='ASSIGN_ROLE_FAILED'")->fetchColumn()===2,'Failed passwords are audited.');
roleCheck(changeAdminUserRole(2,'The Mirror',$actorPassword),'The acting administrator password authorizes the change.');
roleCheck($db->query('SELECT role FROM users WHERE user_id=2')->fetchColumn()==='The Mirror','Selected role saved.');
roleCheck($db->query('SELECT remember_token FROM users WHERE user_id=2')->fetchColumn()===null,'Remembered login cleared.');
roleCheck((int)$db->query("SELECT COUNT(*) FROM activity_logs WHERE action='ASSIGN_ROLE'")->fetchColumn()===1,'Successful role change audited.');
roleCheck(!changeAdminUserRole(2,'The Mirror',$actorPassword),'No-op does not write another role change.');
roleRefused(fn()=>changeAdminUserRole(1,'Content Editor',$actorPassword));
roleRefused(fn()=>changeAdminUserRole(2,'Invalid',$actorPassword));
$_SESSION['user_role']='Content Editor';roleRefused(fn()=>changeAdminUserRole(2,'Super Admin',$actorPassword));$_SESSION['user_role']='Super Admin';
$_SESSION['credential_version']=2;roleRefused(fn()=>changeAdminUserRole(2,'Super Admin',$actorPassword));$_SESSION['credential_version']=1;
$db->exec("UPDATE users SET locked_until=DATE_ADD(NOW(),INTERVAL 1 HOUR) WHERE user_id=1");roleRefused(fn()=>changeAdminUserRole(2,'Super Admin',$actorPassword));$db->exec('UPDATE users SET locked_until=NULL WHERE user_id=1');
$db->exec('DELETE FROM auth_rate_limits');for($i=0;$i<5;$i++)roleRefused(fn()=>changeAdminUserRole(2,'Super Admin','incorrect'));
roleRefused(fn()=>changeAdminUserRole(2,'Super Admin',$actorPassword));
roleCheck($db->query('SELECT role FROM users WHERE user_id=2')->fetchColumn()==='The Mirror','Rate-limited requests cannot change roles.');
roleCheck(!str_contains(json_encode($_SESSION),$actorPassword),'Password not retained in the session.');
echo "$checks role-change security checks passed. No persistent accounts changed.\n";
