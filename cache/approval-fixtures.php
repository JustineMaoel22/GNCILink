<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../config/config.php';
require __DIR__.'/../admin/admin-functions.php';
require __DIR__.'/../includes/content-submissions.php';
$db=getDB();
if(($argv[1]??'')==='destroy'){
    $ids=json_decode($argv[2]??'[]',true,32,JSON_THROW_ON_ERROR);
    $db->beginTransaction();
    try{
        foreach($ids as $id){
            $q=$db->prepare("SELECT submission_id FROM rbac_submissions WHERE submission_id=? AND title LIKE 'GNCI approval QA %' FOR UPDATE");$q->execute([(int)$id]);
            if(!$q->fetchColumn())continue;
            $db->prepare('DELETE FROM rbac_submission_chunks WHERE submission_id=?')->execute([(int)$id]);
            $db->prepare('DELETE FROM rbac_submissions WHERE submission_id=?')->execute([(int)$id]);
        }
        $db->commit();exit;
    }catch(Throwable $e){$db->rollBack();throw $e;}
}
$author=$db->query("SELECT user_id FROM users WHERE role='Content Editor' AND status='active' AND must_change_password=0 LIMIT 1")->fetchColumn();
if(!$author)throw new RuntimeException('Local editor account needed.');
$target=$db->query('SELECT news_id FROM news ORDER BY news_id LIMIT 1')->fetchColumn();
$ids=[];$db->beginTransaction();
try{
    foreach(['pending','needs_revision','rejected','approved','draft','uncertain'] as $state){
        $q=$db->prepare('INSERT INTO rbac_submissions(user_id,kind,route,target_id,title,status,revision,reason) VALUES(?,?,?,?,?,?,?,?)');
        $q->execute([$author,'request','/admin/action/create-news.php',$target?:null,'GNCI approval QA '.$state.' <script>test</script>',$state,2,in_array($state,['needs_revision','rejected'],true)?'Please verify the date with the school office.':null]);
        $id=(int)$db->lastInsertId();$ids[]=$id;
        rbacWritePayload($id,['post'=>['action'=>$target?'update':'create','title'=>'Reviewed school news','content'=>'<p>Proposed school announcement for review.</p><script>alert("unsafe")</script>','excerpt'=>'Updated introduction','access_token'=>'QA-should-not-appear'],'files'=>['featured_image'=>['name'=>'qa-poster.png','mime'=>'image/png','data'=>'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aN1sAAAAASUVORK5CYII=']], '_review_history'=>[['status'=>'needs_revision','revision'=>1,'feedback'=>'Earlier feedback: clarify the announcement.','at'=>'2026-11-01 10:00:00']]]);
    }
    $q=$db->prepare("INSERT INTO rbac_submissions(user_id,kind,route,title,status) VALUES(?,'snapshot','/admin/content-edit.php','GNCI approval QA page changes','pending')");$q->execute([$author]);$id=(int)$db->lastInsertId();$ids[]=$id;
    rbacWritePayload($id,['page'=>'home','route'=>'/','snapshot'=>['version'=>1,'state'=>[],'preview'=>[]]]);
    $db->commit();echo json_encode(['ids'=>$ids,'author'=>(int)$author]);
}catch(Throwable $e){$db->rollBack();throw $e;}
