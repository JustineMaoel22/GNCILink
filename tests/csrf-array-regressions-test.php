<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$handlers=['page-content','create-announcement','page-review','editor-draft','community-activities','text-layout'];
$mode=$argv[1]??'';if(!in_array($mode,$handlers,true)&&!in_array($mode,['draft-array','draft-sanitize'],true))throw new RuntimeException('Choose a handler.');
require_once __DIR__.'/../config/config.php';require_once __DIR__.'/../admin/admin-functions.php';
$_SERVER['HTTP_HOST']='localhost';session_id(bin2hex(random_bytes(12)));session_start();
$_SESSION=['user_id'=>1,'user_role'=>'Super Admin','credential_version'=>1,'_initiated'=>true,'last_activity'=>time(),'csrf_token'=>str_repeat('c',64)];
$_SERVER['REQUEST_METHOD']='POST';$_POST=['action'=>'save_draft','csrf_token'=>['bad'],'page'=>'history','section_key'=>'intro','content'=>['bad']];
if(in_array($mode,['draft-array','draft-sanitize'],true))$_POST['csrf_token']=$_SESSION['csrf_token'];
if($mode==='draft-sanitize'){
    $db=getDB();$db->exec('CREATE TEMPORARY TABLE pages(page_id INT,slug VARCHAR(100));CREATE TEMPORARY TABLE page_sections(page_id INT,section_key VARCHAR(100),draft_content TEXT,updated_by INT,updated_at DATETIME);CREATE TEMPORARY TABLE activity_logs(user_id INT,action VARCHAR(30),table_name VARCHAR(100),record_id INT,description TEXT,created_at DATETIME)');
    $db->exec("INSERT INTO pages VALUES(1,'history');INSERT INTO page_sections VALUES(1,'intro',NULL,NULL,NULL)");
    $_POST['content']='<p><strong>Keep formatting</strong><script>alert(1)</script><a href="javascript:alert(1)">unsafe</a></p>';
}
http_response_code(200);ob_start();register_shutdown_function(function()use($mode){$body=ob_get_clean();$expected=$mode==='draft-sanitize'?200:($mode==='draft-array'?400:403);if(http_response_code()!==$expected||str_contains($body,'Fatal error')||str_contains($body,'Warning:'))throw new RuntimeException('Unexpected rejection for '.$mode.': '.http_response_code());if($mode==='draft-sanitize'){$stored=getDB()->query('SELECT draft_content FROM page_sections')->fetchColumn();if($stored!==richTextToStorage($_POST['content'])||str_contains($stored,'<script')||str_contains($stored,'javascript:'))throw new RuntimeException('Draft not sanitized');}
echo "PASS: $mode malformed input returns $expected.\n";session_destroy();});
require __DIR__.'/../admin/action/'.($mode==='create-announcement'?'create-announcement.php':(in_array($mode,['draft-array','draft-sanitize'],true)?'page-content':$mode).'-handler.php');
