<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../admin/admin-functions.php';
require_once __DIR__.'/../includes/role-dashboard.php';
require_once __DIR__.'/../admin/activity-log-view.php';
$db=getDB();$checks=0;
function dashCheck(bool $condition,string $message): void{global $checks;if(!$condition)throw new RuntimeException($message);$checks++;}
// Only connection-scoped temporary tables are changed. Production tables are
// shadowed for these queries and all real data stays intact.
$db->exec('CREATE TEMPORARY TABLE users(user_id INT PRIMARY KEY,first_name VARCHAR(100),last_name VARCHAR(100))');
$db->exec("INSERT INTO users VALUES(1,'Juan','Cruz'),(2,'Maria','Santos')");
foreach(['announcements'=>'announcement_id','news'=>'news_id','events'=>'event_id'] as $table=>$key){
    $extra=$table==='events'?',start_date DATETIME':'';
    $db->exec("CREATE TEMPORARY TABLE $table($key INT PRIMARY KEY,user_id INT,title VARCHAR(500),status VARCHAR(20),created_at DATETIME,updated_at DATETIME$extra)");
}
$db->exec("INSERT INTO announcements VALUES(1,1,'My notice','published',NOW(),NOW()),(2,1,'My draft','draft',NOW(),NOW()),(3,2,'Other notice','pending',NOW(),NOW()),(4,2,'Other pending','pending',NOW(),NOW()),(5,2,'Other published','published',NOW(),NOW())");
$db->exec("INSERT INTO news VALUES(1,1,'Newest news','published',NOW(),DATE_ADD(NOW(),INTERVAL 1 DAY)),(2,2,'Other news','published',NOW(),NOW()),(3,2,'Other draft','draft',NOW(),NOW())");
$db->exec("INSERT INTO events VALUES(1,1,'Future event','published',NOW(),NOW(),DATE_ADD(NOW(),INTERVAL 2 DAY)),(2,1,'Past event','published',NOW(),NOW(),DATE_SUB(NOW(),INTERVAL 2 DAY)),(3,1,'Draft event','draft',NOW(),NOW(),DATE_ADD(NOW(),INTERVAL 2 DAY)),(4,2,'Other event','published',NOW(),NOW(),DATE_ADD(NOW(),INTERVAL 2 DAY))");
$db->exec('CREATE TEMPORARY TABLE rbac_submissions(submission_id INT PRIMARY KEY,user_id INT,title VARCHAR(500),status VARCHAR(20),revision INT,created_at DATETIME,updated_at DATETIME,kind VARCHAR(30) DEFAULT \'request\',route VARCHAR(150) NULL)');
$db->exec("INSERT INTO rbac_submissions(submission_id,user_id,title,status,revision,created_at,updated_at) VALUES(1,1,'My pending','pending',1,NOW(),NOW()),(2,2,'Other pending','pending_approval',1,NOW(),NOW()),(3,2,'Another pending','pending',1,NOW(),NOW()),(4,1,'My revision','needs_revision',2,NOW(),NOW()),(5,2,'Other revision','needs_revision',3,NOW(),NOW()),(6,1,'Rejected proposal','rejected',1,NOW(),NOW()),(7,1,'Draft proposal','draft',1,NOW(),NOW())");
$db->exec("INSERT INTO rbac_submissions VALUES(8,1,'My private announcement','draft',1,NOW(),NOW(),'request','/admin/action/create-announcement.php'),(9,1,'My private news','draft',1,NOW(),NOW(),'request','/admin/action/create-news.php'),(10,2,'Other private draft','draft',1,NOW(),NOW(),'request','/admin/action/create-announcement.php'),(11,1,'My private page','draft',1,NOW(),NOW(),'snapshot','/admin/content-edit.php')");
$db->exec('CREATE TEMPORARY TABLE page_sections(section_id INT PRIMARY KEY,review_status VARCHAR(20))');
$db->exec("INSERT INTO page_sections VALUES(1,'pending'),(2,'pending'),(3,'none')");
$db->exec('CREATE TEMPORARY TABLE pages(slug VARCHAR(100),title VARCHAR(255),page_type VARCHAR(30))');
$db->exec('CREATE TEMPORARY TABLE activity_logs(log_id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,action VARCHAR(255),table_name VARCHAR(100),record_id INT NULL,description TEXT,created_at DATETIME)');
$db->exec("INSERT INTO activity_logs(log_id,user_id,action,table_name,description,created_at) VALUES(1,1,'FACEBOOK_PUBLISH','news','Published news',NOW()),(2,2,'UPDATE','users','password=private-test-value',NOW())");
foreach(['Super Admin','Content Editor','The Mirror','Content Admin','Office Staff'] as $role){
    $_SESSION=['user_id'=>1,'user_role'=>$role];$d=roleDashboardData($db);$own=rbacRole()==='The Mirror';$super=rbacIsSuper();
    dashCheck(!$d['errors'],'All summaries loaded');
    dashCheck($d['stats']['total_announcements']===($own?3:5),'Role-scoped announcement count');
    dashCheck($d['stats']['total_news']===($own?2:3),'Role-scoped news count');
    dashCheck($d['stats']['published_content']===($own?2:7),'Published count across permitted modules');
    dashCheck($d['stats']['pending_approvals']===($super?5:1),'Approval count includes page text and proposals');
    dashCheck(($d['submission_counts']['needs_revision']??0)===($super?2:1),'Revision state count');
    dashCheck($d['recent'][0]['title']==='Newest news','News is included and ordered by last update even with announcements');
    dashCheck(count($d['recent'])<=6&&count($d['submissions'])<=6,'Bounded recent lists');
    dashCheck(!in_array(7,array_column($d['submissions'],'submission_id'))&&!isset($d['submission_counts']['draft']),'Private drafts excluded from dashboard submissions');
    dashCheck($d['stats']['upcoming_events']===($own?null:2),'Upcoming excludes past and draft events');
    dashCheck($d['stats']['total_events']===($own?null:4),'Event module visibility');
    dashCheck($d['stats']['total_users']===($super?2:null),'User statistics stay restricted');
    dashCheck(count($d['logs'])===($super?2:0),'Activity stays restricted');
    dashCheck($d['page_pending']===($super?2:null),'Legacy page review visibility');
    if(!$super)dashCheck(!array_intersect([2,3,5],array_column($d['submissions'],'submission_id')),'Other authors proposals never leak');
    if($own)dashCheck(!array_filter($d['recent'],fn($row)=>str_starts_with($row['title'],'Other')||$row['type']==='event'),'Mirror content stays owned');
    if($own){
        dashCheck($d['content_counts']['announcement']['draft']===2&&$d['content_counts']['news']['draft']===1,'Mirror counts private proposal drafts alongside its stored drafts');
        dashCheck(count(array_filter($d['recent'],fn($row)=>!empty($row['_private_draft'])))===2,'Mirror recent content includes its two private article drafts');
        $tasks=dashboardMirrorNextSteps($d);
        dashCheck(array_column($tasks,1)===[2,1,1],'Mirror next steps shows draft counts and waiting proposals');
        dashCheck(array_column($tasks,3)===['/admin/announcement.php?status=draft','/admin/news.php?status=draft','/admin/submissions.php?status=pending'],'Mirror tasks link to existing module filters and submitted proposals');
    }else dashCheck(!array_filter($d['recent'],fn($row)=>!empty($row['_private_draft'])),'Other dashboard roles do not acquire Mirror private drafts');
}
dashCheck(str_contains(activityLogDescription(['action'=>'UPDATE','table_name'=>'users','description'=>'password=private-test-value']),'hidden'),'Dashboard descriptions suppress secrets');
dashCheck(dashboardContentUrl(['type'=>'news','id'=>3])==='/admin/news.php?modal=edit&id=3','Record-specific edit link');
dashCheck(dashboardContentUrl(['type'=>'event','id'=>3])==='/admin/events.php','Calendar management link');
dashCheck(dashboardContentUrl(['type'=>'announcement','id'=>8,'_private_draft'=>true])==='/admin/announcement.php?revise=8','Private draft link opens its owner-only editor');
dashCheck(dashboardMirrorNextSteps(['content_counts'=>[],'stats'=>['pending_approvals'=>2]])[0][1]===2,'Pending-only dashboard no longer displays an empty panel');
dashCheck(dashboardMirrorNextSteps(['content_counts'=>[],'stats'=>['pending_approvals'=>0]])===[],'Truly empty next steps remains empty');
dashCheck(dashTimeAgo('2026-11-04 12:00:00',strtotime('2026-11-04 12:01:00'))==='1 min ago','Relative timestamps');
dashCheck(dashTimeAgo('2026-11-04 12:00:00',strtotime('2026-11-04 11:00:00'))!=='Just now','Future dates are not labelled just now');
// Analytics use existing normalization and historical URL grouping.
$_SESSION=['user_id'=>1,'user_role'=>'Super Admin'];
// School-local event times must give the same count under UTC and Manila DB clocks.
$eventNow=dashNow();
$db->prepare('UPDATE events SET start_date=? WHERE event_id=1')->execute([$eventNow->modify('+1 hour')->format('Y-m-d H:i:s')]);
$db->prepare('UPDATE events SET start_date=? WHERE event_id=2')->execute([$eventNow->modify('-1 hour')->format('Y-m-d H:i:s')]);
$db->prepare('UPDATE events SET start_date=? WHERE event_id=4')->execute([$eventNow->modify('+2 hours')->format('Y-m-d H:i:s')]);
$originalTimezone=$db->query('SELECT @@session.time_zone')->fetchColumn();
try{
    foreach(['+00:00','+08:00'] as $timezone){
        $db->prepare('SET time_zone = ?')->execute([$timezone]);
        $eventData=roleDashboardData($db);
        dashCheck($eventData['stats']['upcoming_events']===2,'Upcoming excludes an event started one hour ago in Manila regardless of DB timezone.');
    }
}finally{$db->prepare('SET time_zone = ?')->execute([$originalTimezone]);}
$db->exec("INSERT INTO pages VALUES('history','History','sections'),('programs','Programs','sections')");
recordWebsitePublication($db,['page'=>'history','sub'=>'','title'=>'History','route'=>'/pages/about/gnc-history.php?ce_preview=1&token=not-retained']);
$db->exec("UPDATE activity_logs SET created_at='2100-01-01 10:00:00' WHERE table_name='website_pages'");
$_SESSION['user_id']=2;
recordWebsitePublication($db,['page'=>'history','sub'=>'','title'=>'Updated History','route'=>'/pages/about/gnc-history.php']);
$historyId=(int)$db->lastInsertId();$db->exec("UPDATE activity_logs SET created_at='2100-01-02 10:00:00' WHERE log_id=$historyId");
recordWebsitePublication($db,['page'=>'programs','sub'=>'bscs','title'=>'Computer Science','route'=>'/pages/academics/college/program.php?slug=bscs&ce_preview=1']);
$programId=(int)$db->lastInsertId();$db->exec("UPDATE activity_logs SET created_at='2099-12-01 10:00:00' WHERE log_id=$programId");
$db->exec("INSERT INTO activity_logs(user_id,action,table_name,description,created_at) VALUES(1,'UPDATE','page_sections','Saved draft for history/intro','2101-01-01 10:00:00'),(1,'PUBLISH','website_pages','invalid-json','2101-01-01 10:00:00'),(1,'PUBLISH','page_sections','Published history/intro','2098-01-01 10:00:00')");
$d=roleDashboardData($db);$pageRows=array_values(array_filter($d['recent'],fn($item)=>$item['type']==='page'));
dashCheck(count($pageRows)===2,'Repeated page publications and legacy sections group into one row per page');
dashCheck($d['recent'][0]['title']==='Updated History'&&(int)$d['recent'][0]['author_id']===2,'Latest publisher and publication time drive the row');
dashCheck(count($d['recent'])<=6&&$pageRows[0]['status']==='published','Published page rows share the six-item content limit');
dashCheck(dashboardContentUrl($pageRows[1])==='/admin/content-edit.php?page=programs&sub=bscs','Entity publication links open the correct subpage');
dashCheck(!str_contains($db->query("SELECT description FROM activity_logs WHERE table_name='website_pages' ORDER BY log_id LIMIT 1")->fetchColumn(),'not-retained'),'Preview and secret query fields are not retained');
$_SESSION=['user_id'=>1,'user_role'=>'Content Editor'];
$visible=dashboardPublishedPages($db);
dashCheck(count($visible)===(hasPermission('manage_page_content')?2:1)&&$visible[0]['page']==='history','Editor page summaries follow existing page permissions');
$_SESSION['user_role']='The Mirror';
dashCheck(dashboardPublishedPages($db)===[],'The Mirror cannot see page publication summaries');
try{recordWebsitePublication($db,['page'=>'history','route'=>'/pages/about/gnc-history.php','title'=>'History']);throw new LogicException('Unauthorized recording allowed.');}catch(RuntimeException $expected){dashCheck(true,'Only Super Admin records direct publications');}
$_SESSION['user_role']='Super Admin';
try{websitePublicationMetadata(['page'=>'history','route'=>'https://example.com/','title'=>'Bad']);throw new LogicException('External route accepted.');}catch(InvalidArgumentException $expected){dashCheck(true,'External editor routes rejected');}
dashCheck(activityLogDescription(['action'=>'PUBLISH','table_name'=>'website_pages','description'=>json_encode(['title'=>'History','page'=>'history'])])==='Published website page: History','Publication logs retain a readable description');

