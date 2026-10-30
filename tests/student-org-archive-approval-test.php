<?php
/** Local HTTP approval round-trip using one isolated organization, no uploads. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../admin/admin-functions.php';
require_once __DIR__.'/../includes/content-submissions.php';
$db=getDB();$sessions=[];$submissions=[];$id=null;
function orgApprovalCheck(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
$users=[];
foreach(['Super Admin','Content Editor','The Mirror'] as $role){$q=$db->prepare("SELECT user_id,role,credential_version FROM users WHERE role=? AND status='active' AND must_change_password=0 LIMIT 1");$q->execute([$role]);$users[$role]=$q->fetch(PDO::FETCH_ASSOC);orgApprovalCheck((bool)$users[$role],'Active '.$role.' required.');}
$createSession=static function(array $user)use(&$sessions): array {
    session_id('gnc-org-approval-'.bin2hex(random_bytes(12)));session_start();
    $_SESSION=['user_id'=>(int)$user['user_id'],'user_role'=>$user['role'],'credential_version'=>(int)$user['credential_version'],'_initiated'=>true,'last_activity'=>time(),'csrf_token'=>bin2hex(random_bytes(32))];
    $auth=['id'=>session_id(),'csrf'=>$_SESSION['csrf_token']];$sessions[]=$auth['id'];session_write_close();return $auth;
};
$request=static function(array $auth,array $values): array {
    $curl=curl_init('http://127.0.0.1/admin/action/student-organizations-handler.php');
    curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$values,CURLOPT_COOKIE=>session_name().'='.$auth['id'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30]);
    $raw=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);$error=curl_error($curl);curl_close($curl);
    if($raw===false)throw new RuntimeException($error);$json=json_decode($raw,true);orgApprovalCheck(is_array($json),'Expected JSON from organization handler (HTTP '.$status.').');return [$status,$json];
};
$tag='ORGARCH-'.bin2hex(random_bytes(8));
try {
    $org=saveStudentOrganization(['name'=>$tag,'status'=>'published','description'=>'Approval round-trip fixture']);$id=(int)$org['org_id'];
    $media=(int)$db->query('SELECT media_id FROM media_library ORDER BY media_id LIMIT 1')->fetchColumn();orgApprovalCheck($media>0,'Existing image required.');
    $db->prepare('UPDATE student_organizations SET logo_media_id=?,cover_media_id=? WHERE org_id=?')->execute([$media,$media,$id]);
    $db->prepare('INSERT INTO student_org_gallery(org_id,media_id) VALUES(?,?)')->execute([$id,$media]);
    $read=static function()use($db,$id): array{$q=$db->prepare('SELECT * FROM student_organizations WHERE org_id=?');$q->execute([$id]);return $q->fetch(PDO::FETCH_ASSOC);};
    $before=$read();$gallery=studentOrganizationGallery($id);
    $admin=$createSession($users['Super Admin']);$editor=$createSession($users['Content Editor']);$mirror=$createSession($users['The Mirror']);
    $values=['action'=>'save','org_id'=>(string)$id,'status'=>'published','archived'=>'1','csrf_token'=>$admin['csrf']];
    [$status,$json]=$request($admin,array_replace($values,['csrf_token'=>'invalid']));orgApprovalCheck($status===403&&empty($json['success']),'Invalid CSRF rejected.');
    [$status,$json]=$request($mirror,array_replace($values,['csrf_token'=>$mirror['csrf']]));orgApprovalCheck($status===403&&empty($json['success']),'Restricted role denied.');
    foreach(['1','0'] as $archive) {
        $snapshot=$read();
        [$status,$json]=$request($editor,array_replace($values,['archived'=>$archive,'csrf_token'=>$editor['csrf']]));
        orgApprovalCheck($status===202&&!empty($json['submitted']),'Editor action must enter approval queue.');$submission=(int)$json['submission_id'];$submissions[]=$submission;
        orgApprovalCheck($read()===$snapshot,'Pending archive/restore must not change the public record.');
        session_id($admin['id']);session_start();$message=rbacApproveRequest($submission,1);session_write_close();
        $row=$read();orgApprovalCheck(($archive==='1')===!empty($row['archived_at']),'Approval applies requested archive state: '.$message);
        orgApprovalCheck($row['status']==='published'&&(int)$row['logo_media_id']===$media&&(int)$row['cover_media_id']===$media,'Approval preserves publication and image references.');
        orgApprovalCheck(studentOrganizationGallery($id)===$gallery,'Approval preserves all gallery entries.');
    }
    $after=$read();unset($before['updated_at'],$after['updated_at']);orgApprovalCheck($before===$after,'Archive/restore preserves the organization record.');
    $q=$db->prepare("SELECT action FROM activity_logs WHERE table_name='student_organizations' AND record_id=? AND action IN ('ARCHIVE','RESTORE') ORDER BY log_id");$q->execute([$id]);orgApprovalCheck($q->fetchAll(PDO::FETCH_COLUMN)===['ARCHIVE','RESTORE'],'Archive and restore audit records retained.');
} finally {
    if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
    foreach($sessions as $session){session_id($session);session_start();$_SESSION=[];session_destroy();}
    foreach($submissions as $submission){$db->prepare('DELETE FROM rbac_submission_chunks WHERE submission_id=?')->execute([$submission]);$db->prepare('DELETE FROM rbac_submissions WHERE submission_id=?')->execute([$submission]);}
    if($id)$db->prepare('DELETE FROM student_organizations WHERE org_id=? AND name=?')->execute([$id,$tag]);
}
echo "PASS: real archive/restore approval, pending-public isolation, permissions, CSRF, audit logging and preservation of organization media. Fixtures removed; no images changed.\n";
