<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
$db=getDB();$action=$argv[1]??'';$name=$argv[2]??'';
if($action==='create')$name='gnci_rbac_test_'.bin2hex(random_bytes(6));
if(!preg_match('/^gnci_rbac_test_[a-f0-9]{12}$/D',$name))throw new RuntimeException('Invalid isolated test database.');
if($action==='create'){
 $db->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4");$db->exec('SET FOREIGN_KEY_CHECKS=0');
 foreach($db->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM) as $table){if($table[1]!=='BASE TABLE')continue;$key=$table[0];if(!preg_match('/^[a-zA-Z0-9_]+$/D',$key))continue;$ddl=$db->query("SHOW CREATE TABLE `$key`")->fetch(PDO::FETCH_NUM)[1];$ddl=preg_replace('/^CREATE TABLE `'.preg_quote($key,'/').'`/','CREATE TABLE `'.$name.'`.`'.$key.'`',$ddl);$db->exec($ddl);}
 $db->exec('SET FOREIGN_KEY_CHECKS=1');$db->exec("USE `$name`");
 $sql=preg_replace('/^--.*$/m','',file_get_contents(__DIR__.'/../sql/rbac-migration.sql'));foreach(explode(';',$sql) as $part)if(trim($part)!=='')$db->exec($part);
 foreach(['must_change_password'=>'TINYINT(1) NOT NULL DEFAULT 0','password_changed_at'=>'DATETIME NULL','credential_version'=>'INT UNSIGNED NOT NULL DEFAULT 1'] as $column=>$definition){if(!$db->query("SHOW COLUMNS FROM users LIKE '$column'")->fetch())$db->exec("ALTER TABLE users ADD COLUMN `$column` $definition");}
 $sql=preg_replace('/^--.*$/m','',file_get_contents(__DIR__.'/../sql/auth-security-migration.sql'));foreach(explode(';',$sql) as $part)if(trim($part)!=='')$db->exec($part);
 foreach([1=>'Super Admin',2=>'Content Editor',3=>'The Mirror'] as $id=>$role)$db->prepare('INSERT INTO users(user_id,first_name,last_name,email,password,role,status) VALUES(?,?,?,?,?,?,\'active\')')->execute([$id,'RBAC',$role,'rbac'.$id.'@example.invalid',password_hash(bin2hex(random_bytes(18)),PASSWORD_DEFAULT),$role]);
 $db->exec('ALTER TABLE users AUTO_INCREMENT=4');
 $db->exec("INSERT INTO announcements(announcement_id,user_id,title,slug,content,status,program) VALUES(1,1,'Published original','published-original','<p>Live original</p>','published','ALL')");
 $db->exec("INSERT INTO pages(page_id,slug,title,page_type) VALUES(1,'history','History','sections')");
 $db->exec("INSERT INTO fb_posts_cache(fb_post_id,message,permalink_url,created_time) VALUES('999_123','Synthetic cached Facebook announcement','https://www.facebook.com/999/posts/123',NOW())");
 $db->exec("INSERT INTO page_sections(section_id,page_id,section_key,title,content,status) VALUES(1,1,'history_intro','History','<p>Live history</p>','published')");
 echo json_encode(['database'=>$name]);
}elseif($action==='session'||$action==='session-user'){
 $role=$argv[3]??'';$column=$action==='session'?'role':'user_id';$q=$db->prepare("SELECT user_id,role,must_change_password,credential_version FROM `$name`.users WHERE `$column`=? LIMIT 1");$q->execute([$role]);$user=$q->fetch();if(!$user)throw new RuntimeException('Test role missing');
 session_id('rbac-test-'.bin2hex(random_bytes(16)));session_start();$_SESSION=['user_id'=>(int)$user['user_id'],'user_role'=>$user['role'],'must_change_password'=>(bool)$user['must_change_password'],'credential_version'=>(int)$user['credential_version'],'_initiated'=>true,'last_activity'=>time(),'csrf_token'=>bin2hex(random_bytes(32))];
 $result=['cookie'=>session_name().'='.session_id(),'csrf'=>$_SESSION['csrf_token'],'session'=>session_id()];session_write_close();echo json_encode($result);
}elseif($action==='auth-prep'){
 $id=(int)($argv[3]??0);$mode=$argv[4]??'trusted';$q=$db->prepare("SELECT user_id,email,role,credential_version FROM `$name`.users WHERE user_id=?");$q->execute([$id]);$user=$q->fetch();if(!$user)throw new RuntimeException('Synthetic test user missing');
 session_id('rbac-test-'.bin2hex(random_bytes(16)));session_start();$csrf=bin2hex(random_bytes(32));$_SESSION=['_initiated'=>true,'csrf_token'=>$csrf,'last_activity'=>time()];
 $result=['cookie'=>session_name().'='.session_id(),'csrf'=>$csrf,'session'=>session_id()];
 if(in_array($mode,['otp','otp-remember'],true)){
  $_SESSION['temp_user_id']=$id;$_SESSION['temp_email']=$user['email'];$_SESSION['temp_name']='Test account';$_SESSION['remember_me']=$mode==='otp-remember';
  $_SESSION['temp_credential_version']=(int)$user['credential_version'];
  $db->prepare("UPDATE `$name`.users SET otp_code='123456',otp_expires_at=DATE_ADD(NOW(),INTERVAL 5 MINUTE) WHERE user_id=?")->execute([$id]);
 }else{$token=bin2hex(random_bytes(32));$db->prepare("UPDATE `$name`.users SET remember_token=?,remember_token_expires_at=DATE_ADD(NOW(),INTERVAL 1 DAY) WHERE user_id=?")->execute([hash('sha256',$token),$id]);$result['cookie'].='; gnc_remember_me='.$token;}
 session_write_close();echo json_encode($result);
}elseif($action==='trusted-state'){
 $db->exec("USE `$name`");$mode=$argv[3]??'';
 if($mode==='setup'){
  $db->prepare("UPDATE users SET password=?,must_change_password=0,login_attempts=0,locked_until=NULL WHERE user_id=1")->execute([password_hash('Synthetic-trusted-password-2026!',PASSWORD_DEFAULT)]);
 }elseif($mode==='expire')$db->exec('UPDATE users SET remember_token_expires_at=DATE_SUB(NOW(),INTERVAL 1 SECOND) WHERE user_id=1');
 elseif($mode==='revoke')$db->exec('UPDATE users SET remember_token=NULL,remember_token_expires_at=NULL WHERE user_id=1');
 elseif($mode==='clear-otp'){$db->exec('DELETE FROM auth_rate_limits');$db->exec('UPDATE users SET otp_code=NULL,otp_expires_at=NULL WHERE user_id=1');}
 elseif($mode==='inactive')$db->exec("UPDATE users SET status='inactive' WHERE user_id=1");
 elseif($mode==='expire-session'){
  $session=$argv[4]??'';if(!preg_match('/^[a-zA-Z0-9,-]{16,128}$/D',$session))throw new RuntimeException('Invalid fixture session');
  session_id($session);session_start();$_SESSION['last_activity']=1;session_write_close();
 }elseif($mode!=='inspect')throw new RuntimeException('Unknown trusted-device fixture action');
 $u=$db->query('SELECT remember_token IS NOT NULL AS remembered,remember_token_expires_at,otp_code IS NOT NULL AS has_otp FROM users WHERE user_id=1')->fetch();
 echo json_encode($u);
}elseif($action==='reset-token'){
 $id=(int)($argv[3]??0);$token=bin2hex(random_bytes(32));$db->prepare("INSERT INTO `$name`.auth_password_resets(user_id,token_hash,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 15 MINUTE)) ON DUPLICATE KEY UPDATE token_hash=VALUES(token_hash),expires_at=VALUES(expires_at)")->execute([$id,hash('sha256',$token)]);echo json_encode(['token'=>$token]);
}elseif($action==='inspect'){
 $db->exec("USE `$name`");$users=$db->query('SELECT user_id,role,must_change_password,password_changed_at,credential_version,password,remember_token FROM users')->fetchAll();foreach($users as &$user){$user['password_is_hash']=password_get_info($user['password'])['algoName']!=='unknown';$user['has_remember_token']=$user['remember_token']!==null;unset($user['password'],$user['remember_token']);}unset($user);
 echo json_encode(['announcements'=>$db->query('SELECT announcement_id,user_id,title,content,status FROM announcements')->fetchAll(),'submissions'=>$db->query('SELECT submission_id,user_id,kind,status,reason,target_id,revision FROM rbac_submissions ORDER BY submission_id')->fetchAll(),'logs'=>$db->query('SELECT user_id,action FROM activity_logs')->fetchAll(),'users'=>$users,'sections'=>$db->query('SELECT content FROM page_sections')->fetchAll()]);
}elseif($action==='drop'){
 // Only databases created by this test helper can be removed.
 $db->exec("DROP DATABASE `$name`");echo "Removed isolated RBAC test database.\n";
}elseif($action==='destroy-session'){
 $id=$argv[3]??'';if(!preg_match('/^rbac-test-[a-f0-9]{32}$/D',$id))throw new RuntimeException('Invalid test session');session_id($id);session_start();$_SESSION=[];session_destroy();
}else throw new RuntimeException('Unknown fixture action');
