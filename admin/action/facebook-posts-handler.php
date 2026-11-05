<?php
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../admin-functions.php';
require_once __DIR__.'/../announcement-view.php';
initSession(); requireLogin(); requirePermission('publish_announcement');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST') { header('Allow: POST'); rbacFail('Use POST to update a post.',405); }
if(!verifyCSRFToken(is_string($_POST['csrf_token']??null)?$_POST['csrf_token']:'')) rbacFail('Your session expired. Reload the page and try again.',403);
$db=getDB();$locked=false;
try {
    $action=$_POST['action']??null;$source=$_POST['source']??'facebook';$id=$_POST['item_id']??null;
    if(!is_string($action)||!in_array($action,['pin','unpin','category'],true)||!is_string($source)||!in_array($source,['facebook','announcement','news'],true)||!is_string($id))throw new InvalidArgumentException('Choose a valid post and action.');
    if($source==='facebook'){
        if(strlen($id)>100||!preg_match('/^\d+(?:_\d+)?$/D',$id))throw new InvalidArgumentException('Choose a valid Facebook post.');
    }elseif(!preg_match('/^[1-9]\d{0,9}$/D',$id)||$action!=='unpin')throw new InvalidArgumentException('Only unpinning is supported for this content.');
    if($source==='news')requirePermission('publish_news');
    // Serialize this management endpoint and lock all shared slideshow rows.
    $q=$db->query("SELECT GET_LOCK('gnci-facebook-slideshow',5)");$locked=(int)$q->fetchColumn()===1;
    if(!$locked)throw new RuntimeException('Please wait a moment and try again.');
    $db->beginTransaction();
    foreach(['announcements','news','fb_posts_cache'] as $table)$db->query("SELECT is_pinned FROM $table FOR UPDATE")->fetchAll();
    $table=['facebook'=>'fb_posts_cache','announcement'=>'announcements','news'=>'news'][$source];
    $key=['facebook'=>'fb_post_id','announcement'=>'announcement_id','news'=>'news_id'][$source];
    $q=$db->prepare("SELECT is_pinned FROM $table WHERE $key=?");$q->execute([$id]);$existing=$q->fetch();
    if(!$existing)throw new InvalidArgumentException('This item is no longer available. Reload the page.');
    if($action==='category'){
        $choice=$_POST['category_choice']??null;
        if(!is_string($choice)||!preg_match('/^(?:auto|none|[1-9]\d{0,9})$/D',$choice))throw new InvalidArgumentException('Choose a valid category.');
        if(!setFacebookPostCategory($id,$choice))throw new InvalidArgumentException('The category could not be saved. Check your selection and try again.');
        $message='Category updated.';
    }elseif($action==='pin'){
        if(!(int)$existing['is_pinned']&&countPinnedItems()>=MAX_PINNED_ITEMS)throw new InvalidArgumentException('All five slideshow slots are used. Unpin an item in Manage slideshow first.');
        if(!pinFacebookPost($id))throw new RuntimeException('Pin failed.');
        $message='Post pinned to the homepage slideshow.';
    }else{
        $ok=$source==='facebook'?unpinFacebookPost($id):($source==='news'?unpinNews((int)$id):unpinAnnouncement((int)$id));
        if(!$ok)throw new RuntimeException('Unpin failed.');
        $message='Item removed from the homepage slideshow.';
    }
    $post=null;
    if($source==='facebook'){
        $q=$db->prepare('SELECT f.is_pinned,f.category_id,f.category_locked,c.category_name FROM fb_posts_cache f LEFT JOIN categories c ON c.category_id=f.category_id WHERE f.fb_post_id=?');$q->execute([$id]);$post=$q->fetch(PDO::FETCH_ASSOC);
    }
    $pinned=announcementPinnedItems($db);$db->commit();
    echo json_encode(['success'=>true,'message'=>$message,'post'=>$post,'pinned'=>$pinned],JSON_INVALID_UTF8_SUBSTITUTE);
}catch(InvalidArgumentException $e){
    if($db->inTransaction())$db->rollBack();http_response_code(422);echo json_encode(['success'=>false,'error'=>$e->getMessage()]);
}catch(Throwable $e){
    if($db->inTransaction())$db->rollBack();error_log('Facebook management update failed.');http_response_code(503);echo json_encode(['success'=>false,'error'=>'The change could not be saved. Please try again.']);
}finally{if($locked)$db->query("SELECT RELEASE_LOCK('gnci-facebook-slideshow')");}
