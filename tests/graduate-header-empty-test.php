<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../admin/admin-functions.php';
$mode=$argv[1]??'public';
if(!in_array($mode,['public','preview','filled'],true))throw new InvalidArgumentException('Invalid test mode.');
$_SERVER['DOCUMENT_ROOT']=dirname(__DIR__);
$_SERVER['PHP_SELF']='/pages/academics/graduate-school/graduate-school.php';
$_SERVER['REQUEST_URI']=$_SERVER['PHP_SELF'];
initSession();
$db=getDB();
$admin=$db->query("SELECT user_id,credential_version FROM users WHERE role='Super Admin' AND status='active' AND must_change_password=0 LIMIT 1")->fetch();
if(!$admin)throw new RuntimeException('An active Super Admin is needed.');
$_SESSION=['user_id'=>(int)$admin['user_id'],'user_role'=>'Super Admin','credential_version'=>(int)$admin['credential_version'],'_initiated'=>true,'last_activity'=>time()];
$current=getDepartmentBySlug('graduate-school');
if(!$current)throw new RuntimeException('Graduate School department is not installed.');
$db->beginTransaction();
try{
    $saved=saveDepartmentDetails(array_merge($current,['hero_text'=>$mode==='filled'?'Custom graduate header line':'','description'=>'Summary must not replace an empty graduate header.']));
    if($mode!=='filled'&&$saved['hero_text']!==null)throw new RuntimeException('The save path must accept an empty header.');
    $_GET=['slug'=>'graduate-school'];
    if($mode==='preview')$_GET['ce_preview']='1';
    ob_start();require __DIR__.'/../components/department-page.php';$html=ob_get_clean();
    preg_match('~<p\b[^>]*class="dept-hero-blurb"[^>]*>(.*?)</p>~s',$html,$match);
    if($mode==='public'&&$match)throw new RuntimeException('Public page regenerated the empty header description.');
    if($mode==='preview'&&(!$match||trim(strip_tags($match[1]))!==''||!str_contains($match[0],'data-warning-allow-empty="true"')))throw new RuntimeException('Empty optional description must remain editable without blocking publishing.');
    if($mode==='filled'&&trim(strip_tags($match[1]??''))!=='Custom graduate header line')throw new RuntimeException('Nonempty header content changed.');
    echo "PASS: Graduate School $mode header save/render behavior; all database changes rolled back.\n";
}finally{if($db->inTransaction())$db->rollBack();}
