<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../admin/admin-functions.php';
require_once __DIR__.'/../includes/content-submissions.php';
require_once __DIR__.'/../admin/approval-view.php';
$checks=0;
function approvalCheck(bool $condition,string $message): void {
    global $checks;if(!$condition)throw new RuntimeException($message);$checks++;
}
function approvalRefused(callable $call,string $message): void {
    try{$call();}catch(RuntimeException|InvalidArgumentException $e){approvalCheck(true,$message);return;}
    throw new RuntimeException($message);
}
$db=getDB();
// Only connection-scoped temporary tables are mutated. Real records are untouched.
$db->exec('CREATE TEMPORARY TABLE rbac_submissions (submission_id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,kind VARCHAR(30),route VARCHAR(150),target_id INT NULL,title VARCHAR(500),status VARCHAR(20),revision INT DEFAULT 1,reason TEXT,reviewed_by INT NULL,reviewed_at DATETIME NULL,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE rbac_submission_chunks (submission_id INT,chunk_no INT,payload MEDIUMBLOB,PRIMARY KEY(submission_id,chunk_no)) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE activity_logs (log_id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,action VARCHAR(255),table_name VARCHAR(100),record_id INT,description TEXT,created_at DATETIME) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE users (user_id INT PRIMARY KEY,first_name VARCHAR(100),last_name VARCHAR(100))');
$db->exec("INSERT INTO users VALUES(1,'Juan','Cruz'),(2,'Maria','Santos')");
$db->exec('CREATE TEMPORARY TABLE pages(page_id INT PRIMARY KEY,slug VARCHAR(100),title VARCHAR(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci)');
$db->exec('CREATE TEMPORARY TABLE page_sections(section_id INT PRIMARY KEY,page_id INT,section_key VARCHAR(100),title VARCHAR(255),content TEXT,draft_content TEXT,status VARCHAR(20),updated_by INT,updated_at DATETIME,review_status VARCHAR(20),submitted_by INT,submitted_at DATETIME,reviewed_by INT NULL,reviewed_at DATETIME NULL,review_note TEXT) ENGINE=InnoDB');
$insert=$db->prepare('INSERT INTO rbac_submissions(submission_id,user_id,kind,route,title,status,revision,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)');
for($i=1;$i<=135;$i++)$insert->execute([$i,$i%2?1:2,'request','/admin/action/create-news.php','News proposal '.$i,$i<=115?'pending':'approved',1,'2026-10-01 12:00:00','2026-10-01 12:00:00']);
$insert->execute([136,2,'snapshot','/admin/content-edit.php','History page 100%_complete!','needs_revision',2,'2026-11-04 23:59:59','2026-11-04 23:59:59']);
$run=function(array $input=[],bool $super=true,int $user=1)use($db){$f=approvalFilters($input);return [approvalQueue($db,$f,$super,$user),$f];};
[$r,$f]=$run();approvalCheck($r['total']===136&&count($r['rows'])===25&&(int)$r['rows'][0]['submission_id']===136,'Newest first by default');
approvalCheck((int)$r['counts']['pending']===115&&(int)$r['counts']['approved']===20,'Global summary counts');
[$r,$f]=$run(['size'=>'100','page'=>'2']);approvalCheck(count($r['rows'])===36,'Pagination beyond previous 100-row limit');
[$r,$f]=$run(['page'=>'99999','size'=>'10']);approvalCheck($f['page']===14&&count($r['rows'])===6,'Clamp page');
[$r]=$run(['sort'=>'priority']);approvalCheck((int)$r['rows'][0]['submission_id']===1,'Explicit pending oldest first');
[$r]=$run(['sort'=>'newest']);approvalCheck((int)$r['rows'][0]['submission_id']===136,'Newest order');
[$r]=$run(['q'=>'Juan Cruz']);approvalCheck($r['total']===68,'Search full author name');
[$r]=$run(['q'=>'Page content']);approvalCheck($r['total']===1,'Search readable content type');
[$r]=$run(['q'=>'100%_complete!']);approvalCheck($r['total']===1,'Literal wildcard search');
[$r]=$run(['q'=>"' OR 1=1 --"]);approvalCheck($r['total']===0,'SQL injection stays literal');
[$r]=$run(['status'=>'needs_revision','type'=>'pages','author'=>'2','from'=>'2026-11-04','to'=>'2026-11-04']);approvalCheck($r['total']===1,'Combined filters and inclusive end date');
[$r]=$run(['author'=>'2'],false,1);approvalCheck($r['total']===0&&!in_array(2,array_column($r['authors'],'user_id')),'Ownership restricts rows, counts and authors');
$f=approvalFilters(['q'=>[],'id'=>[],'from'=>"2026-01\0-01",'to'=>'2026-02-30','sort'=>'x','size'=>'11','status'=>'<script>']);approvalCheck($f['q']===''&&$f['from']===''&&$f['to']===''&&$f['sort']==='newest'&&$f['size']===25&&$f['status']==='','Malformed inputs');
$fields=approvalFields(['title'=>'<b>Safe</b>','content'=>"<p>One</p><p>Two</p>",'csrf_token'=>'secret','access_token'=>'secret','news_id'=>123,'_internal'=>'secret']);
approvalCheck(count($fields)===2&&$fields['title']==='Safe'&&str_contains($fields['content'],"One\n"),'Preview strips markup and transport fields');
$_SESSION=['user_id'=>1,'user_role'=>'Super Admin'];
rbacWritePayload(1,['post'=>['title'=>'Proposal'],'files'=>[]]);
approvalRefused(fn()=>rbacReviewDecision(1,1,'','needs_revision'),'Feedback required');
approvalRefused(fn()=>rbacReviewDecision(1,2,'Fix title','needs_revision'),'Stale revision refused');
rbacReviewDecision(1,1,'Please update the title.','needs_revision');
$row=rbacGetSubmission(1);approvalCheck($row['status']==='needs_revision'&&$row['reason']==='Please update the title.','Distinct revision state');
approvalCheck(count(rbacReadPayload(1)['_review_history'])===1,'Feedback persisted');
approvalRefused(fn()=>rbacReviewDecision(1,1,'Duplicate','rejected'),'Repeat decision refused');
$_SESSION['user_role']='Content Editor';
approvalRefused(fn()=>rbacReviewDecision(2,1,'Reject','rejected'),'Editor cannot review');
approvalRefused(fn()=>rbacApproveRequest(1,1),'Editor cannot approve');
$_SESSION['user_id']=2;
approvalRefused(fn()=>rbacSubmitSaved(1,1),'Other author cannot resubmit');
$_SESSION['user_id']=1;
rbacSubmitSaved(1,1);$row=rbacGetSubmission(1);approvalCheck($row['status']==='pending_approval'&&(int)$row['revision']===2,'Resubmit increments revision');
approvalCheck(count(rbacReadPayload(1)['_review_history'])===1,'Resubmit retains feedback');
approvalRefused(fn()=>rbacSubmitSaved(1,1),'Duplicate resubmit refused');
$id=rbacSaveSubmission('request','/admin/action/create-news.php','Edited proposal',['post'=>['title'=>'Revised title'],'files'=>[]]);
$_SESSION['user_role']='Super Admin';rbacRejectSubmission($id,1,'Not suitable.');
approvalCheck(rbacGetSubmission($id)['status']==='rejected','Reject differs from revision');
$_SESSION['user_role']='Content Editor';
rbacSaveSubmission('request','/admin/action/create-news.php','Revised proposal',['post'=>['title'=>'New version'],'files'=>[]],0,$id);
approvalCheck(count(rbacReadPayload($id)['_review_history'])===1&&(int)rbacGetSubmission($id)['revision']===2,'Saving a new version retains feedback');
$_SESSION['user_role']='Super Admin';$executions=0;
$executor=function()use(&$executions){$executions++;return ['state'=>'approved','message'=>'Approved fixture'];};
rbacApproveRequest($id,2,$executor);
approvalRefused(fn()=>rbacApproveRequest($id,2,$executor),'Duplicate approval refused');
approvalCheck($executions===1&&rbacGetSubmission($id)['status']==='approved','Approve executes once');
// Page-text IDs can overlap proposal IDs without sharing a review target.
$db->exec("INSERT INTO pages VALUES(1,'history','School History')");
$db->exec("INSERT INTO page_sections VALUES(1,1,'intro','Introduction','Live text','Proposed text','published',2,'2026-09-01 10:00:00','pending',2,'2026-09-01 10:00:00',NULL,NULL,NULL),(2,1,'summary','Summary','Live summary','Draft summary','published',1,'2026-09-02 10:00:00','rejected',1,'2026-09-02 10:00:00',1,'2026-09-03 10:00:00','Please revise')");
[$r]=$run(['status'=>'pending','type'=>'pages']);
approvalCheck($r['total']===1&&$r['rows'][0]['record_source']==='section'&&(int)$r['rows'][0]['submission_id']===1,'Page text shares the filtered queue without ID collisions');
[$r]=$run(['q'=>'School History','size'=>'10','sort'=>'priority']);
approvalCheck($r['total']===2&&$r['rows'][0]['status']==='pending','Page text is searchable and pending has priority');
[$r]=$run(['q'=>'School History'],false,1);
approvalCheck($r['total']===1&&(int)$r['rows'][0]['user_id']===1,'Page-text queue respects ownership');
approvalCheck(approvalSection($db,1,false,1)===null,'Another author cannot read a section review');
$version=approvalSectionVersion(approvalSection($db,1,true,1));
$row=approvalSection($db,1,true,1);$super=true;$csrfToken='preview-only-token';$filters=approvalFilters([]);
$e=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
$reviewUrl=static fn($changes=[])=>'/admin/submissions.php?'.http_build_query($changes);
ob_start();require __DIR__.'/../admin/partials/section-review.php';$reviewHtml=ob_get_clean();
approvalCheck(str_contains($reviewHtml,'name="record_source" value="section"')&&str_contains($reviewHtml,'Current published text')&&str_contains($reviewHtml,'Proposed text'),'Page text renders in the shared review panel');
if(in_array('--preview',$argv,true))file_put_contents(__DIR__.'/../cache/unified-section-preview.html',$reviewHtml);
approvalRefused(fn()=>approvalSectionDecision($db,1,'stale','approve',''),'Stale page text cannot be approved');
$_SESSION['user_role']='Content Editor';
approvalRefused(fn()=>approvalSectionDecision($db,1,$version,'approve',''),'Editor cannot approve page text');
$_SESSION['user_role']='Super Admin';
approvalRefused(fn()=>approvalSectionDecision($db,1,$version,'request_revision',''),'Page-text feedback is required');
approvalSectionDecision($db,1,$version,'request_revision','Clarify this introduction.');
$section=approvalSection($db,1,true,1);
approvalCheck($section['content']==='Live text'&&$section['review_status']==='rejected','Request changes preserves published text');
$db->exec("UPDATE page_sections SET review_status='pending',submitted_at='2026-09-04 10:00:00' WHERE section_id=1");
$version=approvalSectionVersion(approvalSection($db,1,true,1));
approvalSectionDecision($db,1,$version,'approve','');
$section=approvalSection($db,1,true,1);
approvalCheck($section['content']==='Proposed text'&&$section['draft_content']===null,'Unified review publishes the section draft');
approvalRefused(fn()=>approvalSectionDecision($db,1,$version,'approve',''),'Duplicate page-text approval refused');
[$r]=$run(['q'=>'School History','status'=>'approved']);
approvalCheck($r['total']===1,'Approved page text appears in combined results');
$db->exec("UPDATE page_sections SET review_status='none',draft_content=NULL WHERE section_id=2");
[$r]=$run(['q'=>'School History','status'=>'approved']);
approvalCheck($r['total']===1,'Discarded page text is not labelled approved');
$actions=$db->query('SELECT action FROM activity_logs')->fetchAll(PDO::FETCH_COLUMN);
approvalCheck(!array_diff(['REQUEST_REVISION','REJECT','RESUBMIT','APPROVE','PUBLISH'],$actions),'Review and publish audit events');
// Private article drafts share storage but never enter submission lists or review access.
foreach(['The Mirror','Content Editor'] as $role){
foreach(['/admin/action/create-news.php','/admin/action/create-announcement.php'] as $route){
    $_SESSION=['user_id'=>1,'user_role'=>$role];
    approvalCheck(rbacRequestSaveStatus($route,['action'=>'create'])==='draft','Missing article intent defaults to private draft');
    approvalCheck(rbacRequestSaveStatus($route,['action'=>'create','status'=>'draft'])==='draft','Explicit draft intent');
    approvalCheck(rbacRequestSaveStatus($route,['action'=>'update','status'=>'pending'])==='pending_approval','Explicit submission uses pending_approval');
    approvalRefused(fn()=>rbacRequestSaveStatus($route,['action'=>'create','status'=>'published']),'Non-publisher cannot publish by forging intent');
    $payload=['post'=>['action'=>'create','status'=>'draft','title'=>'Private fixture','content'=>'Draft body'],'files'=>['image'=>['name'=>'draft.png','mime'=>'image/png','data'=>base64_encode('fixture')]]];
    $draft=rbacSaveSubmission('request',$route,'Private fixture',$payload,0,0,'draft');
    for($rev=1;$rev<=3;$rev++){
        $payload['post']['content']='Edit '.$rev;
        rbacSaveSubmission('request',$route,'Private fixture',$payload,0,$draft,'draft',$rev);
        approvalCheck(rbacGetSubmission($draft)['status']==='draft','Repeated save stays private');
    }
    approvalRefused(fn()=>rbacSaveSubmission('request',$route,'Stale',$payload,0,$draft,'draft',1),'Stale draft update refused');
    approvalCheck(rbacReadPayload($draft)['post']['content']==='Edit 3'&&isset(rbacReadPayload($draft)['files']['image']),'Latest content and attachment retained');
    foreach([true,false] as $super){
        [$r]=$run(['q'=>'Private fixture'],$super,1);
        approvalCheck($r['total']===0&&!isset($r['counts']['draft']),'Drafts excluded from submissions, counts and approvals');
        [$r]=$run(['status'=>'draft'],$super,1);
        approvalCheck($r['total']===0,'Status filter cannot leak drafts');
        [$r]=$run(['q'=>'Private fixture','view'=>'drafts'],$super,2);
        approvalCheck($r['total']===0,'Drafts private even from another Super Admin');
    }
    [$r]=$run(['q'=>'Private fixture','view'=>'drafts'],false,1);
    approvalCheck($r['total']===1,'Owner can find saved draft in Drafts');
    $row=rbacGetSubmission($draft);
    approvalCheck(!rbacSubmissionVisible($row,true,2,true)&&!rbacSubmissionVisible($row,true,1)&&rbacSubmissionVisible($row,false,1,true),'Direct review and media access respect private draft scope');
    $_SESSION['user_role']='Super Admin';
    approvalRefused(fn()=>rbacApproveRequest($draft,4,fn()=>['state'=>'approved','message'=>'Unexpected']),'Private draft cannot be approved');
    $_SESSION=['user_id'=>2,'user_role'=>$role];
    approvalRefused(fn()=>rbacSubmitSaved($draft,4),'Other owner cannot submit draft');
    approvalRefused(fn()=>rbacSaveSubmission('request',$route,'Stolen',$payload,0,$draft,'draft',4),'Other owner cannot update draft');
    $_SESSION['user_id']=1;
    rbacSubmitSaved($draft,4);
    approvalCheck(rbacGetSubmission($draft)['status']==='pending_approval'&&rbacReadPayload($draft)['post']['status']==='pending_approval','Only explicit submission transitions status');
    approvalRefused(fn()=>rbacSubmitSaved($draft,4),'Duplicate submission refused');
    [$r]=$run(['q'=>'Private fixture','view'=>'drafts'],false,1);
    approvalCheck($r['total']===0,'Submitted article leaves Drafts');
    [$r]=$run(['q'=>'Private fixture'],true,2);
    approvalCheck($r['total']===1&&$r['rows'][0]['status']==='pending','Submitted article enters compatible approval queue');
    $_SESSION=['user_id'=>2,'user_role'=>'Super Admin'];
    rbacApproveRequest($draft,5,fn()=>['state'=>'approved','message'=>'Approved article fixture']);
    approvalCheck(rbacGetSubmission($draft)['status']==='approved','New pending_approval state can be approved');
    $db->prepare('DELETE FROM rbac_submissions WHERE submission_id=?')->execute([$draft]);
}
}

