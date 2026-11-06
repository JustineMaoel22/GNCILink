<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';require_once __DIR__.'/../admin/admin-functions.php';require_once __DIR__.'/../includes/content-delete-password.php';
$db=getDB();$checks=0;
function deletePasswordCheck(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
function deletePasswordRefused(callable $fn):void{try{$fn();throw new LogicException('Unverified deletion accepted.');}catch(RuntimeException|InvalidArgumentException $e){global $checks;$checks++;}}
$db->exec('CREATE TEMPORARY TABLE users(user_id INT PRIMARY KEY,password VARCHAR(255),role VARCHAR(30),status VARCHAR(20),credential_version INT,locked_until DATETIME NULL)');
$db->exec('CREATE TEMPORARY TABLE auth_rate_limits(bucket_hash CHAR(64) PRIMARY KEY,attempts INT,expires_at DATETIME)');
$db->exec('CREATE TEMPORARY TABLE auth_security_logs(user_id INT,action VARCHAR(100),ip_address VARCHAR(45),description VARCHAR(255))');
$db->exec('CREATE TEMPORARY TABLE csrf_tokens(token_id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,token VARCHAR(100),expires_at DATETIME)');
$password='delete-test-'.bin2hex(random_bytes(12));$q=$db->prepare("INSERT INTO users VALUES(?,?,?,'active',1,NULL)");
foreach([1=>'Super Admin',2=>'Content Editor',3=>'The Mirror'] as $id=>$role)$q->execute([$id,password_hash($password,PASSWORD_DEFAULT),$role]);
$_SESSION=['user_id'=>1,'user_role'=>'Super Admin','credential_version'=>1];
foreach(['news','announcement','event'] as $kind){
    deletePasswordRefused(fn()=>verifyContentDeletePassword($kind,''));
    deletePasswordRefused(fn()=>verifyContentDeletePassword($kind,'incorrect'));
    verifyContentDeletePassword($kind,$password);$checks++;
}
deletePasswordCheck((int)$db->query("SELECT COUNT(*) FROM auth_security_logs WHERE action='DELETE_CONTENT_FAILED'")->fetchColumn()===3,'Incorrect confirmation attempts are audited.');
$_SESSION['user_id']=2;$_SESSION['user_role']='Content Editor';foreach(['news','announcement','event'] as $kind){verifyContentDeletePassword($kind,$password);$checks++;}
$_SESSION['user_id']=3;$_SESSION['user_role']='The Mirror';deletePasswordRefused(fn()=>verifyContentDeletePassword('news',$password));
$_SESSION['user_id']=1;$_SESSION['user_role']='Super Admin';$_SESSION['credential_version']=2;deletePasswordRefused(fn()=>verifyContentDeletePassword('news',$password));$_SESSION['credential_version']=1;
$db->exec("UPDATE users SET locked_until=DATE_ADD(NOW(),INTERVAL 1 HOUR) WHERE user_id=1");deletePasswordRefused(fn()=>verifyContentDeletePassword('news',$password));$db->exec('UPDATE users SET locked_until=NULL WHERE user_id=1');
$db->exec('DELETE FROM auth_rate_limits');for($i=0;$i<5;$i++)deletePasswordRefused(fn()=>verifyContentDeletePassword('event','incorrect'));
deletePasswordRefused(fn()=>verifyContentDeletePassword('announcement',$password));$db->exec('DELETE FROM auth_rate_limits');
$_SERVER['REQUEST_METHOD']='POST';$csrf=generateCSRFToken();
$_POST=['action'=>'delete','news_id'=>'123','csrf_token'=>$csrf,'current_password'=>$password];contentDeletePasswordBoundary('/admin/action/create-news.php');
deletePasswordCheck(!isset($_POST['current_password']),'Password is removed before approval queuing.');
$eventCsrf=bin2hex(random_bytes(32));$db->prepare('INSERT INTO csrf_tokens(user_id,token,expires_at) VALUES(1,?,DATE_ADD(NOW(),INTERVAL 1 HOUR))')->execute([$eventCsrf]);
$_POST=['ajax_action'=>'delete','event_id'=>'123','csrf_token'=>$eventCsrf,'current_password'=>$password];contentDeletePasswordBoundary('/admin/events.php');
deletePasswordCheck(!isset($_POST['current_password']),'Events accept their existing database CSRF token and strip the password.');
deletePasswordCheck(!str_contains(json_encode($_SESSION),$password),'Confirmation password is not stored in session.');
echo "$checks deletion-password checks passed. No persistent records deleted.\n";
