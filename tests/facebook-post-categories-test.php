<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../admin/admin-functions.php';
require_once __DIR__.'/../admin/facebook-posts-view.php';
require_once __DIR__.'/../includes/campus-event-posts.php';
$db=getDB();$_SESSION=['user_id'=>1,'user_role'=>'Super Admin'];$checks=0;
function fbTagsCheck(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
fbTagsCheck(facebookPostNewsCategoryKeys(['Achievements','Holiday','Achievements'],getNewsCategories())===['Achievements'],'Facebook News tags must map to public News filter codes without duplicates.');
fbTagsCheck(facebookPostNewsCategoryKeys(['Holiday'],getNewsCategories())===[],'Announcement-only tags must not match News categories.');
fbTagsCheck(facebookPostNewsCategoryKeys(['Public label'],['internal-code'=>'Public label'])===['internal-code'],'Category labels must map to their public filter codes.');
foreach(['fb_posts_cache','fb_post_categories','activity_logs'] as $table){
    $ddl=$db->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM)[1];
    $ddl=preg_replace('/^CREATE TABLE/','CREATE TEMPORARY TABLE',$ddl);
    $ddl=preg_replace('/^\s*CONSTRAINT[^\n]*\n?/m','',$ddl);
    $db->exec(preg_replace('/,\s*\)/',"\n)",$ddl));
}
$choices=getFacebookPostCategories($db);fbTagsCheck(count($choices)>=2,'Need two existing category choices.');
$ids=array_map('intval',array_column(array_slice($choices,0,2),'category_id'));$post='999999999_123';
$db->prepare('INSERT INTO fb_posts_cache(fb_post_id,message,created_time,fetched_at,category_id,category_locked) VALUES(?,?,NOW(),NOW(),?,0)')->execute([$post,'Reminder: enrollment deadline',$ids[0]]);
fbTagsCheck(setFacebookPostCategories($post,'manual',[$ids[0],(string)$ids[1],$ids[0]]),'Multiple category save failed.');
$read=static function()use($db){$filters=facebookPostFilters([]);return facebookPostList($db,$filters)['rows'][0];};
$saved=$read();fbTagsCheck(count($saved['category_ids'])===2&&(int)$saved['category_locked']===1,'Manual categories did not restore.');
foreach($ids as $id){$filters=facebookPostFilters(['category'=>(string)$id]);$list=facebookPostList($db,$filters);fbTagsCheck($list['total']===1&&count($list['rows'])===1,'Filtering must match every selected category without duplicates.');}
$name=$choices[1]['category_name'];$q=$db->prepare('SELECT category_name FROM categories WHERE category_id=?');$q->execute([$ids[1]]);$rawName=$q->fetchColumn();
$campus=campusEventPosts($db,[],$rawName);$matches=array_filter($campus['items'],fn($item)=>$item['post_type']==='facebook'&&$item['post_id']===$post);
fbTagsCheck(count($matches)===1,'Public category feed lost the secondary Facebook tag.');
foreach([['bad'],['0'],['9999999999'],[[1]]] as $invalid){try{setFacebookPostCategories($post,'manual',$invalid);throw new LogicException('Invalid category accepted.');}catch(InvalidArgumentException $expected){$checks++;}}
fbTagsCheck(count($read()['category_ids'])===2,'Rejected save changed existing tags.');
// The caller's transaction must cover the primary and child assignments together.
$db->beginTransaction();setFacebookPostCategories($post,'manual',[$ids[0]]);$db->rollBack();
fbTagsCheck(count($read()['category_ids'])===2,'Rollback did not retain both categories.');
setFacebookPostCategories($post,'auto',[]);$automatic=$read();
fbTagsCheck((int)$automatic['category_locked']===0,'Automatic mode was not restored.');
fbTagsCheck((int)$db->query('SELECT COUNT(*) FROM fb_post_categories')->fetchColumn()===0,'Switching to automatic retained stale manual tags.');
setFacebookPostCategories($post,'manual',[]);$empty=$read();
fbTagsCheck($empty['category_ids']===[]&&$empty['category_id']===null&&(int)$empty['category_locked']===1,'Clearing all categories did not retain manual Uncategorized.');
$filters=facebookPostFilters(['category'=>'none']);fbTagsCheck(facebookPostList($db,$filters)['total']===1,'Uncategorized filtering failed.');
fbTagsCheck(setFacebookPostCategory($post,(string)$ids[0])&&count($read()['category_ids'])===1,'Legacy single-category save failed.');
echo "$checks Facebook category persistence, filtering, public feed, validation and rollback checks passed. No real posts or categories changed.\n";
