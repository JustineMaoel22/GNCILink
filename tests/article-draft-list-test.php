<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../admin/admin-functions.php';
require_once __DIR__.'/../admin/news-view.php';
require_once __DIR__.'/../includes/article-draft-list.php';
$db=getDB();$checks=0;
function draftListCheck(bool $ok,string $message): void {global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
// All writes are to connection-scoped temporary tables, including approval payloads.
$db->exec('CREATE TEMPORARY TABLE users(user_id INT PRIMARY KEY,first_name VARCHAR(100),last_name VARCHAR(100))');
$db->exec("INSERT INTO users VALUES(1,'Test','Editor'),(2,'Other','Editor')");
$db->exec('CREATE TEMPORARY TABLE categories(category_id INT PRIMARY KEY,category_name VARCHAR(100))');
$db->exec("INSERT INTO categories VALUES(1,'School'),(2,'Campus')");
$db->exec('CREATE TEMPORARY TABLE media_library(media_id INT PRIMARY KEY,file_path VARCHAR(500))');
$db->exec('CREATE TEMPORARY TABLE announcements(announcement_id INT PRIMARY KEY,user_id INT,title VARCHAR(500),content TEXT,status VARCHAR(20),created_at DATETIME,updated_at DATETIME,category_id INT,featured_image INT,program VARCHAR(20),dept_id INT,is_pinned INT)');
$db->exec('CREATE TEMPORARY TABLE news(news_id INT PRIMARY KEY,user_id INT,title VARCHAR(500),content TEXT,author VARCHAR(100),category VARCHAR(100),status VARCHAR(20),created_at DATETIME,updated_at DATETIME,published_at DATETIME,featured_image INT,is_pinned INT)');
$db->exec('CREATE TEMPORARY TABLE announcement_images(image_id INT,announcement_id INT,media_id INT,display_order INT)');
$db->exec('CREATE TEMPORARY TABLE news_images(image_id INT,news_id INT,media_id INT,display_order INT)');
$db->exec('CREATE TEMPORARY TABLE announcement_departments(announcement_id INT,dept_id INT)');
$db->exec('CREATE TEMPORARY TABLE announcement_programs(announcement_id INT,program_code VARCHAR(20))');
$db->exec('CREATE TEMPORARY TABLE announcement_categories(announcement_id INT,category_id INT,PRIMARY KEY(announcement_id,category_id))');
$db->exec('CREATE TEMPORARY TABLE rbac_submissions(submission_id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,kind VARCHAR(30),route VARCHAR(150),target_id INT NULL,title VARCHAR(500),status VARCHAR(20),revision INT DEFAULT 1,reason TEXT,reviewed_by INT NULL,reviewed_at DATETIME NULL,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE rbac_submission_chunks(submission_id INT,chunk_no INT,payload MEDIUMBLOB,PRIMARY KEY(submission_id,chunk_no)) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE activity_logs(log_id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,action VARCHAR(255),table_name VARCHAR(100),record_id INT,description TEXT,created_at DATETIME) ENGINE=InnoDB');
for($i=1;$i<=4;$i++){
    $status=$i===4?'draft':'published';
    $db->prepare("INSERT INTO announcements VALUES(?,1,?,'Original body',?,'2026-10-01 10:00:00','2026-10-01 10:00:00',1,NULL,'ALL',NULL,?)")->execute([$i,'Stored announcement '.$i,$status,$i===1?1:0]);
    $db->prepare("INSERT INTO news VALUES(?,1,?,'Original body','Editor','Achievements',?,'2026-10-01 10:00:00','2026-10-01 10:00:00',NULL,NULL,?)")->execute([$i,'Stored article '.$i,$status,$i===1?1:0]);
}
$db->exec("INSERT INTO announcements VALUES(5,2,'Other stored draft','Hidden original','draft','2026-10-01 10:00:00','2026-10-01 10:00:00',1,NULL,'ALL',NULL,0)");
$db->exec("INSERT INTO news VALUES(5,2,'Other stored draft','Hidden original','Editor','Achievements','draft','2026-10-01 10:00:00','2026-10-01 10:00:00',NULL,NULL,0)");
foreach(['The Mirror','Content Editor'] as $role){
    foreach(['announcement','news'] as $kind){
        $run=static function(array $input=[])use($db,$kind){
            $filters=$kind==='news'?newsFilters($input):announcementFilters($input);
            $loader=$kind==='news'?static function($db,&$f){return newsListResults($db,$f,rbacRole(),(int)$_SESSION['user_id']);}:'announcementList';
            $result=articleListWithDrafts($db,$filters,$kind,$loader);return [$result,$filters];
        };
        $_SESSION=['user_id'=>2,'user_role'=>$role];
        $route='/admin/action/create-'.$kind.'.php';
        $foreign=rbacSaveSubmission('request',$route,'Other private draft',['post'=>['action'=>'create','title'=>'Other private draft','content'=>'Hidden content'],'files'=>[]],0,0,'draft');
        $_SESSION['user_id']=1;
        $post=['action'=>'create','title'=>'Private 100%_draft!','content'=>'Unique needle in draft body','category'=>'Alumni','category_id'=>2,'category_ids'=>[2],'dept_ids'=>[4],'programs'=>['BSIT']];
        $id=rbacSaveSubmission('request',$route,$post['title'],['post'=>$post,'files'=>[]],0,0,'draft');
        $db->prepare("UPDATE rbac_submissions SET created_at='2026-11-10 10:00:00',updated_at='2026-11-11 10:00:00' WHERE submission_id=?")->execute([$id]);
        [$r]=$run();draftListCheck($r['total']===5&&count($r['rows'])===5,'All statuses includes live records and own private drafts');
        draftListCheck((int)$r['rows'][0]['_draft_id']===$id,'Newest drafts sort with normal records');
        [$r]=$run(['status'=>'draft']);draftListCheck($r['total']===2,'Draft filter combines private and existing stored drafts');
        [$r]=$run(['status'=>'published']);draftListCheck($r['total']===3&&!array_filter($r['rows'],fn($row)=>!empty($row['_private_draft'])),'Published filter excludes draft proposals');
        [$r]=$run(['search'=>'Other private']);draftListCheck($r['total']===0,'Other accounts private drafts never leak');
        [$r]=$run(['search'=>'100%_draft!']);draftListCheck($r['total']===1,'Literal wildcard search finds drafts');
        [$r]=$run(['search'=>'Unique needle']);draftListCheck($r['total']===1,'Body search includes drafts');
        [$r]=$run(['category'=>$kind==='news'?'Alumni':'2']);draftListCheck($r['total']===1,'Category filters apply to private drafts');
        [$r]=$run(['from'=>'2026-11-10','to'=>'2026-11-10']);draftListCheck($r['total']===1,'Date filters apply to private drafts');
        [$r]=$run(['pinned'=>'1']);draftListCheck($r['total']===1,'Pinned filter excludes private drafts');
        [$r]=$run(['sort'=>'oldest']);draftListCheck((int)$r['rows'][0][$kind.'_id']===1,'Oldest order includes normal records first');
        $ids=[];
        // Exercise smaller page sizes directly, using the same validated query helpers.
        $filters=$kind==='news'?newsFilters([]):announcementFilters([]);$filters['size']=2;
        $loader=$kind==='news'?static function($db,&$f){return newsListResults($db,$f,rbacRole(),1);}:'announcementList';
        for($page=1;$page<=3;$page++){$filters['page']=$page;$r=articleListWithDrafts($db,$filters,$kind,$loader);$ids=array_merge($ids,array_column($r['rows'],$kind.'_id'));}
        draftListCheck(count($ids)===5&&count(array_unique($ids))===5,'Mixed pagination neither loses nor duplicates rows');
        if($kind==='announcement'){[$r]=$run(['dept'=>'4','program'=>'BSIT']);draftListCheck($r['total']===1,'Audience filters apply to private drafts');}
        else{[$r]=$run(['date_field'=>'published','from'=>'2026-11-10']);draftListCheck($r['total']===0,'Unpublished draft has no publication date');}
        $_SESSION['user_role']='Super Admin';[$r]=$run();draftListCheck($r['total']===5,'Super Admin normal list does not expose private proposals');
        $_SESSION['user_role']=$role;rbacSubmitSaved($id,1);[$r]=$run();draftListCheck($r['total']===4,'Explicit submission removes draft from content list');
        $db->prepare('DELETE FROM rbac_submissions WHERE submission_id IN (?,?)')->execute([$id,$foreign]);
    }
}
echo "$checks integrated draft list checks passed. No persistent data changed.\n";
