<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/community-activities.php';
class CommunityActivityTestDB extends PDO {
    public function query(string $query,?int $fetchMode=null,mixed ...$args):PDOStatement|false{return parent::query(str_replace(' FOR UPDATE','',$query));}
}
function activityCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function activityReject(callable $fn):void{try{$fn();}catch(InvalidArgumentException $e){return;}throw new RuntimeException('Invalid request accepted.');}
$db=new CommunityActivityTestDB('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE community_activity_page(page_id INTEGER PRIMARY KEY,content TEXT,updated_at TEXT)');
$initial=['texts'=>array_fill_keys(array_keys(COMMUNITY_ACTIVITY_FIELDS),'Page fixture'),'hero_image'=>'/assets/images/admission-header.png','activities'=>[]];
$db->prepare('INSERT INTO community_activity_page(page_id,content) VALUES(1,?)')->execute([json_encode($initial)]);
activityCheck(communityActivityContent($db)['activities']===[],'Empty source, no named defaults');
$input=$initial;
foreach(['a','b'] as $id)$input['activities'][]=['id'=>'activity-'.$id,'title'=>'Authored fixture '.$id,'description'=>'Description '.$id,'category'=>'Custom category '.$id,'image'=>'https://untrusted.invalid/image.jpg'];
$created=communityActivitySave($db,$input,[],communityActivityRevision($initial));
activityCheck($created['showcase_image']==='','No hardcoded shared photo');
$legacy=$created;unset($legacy['showcase_image']);$legacy['activities'][1]['image']='/uploads/retained-photo.png';
activityCheck(communityActivityShowcaseImage($legacy)==='/uploads/retained-photo.png','Reuse an existing photo even if the first row is empty');
$changed=$legacy;$changed['activities']=array_reverse($changed['activities']);$changed['showcase_image']='https://untrusted.invalid/photo.png';
activityCheck(communityActivityValidate($changed,$legacy)['showcase_image']==='/uploads/retained-photo.png','Reorder preserves shared photo; forged client image paths are ignored');
$changed['activities']=[];activityCheck(communityActivityValidate($changed,$legacy)['showcase_image']==='/uploads/retained-photo.png','Deleting activities preserves shared photo');
activityCheck(count($created['activities'])===2,'Create arbitrary activities');
activityCheck($created['closing_image']===COMMUNITY_CLOSING_IMAGE_DEFAULT,'Older content receives the matching closing-banner background');
$forged=$created;$forged['closing_image']='https://untrusted.invalid/background.png';
activityCheck(communityActivityValidate($forged,$created)['closing_image']===$created['closing_image'],'Reject forged banner image paths');
$forged=$created;$forged['texts']['closing_title']='Edited mission title';$forged['texts']['closing_description']='Edited mission description';
activityCheck(communityActivityValidate($forged,$created)['texts']['closing_title']==='Edited mission title','Closing banner text is validated for publication');
activityCheck(array_column($created['activities'],'image')===['',''],'Ignore client-provided image URLs');
$input=$created;$input['activities'][0]['title']='Updated title';$input['activities'][0]['category']='Edited category';$input['activities']=array_reverse($input['activities']);
$updated=communityActivitySave($db,$input,[],communityActivityRevision($created));
activityCheck($updated['activities'][0]['id']==='activity-b'&&$updated['activities'][1]['category']==='Edited category','Persist edits, categories and ordering');
activityCheck(communityActivityContent($db)===$updated,'Public source reads the saved collection');
activityReject(fn()=>communityActivitySave($db,$input,[],communityActivityRevision($initial)));
$invalid=$updated;$invalid['activities'][1]['id']='activity-b';activityReject(fn()=>communityActivitySave($db,$invalid,[],communityActivityRevision($updated)));
$invalid=$updated;$invalid['activities'][0]['title']='';activityReject(fn()=>communityActivitySave($db,$invalid,[],communityActivityRevision($updated)));
$invalid=$updated;$invalid['activities'][0]['category']=str_repeat('x',101);activityReject(fn()=>communityActivitySave($db,$invalid,[],communityActivityRevision($updated)));
activityReject(fn()=>communityActivitySave($db,$updated,['image_activity-a'=>['error'=>UPLOAD_ERR_NO_FILE]],communityActivityRevision($updated)));
activityCheck(communityActivityContent($db)===$updated,'Invalid upload leaves the published state intact');
$input=$updated;array_pop($input['activities']);$deleted=communityActivitySave($db,$input,[],communityActivityRevision($updated));
activityCheck(count($deleted['activities'])===1,'Delete an activity');
$input=$deleted;$input['activities']=[];$empty=communityActivitySave($db,$input,[],communityActivityRevision($deleted));activityCheck($empty['activities']===[],'Deleting the last entry keeps an empty collection');

$_POST['allow_incomplete']='1';
$incomplete=$empty;$incomplete['texts']['hero_title']='';$incomplete['texts']['empty_title']='';
$incomplete['activities']=[['id'=>'activity-incomplete','title'=>'','description'=>'','category'=>'','image'=>'https://untrusted.invalid/photo.png']];
$savedIncomplete=communityActivitySave($db,$incomplete,[],communityActivityRevision($empty));
activityCheck(communityActivityContent($db)===$savedIncomplete&&$savedIncomplete['activities'][0]['description']==='','Publish Anyway persists incomplete content');
$invalid=$savedIncomplete;$invalid['activities'][0]['title']=str_repeat('x',256);activityReject(fn()=>communityActivityValidate($invalid,$savedIncomplete));
$invalid=$savedIncomplete;$invalid['activities'][0]['id']='../invalid';activityReject(fn()=>communityActivityValidate($invalid,$savedIncomplete));
activityCheck($savedIncomplete['activities'][0]['image']==='','Override still rejects forged image paths');
unset($_POST['allow_incomplete']);activityReject(fn()=>communityActivityValidate($incomplete,$savedIncomplete));
echo "PASS: isolated database create/edit/category/reorder/delete, public source, empty state, stale revision, field validation, forged paths, invalid uploads and atomic failure.\n";
