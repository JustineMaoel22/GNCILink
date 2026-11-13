<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../admin/admin-functions.php';
require_once __DIR__.'/../includes/content-submissions.php';
require_once __DIR__.'/../includes/publish-confirmation.php';
require_once __DIR__.'/../includes/content-delete-password.php';
$db=getDB();$checks=0;
function homeCheck(bool $ok,string $message):void { global $checks; if(!$ok)throw new RuntimeException($message);$checks++; }
function homeRefused(callable $fn):void { global $checks;try{$fn();}catch(RuntimeException|InvalidArgumentException $e){$checks++;return;}throw new RuntimeException('An unauthorized confirmation was accepted.'); }
// Connection-local shadow tables: no existing users, slides, files, logs or approvals change.
$db->exec('CREATE TEMPORARY TABLE hero_slides(slide_id INT AUTO_INCREMENT PRIMARY KEY,media_type VARCHAR(10),media_path VARCHAR(255),media_path_mobile VARCHAR(255),title VARCHAR(100),subtitle TEXT,btn1_text VARCHAR(30),btn1_link VARCHAR(255),btn2_text VARCHAR(30),btn2_link VARCHAR(255),display_order INT,status VARCHAR(20),show_on_mobile TINYINT,show_gradient TINYINT,created_by INT,created_at DATETIME) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE users(user_id INT PRIMARY KEY,password VARCHAR(255),status VARCHAR(20),role VARCHAR(30),credential_version INT,locked_until DATETIME NULL)');
$db->exec('CREATE TEMPORARY TABLE auth_rate_limits(bucket_hash CHAR(64) PRIMARY KEY,attempts INT,expires_at DATETIME)');
$db->exec('CREATE TEMPORARY TABLE auth_security_logs(user_id INT,action VARCHAR(100),ip_address VARCHAR(45),description VARCHAR(255))');
$db->exec('CREATE TEMPORARY TABLE activity_logs(user_id INT,action VARCHAR(100),table_name VARCHAR(100),record_id INT,description TEXT,created_at DATETIME) ENGINE=InnoDB');
$password='Home-slide-test-'.bin2hex(random_bytes(12));
foreach([1=>'Super Admin',2=>'Content Editor',3=>'The Mirror'] as $id=>$role)$db->prepare("INSERT INTO users VALUES(?,?,'active',?,1,NULL)")->execute([$id,password_hash($password,PASSWORD_DEFAULT),$role]);
$_SESSION=['user_id'=>1,'user_role'=>'Super Admin','credential_version'=>1];
$route='/admin/action/page-content-handler.php';
homeCheck(!publishNeedsConfirmation($route,['action'=>'save','slide_id'=>'0','status'=>'draft']),'New draft upload unexpectedly requires publishing confirmation.');
homeCheck(publishNeedsConfirmation($route,['action'=>'save','slide_id'=>'0','status'=>'published']),'New published slide bypasses publishing confirmation.');
homeRefused(fn()=>issuePublishConfirmation('incorrect'));
$token=issuePublishConfirmation($password);homeCheck(publishConfirmationValid($token),'Correct publication password rejected.');
foreach(['image'=>'png','video'=>'mp4'] as $type=>$extension){
    $data=['title'=>'QA '.$type,'subtitle'=>'Description','btn1_text'=>'','btn1_link'=>'','btn2_text'=>'','btn2_link'=>'','status'=>'draft','show_on_mobile'=>true,'show_gradient'=>true];
    // Nonexistent, randomly named media paths ensure cleanup cannot touch any real uploads.
    $id=createHeroSlide($data,['type'=>$type,'path'=>'/uploads/hero-slides/qa-'.bin2hex(random_bytes(16)).'.'.$extension]);
    homeCheck(is_int($id)&&getHeroSlideById($id)['status']==='draft','Draft '.$type.' was not stored as a draft.');
    homeCheck(count(getHeroSlides(true))===0,'Draft '.$type.' leaked into the public slideshow.');
    homeCheck(!publishNeedsConfirmation($route,['action'=>'save','slide_id'=>(string)$id,'status'=>'draft']),'Editing an existing draft asks for publication confirmation.');
    homeCheck(publishNeedsConfirmation($route,['action'=>'save','slide_id'=>(string)$id,'status'=>'published']),'Draft publication lacks confirmation.');
    $data['status']='published';homeCheck(updateHeroSlide($id,$data),'Confirmed '.$type.' publication failed.');
    homeCheck(count(getHeroSlides(true))===1&&getHeroSlideById($id)['media_type']===$type,'Published '.$type.' was not visible.');
    homeCheck(publishNeedsConfirmation($route,['action'=>'save','slide_id'=>(string)$id,'status'=>'draft']),'A live slide can be changed without confirmation.');
    $q=$db->prepare("SELECT COUNT(*) FROM activity_logs WHERE action='PUBLISH' AND record_id=? AND user_id=1 AND created_at IS NOT NULL");$q->execute([$id]);homeCheck((int)$q->fetchColumn()===1,'Publication actor/timestamp audit missing.');
    homeRefused(fn()=>verifyContentDeletePassword('slide',''));
    homeRefused(fn()=>verifyContentDeletePassword('slide','incorrect'));
    homeCheck(getHeroSlideById($id)!==null,'Incorrect deletion password changed the slide.');
    verifyContentDeletePassword('slide',$password);homeCheck(deleteHeroSlide($id),'Password-confirmed '.$type.' deletion failed.');
    homeCheck(getHeroSlideById($id)===null,'Deleted '.$type.' remains stored.');
    $q=$db->prepare("SELECT COUNT(*) FROM activity_logs WHERE action='DELETE' AND record_id=? AND user_id=1 AND created_at IS NOT NULL");$q->execute([$id]);homeCheck((int)$q->fetchColumn()===1,'Deletion actor/timestamp audit missing.');
}
$_SERVER['REQUEST_METHOD']='POST';$_POST=['action'=>'delete','slide_id'=>'123','csrf_token'=>generateCSRFToken(),'current_password'=>$password];
contentDeletePasswordBoundary($route);homeCheck(!isset($_POST['current_password']),'Deletion password could reach approval storage.');
homeCheck(!str_contains(json_encode($_SESSION),$password),'A password was stored in the session.');
$_SESSION=['user_id'=>2,'user_role'=>'Content Editor','credential_version'=>1];
verifyContentDeletePassword('slide',$password);$checks++;
homeRefused(fn()=>issuePublishConfirmation($password));
homeCheck(rbacShouldQueue($route,['action'=>'save','status'=>'published'])&&rbacShouldQueue($route,['action'=>'delete']),'Content Editor approval routing changed.');
homeCheck(!rbacRouteAllowed('The Mirror',$route),'The Mirror gained Home Page privileges.');
$_SESSION=['user_id'=>3,'user_role'=>'The Mirror','credential_version'=>1];homeRefused(fn()=>verifyContentDeletePassword('slide',$password));
$_SESSION=['user_id'=>1,'user_role'=>'Super Admin','credential_version'=>2];homeRefused(fn()=>verifyContentDeletePassword('slide',$password));
$_SESSION['credential_version']=1;$db->exec('DELETE FROM auth_rate_limits');
for($i=0;$i<5;$i++)homeRefused(fn()=>verifyContentDeletePassword('slide','incorrect'));
homeRefused(fn()=>verifyContentDeletePassword('slide',$password));
// Failed auditing rolls back the content write and never removes media first.
$draft=['title'=>'Rollback','subtitle'=>'','btn1_text'=>'','btn1_link'=>'','btn2_text'=>'','btn2_link'=>'','status'=>'draft'];
$id=createHeroSlide($draft,['type'=>'image','path'=>'']);
$db->exec('DROP TEMPORARY TABLE activity_logs');
$db->exec("CREATE TEMPORARY TABLE activity_logs(user_id INT,action VARCHAR(100) CHECK(action IN ('CREATE','UPDATE')),table_name VARCHAR(100),record_id INT,description TEXT,created_at DATETIME) ENGINE=InnoDB");
$published=$draft;$published['status']='published';
homeCheck(!updateHeroSlide($id,$published)&&getHeroSlideById($id)['status']==='draft','Audit failure left a slide published.');
homeCheck(!deleteHeroSlide($id)&&getHeroSlideById($id)!==null,'Audit failure permanently deleted a slide.');
$count=count(getHeroSlides());homeCheck(createHeroSlide($published,['type'=>'video','path'=>''])===false&&count(getHeroSlides())===$count,'Audit failure created an unaudited published slide.');
echo "$checks Home Page slide workflow checks passed. Temporary tables only; no real uploads, slides or accounts changed.\n";
