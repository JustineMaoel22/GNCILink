<?php
/** Pending proposals are isolated from all public content tables. */
const RBAC_PAYLOAD_LIMIT = 100663296;
function rbacSubmissionLabel(array $submission,array $payload): string {
    if(($submission['kind']??'')==='snapshot')return 'Website content changes';
    $type=match($submission['route']??''){
        '/admin/action/create-news.php'=>'news article',
        '/admin/action/create-announcement.php'=>'announcement',
        '/admin/events.php'=>'event',
        default=>null,
    };
    if($type===null)return ($submission['route']??'')==='/admin/settings.php'?'Website settings changes':'Website content changes';
    $action=$payload['post']['action']??$payload['post']['ajax_action']??'';
    $prefix=match($action){
        'create'=>'New',
        'update','edit'=>'Edited',
        'delete'=>'Delete',
        'archive'=>'Archive',
        'restore','unarchive'=>'Restore',
        default=>'Changes to',
    };
    return $prefix.' '.$type;
}
function rbacSubmissionsReady(): bool {
    try { getDB()->query('SELECT submission_id FROM rbac_submissions LIMIT 1');getDB()->query('SELECT submission_id FROM rbac_submission_chunks LIMIT 1');return true; }
    catch (PDOException $e) { return false; }
}
function rbacContentRoutes(): array {
    return ['/admin/action/create-announcement.php','/admin/action/create-news.php','/admin/events.php',
        '/admin/action/page-content-handler.php','/admin/action/page-review-handler.php',
        '/admin/action/program-page-handler.php','/admin/action/admission-category-handler.php',
        '/admin/action/athletics-sports-handler.php','/admin/action/community-activities-handler.php',
        '/admin/action/enrollment-canvas-handler.php','/admin/action/enrollment-illustrations-handler.php',
        '/admin/action/faqs-handler.php','/admin/action/image-framing-handler.php',
        '/admin/action/payment-guide-handler.php','/admin/action/scholarship-handler.php','/admin/action/student-achievements-handler.php',
        '/admin/action/student-organizations-handler.php','/admin/action/text-layout-handler.php',
        '/admin/action/tuition-fees-handler.php','/admin/settings.php'];
}
function rbacShouldQueue(string $route,array $post): bool {
    if (!in_array($route,rbacContentRoutes(),true)) return false;
    $action=$post['action']??$post['ajax_action']??'';
    // These are read-only lookups; all other POSTs to content handlers are proposals.
    return !in_array($action,['list','get','fetch_one','load','upload_environment','categories','get_content'],true);
}
function rbacGetSubmission(int $id): ?array {
    $q=getDB()->prepare('SELECT * FROM rbac_submissions WHERE submission_id=?');$q->execute([$id]);return $q->fetch()?:null;
}
function rbacReadPayload(int $id): array {
    $q=getDB()->prepare('SELECT payload FROM rbac_submission_chunks WHERE submission_id=? ORDER BY chunk_no');$q->execute([$id]);$raw='';
    while(($part=$q->fetchColumn())!==false){$raw.=$part;if(strlen($raw)>RBAC_PAYLOAD_LIMIT)throw new RuntimeException('Submission is too large.');}
    return json_decode($raw,true,64,JSON_THROW_ON_ERROR);
}
function rbacWritePayload(int $id,array $payload): void {
    $raw=json_encode($payload,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE);
    if(strlen($raw)>RBAC_PAYLOAD_LIMIT)throw new LengthException('This submission is too large. Submit fewer files at a time.');
    $db=getDB();$db->prepare('DELETE FROM rbac_submission_chunks WHERE submission_id=?')->execute([$id]);
    $insert=$db->prepare('INSERT INTO rbac_submission_chunks VALUES(?,?,?)');
    for($i=0,$offset=0;$offset<strlen($raw);$i++,$offset+=262144)$insert->execute([$id,$i,substr($raw,$offset,262144)]);
}
function rbacSafePreviewRoute(string $route): bool {
    $path=rawurldecode((string)parse_url($route,PHP_URL_PATH));
    return str_starts_with($route,'/')&&!str_starts_with($route,'//')&&!preg_match('/[\x00-\x20\\\\]/',$route)
        && !in_array('..',explode('/',$path),true)
        && (in_array($path,['/','/index.php'],true)||str_starts_with($path,'/pages/')||str_starts_with($path,'/college/'));
}
function rbacRevisionFormData(string $route): array {
    $id=(int)($_GET['revise']??0);if(!$id)return [];
    $row=rbacGetSubmission($id);
    if(!$row||(int)$row['user_id']!==(int)$_SESSION['user_id']||$row['route']!==$route||!in_array($row['status'],['draft','rejected','failed'],true))rbacFail('You cannot revise this submission.');
    return rbacReadPayload($id)['post']??[];
}
function rbacSaveSubmission(string $kind,string $route,string $title,array $payload,int $target=0,int $resubmit=0,string $status='pending'): int {
    if(!rbacSubmissionsReady())throw new RuntimeException('Approval storage is not set up. Ask the developer to import sql/rbac-migration.sql.');
    if(!in_array($status,['draft','pending'],true))throw new InvalidArgumentException('Invalid submission status.');
    $db=getDB();$db->beginTransaction();
    try {
        if($resubmit){
            $q=$db->prepare('SELECT * FROM rbac_submissions WHERE submission_id=? FOR UPDATE');$q->execute([$resubmit]);$old=$q->fetch();
            if(!$old||(int)$old['user_id']!==(int)$_SESSION['user_id']||$old['route']!==$route||(int)($old['target_id']??0)!==$target||!in_array($old['status'],['draft','rejected','failed'],true))throw new RuntimeException('This submission cannot be revised by your account.');
            $db->prepare('UPDATE rbac_submissions SET title=?,status=?,reason=NULL,reviewed_by=NULL,reviewed_at=NULL,revision=revision+1,updated_at=NOW() WHERE submission_id=?')->execute([mb_substr($title,0,500),$status,$resubmit]);$id=$resubmit;
        }else{
            $db->prepare('INSERT INTO rbac_submissions(user_id,kind,route,target_id,title,status) VALUES(?,?,?,?,?,?)')->execute([(int)$_SESSION['user_id'],$kind,$route,$target?:null,mb_substr($title,0,500),$status]);$id=(int)$db->lastInsertId();
        }
        rbacWritePayload($id,$payload);
        logActivity((int)$_SESSION['user_id'],$status==='draft'?'SAVE_DRAFT':($resubmit?'RESUBMIT':'SUBMIT'),'rbac_submissions',$id,$title.' — proposed '.$route);
        $db->commit();return $id;
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
}
function rbacCollectUploads(array $files): array {
    $out=[];$total=0;
    $walk=function($name,$type,$tmp,$error,$size,$field)use(&$walk,&$out,&$total){
        if(is_array($name)){foreach($name as $key=>$value)$walk($value,$type[$key]??'',$tmp[$key]??'',$error[$key]??UPLOAD_ERR_NO_FILE,$size[$key]??0,$field.'['.$key.']');return;}
        if($error===UPLOAD_ERR_NO_FILE)return;
        if($error!==UPLOAD_ERR_OK||!is_uploaded_file($tmp))throw new InvalidArgumentException('An uploaded file was not received correctly.');
        $total+=filesize($tmp);if($total>60*1024*1024)throw new LengthException('Submit at most 60 MB of files at a time.');
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
        if(!in_array($mime,['image/jpeg','image/png','image/gif','image/webp','application/pdf','video/mp4','video/webm'],true))throw new InvalidArgumentException('This file type cannot be submitted for approval.');
        $out[$field]=['name'=>basename($name),'mime'=>$mime,'data'=>base64_encode(file_get_contents($tmp))];
    };
    foreach($files as $field=>$file)$walk($file['name'],$file['type'],$file['tmp_name'],$file['error'],$file['size'],$field);
    return $out;
}
function rbacValidatePostOwnership(string $route,array $post): int {
    $action=$post['action']??$post['ajax_action']??'';
    $kind=$route==='/admin/action/create-announcement.php'?'announcement':($route==='/admin/action/create-news.php'?'news':($route==='/admin/events.php'?'event':''));
    if($kind==='')return 0;
    $id=(int)($post[$kind.'_id']??0);
    if(in_array($action,['create','update'],true)){
        $permission=$action==='create'?'create_'.$kind:'edit_'.$kind;
        if($action==='create'&&!hasPermission($permission))throw new RuntimeException('You cannot create this content.');
        if(trim((string)($post['title']??''))==='')throw new InvalidArgumentException('A title is required.');
    }
    if($id){
        $table=['announcement'=>'announcements','news'=>'news','event'=>'events'][$kind];$q=getDB()->prepare("SELECT user_id FROM `$table` WHERE `{$kind}_id`=?");$q->execute([$id]);$owner=$q->fetchColumn();
        if($owner===false)throw new RuntimeException('Content not found.');
        $verb=$action==='delete'?'delete':'edit';
        if(!hasPermission($verb.'_'.$kind)&&!(hasPermission($verb.'_own_'.$kind)&&(int)$owner===(int)$_SESSION['user_id']))throw new RuntimeException('You cannot change another user’s content.');
    }elseif($action!=='create')throw new InvalidArgumentException('A valid content ID is required.');
    if(rbacRole()==='The Mirror'&&!in_array($action,['create','update'],true))throw new RuntimeException('The Mirror can create or edit news and announcements only.');
    if(in_array($action,['pin_facebook','unpin_facebook','set_fb_category'],true))throw new RuntimeException('Only Super Admin can change Facebook posts.');
    return $id;
}
function rbacQueueCurrentRequest(string $route): void {
    try {
        if(!rbacSubmissionsReady())throw new RuntimeException('Approval storage is not set up. Ask the developer to import sql/rbac-migration.sql.');
        $valid=verifyCSRFToken(is_string($_POST['csrf_token']??null)?$_POST['csrf_token']:'');
        if(!$valid&&$route==='/admin/events.php'){
            $q=getDB()->prepare('SELECT token_id FROM csrf_tokens WHERE user_id=? AND token=? AND expires_at>NOW()');$q->execute([(int)$_SESSION['user_id'],$_POST['csrf_token']??'']);$valid=(bool)$q->fetchColumn();
        }
        if(!$valid)rbacFail('Invalid request token. Reload and try again.');
        $post=$_POST;unset($post['csrf_token'],$post['facebook_confirmed'],$post['facebook_page_id'],$post['facebook_request_key'],$post['facebook_link']);
        if(in_array($route,['/admin/action/create-news.php','/admin/action/create-announcement.php'],true)&&isset($post['content']))$post['content']=richTextNormalize((string)$post['content']);
        // Approval never implicitly publishes to Facebook.
        if(isset($post['publish_destination']))$post['publish_destination']='website';
        $target=rbacValidatePostOwnership($route,$post);
        $resubmit=(int)($post['_review_id']??0);unset($post['_review_id']);
        $uploads=rbacCollectUploads($_FILES);
        if($resubmit){$old=rbacGetSubmission($resubmit);if(!$old||(int)$old['user_id']!==(int)$_SESSION['user_id'])throw new RuntimeException('Submission not found.');$previous=rbacReadPayload($resubmit);$uploads=array_replace($previous['files']??[],$uploads);}
        $status=($post['status']??'')==='draft'?'draft':'pending';
        $title=(string)($post['title']??$post['page']??$post['section_key']??($post['action']??'Website content change'));
        $id=rbacSaveSubmission('request',$route,$title,['post'=>$post,'files'=>$uploads],$target,$resubmit,$status);
        $message=$status==='draft'?'Draft saved. It is not live.':'Submitted for Super Admin approval. Published content is unchanged.';
        if(str_starts_with($route,'/admin/action/create-')||$route==='/admin/settings.php'){$_SESSION['flash_success']=$message;header('Location: /admin/submissions.php?id='.$id);exit;}
        header('Content-Type: application/json');http_response_code(202);echo json_encode(['success'=>true,'submitted'=>true,'submission_id'=>$id,'message'=>$message]);exit;
    }catch(Throwable $e){rbacFail($e instanceof PDOException?'Submission could not be stored. No live content was changed.':$e->getMessage(),422);}
}
function rbacRejectSubmission(int $id,int $revision,string $reason): void {
    if(!rbacIsSuper())throw new RuntimeException('Only Super Admin can reject submissions.');
    $reason=trim($reason);if($reason===''||mb_strlen($reason)>2000)throw new InvalidArgumentException('Provide a rejection reason of 1–2,000 characters.');
    $q=getDB()->prepare("UPDATE rbac_submissions SET status='rejected',reason=?,reviewed_by=?,reviewed_at=NOW(),updated_at=NOW() WHERE submission_id=? AND revision=? AND status='pending'");$q->execute([$reason,(int)$_SESSION['user_id'],$id,$revision]);
    if($q->rowCount()!==1)throw new RuntimeException('The submission changed or has already been reviewed. Reload before reviewing.');
    logActivity((int)$_SESSION['user_id'],'REJECT','rbac_submissions',$id,$reason);
}
function rbacFlattenFields(array $data,string $prefix=''): array {
    $flat=[];foreach($data as $key=>$value){$name=$prefix===''?(string)$key:$prefix.'['.$key.']';if(is_array($value))$flat+=rbacFlattenFields($value,$name);elseif(is_scalar($value)||$value===null)$flat[$name]=(string)$value;}return $flat;
}
/** A one-use, server-only reviewer session reuses the real validated handlers. */
function rbacExecuteRequest(array $submission,array $payload): array {
    if(!rbacIsSuper()||!in_array($submission['route'],rbacContentRoutes(),true))throw new RuntimeException('Invalid approval route.');
    if(!function_exists('curl_init'))throw new RuntimeException('PHP cURL is required to apply approved submissions.');
    $origin=(string)env('RBAC_INTERNAL_ORIGIN','http://127.0.0.1');$parts=parse_url($origin);
    if(!in_array($parts['host']??'',['127.0.0.1','localhost','::1'],true)||!in_array($parts['scheme']??'',['http','https'],true)||isset($parts['user'])||isset($parts['pass'])||!in_array($parts['path']??'',['','/'],true)||isset($parts['query'])||isset($parts['fragment']))throw new RuntimeException('Invalid internal approval origin. Use a loopback HTTP(S) origin.');
    $reviewer=(int)$_SESSION['user_id'];$original=session_id();$nonce=bin2hex(random_bytes(32));$workerId=bin2hex(random_bytes(24));$csrf=bin2hex(random_bytes(32));$temporary=[];$workerData=[];
    $post=$payload['post'];$post['csrf_token']=$csrf;
    if(in_array($submission['route'],['/admin/action/create-announcement.php','/admin/action/create-news.php','/admin/events.php'],true)&&in_array($post['action']??$post['ajax_action']??'',['create','update'],true))$post['status']='published';
    if($submission['route']==='/admin/events.php'){
        // Events use the existing database-backed CSRF token store.
        getDB()->prepare('INSERT INTO csrf_tokens(user_id,token,expires_at) VALUES(?,?,DATE_ADD(NOW(),INTERVAL 5 MINUTE))')->execute([$reviewer,$csrf]);
    }
    $credentialVersion=$_SESSION['credential_version']??null;
    session_write_close();session_id($workerId);session_start(['use_cookies'=>0,'use_only_cookies'=>0]);$workerId=session_id();
    $_SESSION=['user_id'=>$reviewer,'user_role'=>'Super Admin','credential_version'=>$credentialVersion,'_initiated'=>true,'last_activity'=>time(),'csrf_token'=>$csrf,
        '_rbac_worker'=>['route'=>$submission['route'],'nonce'=>$nonce,'owner'=>(int)$submission['user_id'],'expires'=>time()+120,'used'=>false]];
    session_write_close();
    try {
        $fields=rbacFlattenFields($post);
        foreach($payload['files']??[] as $field=>$file){$bytes=base64_decode($file['data'],true);if($bytes===false)throw new RuntimeException('Stored media could not be read.');$path=tempnam(sys_get_temp_dir(),'gnc-review-');$temporary[]=$path;file_put_contents($path,$bytes);$fields[$field]=new CURLFile($path,$file['mime'],$file['name']);}
        $curl=curl_init(rtrim($origin,'/').$submission['route']);
        curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$fields,CURLOPT_RETURNTRANSFER=>true,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>5,CURLOPT_TIMEOUT=>90,
            CURLOPT_HTTPHEADER=>['Cookie: '.session_name().'='.$workerId,'X-GNCI-Review: '.$nonce,'Accept: application/json']]);
        $raw=curl_exec($curl);$http=(int)curl_getinfo($curl,CURLINFO_HTTP_CODE);curl_close($curl);
        if($raw===false) return ['state'=>'uncertain','message'=>'The approved request did not confirm completion. Check the content before another attempt.'];
        session_id($workerId);session_start(['use_cookies'=>0,'use_only_cookies'=>0]);$workerData=$_SESSION;$_SESSION=[];session_destroy();
        $json=json_decode($raw,true);
        $ok=$http>=200&&$http<400&&empty($workerData['flash_errors'])&&($http===302?!empty($workerData['flash_success']):(is_array($json)&&($json['success']??false)===true));
        return ['state'=>$ok?'approved':(in_array($http,[401,403],true)?'failed':'uncertain'),'created'=>$workerData['_rbac_created']??null,'message'=>$ok?'Approved and applied.':'The existing handler did not confirm this change. Review the content before another attempt.'];
    }finally{
        foreach($temporary as $path)if(is_file($path))unlink($path);
        if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
        session_id($original);session_start(['use_cookies'=>1,'use_only_cookies'=>1]);
    }
}
function rbacApproveRequest(int $id,int $revision,?callable $executor=null): string {
    if(!rbacIsSuper())throw new RuntimeException('Only Super Admin can approve submissions.');
    $submission=rbacGetSubmission($id);if(!$submission||$submission['kind']!=='request')throw new RuntimeException('This submission must be reviewed in the visual editor.');
    $q=getDB()->prepare("UPDATE rbac_submissions SET status='processing',reviewed_by=?,reviewed_at=NOW(),updated_at=NOW() WHERE submission_id=? AND revision=? AND status='pending'");$q->execute([(int)$_SESSION['user_id'],$id,$revision]);
    if($q->rowCount()!==1)throw new RuntimeException('The submission changed or has already been reviewed. Reload before approving.');
    try{$result=($executor??'rbacExecuteRequest')($submission,rbacReadPayload($id));}
    catch(Throwable $e){$result=['state'=>'uncertain','message'=>'Approval could not confirm completion. Check the server setup and actual content before another attempt.'];}
    $state=in_array($result['state']??'', ['approved','failed','uncertain'],true)?$result['state']:'uncertain';
    getDB()->prepare('UPDATE rbac_submissions SET status=?,reason=?,target_id=COALESCE(?,target_id),updated_at=NOW() WHERE submission_id=? AND status=\'processing\'')->execute([$state,$state==='approved'?null:$result['message'],$result['created']['id']??null,$id]);
    logActivity((int)$_SESSION['user_id'],$state==='approved'?'APPROVE':'APPROVAL_ERROR','rbac_submissions',$id,$result['message'].' Submitted by user '.$submission['user_id']);
    if($state==='approved')logActivity((int)$_SESSION['user_id'],'PUBLISH','rbac_submissions',$id,'Applied approved proposal through its existing handler.');
    return $result['message'];
}
