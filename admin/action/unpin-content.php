<?php
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../admin-functions.php';
initSession();requireLogin();requirePermission('publish_announcement');
header('Cache-Control: no-store');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){header('Allow: POST');rbacFail('Use POST to unpin content.',405);}
if(!authCsrfValid())rbacFail('Your session expired. Reload and try again.',403);
try{
    $source=$_POST['source']??null;$id=$_POST['item_id']??null;
    if(!is_string($source)||!is_string($id))throw new InvalidArgumentException('Choose a valid pinned item.');
    if($source==='facebook'){
        if(!preg_match('/^\d+(?:_\d+)?$/D',$id)||strlen($id)>100)throw new InvalidArgumentException('Choose a valid Facebook post.');
        $ok=unpinFacebookPost($id);
    }elseif(in_array($source,['announcement','news'],true)){
        if(!preg_match('/^[1-9]\d{0,9}$/D',$id))throw new InvalidArgumentException('Choose a valid pinned item.');
        if($source==='news'){requirePermission('publish_news');$ok=unpinNews((int)$id);}
        else $ok=unpinAnnouncement((int)$id);
    }else throw new InvalidArgumentException('Choose a valid pinned item.');
    if(!$ok)throw new RuntimeException('Could not unpin this item. Please reload and try again.');
    $_SESSION['flash_success']='Item unpinned. Its content and publication status are unchanged.';
}catch(InvalidArgumentException $e){$_SESSION['flash_errors']=[$e->getMessage()];}
catch(Throwable $e){error_log('Pinned content update failed.');$_SESSION['flash_errors']=['Could not unpin this item. Please reload and try again.'];}
$returnUrl='/admin/announcement.php';
if(($_POST['return_to']??'')==='news'){require_once __DIR__.'/../news-view.php';$returnUrl=newsReturnUrl($_POST);}
header('Location: '.$returnUrl.'#pinned-content',true,303);exit;
