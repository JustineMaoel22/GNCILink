<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin/admin-functions.php';
require_once __DIR__ . '/../components/enrollment-procedures-content.php';
function imageCheck(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); }
function imageRefused(callable $work): void { try {$work();} catch(InvalidArgumentException $e){return;} throw new RuntimeException('Invalid request accepted'); }
$db=getDB();
foreach (['basic_enrollment_steps','enrollment_illustrations'] as $table) {
    $ddl=$db->query('SHOW CREATE TABLE '.$table)->fetch(PDO::FETCH_NUM)[1];
    $db->exec(str_replace('CREATE TABLE','CREATE TEMPORARY TABLE',$ddl));
}
$db->exec('CREATE TEMPORARY TABLE activity_logs (user_id INT,action VARCHAR(50),table_name VARCHAR(100),record_id INT,description TEXT,created_at DATETIME)');
$_SESSION=['user_id'=>1,'user_role'=>'Super Admin'];
$images=enrollmentIllustrations(); imageCheck(count($images)===26,'26 College illustrations have defaults');
$uploaded='/uploads/enrollment-procedures/'.str_repeat('a',32).'.svg';
$db->prepare('INSERT INTO enrollment_illustrations VALUES (?,?)')->execute(['fresh-1',$uploaded]);
imageCheck(enrollmentIllustrations()['fresh-1']['url']===$uploaded,'Published override used by public rendering');
imageRefused(fn()=>publishEnrollmentIllustrations(['fresh-1'=>['reset'=>true,'expected'=>'stale']],[]));
imageCheck(enrollmentIllustrations()['fresh-1']['url']===$uploaded,'Stale image change does not overwrite');
$res=publishEnrollmentIllustrations(['fresh-1'=>['reset'=>true,'expected'=>$uploaded]],[]);
imageCheck($res['fresh-1']['url']===$images['fresh-1']['default'],'Restore removes override');
imageCheck((int)$db->query('SELECT COUNT(*) FROM enrollment_illustrations')->fetchColumn()===0,'Restoring removes only the saved override');
foreach ([['unknown'=>['reset'=>true,'expected'=>'']],['fresh-1'=>['reset'=>'true','expected'=>'']],['fresh-1'=>['reset'=>false,'expected'=>$images['fresh-1']['url']]]] as $bad) imageRefused(fn()=>publishEnrollmentIllustrations($bad,[]));
$id=str_repeat('b',32); $preset='/assets/images/svg/enrollment-procedures/trans-2.svg';
$db->prepare('INSERT INTO basic_enrollment_steps (step_id,title,location,instruction,illustration) VALUES (?,?,?,?,?)')->execute([$id,'Office','Window','Instruction',$uploaded]);
$db->prepare('INSERT INTO enrollment_illustrations VALUES (?,?)')->execute(['basic-'.$id,$preset]);
imageCheck(enrollmentIllustrations()['basic-'.$id]['default']===$preset,'Basic Education original illustration survives reload');
publishEnrollmentIllustrations(['basic-'.$id=>['reset'=>true,'expected'=>$uploaded]],[]);
$basic=basicEnrollmentSteps(); imageCheck($basic[0]['illustration']===$preset && $basic[0]['title']==='Office' && $basic[0]['instruction']==='Instruction','Inline restore updates the existing managed step without changing text');
$db->prepare('INSERT INTO enrollment_illustrations VALUES (?,?)')->execute(['basic-'.$id,$preset]);
$basic[0]['illustration']='/assets/images/svg/enrollment-procedures/old-1.svg';
publishBasicEnrollmentSteps($basic,basicEnrollmentRevision(basicEnrollmentSteps()));
imageCheck((int)$db->query('SELECT COUNT(*) FROM enrollment_illustrations')->fetchColumn()===0,'Step manager replacement clears an obsolete restore target');
$before=basicEnrollmentSteps();
$new=['step_id'=>str_repeat('c',32),'title'=>'New canvas step','location'=>'','instruction'=>'Approved instructions','illustration'=>'/assets/images/svg/enrollment-procedures/fresh-1.svg'];
imageRefused(fn()=>publishEnrollmentCanvas(array_merge($before,[$new]),basicEnrollmentRevision($before),['unknown'=>['reset'=>true,'expected'=>'']],[]));
imageCheck(basicEnrollmentSteps()===$before,'Invalid image change rolls back simultaneous canvas step creation');
$result=publishEnrollmentCanvas(array_merge($before,[$new]),basicEnrollmentRevision($before),['basic-'.$new['step_id']=>['reset'=>true,'expected'=>$new['illustration']]],[]);
imageCheck(count($result['steps'])===2 && $result['steps'][1]['title']==='New canvas step','Canvas creates steps and changes illustrations in the same transaction');
imageCheck($result['revision']===basicEnrollmentRevision($result['steps']),'Canvas returns current snapshot revision');
imageRefused(fn()=>publishEnrollmentCanvas([],basicEnrollmentRevision($before),[],[]));
imageCheck(count(basicEnrollmentSteps())===2,'Stale canvas does not erase newer steps');
$_SESSION['user_role']='Content Editor';imageRefused(fn()=>publishEnrollmentIllustrations([],[]));
imageRefused(fn()=>publishEnrollmentCanvas([],basicEnrollmentRevision(basicEnrollmentSteps()),[],[]));
echo "PASS: isolated College and Basic Education image rendering, restore, stale edits, validation, permissions and manager synchronization. Live content unchanged.\n";
