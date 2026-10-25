<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$mode=$argv[1] ?? 'environment';
$log=tempnam(sys_get_temp_dir(),'gnc-upload-error-');
ini_set('error_log',$log);
session_save_path(sys_get_temp_dir());session_id('gnc-hosted-upload-'.bin2hex(random_bytes(12)));session_start();
$_SESSION=['user_id'=>1,'user_role'=>'Super Admin','_initiated'=>true,'last_activity'=>time(),'csrf_token'=>'test-token'];
$_SERVER['REQUEST_METHOD']=$mode==='environment'?'GET':'POST';
$_GET=[];$_POST=['action'=>'admin_save_office','csrf_token'=>'test-token','office_id'=>1,'office_name'=>'Office','head_name'=>'Head'];
if($mode==='environment'){$_POST=[];$_GET=['action'=>'upload_environment'];}
if($mode==='engine-error')$_POST['office_name']=[];
try {
    ob_start();require __DIR__.'/../admin/action/page-content-handler.php';$raw=ob_get_clean();
    $data=json_decode($raw,true,512,JSON_THROW_ON_ERROR);
    if($mode==='missing-mbstring') {
        if(function_exists('mb_strlen'))throw new RuntimeException('Run with -d disable_functions=mb_strlen,mb_substr');
        if(http_response_code()!==422 || !str_contains($data['error'] ?? '','mbstring'))throw new RuntimeException('Missing extension must return an actionable JSON validation error');
    } elseif($mode==='engine-error') {
        if(http_response_code()!==500 || !preg_match('/^[a-f0-9]{12}$/D',$data['reference'] ?? ''))throw new RuntimeException('Runtime errors must return JSON with a support reference');
        if(!str_contains(file_get_contents($log),$data['reference']) || str_contains($raw,'TypeError') || str_contains($raw,'/var/www'))throw new RuntimeException('Private exception details must stay in the PHP log');
    } else {
        if(empty($data['success']) || !isset($data['environment']['extensions']['mbstring'],$data['environment']['administration_upload_writable']))throw new RuntimeException('Safe runtime diagnostics missing');
        if(str_contains($raw,'DB_PASS') || str_contains($raw,'API_KEY') || str_contains($raw,'/var/www'))throw new RuntimeException('Runtime report must not expose secrets or server paths');
    }
} finally {session_destroy();@unlink($log);}
echo "PASS: $mode returns safe JSON without changing any records.\n";