// Published records are shadowed only on this test connection; no live data is edited.
require_once __DIR__.'/../includes/admin-notifications.php';
foreach(['news'=>'news_id','announcements'=>'announcement_id'] as $table=>$key){
    $db->exec("CREATE TEMPORARY TABLE $table ($key INT PRIMARY KEY,user_id INT,title VARCHAR(500),content TEXT,status VARCHAR(20)) ENGINE=InnoDB");
}
foreach(['The Mirror','Content Editor'] as $role){
    foreach(['news'=>'news','announcements'=>'announcement'] as $table=>$kind){
        $key=$kind.'_id';$route='/admin/action/create-'.$kind.'.php';
        $_SESSION=['user_id'=>1,'user_role'=>$role];
        $db->exec("DELETE FROM $table");
        // Editors may propose changes to other authors' published content; Mirror remains owner-only.
        $owner=$role==='Content Editor'?2:1;
        $db->prepare("INSERT INTO $table VALUES(1,?,'Published fixture','Original published text','published')")->execute([$owner]);
        $readOriginal=fn()=>$db->query("SELECT * FROM $table WHERE $key=1")->fetch(PDO::FETCH_ASSOC);
        $original=$readOriginal();
        $post=['action'=>'update',$key=>1,'title'=>'Private published edit','content'=>'Proposed edit','status'=>'draft'];
        approvalCheck(rbacValidatePostOwnership($route,$post)===1,'Published edit respects role permissions');
        $draft=rbacSaveSubmission('request',$route,$post['title'],['post'=>$post,'files'=>[]],1,0,rbacRequestSaveStatus($route,$post));
        approvalCheck($readOriginal()===$original,'Saving edit as draft preserves published original');
        $post['content']='Revised proposed edit';
        rbacSaveSubmission('request',$route,$post['title'],['post'=>$post,'files'=>[]],1,$draft,'draft',1);
        approvalCheck($readOriginal()===$original,'Repeated edit saves preserve published original');
        $_SESSION['user_role']='Super Admin';
        $notices=adminNotifications();
        approvalCheck(!$notices['unavailable'],'Notification reads remain available');
        approvalCheck(!in_array('/admin/submissions.php?id='.$draft,array_column($notices['items'],'url'),true),'Saving private edits generates no approval notification');
        $_SESSION['user_role']=$role;
        rbacSubmitSaved($draft,2);
        approvalCheck(rbacGetSubmission($draft)['status']==='pending_approval'&&$readOriginal()===$original,'Explicit submission queues edited version without changing original');
        $_SESSION=['user_id'=>2,'user_role'=>'Super Admin'];
        approvalCheck(in_array('/admin/submissions.php?id='.$draft,array_column(adminNotifications()['items'],'url'),true),'Only explicit submission generates approval notification');
        approvalRefused(fn()=>rbacApproveRequest($draft,2),'Stale review cannot publish proposed edit');
        approvalCheck($readOriginal()===$original,'Refused review leaves original unchanged');
        // The real approval boundary must authorize the executor before it can write.
        rbacApproveRequest($draft,3,function($row,$payload)use($db,$table,$key){
            approvalCheck($row['status']==='pending_approval'&&$payload['post']['status']==='pending_approval','Executor receives explicitly submitted edit');
            $db->prepare("UPDATE $table SET content=? WHERE $key=?")->execute([$payload['post']['content'],$row['target_id']]);
            return ['state'=>'approved','message'=>'Approved temporary published-edit fixture'];
        });
        approvalCheck($readOriginal()['content']==='Revised proposed edit'&&$readOriginal()['status']==='published','Approved edit replaces only temporary published content');
        $db->prepare('DELETE FROM rbac_submissions WHERE submission_id=?')->execute([$draft]);
    }
}

echo "$checks approval checks passed. No persistent data changed.\n";
