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
$db->exec('CREATE TEMPORARY TABLE rbac_submissions(submission_id INT PRIMARY KEY,user_id INT,title VARCHAR(500),status VARCHAR(20),revision INT,created_at DATETIME,updated_at DATETIME)');
$db->exec("INSERT INTO rbac_submissions VALUES(1,1,'My pending','pending',1,NOW(),NOW()),(2,2,'Other pending','pending',1,NOW(),NOW()),(3,2,'Another pending','pending',1,NOW(),NOW()),(4,1,'My revision','needs_revision',2,NOW(),NOW()),(5,2,'Other revision','needs_revision',3,NOW(),NOW()),(6,1,'Rejected proposal','rejected',1,NOW(),NOW()),(7,1,'Draft proposal','draft',1,NOW(),NOW())");
$db->exec('CREATE TEMPORARY TABLE page_sections(section_id INT PRIMARY KEY,review_status VARCHAR(20))');
$db->exec("INSERT INTO page_sections VALUES(1,'pending'),(2,'pending'),(3,'none')");
$db->exec('CREATE TEMPORARY TABLE activity_logs(log_id INT PRIMARY KEY,user_id INT,action VARCHAR(255),table_name VARCHAR(100),description TEXT,created_at DATETIME)');
$db->exec("INSERT INTO activity_logs VALUES(1,1,'FACEBOOK_PUBLISH','news','Published news',NOW()),(2,2,'UPDATE','users','password=private-test-value',NOW())");
foreach(['Super Admin','Content Editor','The Mirror','Content Admin','Office Staff'] as $role){
    $_SESSION=['user_id'=>1,'user_role'=>$role];$d=roleDashboardData($db);$own=rbacRole()==='The Mirror';$super=rbacIsSuper();
    dashCheck(!$d['errors'],'All summaries loaded');
    dashCheck($d['stats']['total_announcements']===($own?2:5),'Role-scoped announcement count');
    dashCheck($d['stats']['total_news']===($own?1:3),'Role-scoped news count');
    dashCheck($d['stats']['published_content']===($own?2:7),'Published count across permitted modules');
    dashCheck($d['stats']['pending_approvals']===($super?3:1),'Proposal counts use actual queue');
    dashCheck(($d['submission_counts']['needs_revision']??0)===($super?2:1),'Revision state count');
    dashCheck($d['recent'][0]['title']==='Newest news','News is included and ordered by last update even with announcements');
    dashCheck(count($d['recent'])<=6&&count($d['submissions'])<=6,'Bounded recent lists');
    dashCheck($d['stats']['upcoming_events']===($own?null:2),'Upcoming excludes past and draft events');
    dashCheck($d['stats']['total_events']===($own?null:4),'Event module visibility');
    dashCheck($d['stats']['total_users']===($super?2:null),'User statistics stay restricted');
    dashCheck(count($d['logs'])===($super?2:0),'Activity stays restricted');
    dashCheck($d['page_pending']===($super?2:null),'Legacy page review visibility');
    if(!$super)dashCheck(!array_intersect([2,3,5],array_column($d['submissions'],'submission_id')),'Other authors proposals never leak');
    if($own)dashCheck(!array_filter($d['recent'],fn($row)=>str_starts_with($row['title'],'Other')||$row['type']==='event'),'Mirror content stays owned');
}
dashCheck(str_contains(activityLogDescription(['action'=>'UPDATE','table_name'=>'users','description'=>'password=private-test-value']),'hidden'),'Dashboard descriptions suppress secrets');
dashCheck(dashboardContentUrl(['type'=>'news','id'=>3])==='/admin/news.php?modal=edit&id=3','Record-specific edit link');
dashCheck(dashboardContentUrl(['type'=>'event','id'=>3])==='/admin/events.php','Calendar management link');
dashCheck(dashTimeAgo('2026-11-04 12:00:00',strtotime('2026-11-04 12:01:00'))==='1 min ago','Relative timestamps');
dashCheck(dashTimeAgo('2026-11-04 12:00:00',strtotime('2026-11-04 11:00:00'))!=='Just now','Future dates are not labelled just now');
// Analytics use existing normalization and historical URL grouping.
$db->exec('CREATE TEMPORARY TABLE page_visits(page_id INT PRIMARY KEY,page_url VARCHAR(255),page_title VARCHAR(255),visit_count INT,last_visited DATETIME)');
$db->exec('CREATE TEMPORARY TABLE page_visits_daily(page_id INT,visit_date DATE,views INT)');
$db->exec('CREATE TEMPORARY TABLE site_visitors(visitor_hash VARCHAR(64),visit_date DATE)');
$db->exec("INSERT INTO page_visits VALUES(1,'/pages/about/gnc-history.php','School history',12,NOW()),(2,'/pages/about/gnc-history','School history',8,NOW()),(3,'/admin/users.php','Private page',999,NOW())");
require_once __DIR__.'/../components/page-analytics.php';$today=gncVisitorToday();
$db->prepare('INSERT INTO page_visits_daily VALUES(1,?,3),(2,?,2),(3,?,999)')->execute([$today,$today,$today]);
$db->prepare("INSERT INTO site_visitors VALUES('one',?),('one',?),('two',?)")->execute([$today,$today,$today]);
$a=dashboardAnalytics($db,7);
dashCheck($a['total_views']===20&&$a['today_views']===5,'Recorded views exclude private pages');
dashCheck(count($a['top'])===1&&$a['most_visited']['visit_count']===20,'Historical aliases are combined');
dashCheck($a['unique_visitors']===2,'Unique visitors are distinct from views');
dashCheck(count($a['by_date'])===7&&array_sum($a['by_date'])===5,'Zero-filled seven-day trend');
// Simulate unavailable columns without touching real tables.
$db->exec('ALTER TABLE page_visits_daily DROP COLUMN views');
try{dashboardAnalytics($db,7);throw new LogicException('Missing analytics must be reported.');}catch(PDOException $e){dashCheck(true,'Unavailable analytics are not zero');}
$_SESSION=['user_id'=>1,'user_role'=>'Super Admin'];
$db->exec('ALTER TABLE rbac_submissions DROP COLUMN status');$d=roleDashboardData($db);
dashCheck($d['submission_counts']===null&&$d['stats']['pending_approvals']===null&&isset($d['errors']['submissions']),'Unavailable queue is not empty');
echo "$checks dashboard data, analytics and permission checks passed. No persistent data changed.\n";
