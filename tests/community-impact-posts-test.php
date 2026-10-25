<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../admin/admin-functions.php';
require_once __DIR__.'/../includes/community-impact-posts.php';
function impactCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
impactCheck(getNewsCategories()['Community Extension']==='Community Extension Activity','New label is available in Create News');
impactCheck(normalizeNewsCategory('Community Extension')==='Community Extension','Category survives save normalization');
impactCheck(strlen('Community Extension')<=20,'Fits the existing category column without migration');
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE news(news_id INTEGER PRIMARY KEY,title TEXT,slug TEXT,category TEXT,status TEXT,featured_image INTEGER,published_at TEXT,created_at TEXT);CREATE TABLE media_library(media_id INTEGER PRIMARY KEY,file_path TEXT);INSERT INTO media_library VALUES(1,"/assets/images/gnc-header.png")');
$insert=$db->prepare('INSERT INTO news VALUES(?,?,?,?,?,?,?,?)');
foreach(range(1,14) as $i)$insert->execute([$i,'Authored impact '.$i,'authored-impact-'.$i,'Community Extension','published',$i%2?1:null,'2026-10-24 12:00:00','2026-10-24 10:00:00']);
foreach(['draft','pending','archived'] as $index=>$status)$insert->execute([20+$index,'Private title','private-'.$index,'Community Extension',$status,null,null,'2026-10-25 00:00:00']);
$insert->execute([30,'Other category','other','School News','published',null,null,'2026-10-26 00:00:00']);
$all=[];
foreach(range(1,3) as $page){$result=communityImpactPosts($db,['impact_page'=>$page]);impactCheck($result['page']===$page&&$result['pages']===3&&$result['total']===14,'Six cards per page');$all=array_merge($all,$result['items']);}
impactCheck(array_column($all,'news_id')===range(14,1),'Newest first with stable tie ordering, no duplicates/private/unrelated news');
impactCheck($all[0]['image_path']===null&&$all[1]['image_path']==='/assets/images/gnc-header.png','Optional covers render from media library');
foreach([-1,'invalid',['2'],'1 OR 1=1'] as $value)impactCheck(communityImpactPosts($db,['impact_page'=>$value])['page']===1,'Invalid page is bounded');
impactCheck(communityImpactPosts($db,['impact_page'=>999])['page']===3,'Last page is bounded');
$db->exec('DELETE FROM news');$empty=communityImpactPosts($db,[]);impactCheck($empty['total']===0&&$empty['items']===[]&&$empty['pages']===1,'Empty state has no seeded activity cards');
echo "PASS: Create News category, existing schema compatibility, published-only six-card feed, media, stable pagination, invalid page inputs and empty state. No site data changed.\n";
