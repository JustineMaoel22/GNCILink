<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../admin/admin-functions.php';
require_once __DIR__.'/../includes/content-editor-drafts.php';
require_once __DIR__.'/../includes/content-submissions.php';
$db=getDB();$checks=0;
$db->exec('CREATE TEMPORARY TABLE content_editor_versions(scope_key VARCHAR(120) PRIMARY KEY,revision BIGINT DEFAULT 0,lease_owner CHAR(64) NULL,lease_until BIGINT DEFAULT 0)');
$db->exec('CREATE TEMPORARY TABLE content_editor_presence(peer_key CHAR(64) PRIMARY KEY)');
function submissionCheck(bool $ok,string $message): void {global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
function submissionRefused(callable $call,string $message): void {try{$call();}catch(RuntimeException $e){submissionCheck(true,$message);return;}throw new RuntimeException($message);}
// Approval records, checkpoints and logs are all connection-scoped temporary tables.
$db->exec('CREATE TEMPORARY TABLE content_editor_drafts(draft_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id BIGINT UNSIGNED,page_key CHAR(64),revision INT UNSIGNED,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY owner_page(user_id,page_key)) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE content_editor_draft_chunks(draft_id BIGINT UNSIGNED,chunk_no INT UNSIGNED,payload MEDIUMBLOB,PRIMARY KEY(draft_id,chunk_no)) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE rbac_submissions(submission_id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,kind VARCHAR(30),route VARCHAR(150),target_id INT NULL,title VARCHAR(500),status VARCHAR(20),revision INT DEFAULT 1,reason TEXT,reviewed_by INT NULL,reviewed_at DATETIME NULL,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE rbac_submission_chunks(submission_id INT,chunk_no INT,payload MEDIUMBLOB,PRIMARY KEY(submission_id,chunk_no)) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE activity_logs(log_id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,action VARCHAR(255),table_name VARCHAR(100),record_id INT,description TEXT,created_at DATETIME) ENGINE=InnoDB');
$_SESSION=['user_id'=>1,'user_role'=>'Content Editor'];
$page='home';$route='/';$key=$page.':'.$route;
$snapshot=['version'=>1,'state'=>['heading'=>'First version'],'preview'=>[]];
editorDraftSave($db,1,$key,0,json_encode($snapshot));
$first=editorDraftSubmitForReview($db,1,$page,$route,1);
submissionCheck(!$first['already_submitted'],'First click creates approval');
$again=editorDraftSubmitForReview($db,1,$page,$route,1);
submissionCheck($again['already_submitted']&&$again['submission_id']===$first['submission_id'],'Repeated click returns the same approval');
submissionCheck((int)$db->query('SELECT COUNT(*) FROM rbac_submissions')->fetchColumn()===1,'Repeated click creates no duplicate approval row');
submissionCheck((int)$db->query("SELECT COUNT(*) FROM activity_logs WHERE action='SUBMIT'")->fetchColumn()===1,'Repeated click creates no duplicate submission audit');
editorDraftSave($db,1,$key,1,json_encode($snapshot));
submissionCheck(editorDraftSubmitForReview($db,1,$page,$route,2)['submission_id']===$first['submission_id'],'Saving identical content again does not create another approval');
submissionRefused(fn()=>editorDraftSubmitForReview($db,1,$page,$route,1),'Stale checkpoint revision refused');
$snapshot['state']['heading']='Second version';
editorDraftSave($db,1,$key,2,json_encode($snapshot));
$changed=editorDraftSubmitForReview($db,1,$page,$route,3);
submissionCheck(!$changed['already_submitted']&&$changed['submission_id']!==$first['submission_id'],'Changed content can be submitted as a new version');
$_SESSION['user_id']=2;
editorDraftSave($db,2,$key,0,json_encode($snapshot));
$other=editorDraftSubmitForReview($db,2,$page,$route,1);
submissionCheck(!$other['already_submitted']&&$other['submission_id']!==$changed['submission_id'],'Different authors never share an approval request');
submissionRefused(fn()=>editorDraftSubmitForReview($db,1,$page,$route,3),'Another author checkpoint cannot be submitted');
submissionRefused(fn()=>editorDraftSubmitForReview($db,2,$page,$route,1,$first['submission_id']),'Another author review cannot be resubmitted');
$_SESSION['user_id']=1;
$otherRoute='/?section=separate';editorDraftSave($db,1,$page.':'.$otherRoute,0,json_encode($snapshot));
submissionRefused(fn()=>editorDraftSubmitForReview($db,1,$page,$otherRoute,1,$first['submission_id']),'Review cannot be redirected to a different page route');
$_SESSION['user_id']=3;$_SESSION['user_role']='The Mirror';
submissionRefused(fn()=>editorDraftSubmitForReview($db,3,$page,$route,1),'Mirror does not gain website editing permissions');
$_SESSION=['user_id'=>1,'user_role'=>'Content Editor'];
// New review feedback uses the existing explicit resubmission path and keeps its ID/history.
$id=$changed['submission_id'];$db->prepare("UPDATE rbac_submissions SET status='needs_revision',reason='Please revise',reviewed_at=NOW() WHERE submission_id=?")->execute([$id]);
$resubmitted=editorDraftSubmitForReview($db,1,$page,$route,3,$id);
submissionCheck(!$resubmitted['already_submitted']&&$resubmitted['submission_id']===$id,'Explicit revision resubmits the existing record');
submissionCheck(count(rbacReadPayload($id)['_review_history'])===1,'Resubmission preserves feedback history');
submissionCheck(editorDraftSubmitForReview($db,1,$page,$route,3,$id)['already_submitted'],'Duplicate resubmission is idempotent too');
$db->prepare("UPDATE rbac_submissions SET status='approved' WHERE submission_id=?")->execute([$id]);
submissionCheck(editorDraftSubmitForReview($db,1,$page,$route,3)['submission_id']===$id,'Late retry after approval cannot create a fresh duplicate');
// Pre-fix snapshots have no special idempotency metadata and are also recognized.
$legacy=['version'=>1,'state'=>['heading'=>'Legacy snapshot'],'preview'=>[]];
editorDraftSave($db,1,'history:/pages/about/gnc-history.php',0,json_encode($legacy));
$legacyId=rbacSaveSubmission('snapshot','/admin/content-edit.php','Page changes: history',['page'=>'history','route'=>'/pages/about/gnc-history.php','snapshot'=>$legacy]);
submissionCheck(editorDraftSubmitForReview($db,1,'history','/pages/about/gnc-history.php',1)['submission_id']===$legacyId,'Existing pending snapshots are recognized without migration');
$db->exec("UPDATE content_editor_versions SET revision=revision+1 WHERE scope_key='history'");
submissionRefused(fn()=>editorDraftSubmitForReview($db,1,'history','/pages/about/gnc-history.php',1),'Fresh preview cannot submit an outdated saved checkpoint');
echo "$checks draft submission checks passed. No persistent data changed.\n";