$db->exec('CREATE TEMPORARY TABLE page_visits(page_id INT PRIMARY KEY,page_url VARCHAR(255),page_title VARCHAR(255),visit_count INT,last_visited DATETIME)');
$db->exec('CREATE TEMPORARY TABLE page_visits_daily(page_id INT,visit_date DATE,views INT)');
$db->exec('CREATE TEMPORARY TABLE site_visitors(visitor_hash VARCHAR(64),visit_date DATE)');
$db->exec("INSERT INTO page_visits VALUES(1,'/pages/about/gnc-history.php','School history',12,NOW()),(2,'/pages/about/gnc-history','School history',8,NOW()),(3,'/admin/users.php','Private page',999,NOW())");
require_once __DIR__.'/../components/page-analytics.php';$today=gncVisitorToday();
$db->prepare('INSERT INTO page_visits_daily VALUES(1,?,3),(2,?,2),(3,?,999)')->execute([$today,$today,$today]);
$db->prepare("INSERT INTO site_visitors VALUES('one',?),('one',?),('two',?)")->execute([$today,$today,$today]);
$yesterday=(new DateTimeImmutable($today))->modify('-1 day')->format('Y-m-d');
$db->prepare("INSERT INTO site_visitors VALUES('one',?),('past-only',?)")->execute([$yesterday,$yesterday]);
$a=dashboardAnalytics($db,7);
dashCheck($a['total_views']===20&&$a['today_views']===5,'Recorded views exclude private pages');
dashCheck(count($a['top'])===1&&$a['most_visited']['visit_count']===20,'Historical aliases are combined');
dashCheck($a['unique_visitors']===3,'All-time visitors count returning visitors once across dates');
dashCheck($a['today_visitors']===2,'Today visitors exclude past-only visitors and duplicate daily identifiers');
dashCheck(count($a['by_date'])===7&&array_sum($a['by_date'])===5,'Zero-filled seven-day trend');
$db->exec('DELETE FROM site_visitors');
$a=dashboardAnalytics($db,30);
dashCheck($a['today_visitors']===0&&$a['unique_visitors']===0,'Empty visitor tracking returns zero totals');
$db->exec('ALTER TABLE site_visitors DROP COLUMN visit_date');
$a=dashboardAnalytics($db,14);
dashCheck($a['today_visitors']===null&&$a['unique_visitors']===null,'Unavailable visitor tracking is not reported as zero');
// Simulate unavailable columns without touching real tables.
$db->exec('ALTER TABLE page_visits_daily DROP COLUMN views');
try{dashboardAnalytics($db,7);throw new LogicException('Missing analytics must be reported.');}catch(PDOException $e){dashCheck(true,'Unavailable analytics are not zero');}
$_SESSION=['user_id'=>1,'user_role'=>'Super Admin'];
$db->exec('ALTER TABLE rbac_submissions DROP COLUMN status');$d=roleDashboardData($db);
dashCheck($d['submission_counts']===null&&$d['stats']['pending_approvals']===null&&isset($d['errors']['submissions']),'Unavailable queue is not empty');
echo "$checks dashboard data, analytics and permission checks passed. No persistent data changed.\n";
