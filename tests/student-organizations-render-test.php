<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';require_once __DIR__.'/../admin/admin-functions.php';
$_SERVER['DOCUMENT_ROOT']=dirname(__DIR__);$_SERVER['PHP_SELF']='/admin/content-edit.php';
initSession();$_SESSION['user_id']=(int)getDB()->query('SELECT user_id FROM users LIMIT 1')->fetchColumn();$_SESSION['user_role']='Super Admin';
$_GET=['page'=>'student-organizations'];
ob_start();require __DIR__.'/../admin/content-edit.php';$html=ob_get_clean();
foreach(['organizations','student-org.php'] as $marker)if(!str_contains($html,$marker))throw new RuntimeException('Missing Content Editor integration: '.$marker);
foreach(['id="orgEditForm"','id="orgCategoryForm"','id="organizationManager"'] as $marker)if(str_contains($html,$marker))throw new RuntimeException('Legacy organization management form still mounted: '.$marker);
echo "PASS: organizations use the visual canvas without the legacy management forms.\n";
