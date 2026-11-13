<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../admin/admin-functions.php';
require_once __DIR__.'/../includes/content-submissions.php';
require_once __DIR__.'/../includes/editor-presence.php';
$db=getDB();$checks=0;
function epAssert(bool $value,string $message):void{global $checks;if(!$value)throw new RuntimeException($message);$checks++;}
function epConflict(callable $fn,string $message):void{try{$fn();throw new LogicException($message);}catch(RuntimeException $e){epAssert($e->getCode()===409,$message);}}
foreach (['/admin/action/alumni-voices-handler.php'=>'alumni','/admin/enrollment-steps.php'=>'enrollment-procedures'] as $route=>$scope) {
    editorPresenceValidateTarget(['scope_key'=>$scope],$route,[]);
    epAssert(true,'Matching editor target accepted.');
    epConflict(static fn()=>editorPresenceValidateTarget(['scope_key'=>'home'],$route,[]),'Wrong editor target rejected.');
}
// Shadow metadata/users for this connection. No live content or presence is changed.
foreach(['content_editor_presence','content_editor_versions'] as $table){$ddl=$db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];$db->exec(preg_replace('/^CREATE TABLE/','CREATE TEMPORARY TABLE',$ddl));}
$db->exec('CREATE TEMPORARY TABLE users(user_id INT PRIMARY KEY,first_name VARCHAR(100),last_name VARCHAR(100),status VARCHAR(20),credential_version INT)');
$db->exec("INSERT INTO users VALUES(1,'Sir','CJ','active',1),(2,'Sir','Mich','active',1)");
$db->exec('CREATE TEMPORARY TABLE admin_settings(setting_key VARCHAR(100) PRIMARY KEY,setting_value TEXT)');
$avatar=base64_encode("\xff\xd8\xffpresence-avatar-test");
$db->prepare('INSERT INTO admin_settings VALUES(?,?)')->execute(['user_profile_picture_1',$avatar]);
$_SESSION=['user_id'=>1,'user_role'=>'Super Admin','credential_version'=>1];session_id('editor-presence-test-one');
$a=editorPresenceJoin($db,'home','/index.php?ce_preview=1&_ve=123');
$a2=editorPresenceJoin($db,'home','/');
$peerA=editorPresencePeer($db,$a['token']);
epAssert($peerA['page_key']===editorPresencePeer($db,$a2['token'])['page_key'],'Home aliases share identity.');
$_SESSION['user_id']=2;session_id('editor-presence-test-two');
$b=editorPresenceJoin($db,'home','/');$peerB=editorPresencePeer($db,$b['token']);
epAssert(count($b['users'])===2,'Multiple tabs deduplicate the account and two users appear.');
$byId=array_column($b['users'],null,'id');
epAssert($byId[1]['avatar']==='data:image/jpeg;base64,'.$avatar,'Presence returns the existing saved profile picture.');
epAssert($byId[2]['avatar']===null,'Missing profile pictures use the initials fallback.');
$db->prepare("UPDATE content_editor_presence SET activity='editing' WHERE peer_key=?")->execute([$peerA['peer_key']]);
$users=editorPresenceSnapshot($db,$peerB)['users'];
epAssert($users[0]['id']===1&&$users[0]['activity']==='editing','Editing status is visible to the other user.');
$history=editorPresenceJoin($db,'history','/pages/about/gnc-history.php');
epAssert(count($history['users'])===1,'Different pages do not share presence.');
$p1=editorPresencePage('programs','/programs/beed');$p2=editorPresencePage('programs','/pages/academics/college/program.php?slug=beed&ce_preview=1');
epAssert($p1===$p2,'Pretty and legacy program routes share presence.');
epAssert($p1===editorPresencePage('programs','/beed'),'Legacy fixed program aliases share presence.');
epAssert($p1['page_key']!==editorPresencePage('programs','/programs/bsit')['page_key'],'Different programs have separate presence.');
$org1=editorPresencePage('student-organizations','/pages/student%20life/student-org.php?p=1&category=2');
$org2=editorPresencePage('student-organizations','/pages/student%20life/student-org.php?p=3');
epAssert($org1===$org2,'Organization directory pagination shares presence.');
$administration=editorPresencePage('administration','/pages/about/administration.php');
foreach(['board-of-trustees','corporate-officers','board-of-administration','heads-of-student-services'] as $section){
    $tab=editorPresencePage('administration','/pages/about/administration.php?section='.$section.'&navigation=1&ce_preview=1');
    epAssert($tab===$administration,'Administration tabs share the collection draft identity.');
    $tabPeer=['page_key'=>$tab['page_key'],'scope_key'=>$tab['scope']];
    foreach(['save','save_recovery','submit_review'] as $action){
        editorPresenceValidateTarget($tabPeer,'/admin/action/editor-draft-handler.php',['action'=>$action,'page'=>'administration','route'=>'/pages/about/administration.php']);
        $checks++;
    }
    epConflict(fn()=>editorPresenceValidateTarget($tabPeer,'/admin/action/editor-draft-handler.php',['action'=>'save','page'=>'home','route'=>'/']),'Administration peers cannot save another page draft.');
}
epConflict(fn()=>editorPresencePeer($db,$a['token']),'A peer token from another session/account is rejected.');
$db->prepare('UPDATE content_editor_presence SET seen_at=? WHERE user_id=1')->execute([time()-46]);
epAssert(count(editorPresenceSnapshot($db,$peerB)['users'])===1,'Disconnected users expire after 45 seconds.');
$db->exec('UPDATE users SET credential_version=2 WHERE user_id=1');
$db->exec('UPDATE content_editor_presence SET seen_at=UNIX_TIMESTAMP()');
epAssert(count(editorPresenceSnapshot($db,$peerB)['users'])===1,'Revoked credentials disappear immediately.');
$db->exec('UPDATE users SET credential_version=1 WHERE user_id=1');
$db->prepare('UPDATE content_editor_versions SET lease_owner=?,lease_until=? WHERE scope_key=?')->execute([$peerA['peer_key'],time()+120,$peerA['scope_key']]);
epConflict(fn()=>editorPresenceCheck($db,$peerB),'Other users cannot race a publishing batch.');
editorPresenceRelease($db,$peerA);editorPresenceCheck($db,$peerB);$checks++;
$db->prepare('UPDATE content_editor_versions SET revision=revision+1 WHERE scope_key=?')->execute([$peerA['scope_key']]);
epConflict(fn()=>editorPresenceCheck($db,$peerB),'Stale previews cannot save over newer content.');
$fresh=editorPresenceJoin($db,'home','/');$freshPeer=editorPresencePeer($db,$fresh['token']);editorPresenceCheck($db,$freshPeer);$checks++;
$savedVersion=editorPresenceDraftVersion($db,$fresh['token']);editorPresenceCheckSavedVersion($db,$savedVersion);$checks++;
$db->exec("UPDATE content_editor_versions SET revision=revision+1 WHERE scope_key='external'");
epConflict(fn()=>editorPresenceCheck($db,$freshPeer),'Conventional/approval writes invalidate stale previews.');
epConflict(fn()=>editorPresenceCheckSavedVersion($db,$savedVersion),'Queued approval cannot overwrite newer live content.');
editorPresenceRestoreDraft($db,$fresh['token'],['content_version'=>['scope'=>$freshPeer['scope_key'],'revision'=>0,'external'=>0]]);
epAssert(editorPresenceSnapshot($db,editorPresencePeer($db,$fresh['token']))['stale'],'Restored old drafts retain their original stale baseline.');
$_SESSION['user_role']='The Mirror';
try{editorPresencePage('home','/');throw new LogicException('Restricted role accepted.');}catch(RuntimeException $e){epAssert($e->getCode()===403,'Restricted role cannot join.');}
echo "$checks editor presence/domain checks passed. No website content was changed.\n";
