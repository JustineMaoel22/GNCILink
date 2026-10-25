<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../admin/admin-functions.php';
require_once __DIR__.'/../includes/community-activities.php';
$db=getDB();$content=communityActivityContent($db);if(!$content)throw new RuntimeException('Install community activities storage first.');
if(($argv[1]??'')==='cleanup'){
    foreach(array_slice($argv,2) as $path){
        if(!preg_match('~\A/uploads/community-activities/[a-f0-9]{32}\.(jpg|png|webp|gif)\z~D',$path)||in_array($path,array_column($content['activities'],'image'),true)||$content['hero_image']===$path||($content['closing_image']??null)===$path||communityActivityShowcaseImage($content)===$path)throw new RuntimeException('Unsafe or still-used test image.');
        $file=realpath(dirname(__DIR__).$path);$root=realpath(dirname(__DIR__).'/uploads/community-activities');
        if($file&&$root&&str_starts_with($file,$root.DIRECTORY_SEPARATOR))unlink($file);
        $db->prepare('DELETE FROM image_display_settings WHERE image_path=?')->execute([$path]);
    }
    echo "Unused test images removed.\n";exit;
}
// The real page renders from a temporary table on this connection; live data is untouched.
$db->exec('CREATE TEMPORARY TABLE community_activity_page(page_id TINYINT UNSIGNED PRIMARY KEY,content MEDIUMTEXT NOT NULL,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
$content['activities']=[];
foreach(range(1,6) as $i)$content['activities'][]=['id'=>'activity-fixture-'.$i,'title'=>'Authored fixture '.$i,'description'=>"Database description $i.\nA second line of detail.",'category'=>'Category fixture '.($i%2+1),'image'=>'/assets/images/gnc-header.png'];
$db->prepare('INSERT INTO community_activity_page(page_id,content) VALUES(1,?)')->execute([json_encode($content)]);
$_GET=[];
if(($argv[1]??'')==='impact'){
    $db->exec('CREATE TEMPORARY TABLE news(news_id INT PRIMARY KEY,title VARCHAR(500),slug VARCHAR(500),content TEXT,category VARCHAR(20),status VARCHAR(20),featured_image INT,published_at DATETIME,created_at DATETIME)');
    $db->exec('CREATE TEMPORARY TABLE media_library(media_id INT PRIMARY KEY,file_name VARCHAR(255),file_type VARCHAR(100),file_size INT,file_path VARCHAR(500))');
    $db->exec("INSERT INTO media_library(media_id,file_name,file_type,file_size,file_path) VALUES(999999,'impact-fixture.png','image/png',100,'/assets/images/gnc-header.png')");
    $insert=$db->prepare("INSERT INTO news(news_id,title,slug,content,category,status,featured_image,published_at,created_at) VALUES(?,?,?,?,?,'published',999999,?,?)");
    foreach(range(1,14) as $i)$insert->execute([$i,'Community impact fixture '.$i,'community-impact-fixture-'.$i,'Fixture article content','Community Extension','2026-10-24 12:00:00','2026-10-24 10:00:00']);
    $_GET['impact_page']=$argv[2]??1;
}
$_SERVER['REQUEST_URI']='/pages/student%20life/community-activity.php?activity_fixture=1';
include __DIR__.'/../pages/student life/community-activity.php';
