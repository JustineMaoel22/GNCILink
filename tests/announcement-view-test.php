<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';require_once __DIR__.'/../admin/announcement-view.php';
$db=getDB();$checks=0;
function annCheck(bool $condition,string $message): void{global $checks;if(!$condition)throw new RuntimeException($message);$checks++;}
// Temporary tables shadow real collections for this connection only.
$db->exec('CREATE TEMPORARY TABLE announcements(announcement_id INT PRIMARY KEY,user_id INT,title VARCHAR(500),content TEXT,status VARCHAR(20),created_at DATETIME,category_id INT,featured_image INT,program VARCHAR(20),dept_id INT,is_pinned INT)');
$db->exec('CREATE TEMPORARY TABLE users(user_id INT PRIMARY KEY,first_name VARCHAR(100),last_name VARCHAR(100))');
$db->exec('CREATE TEMPORARY TABLE categories(category_id INT PRIMARY KEY,category_name VARCHAR(100))');
$db->exec('CREATE TEMPORARY TABLE media_library(media_id INT PRIMARY KEY,file_path VARCHAR(500))');
$db->exec('CREATE TEMPORARY TABLE announcement_images(image_id INT PRIMARY KEY,announcement_id INT,media_id INT,display_order INT)');
$db->exec('CREATE TEMPORARY TABLE announcement_departments(announcement_id INT,dept_id INT)');
$db->exec('CREATE TEMPORARY TABLE announcement_programs(announcement_id INT,program_code VARCHAR(20))');
$db->exec('CREATE TEMPORARY TABLE announcement_categories(announcement_id INT,category_id INT,PRIMARY KEY(announcement_id,category_id))');
$db->exec("INSERT INTO users VALUES(1,'Juan','Cruz');INSERT INTO categories VALUES(1,'School notice');INSERT INTO media_library VALUES(1,'/assets/images/test-image.png')");
$insert=$db->prepare('INSERT INTO announcements VALUES(?,?,?,?,?,?,?,?,?,?,?)');
for($i=1;$i<=135;$i++)$insert->execute([$i,1,'Announcement '.$i,'Example body',$i%2?'published':'draft','2026-10-01 10:00:00',1,1,'ALL',null,0]);
$insert->execute([136,1,'Special 100%_complete!','Unique searchable text','archived','2026-11-04 23:59:59',1,1,'ALL',null,1]);
$db->exec("INSERT INTO announcement_departments VALUES(136,4);INSERT INTO announcement_programs VALUES(136,'BSIT');INSERT INTO announcement_images VALUES(1,136,1,1)");
$run=function(array $input=[])use($db){$f=announcementFilters($input);return [announcementList($db,$f),$f];};
[$r,$f]=$run();annCheck($r['total']===136&&count($r['rows'])===10&&(int)$r['rows'][0]['announcement_id']===136,'Newest and full count');
annCheck(count($r['galleries'][136])===1,'Batch gallery metadata');
[$r,$f]=$run(['size'=>'100','page'=>'2']);annCheck(count($r['rows'])===36&&$f['page']===2,'Records beyond old 50-record cap');
[$r,$f]=$run(['page'=>'9999']);annCheck($f['page']===14&&count($r['rows'])===6,'Clamp high page');
[$r]=$run(['sort'=>'oldest']);annCheck((int)$r['rows'][0]['announcement_id']===1,'Stable oldest sort');
[$r]=$run(['search'=>'100%_complete!']);annCheck($r['total']===1,'Escape search wildcards');
[$r]=$run(['search'=>'Unique searchable']);annCheck($r['total']===1,'Search body');
[$r]=$run(['search'=>"' OR 1=1 --"]);annCheck($r['total']===0,'Parameterized search');
[$r]=$run(['status'=>'archived','category'=>'1','dept'=>'4','program'=>'BSIT','from'=>'2026-11-04','to'=>'2026-11-04','pinned'=>'1']);annCheck($r['total']===1,'All filters combine with multi-audience and inclusive date boundary');
[$r]=$run(['status'=>'draft']);annCheck($r['total']===67,'Status filter');
[$r]=$run(['dept'=>'999']);annCheck($r['total']===0,'Unmatched audience');
$f=announcementFilters(['search'=>[],'from'=>"2026-11\0-04",'to'=>'2026-02-30','status'=>[],'sort'=>'DROP','size'=>'11','page'=>'-1']);annCheck($f['search']===''&&$f['from']===''&&$f['to']===''&&$f['status']===''&&$f['size']===10&&$f['page']===1,'Malformed query validation');
$f=announcementFilters(['from'=>'2026-11-05','to'=>'2026-11-01']);annCheck($f['from']==='2026-11-01','Reversed date range normalization');
$db->exec('CREATE TEMPORARY TABLE news(news_id INT,title VARCHAR(500),status VARCHAR(20),is_pinned INT)');
$db->exec('CREATE TEMPORARY TABLE fb_posts_cache(fb_post_id VARCHAR(100) CHARACTER SET ascii,message TEXT,status VARCHAR(20),is_pinned INT)');
$db->exec("INSERT INTO news VALUES(7,'Pinned news','published',1);INSERT INTO fb_posts_cache VALUES('123_456','Pinned Facebook post','published',1)");
$pinned=announcementPinnedItems($db);
annCheck(count($pinned)===3,'All pinned sources load with mixed ID collations');
annCheck(array_column($pinned,'item_id')===['136','7','123_456'],'Unpin uses the correct source IDs');
annCheck(!array_diff(['source','item_id','title','status'],array_keys($pinned[0])),'Pinned renderer fields match query');
$returnUrl=announcementReturnUrl(['return_query'=>'search=Example&status=&category=1&sort=oldest&size=25&page=2&redirect=https://example.com']);
parse_str(parse_url($returnUrl,PHP_URL_QUERY),$returned);
annCheck($returned['status']===''&&$returned['search']==='Example'&&$returned['sort']==='oldest'&&$returned['size']==='25'&&$returned['page']==='2','Delete return preserves All statuses, search, sort and pagination');
annCheck(parse_url($returnUrl,PHP_URL_PATH)==='/admin/announcement.php'&&!isset($returned['redirect']),'Return URL is restricted to validated announcement filters');
echo "$checks announcement list checks passed. No persistent data changed.\n";
