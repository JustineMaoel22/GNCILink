<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../admin/admin-functions.php';
$mode=$argv[1]??'preview';
if(!in_array($mode,['preview','empty','vision','both'],true))throw new InvalidArgumentException('Invalid test mode.');
$_SERVER['DOCUMENT_ROOT']=dirname(__DIR__);
$_SERVER['PHP_SELF']='/pages/academics/graduate-school/graduate-school.php';
$_SERVER['REQUEST_URI']=$_SERVER['PHP_SELF'];
initSession();$db=getDB();
$admin=$db->query("SELECT user_id,credential_version FROM users WHERE role='Super Admin' AND status='active' AND must_change_password=0 LIMIT 1")->fetch();
if(!$admin)throw new RuntimeException('An active Super Admin is needed.');
$_SESSION=['user_id'=>(int)$admin['user_id'],'user_role'=>'Super Admin','credential_version'=>(int)$admin['credential_version'],'_initiated'=>true,'last_activity'=>time()];
$dept=getDepartmentBySlug('graduate-school');if(!$dept)throw new RuntimeException('Graduate School is not installed.');
$db->beginTransaction();
try{
    saveDepartmentPageContent((int)$dept['dept_id'],['vision_title'=>'Graduate vision','mission_title'=>'Graduate mission','vision_text'=>in_array($mode,['vision','both'],true)?'<p><strong>Advanced learning</strong> and research.</p>':'','mission_text'=>$mode==='both'?'<p>Serve our communities.</p><script>unsafe()</script>':'','vision_icon'=>'bi:bullseye','mission_icon'=>'material:school']);
    $_GET=['slug'=>'graduate-school'];if($mode==='preview')$_GET['ce_preview']='1';
    ob_start();require __DIR__.'/../components/department-page.php';$html=ob_get_clean();
    $count=substr_count($html,'class="dept-statement-card"');$expected=match($mode){'preview','both'=>2,'vision'=>1,default=>0};
    if($count!==$expected)throw new RuntimeException('Wrong statement visibility: '.$mode);
    if($mode==='preview')foreach(['vision_title','vision_text','mission_title','mission_text'] as $key)if(!str_contains($html,'data-ve-key="'.$key.'"'))throw new RuntimeException('Missing editable field '.$key);
    if(in_array($mode,['vision','both'],true)&&(!str_contains($html,'<strong>Advanced learning</strong>')||!str_contains($html,'bi-bullseye')))throw new RuntimeException('Saved formatting or icon missing.');
    if($mode==='both'&&(!str_contains($html,'Serve our communities.')||str_contains($html,'unsafe()')))throw new RuntimeException('Mission content was not rendered safely.');
    echo "PASS: Graduate School $mode statements, editability, optional visibility and safe rendering; writes rolled back.\n";
}finally{if($db->inTransaction())$db->rollBack();}
