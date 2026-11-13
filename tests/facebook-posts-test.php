<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../admin/admin-functions.php';
require_once __DIR__.'/../admin/facebook-posts-view.php';
$db=getDB();$checks=0;
function fbCheck(bool $condition,string $message): void {global $checks;if(!$condition)throw new RuntimeException($message);$checks++;}
$db->exec('CREATE TEMPORARY TABLE fb_posts_cache(fb_post_id VARCHAR(100) PRIMARY KEY,message TEXT,permalink_url TEXT,created_time DATETIME,is_pinned INT,category_id INT,category_locked INT,image_media_id INT)');
$db->exec('CREATE TEMPORARY TABLE categories(category_id INT PRIMARY KEY,category_name VARCHAR(100))');
$db->exec('CREATE TEMPORARY TABLE media_library(media_id INT PRIMARY KEY,file_path VARCHAR(500))');
$db->exec('CREATE TEMPORARY TABLE fb_post_categories(fb_post_id VARCHAR(100),category_id INT,PRIMARY KEY(fb_post_id,category_id))');
$db->exec("INSERT INTO categories VALUES(1,'School update');INSERT INTO media_library VALUES(1,'uploads/facebook/example.jpg')");
$insert=$db->prepare('INSERT INTO fb_posts_cache VALUES(?,?,?,?,?,?,?,?)');
for($i=1;$i<=135;$i++)$insert->execute([(string)$i,'Caption '.$i,'','2026-10-01 12:00:00',$i%2,$i%2?1:null,0,1]);
$insert->execute(['136','𝗦𝗰𝗵𝗼𝗼𝗹 special 100%_complete!','','2026-11-04 23:59:59',1,1,1,1]);
$run=function(array $input=[])use($db){$f=facebookPostFilters($input);return [facebookPostList($db,$f),$f];};
[$r]=$run();fbCheck($r['total']===136&&count($r['rows'])===10&&$r['rows'][0]['fb_post_id']==='136','Newest first and full count');
[$r,$f]=$run(['size'=>'100','page'=>'2']);fbCheck(count($r['rows'])===36&&$f['page']===2,'Pagination beyond old cap');
[$r,$f]=$run(['page'=>'999999']);fbCheck($f['page']===14&&count($r['rows'])===6,'Page clamping');
[$r]=$run(['search'=>'school']);fbCheck($r['total']===1,'Styled Unicode caption search');
[$r]=$run(['search'=>'100%_complete!']);fbCheck($r['total']===1,'Literal wildcard characters');
[$r]=$run(['search'=>"' OR 1=1 --"]);fbCheck($r['total']===0,'SQL-looking caption is literal');
[$r]=$run(['category'=>'1','pinned'=>'1','from'=>'2026-11-04','to'=>'2026-11-04']);fbCheck($r['total']===1,'Combined filters and inclusive date boundary');
[$r]=$run(['category'=>'none','pinned'=>'0']);fbCheck($r['total']===67,'Uncategorized and unpinned');
[$r]=$run(['search'=>'no-match']);fbCheck($r['total']===0&&$r['pages']===1,'Empty results');
$f=facebookPostFilters(['search'=>[],'category'=>[],'pinned'=>[],'from'=>'2026-02-30','to'=>"2026-11\0-04",'size'=>'11','page'=>'-1']);
fbCheck($f['search']===''&&$f['category']===''&&$f['pinned']===''&&$f['from']===''&&$f['to']===''&&$f['size']===10&&$f['page']===1,'Malformed input');
fbCheck(facebookPostImage('javascript:alert(1)')===''&&facebookPostImage('//evil.example/image.jpg')===''&&facebookPostImage('/uploads/../config/x')==='','Unsafe media paths');
fbCheck(facebookPostImage('uploads/facebook/example.jpg')==='/uploads/facebook/example.jpg','Relative cached media path');
fbCheck(!in_array('publish_announcement',getPermissions('Content Editor'),true)&&!in_array('publish_announcement',getPermissions('The Mirror'),true),'Existing read-only roles preserved');
echo "$checks Facebook management checks passed. No persistent data changed.\n";
