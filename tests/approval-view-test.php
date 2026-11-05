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
$insert=$db->prepare('INSERT INTO rbac_submissions(submission_id,user_id,kind,route,title,status,revision,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?)');
for($i=1;$i<=135;$i++)$insert->execute([$i,$i%2?1:2,'request','/admin/action/create-news.php','News proposal '.$i,$i<=115?'pending':'approved',1,'2026-10-01 12:00:00','2026-10-01 12:00:00']);
$insert->execute([136,2,'snapshot','/admin/content-edit.php','History page 100%_complete!','needs_revision',2,'2026-11-04 23:59:59','2026-11-04 23:59:59']);
$run=function(array $input=[],bool $super=true,int $user=1)use($db){$f=approvalFilters($input);return [approvalQueue($db,$f,$super,$user),$f];};
[$r,$f]=$run();approvalCheck($r['total']===136&&count($r['rows'])===25&&(int)$r['rows'][0]['submission_id']===1,'Pending oldest first');
approvalCheck((int)$r['counts']['pending']===115&&(int)$r['counts']['approved']===20,'Global summary counts');
[$r,$f]=$run(['size'=>'100','page'=>'2']);approvalCheck(count($r['rows'])===36,'Pagination beyond previous 100-row limit');
[$r,$f]=$run(['page'=>'99999','size'=>'10']);approvalCheck($f['page']===14&&count($r['rows'])===6,'Clamp page');
[$r]=$run(['sort'=>'newest']);approvalCheck((int)$r['rows'][0]['submission_id']===136,'Newest order');
[$r]=$run(['q'=>'Juan Cruz']);approvalCheck($r['total']===68,'Search full author name');
[$r]=$run(['q'=>'Page content']);approvalCheck($r['total']===1,'Search readable content type');
[$r]=$run(['q'=>'100%_complete!']);approvalCheck($r['total']===1,'Literal wildcard search');
[$r]=$run(['q'=>"' OR 1=1 --"]);approvalCheck($r['total']===0,'SQL injection stays literal');
[$r]=$run(['status'=>'needs_revision','type'=>'pages','author'=>'2','from'=>'2026-11-04','to'=>'2026-11-04']);approvalCheck($r['total']===1,'Combined filters and inclusive end date');
[$r]=$run(['author'=>'2'],false,1);approvalCheck($r['total']===0&&!in_array(2,array_column($r['authors'],'user_id')),'Ownership restricts rows, counts and authors');
$f=approvalFilters(['q'=>[],'id'=>[],'from'=>"2026-01\0-01",'to'=>'2026-02-30','sort'=>'x','size'=>'11','status'=>'<script>']);approvalCheck($f['q']===''&&$f['from']===''&&$f['to']===''&&$f['sort']==='priority'&&$f['size']===25&&$f['status']==='','Malformed inputs');
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
rbacSubmitSaved(1,1);$row=rbacGetSubmission(1);approvalCheck($row['status']==='pending'&&(int)$row['revision']===2,'Resubmit increments revision');
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
$actions=$db->query('SELECT action FROM activity_logs')->fetchAll(PDO::FETCH_COLUMN);
approvalCheck(!array_diff(['REQUEST_REVISION','REJECT','RESUBMIT','APPROVE','PUBLISH'],$actions),'Review and publish audit events');
echo "$checks approval checks passed. No persistent data changed.\n";
