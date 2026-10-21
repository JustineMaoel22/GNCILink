<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/athletics-news.php';
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE news(news_id INTEGER,title TEXT,slug TEXT,author TEXT,category TEXT,category_id INTEGER,status TEXT,featured_image INTEGER,published_at TEXT,created_at TEXT)');
$db->exec('CREATE TABLE media_library(media_id INTEGER,file_path TEXT)');$db->exec("INSERT INTO media_library VALUES(1,'/uploads/news/team.jpg')");
$insert=$db->prepare('INSERT INTO news VALUES(?,?,?,?,?,?,?,?,?,?)');
for($i=1;$i<=13;$i++)$insert->execute([$i,'Sports '.$i,'sports-'.$i,'GNC','Athletics and Sports',null,'published',$i===13?1:null,'2026-10-20 10:00:00','2026-01-01']);
foreach(['draft','pending','archived','rejected'] as $i=>$status)$insert->execute([20+$i,'Hidden '.$status,'hidden-'.$status,null,'Athletics and Sports',1,$status,null,null,'2027-01-01']);
$insert->execute([30,'Other category','other',null,'School News',1,'published',null,null,'2027-01-01']);
function check(bool $value,string $label): void{if(!$value)throw new RuntimeException($label);}
$first=athleticsNews($db,[]);check($first['total']===13&&$first['pages']===3&&count($first['items'])===6,'Only published news with the exact category appears');
check(array_column($first['items'],'news_id')===[13,12,11,10,9,8],'Date ties use descending article ID');check($first['items'][0]['image_path']==='/uploads/news/team.jpg','Cover image included');check($first['items'][1]['image_path']===null,'Articles without photos still appear');
$second=athleticsNews($db,['highlights_page'=>2]);$third=athleticsNews($db,['highlights_page'=>3]);
check(array_column($second['items'],'news_id')===[7,6,5,4,3,2]&&array_column($third['items'],'news_id')===[1],'Every matching article is reachable without duplicate pagination entries');
check(athleticsNews($db,['highlights_page'=>999])['page']===3,'Out-of-range page clamps');
foreach([-3,0,'invalid',[],'1 OR 1=1'] as $page)check(athleticsNews($db,['highlights_page'=>$page])['page']===1,'Invalid page safely falls back');
$db->exec("UPDATE news SET category='Others' WHERE news_id=13");check(athleticsNews($db,[])['total']===12,'Recategorized articles disappear automatically');
$db->exec('DELETE FROM news');$empty=athleticsNews($db,[]);check($empty['items']===[]&&$empty['page']===1&&$empty['pages']===1,'Empty feed renders a valid first page');
echo "PASS: category and publication filtering, missing images, deterministic sorting, complete pagination, invalid pages, category changes and empty feed.\n";
