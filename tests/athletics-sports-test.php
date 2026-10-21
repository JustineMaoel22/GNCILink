<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../components/athletics-sports-manager.php';
class AthleticsTestDB extends PDO {
    public function prepare(string $sql,array $options=[]): PDOStatement|false {return parent::prepare(str_replace('INSERT IGNORE','INSERT OR IGNORE',$sql),$options);}
    public function query(string $sql,?int $fetchMode=null,mixed ...$args): PDOStatement|false {return parent::query(str_replace(' FOR UPDATE','',$sql));}
}
function getDB(): PDO {static $db;return $db??=new AthleticsTestDB('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);}
function ensureAcademicContentTable(): void {getDB()->exec('CREATE TABLE IF NOT EXISTS basic_education_content(page_key TEXT,field_key TEXT,content TEXT,PRIMARY KEY(page_key,field_key))');}
$uploaded=[];$discarded=[];
function storeDepartmentUpload(array $file,string $kind): array {
    if($file['name']==='bad')throw new RuntimeException('Simulated invalid image');
    $upload=['path'=>'/uploads/homepage/'.$file['name'].'.png'];$GLOBALS['uploaded'][]=$upload;return $upload;
}
function discardStoredDepartmentUpload(array $upload): void {$GLOBALS['discarded'][]=$upload;}
function check(bool $value,string $label): void {if(!$value)throw new RuntimeException($label);}
function rejected(callable $fn): void {try{$fn();}catch(InvalidArgumentException $e){return;}throw new RuntimeException('Invalid request accepted');}
ensureAcademicContentTable();$initial=athleticsSportsContent();$revision=athleticsSportsRevision($initial);
check(count($initial['sports'])===3,'Three initial sports');
check($initial['speed']===1,'Default speed matches the original animation');
check(array_column($initial['sports'],'image')===['','',''],'Default sports use gradients');
$old=$initial;$old['sports'][0]['image']='/assets/images/athletic_scholar.png';$old['sports'][1]['image']='/uploads/homepage/real-photo.png';$migrated=athleticsSportsWithImages($old);
check($migrated['sports'][0]['image']===''&&$migrated['sports'][1]['image']==='/uploads/homepage/real-photo.png','Old placeholders become gradients and uploaded images remain');
$input=$initial;$input['sports'][]=['id'=>'badminton','name'=>'Badminton','type'=>'Mixed','image'=>'https://untrusted.test/image.png'];
$result=saveAthleticsSports($input,['sport_badminton'=>['error'=>0,'name'=>'badminton']],$revision);
check(count($result['sports'])===4,'New sport persisted');check($result['sports'][3]['image']==='/uploads/homepage/badminton.png','Only server upload paths used');
check(athleticsSportsContent()===$result,'Public reads published content');
$input=$result;$input['images']=['hero'=>'https://untrusted.test/image.png','showcase'=>'/untrusted.png'];
$updated=saveAthleticsSports($input,[],athleticsSportsRevision($result));check($updated['images']===$result['images'],'Background paths cannot be supplied by clients');$result=$updated;
$result=saveAthleticsSports($result,['background_hero'=>['error'=>0,'name'=>'banner'],'background_showcase'=>['error'=>0,'name'=>'background']],athleticsSportsRevision($result));
check($result['images']['hero']==='/uploads/homepage/banner.png' && $result['images']['showcase']==='/uploads/homepage/background.png','Both background uploads persist');
check(athleticsSportsContent()['images']===$result['images'],'Public page reads saved backgrounds');
$input=$result;$input['teams'][]=['id'=>'new-team','name'=>'New varsity team','image'=>'https://untrusted.test/logo.png'];
$result=saveAthleticsSports($input,['team_green-mamba'=>['error'=>0,'name'=>'mamba'],'team_new-team'=>['error'=>0,'name'=>'team']],athleticsSportsRevision($result));
check(count($result['teams'])===3&&$result['teams'][0]['image']==='/uploads/homepage/mamba.png'&&$result['teams'][2]['image']==='/uploads/homepage/team.png','Default slots and additional varsity logos persist');
$input=$result;$input['facilities'][]=['id'=>'training-gym','name'=>'Training Gym','image'=>'https://untrusted.test/gym.jpg'];
$result=saveAthleticsSports($input,['facility_training-gym'=>['error'=>0,'name'=>'gym']],athleticsSportsRevision($result));
check(count($result['facilities'])===3&&$result['facilities'][2]['image']==='/uploads/homepage/gym.png','New facility image persisted');check($result['facilities'][0]['image']==='/assets/images/Covered Courts.jpg','Existing facility retained');
rejected(fn()=>saveAthleticsSports($initial,[],$revision));check(athleticsSportsContent()===$result,'Stale editor cannot overwrite');
$revision=athleticsSportsRevision($result);$bad=$result;$bad['sports'][]=$bad['sports'][0];rejected(fn()=>saveAthleticsSports($bad,[],$revision));
$bad=$result;$bad['teams'][]=$bad['teams'][0];rejected(fn()=>saveAthleticsSports($bad,[],$revision));
$bad=$result;$bad['teams'][0]['name']='';rejected(fn()=>saveAthleticsSports($bad,[],$revision));
rejected(fn()=>saveAthleticsSports($result,['team_unknown'=>['name'=>'bad']],$revision));
$bad=$result;$bad['facilities'][]=$bad['facilities'][0];rejected(fn()=>saveAthleticsSports($bad,[],$revision));
$bad=$result;$bad['facilities'][0]['name']='';rejected(fn()=>saveAthleticsSports($bad,[],$revision));
rejected(fn()=>saveAthleticsSports($result,['facility_unknown'=>['name'=>'bad']],$revision));
foreach([0,0.1,4,'2',[],null] as $invalidSpeed) {
    if($invalidSpeed===null)continue;
    $bad=$result;$bad['speed']=$invalidSpeed;rejected(fn()=>saveAthleticsSports($bad,[],$revision));
}
$bad=$result;$bad['sports'][0]['name']=' ';rejected(fn()=>saveAthleticsSports($bad,[],$revision));
$bad=$result;$bad['sports'][0]['id']='../escape';rejected(fn()=>saveAthleticsSports($bad,[],$revision));
rejected(fn()=>saveAthleticsSports($result,['sport_missing'=>['name'=>'bad']],$revision));
$bad=$result;$bad['sports']=array_fill(0,31,$result['sports'][0]);rejected(fn()=>saveAthleticsSports($bad,[],$revision));
$before=count($discarded);
try{saveAthleticsSports($result,['sport_basketball'=>['error'=>0,'name'=>'good'],'sport_indoor-volleyball'=>['error'=>0,'name'=>'bad']],$revision);throw new LogicException('Expected upload failure');}catch(RuntimeException $error){check($error->getMessage()==='Simulated invalid image','Expected failure');}
check(count($discarded)===$before+1,'Failed publish removes already stored uploads');check(athleticsSportsContent()===$result,'Failed publish rolls back text and list changes');
$before=count($discarded);
try{saveAthleticsSports($result,['background_hero'=>['error'=>0,'name'=>'new-banner'],'background_showcase'=>['error'=>0,'name'=>'bad']],$revision);throw new LogicException('Expected background failure');}catch(RuntimeException $error){check($error->getMessage()==='Simulated invalid image','Expected background failure');}
check(count($discarded)===$before+1,'Failed backgrounds clean up previous upload');check(athleticsSportsContent()===$result,'Failed background publication retains saved images');
$result['sports']=array_reverse($result['sports']);$result['sports'][0]['name']='Updated badminton';$result['texts']['hero_title']='GNC Athletics';$result['speed']=2;
$saved=saveAthleticsSports($result,[],athleticsSportsRevision(athleticsSportsContent()));check($saved['sports'][0]['name']==='Updated badminton','Edits and reordered slides saved');
check(athleticsSportsContent()['speed']===2,'Speed persisted for the public slideshow');
$saved['sports']=[];saveAthleticsSports($saved,[],athleticsSportsRevision(athleticsSportsContent()));check(athleticsSportsContent()['sports']===[],'Empty list stays empty after publication');
$legacy=$saved;unset($legacy['images'],$legacy['speed']);getDB()->prepare("UPDATE basic_education_content SET content=? WHERE page_key='athletics-sports'")->execute([json_encode($legacy)]);
$legacyRead=athleticsSportsContent();check($legacyRead['images']===athleticsSportsDefaults()['images'],'Older content receives default backgrounds');saveAthleticsSports($legacyRead,[],athleticsSportsRevision($legacyRead));
check($legacyRead['speed']===1,'Older pages keep the original slideshow speed');
echo "PASS: defaults, published additions, images, edits, ordering, empty list, stale-editor protection, validation and upload rollback.\n";
