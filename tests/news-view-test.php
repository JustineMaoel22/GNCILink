<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if(($argv[1]??'')==='all'){
    foreach(['list','create','update','archive','unarchive','delete','csrf','pin-full','pin-draft','invalid-id','invalid-fields'] as $case){passthru(escapeshellarg(PHP_BINARY).' '.escapeshellarg(__FILE__).' '.escapeshellarg($case),$status);if($status!==0)exit($status);}exit;
}
require_once __DIR__.'/../config/config.php';require_once __DIR__.'/../admin/admin-functions.php';require_once __DIR__.'/../admin/news-view.php';
$db=getDB();$checks=0;
function newsCheck(bool $condition,string $message): void{global $checks;if(!$condition)throw new RuntimeException($message);$checks++;}
$db->exec('CREATE TEMPORARY TABLE news(news_id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,title VARCHAR(500),slug VARCHAR(500),content TEXT,author VARCHAR(255),category VARCHAR(100),status VARCHAR(20),created_at DATETIME,updated_at DATETIME NULL,published_at DATETIME NULL,featured_image INT NULL,is_pinned INT DEFAULT 0,pinned_at DATETIME NULL)');
$db->exec('CREATE TEMPORARY TABLE users(user_id INT PRIMARY KEY,first_name VARCHAR(100),last_name VARCHAR(100))');
$db->exec('CREATE TEMPORARY TABLE media_library(media_id INT PRIMARY KEY,file_path VARCHAR(500))');
$db->exec('CREATE TEMPORARY TABLE news_images(image_id INT PRIMARY KEY,news_id INT,media_id INT,display_order INT)');
$db->exec('CREATE TEMPORARY TABLE announcements(is_pinned INT)');
$db->exec('CREATE TEMPORARY TABLE fb_posts_cache(is_pinned INT)');
$db->exec('CREATE TEMPORARY TABLE activity_logs(log_id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,action VARCHAR(255),table_name VARCHAR(100),record_id INT NULL,description TEXT,created_at DATETIME DEFAULT CURRENT_TIMESTAMP)');
$db->exec("INSERT INTO users VALUES(1,'School','Editor');INSERT INTO media_library VALUES(1,'uploads/news/example.jpg')");
$insert=$db->prepare('INSERT INTO news(news_id,user_id,title,slug,content,category,status,created_at,updated_at,published_at,featured_image,is_pinned) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
for($i=1;$i<=135;$i++)$insert->execute([$i,$i%2?1:2,'Article '.$i,'article-'.$i,'Example story','Academic',$i%2?'published':'draft','2026-10-01 10:00:00',$i===1?'2026-11-05 12:00:00':null,null,1,0]);
$insert->execute([136,1,'Special 100%_complete!','special-136','Unique search content','Others','published','2026-11-04 15:59:59',null,'2026-11-04 15:59:59',1,1]);
$db->exec('INSERT INTO news_images VALUES(1,136,1,1)');
$mode=$argv[1]??'list';
if($mode!=='list'){
    session_start();$_SESSION=['user_id'=>1,'user_role'=>'Super Admin','csrf_token'=>str_repeat('a',64),'_initiated'=>true,'last_activity'=>time()];
    $_SERVER['REQUEST_METHOD']='POST';$_POST=['csrf_token'=>$_SESSION['csrf_token'],'news_id'=>'136','action'=>$mode,'title'=>'Changed article','content'=>'<p>Changed story</p>','author'=>'School editor','category'=>'Academic','status'=>'draft','return_query'=>'search=Changed&sort=updated&page=2'];
    if($mode==='csrf'){$_POST['action']='delete';$_POST['csrf_token']='invalid';}
    if($mode==='invalid-id'){$_POST['action']='delete';$_POST['news_id']=['136'];}
    if($mode==='invalid-fields'){$_POST['action']='update';$_POST['title']=['Bad headline'];$_POST['status']='invalid';}
    if($mode==='pin-full'){$_POST['action']='pin';$db->exec('UPDATE news SET is_pinned=0;INSERT INTO announcements VALUES(1),(1),(1),(1),(1)');}
    if($mode==='pin-draft'){$_POST['action']='pin';$db->exec("UPDATE news SET is_pinned=0,status='draft' WHERE news_id=136");}
    if($mode==='unarchive')$db->exec("UPDATE news SET status='archived',is_pinned=0 WHERE news_id=136");
    register_shutdown_function(function()use($db,$mode){
        $row=$db->query('SELECT * FROM news WHERE news_id=136')->fetch(PDO::FETCH_ASSOC);
        if(in_array($mode,['csrf','invalid-id','invalid-fields'],true))newsCheck((bool)$row&&$row['title']==='Special 100%_complete!'&&!empty($_SESSION['flash_errors']),'Invalid input blocked');
        elseif(in_array($mode,['pin-full','pin-draft'],true))newsCheck(!(int)$row['is_pinned']&&!empty($_SESSION['flash_errors']),'Invalid pin rejected');
        elseif($mode==='create')newsCheck((int)$db->query('SELECT COUNT(*) FROM news')->fetchColumn()===137&&!empty($_SESSION['flash_success']),'Create draft');
        elseif($mode==='update')newsCheck($row['title']==='Changed article'&&$row['status']==='draft','Update article');
        elseif($mode==='archive')newsCheck($row['status']==='archived'&&!(int)$row['is_pinned'],'Archive unpins');
        elseif($mode==='unarchive')newsCheck($row['status']==='draft','Restore as draft');
        elseif($mode==='delete')newsCheck(!$row&&!empty($_SESSION['flash_success']),'Delete article');
        echo "News handler $mode check passed using temporary tables.\n";
    });
    require __DIR__.'/../admin/action/create-news.php';exit;
}
$run=function(array $input=[],string $role='Super Admin',int $id=1)use($db){$f=newsFilters($input);return [newsListResults($db,$f,$role,$id),$f];};
[$r]=$run();newsCheck($r['total']===136&&count($r['rows'])===10&&(int)$r['rows'][0]['news_id']===136,'Newest and count');newsCheck(count($r['galleries'][136])===1,'Batch galleries');
[$r,$f]=$run(['size'=>'100','page'=>'2']);newsCheck(count($r['rows'])===36&&$f['page']===2,'Pagination beyond 50');
[$r,$f]=$run(['page'=>'999999']);newsCheck($f['page']===14&&count($r['rows'])===6,'Clamp page');
[$r]=$run(['sort'=>'updated']);newsCheck((int)$r['rows'][0]['news_id']===1,'Recently updated');
[$r]=$run(['sort'=>'oldest']);newsCheck((int)$r['rows'][0]['news_id']===1,'Oldest first');
[$r]=$run(['search'=>'100%_complete!']);newsCheck($r['total']===1,'Literal wildcard search');
[$r]=$run(['search'=>'Unique search']);newsCheck($r['total']===1,'Content search');
[$r]=$run(['search'=>"' OR 1=1 --"]);newsCheck($r['total']===0,'Parameterized search');
[$r]=$run(['category'=>'Others','status'=>'published','pinned'=>'1','from'=>'2026-11-04','to'=>'2026-11-04','date_field'=>'published']);newsCheck($r['total']===1,'Combined local date filters');
[$r]=$run(['from'=>'2026-11-05']);newsCheck($r['total']===0,'Philippine midnight boundary');
[$r]=$run([],'The Mirror',2);newsCheck($r['total']===67,'Mirror sees only own articles');
[$r]=$run([],'Content Editor',2);newsCheck($r['total']===136,'Editor sees all records');
$f=newsFilters(['search'=>[],'status'=>[],'category'=>[],'from'=>'2026-02-30','to'=>"2026-11\0-04",'sort'=>'DROP','size'=>'11','page'=>'-1']);newsCheck($f['search']===''&&$f['status']===''&&$f['category']===''&&$f['from']===''&&$f['to']===''&&$f['size']===10&&$f['page']===1,'Malformed input');
$url=newsReturnUrl(['return_query'=>'search=Article&status=draft&sort=updated&page=2&redirect=https://example.com']);newsCheck(str_starts_with($url,'/admin/news.php?')&&!str_contains($url,'example.com')&&str_contains($url,'page=2'),'Local return URL preserves filters');
echo "$checks News list checks passed. No persistent data changed.\n";
