<?php
/** Reauthenticate the acting account before deleting content or queuing its deletion. */
function verifyContentDeletePassword(string $kind,string $password): void {
    if(!in_array($kind,['news','announcement','event','slide'],true))throw new InvalidArgumentException('Invalid content type.',400);
    $allowed=$kind==='slide'?hasPermission('manage_page_content'):(hasPermission('delete_'.$kind)||hasPermission('delete_own_'.$kind));
    if(!$allowed)throw new RuntimeException('You do not have permission to delete this content.',403);
    if($password===''||strlen($password)>4096)throw new InvalidArgumentException('Enter your current password to confirm deletion.',403);
    $id=(int)$_SESSION['user_id'];
    if(!authRateAllowed('delete-content-password',(string)$id,5,900))throw new RuntimeException('Too many confirmation attempts. Wait 15 minutes before trying again.',429);
    $db=getDB();$q=$db->prepare('SELECT password,role,status,credential_version,locked_until FROM users WHERE user_id=?');$q->execute([$id]);$user=$q->fetch(PDO::FETCH_ASSOC);
    if(!$user||$user['status']!=='active'||rbacRole($user['role'])!==rbacRole()||(int)$user['credential_version']!==(int)($_SESSION['credential_version']??-1))throw new RuntimeException('Sign in again before deleting content.',401);
    if(!empty($user['locked_until'])&&strtotime($user['locked_until'])>time())throw new RuntimeException('Your account is temporarily locked. Please try again later.',403);
    if(!password_verify($password,$user['password'])){
        authAudit($id,'DELETE_CONTENT_FAILED','Deletion confirmation refused for '.$kind.': incorrect password.');
        throw new InvalidArgumentException('Your current password is incorrect. Nothing was deleted or submitted.',403);
    }
    $db->prepare('DELETE FROM auth_rate_limits WHERE bucket_hash=?')->execute([hash('sha256','delete-content-password:'.$id)]);
}
function contentDeletePasswordBoundary(string $route): void {
    $kind=['/admin/action/create-news.php'=>'news','/admin/action/create-announcement.php'=>'announcement','/admin/events.php'=>'event','/admin/action/page-content-handler.php'=>'slide'][$route]??null;
    $action=$kind==='event'?($_POST['ajax_action']??''):($_POST['action']??'');
    if(!$kind||($_SERVER['REQUEST_METHOD']??'GET')!=='POST'||!in_array($action,['delete','bulk_delete'],true))return;
    if($kind==='slide'&&$action!=='delete')return;
    $password=is_string($_POST['current_password']??null)?$_POST['current_password']:'';
    unset($_POST['current_password']); // Never persist a password in an approval payload.
    // The one-use approval worker is authenticated by rbacRequestBoundary; its reviewer
    // already confirms their password when approving. It never receives the submitter's password.
    if(!empty($_SESSION['_rbac_worker']))return;
    try{
        $csrf=is_string($_POST['csrf_token']??null)?$_POST['csrf_token']:'';
        $valid=verifyCSRFToken($csrf);
        if(!$valid&&$kind==='event'){
            $q=getDB()->prepare('SELECT token_id FROM csrf_tokens WHERE user_id=? AND token=? AND expires_at>NOW()');$q->execute([(int)$_SESSION['user_id'],$csrf]);$valid=(bool)$q->fetchColumn();
        }
        if(!$valid)throw new RuntimeException('Your session token expired. Refresh the page and try again.',403);
        verifyContentDeletePassword($kind,$password);
    }catch(Throwable $e){
        $message=$e instanceof PDOException?'Deletion confirmation is unavailable. Nothing was deleted.':$e->getMessage();
        if($kind==='event'||$kind==='slide'){
            http_response_code(in_array($e->getCode(),[400,401,403,429],true)?$e->getCode():503);
            header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
            echo json_encode(['success'=>false,'error'=>$message,'message'=>$message]);exit;
        }
        $_SESSION['flash_errors']=[$message];
        header('Location: /admin/'.($kind==='news'?'news':'announcement').'.php');exit;
    }
}
