<?php
// Real HTTP uploads/password confirmation/public rendering in a disposable local schema.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../admin/admin-functions.php';
if(!in_array(DB_HOST,['db','127.0.0.1','localhost'],true))throw new RuntimeException('Local testing only.');
$db=getDB();$source=(string)$db->query('SELECT DATABASE()')->fetchColumn();
$schema='gnci_office_http_'.bin2hex(random_bytes(6));$created=false;$server=null;$sessionId=null;$checks=0;$draftUploadPath=null;$invalidUploadPath=null;
$log=tempnam(sys_get_temp_dir(),'gnci-office-http-');$root=dirname(__DIR__);
function officeHttpCheck(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
try{
    $ddl=[];foreach($db->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as $row)$ddl[]=$db->query('SHOW CREATE TABLE `'.str_replace('`','``',$row[0]).'`')->fetch(PDO::FETCH_NUM)[1];
    $db->exec("CREATE DATABASE `$schema` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");$created=true;$db->exec("USE `$schema`");
    $db->exec('SET FOREIGN_KEY_CHECKS=0');try{foreach($ddl as $sql)$db->exec($sql);}finally{$db->exec('SET FOREIGN_KEY_CHECKS=1');}
    $password=bin2hex(random_bytes(20));
    $db->prepare("INSERT INTO users(first_name,last_name,email,password,role,status,credential_version,must_change_password) VALUES('Office','HTTP test','office-http@example.invalid',?,'Super Admin','active',1,0)")->execute([password_hash($password,PASSWORD_DEFAULT)]);
    $userId=(int)$db->lastInsertId();
    $db->exec("INSERT INTO admin_sections(section_key,title,layout) VALUES('heads-of-student-services','Heads of Student Services','offices')");
    // Optional AI is disabled only in this empty test schema; upload safety stays active.
    $db->exec("INSERT INTO admin_settings(setting_key,setting_value) VALUES('ai_image_checker_enabled','0')");
    $_SERVER['HTTP_HOST']='localhost';initSession();$_SESSION=['user_id'=>$userId,'user_role'=>'Super Admin','credential_version'=>1,'_initiated'=>true,'last_activity'=>time()];
    $csrf=generateCSRFToken();$sessionId=session_id();$cookie=session_name().'='.$sessionId;session_write_close();
    $port=random_int(18000,28000);$base='http://127.0.0.1:'.$port;
    $server=proc_open(['php','-S','127.0.0.1:'.$port,'-t',$root,__DIR__.'/administration-office-http-router.php'],[0=>['pipe','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes,$root,array_merge(getenv(),['GNCI_OFFICE_TEST_SCHEMA'=>$schema,'GNCI_OFFICE_TEST_FIELD_LIMIT'=>'1048576']));
    if(!is_resource($server))throw new RuntimeException('Local test server did not start.');fclose($pipes[0]);
    for($end=microtime(true)+5;microtime(true)<$end;){$ready=@fsockopen('127.0.0.1',$port,$errno,$error,.1);if($ready){fclose($ready);break;}usleep(50000);}
    $editorPeer='';
    $request=static function(string $route,?array $fields=null,array $headers=[],bool $authenticated=true)use($base,$cookie,&$editorPeer):array{
        if($editorPeer!=='')$headers[]='X-GNCI-Editor-Peer: '.$editorPeer;
        $curl=curl_init($base.$route);curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20,CURLOPT_FOLLOWLOCATION=>true,CURLOPT_HTTPHEADER=>array_merge(['Host: localhost'],$headers)]);
        if($authenticated)curl_setopt($curl,CURLOPT_COOKIE,$cookie);if($fields!==null)curl_setopt($curl,CURLOPT_POSTFIELDS,$fields);
        $body=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);if($body===false)throw new RuntimeException('Local HTTP request failed.');curl_close($curl);return [$status,$body,json_decode($body,true)];
    };
    [$status,,$joined]=$request('/admin/action/editor-presence-handler.php',['csrf_token'=>$csrf,'action'=>'join','page'=>'administration','route'=>'/pages/about/administration.php']);
    officeHttpCheck($status===200,'Test editor could not join.');$editorPeer=$joined['token'];
    [$status]=$request('/admin/action/editor-presence-handler.php',['csrf_token'=>$csrf,'action'=>'acquire','token'=>$editorPeer]);
    officeHttpCheck($status===200,'Test editor could not reserve Administration.');
    $snapshot=['version'=>1,'state'=>['edits'=>['office'=>['-1'=>['file'=>['$draftFile'=>0]]]],'additions'=>['-1'=>['kind'=>'office','section'=>'heads-of-student-services','item'=>['id'=>-1,'name'=>'QA Office Head','office'=>'QA Office']]]],'preview'=>[],'files'=>[['name'=>'portrait.png','type'=>'image/png','data'=>base64_encode(file_get_contents($root.'/assets/images/test-image.png'))]]];
    $draft=['csrf_token'=>$csrf,'page'=>'administration','route'=>'/pages/about/administration.php','revision'=>0];
    [$status,,$reply]=$request('/admin/action/editor-draft-handler.php',array_merge($draft,['action'=>'save','snapshot'=>json_encode($snapshot)]));
    officeHttpCheck($status===200&&!empty($reply['success']),'Private office/photo draft failed without a publishing password.');
    officeHttpCheck((int)$db->query('SELECT COUNT(*) FROM admin_offices')->fetchColumn()===0,'Draft save created live offices.');
    [$status,,$reply]=$request('/admin/action/editor-draft-handler.php',array_merge($draft,['action'=>'load']));
    officeHttpCheck($status===200&&$reply['draft']['snapshot']['files'][0]['data']===$snapshot['files'][0]['data'],'Database draft round trip lost the photo.');
    // A genuine PNG with a large ancillary text chunk represents a 1.72 MiB photo.
    $png=base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII=');
    $padding='Comment'.chr(0).str_repeat('q',1803550-strlen($png)-12-8);
    $photo=substr($png,0,-12).pack('N',strlen($padding)).'tEXt'.$padding.pack('N',crc32('tEXt'.$padding)).substr($png,-12);
    officeHttpCheck(strlen($photo)===1803550&&getimagesizefromstring($photo)!==false,'Large photo fixture is a valid 1.72 MiB PNG.');
    $large=$snapshot;$large['files'][0]['data']=base64_encode($photo);$encoded=json_encode($large);
    [$status]=$request('/admin/action/editor-draft-handler.php',array_merge($draft,['revision'=>1,'action'=>'save','snapshot'=>$encoded]));
    officeHttpCheck($status===413,'Legacy large text-field transport did not reproduce the hosting rejection.');
    $draftUploadPath=tempnam(sys_get_temp_dir(),'gnci-draft-upload-');file_put_contents($draftUploadPath,$encoded);
    $uploaded=array_merge($draft,['revision'=>1,'action'=>'save','snapshot_file'=>new CURLFile($draftUploadPath,'application/json','administration-draft.json')]);
    [$status,,$reply]=$request('/admin/action/editor-draft-handler.php',$uploaded);
    officeHttpCheck($status===200&&!empty($reply['success'])&&(int)$reply['revision']===2,'Multipart snapshot failed under the same hosting text-field cap.');
    [$status,,$reply]=$request('/admin/action/editor-draft-handler.php',array_merge($draft,['action'=>'load']));
    officeHttpCheck($status===200&&$reply['draft']['snapshot']['files'][0]['data']===$large['files'][0]['data'],'Large photo bytes did not survive real upload, private storage and restore.');
    officeHttpCheck((int)$db->query('SELECT COUNT(*) FROM admin_offices')->fetchColumn()===0,'Large photo draft changed published Administration content.');
    $invalidUploadPath=tempnam(sys_get_temp_dir(),'gnci-invalid-draft-');file_put_contents($invalidUploadPath,'not JSON');
    $bad=$uploaded;$bad['revision']=2;$bad['snapshot_file']=new CURLFile($invalidUploadPath,'application/json','invalid.json');
    [$status]=$request('/admin/action/editor-draft-handler.php',$bad);officeHttpCheck($status===400,'Invalid uploaded snapshot was accepted.');
    $bad=$uploaded;$bad['snapshot']='{}';[$status]=$request('/admin/action/editor-draft-handler.php',$bad);officeHttpCheck($status===400,'Ambiguous text/file snapshots were accepted.');
    $bad=$uploaded;$bad['csrf_token']='invalid';[$status]=$request('/admin/action/editor-draft-handler.php',$bad);officeHttpCheck($status===403,'Multipart snapshot bypassed CSRF protection.');
    [$status]=$request('/admin/action/editor-draft-handler.php',$uploaded,[],false);officeHttpCheck(in_array($status,[401,403],true),'Unauthenticated multipart snapshot was accepted.');
    [$status,,$reply]=$request('/admin/action/editor-draft-handler.php',array_merge($draft,['action'=>'load']));
    officeHttpCheck($status===200&&(int)$reply['draft']['revision']===2&&$reply['draft']['snapshot']['files'][0]['data']===$large['files'][0]['data'],'Refused uploads overwrote the previously saved draft.');
    $reviewFields=array_merge($draft,['action'=>'submit_review','revision'=>2]);
    [$status,,$firstReview]=$request('/admin/action/editor-draft-handler.php',$reviewFields);
    officeHttpCheck($status===200&&!empty($firstReview['submission_id'])&&empty($firstReview['already_submitted']),'First snapshot submission failed.');
    [$status,,$repeatReview]=$request('/admin/action/editor-draft-handler.php',$reviewFields);
    officeHttpCheck($status===200&&!empty($repeatReview['already_submitted'])&&$repeatReview['submission_id']===$firstReview['submission_id'],'Repeated HTTP submission created another approval.');
    officeHttpCheck((int)$db->query("SELECT COUNT(*) FROM rbac_submissions WHERE kind='snapshot'")->fetchColumn()===1,'Duplicate snapshot approval stored after repeated HTTP requests.');
    $fields=['action'=>'admin_save_office','csrf_token'=>$csrf,'office_id'=>0,'section_key'=>'heads-of-student-services','office_name'=>'QA HTTP Office','head_name'=>'QA HTTP Head','head_role'=>'Director','phone'=>'045-900-4473 loc. 123','email'=>'office@example.invalid','location'=>'Building A','hours'=>'Monday to Friday','services'=>"Student support\nRecords"];
    [$status]=$request('/admin/action/page-content-handler.php',$fields);
    officeHttpCheck($status===428&&(int)$db->query('SELECT COUNT(*) FROM admin_offices')->fetchColumn()===0,'Publication bypassed password confirmation.');
    [$status,,$reply]=$request('/admin/action/publish-confirmation-handler.php',['csrf_token'=>$csrf,'password'=>$password]);
    officeHttpCheck($status===200&&!empty($reply['token']),'Publication password confirmation failed.');$token=$reply['token'];
    $fields['photo']=new CURLFile($root.'/assets/images/test-image.png','image/png','portrait.png');
    [$status,,$reply]=$request('/admin/action/page-content-handler.php',$fields,['X-GNCI-Publish-Token: '.$token]);
    officeHttpCheck($status===200&&!empty($reply['success']),'Office upload/publication failed: '.($reply['error']??'invalid response'));
    $id=(int)($reply['office_id']??0);$q=$db->prepare('SELECT o.*,m.file_path FROM admin_offices o LEFT JOIN media_library m ON m.media_id=o.media_id WHERE office_id=?');$q->execute([$id]);$office=$q->fetch(PDO::FETCH_ASSOC);
    officeHttpCheck($id>0&&!empty($office['media_id'])&&!empty($office['file_path']),'Published office was not linked to uploaded media.');
    officeHttpCheck(is_file($root.$office['file_path'])&&getimagesize($root.$office['file_path'])!==false,'Uploaded photo file was not saved.');
    [$status,$html]=$request('/pages/about/administration.php?section=heads-of-student-services',null,[],false);
    officeHttpCheck($status===200&&str_contains($html,'QA HTTP Office')&&str_contains($html,'QA HTTP Head')&&str_contains($html,$office['file_path']),'Visitor page did not render the published office/photo.');
    officeHttpCheck(str_contains($html,'Student support')&&str_contains($html,'Records'),'Published office services were lost.');
    unset($fields['photo']);$fields['office_name']='QA HTTP Office Without Photo';
    [$status,,$reply]=$request('/admin/action/page-content-handler.php',$fields,['X-GNCI-Publish-Token: '.$token]);
    officeHttpCheck($status===200&&!empty($reply['success']),'Office publication without a photo failed.');
    $q->execute([(int)$reply['office_id']]);$withoutPhoto=$q->fetch(PDO::FETCH_ASSOC);
    officeHttpCheck(empty($withoutPhoto['media_id'])&&$withoutPhoto['office_name']==='QA HTTP Office Without Photo','Photo-less office has an incorrect media link.');
    [$status,$html]=$request('/pages/about/administration.php?section=heads-of-student-services',null,[],false);
    officeHttpCheck($status===200&&str_contains($html,'QA HTTP Office Without Photo')&&str_contains($html,$office['file_path']),'Both published office variants did not survive a fresh visitor request.');
    $summary="Office HTTP integration: $checks checks passed (real draft storage, password boundary, multipart upload, media linkage and public rendering). No existing content changed.\n";
}finally{
    if(is_resource($server)){proc_terminate($server);proc_close($server);}
    if($created){
        $prefix=realpath($root.'/uploads/administration');
        foreach($db->query('SELECT file_path FROM media_library')->fetchAll(PDO::FETCH_COLUMN) as $path){$file=realpath($root.$path);if($prefix&&$file&&str_starts_with($file,$prefix.DIRECTORY_SEPARATOR)&&is_file($file))unlink($file);}
        $db->exec('USE `'.str_replace('`','``',$source).'`');
        if($schema!==$source&&preg_match('/\Agnci_office_http_[a-f0-9]{12}\z/D',$schema))$db->exec("DROP DATABASE `$schema`");
    }
    if($sessionId){session_id($sessionId);session_start();$_SESSION=[];session_destroy();}
    foreach([$draftUploadPath,$invalidUploadPath] as $temporary)if(is_string($temporary)&&is_file($temporary))unlink($temporary);
    if(is_file($log))unlink($log);
}
if(isset($summary))echo $summary;
