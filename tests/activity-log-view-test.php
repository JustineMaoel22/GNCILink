<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../admin/activity-log-view.php';
$checks=0;
function checkLogView(bool $condition, string $message): void {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
$db=getDB();
// Connection-scoped temporary tables shadow the real tables; no real logs or users
// are read, changed or deleted. They disappear when this process exits.
$db->exec('CREATE TEMPORARY TABLE users (user_id INT PRIMARY KEY,first_name VARCHAR(100),last_name VARCHAR(100),email VARCHAR(255))');
$db->exec('CREATE TEMPORARY TABLE activity_logs (log_id INT PRIMARY KEY,user_id INT NULL,action VARCHAR(255),table_name VARCHAR(100),record_id INT NULL,description TEXT,created_at DATETIME)');
$db->exec('CREATE TEMPORARY TABLE auth_security_logs (log_id INT PRIMARY KEY,user_id INT NULL,action VARCHAR(50),ip_address VARCHAR(45),description TEXT,created_at DATETIME)');
$db->exec("INSERT INTO users VALUES(1,'Juan','Cruz','juan@example.test'),(2,'Maria','Santos','maria@example.test')");
$insert=$db->prepare('INSERT INTO activity_logs VALUES(?,?,?,?,?,?,?)');
for($id=1;$id<=235;$id++) $insert->execute([$id,$id%2?1:2,'UPDATE','news',$id,'Updated news article '.$id,'2026-10-01 12:00:00']);
$insert->execute([236,null,'FACEBOOK_PUBLISH','announcements',12,'Published to Facebook','2026-11-04 23:59:59']);
$insert->execute([237,999,'IMPORT_PREVIEW','events',null,'Preview 100%_complete!','2026-11-05 00:00:00']);
$insert->execute([238,1,'LOGIN',null,null,'Successful login','2026-11-05 00:00:00']);
$db->exec("INSERT INTO auth_security_logs VALUES(1,NULL,'LOGIN_FAILED','127.0.0.1','Unsuccessful login','2026-11-05 00:00:00')");
$run=function(array $input=[]) use($db) { $filters=activityLogFilters($input); return [activityLogResults($db,$filters),$filters]; };
[$r,$f]=$run(); checkLogView($r['total']===238 && count($r['logs'])===25 && $r['logs'][0]['log_id']===238,'Newest first and complete count');
[$r,$f]=$run(['size'=>'100','page'=>'3']); checkLogView(count($r['logs'])===38 && $f['page']===3,'Records beyond the old 200-row limit');
[$r,$f]=$run(['page'=>'9999999']); checkLogView($f['page']===10 && count($r['logs'])===13,'Out-of-range page clamps');
[$r]=$run(['sort'=>'oldest','size'=>'10']); checkLogView($r['logs'][0]['log_id']===1 && count($r['logs'])===10,'Oldest first and stable timestamp tie ordering');
[$r]=$run(['q'=>'Juan Cruz']); checkLogView($r['total']===119,'Search full user name');
[$r]=$run(['q'=>'maria@example.test']); checkLogView($r['total']===117,'Search account identifier');
[$r]=$run(['q'=>'preview calendar import']); checkLogView($r['total']===1,'Search readable action label');
[$r]=$run(['q'=>'100%_complete!']); checkLogView($r['total']===1,'Literal SQL wildcard characters');
[$r]=$run(['q'=>"' OR 1=1 --"]); checkLogView($r['total']===0,'SQL injection remains literal');
[$r]=$run(['action'=>'FACEBOOK_PUBLISH','module'=>'announcements','user'=>'0','from'=>'2026-11-04','to'=>'2026-11-04']); checkLogView($r['total']===1,'Combined filters and inclusive end date');
[$r]=$run(['module'=>'__general']); checkLogView($r['total']===1,'General module');
[$r]=$run(['user'=>'999']); checkLogView($r['total']===1 && activityLogUser($r['logs'][0])==='Former user #999','Historical user remains accessible');
[$r]=$run(['source'=>'security','module'=>'authentication']); checkLogView($r['total']===1 && $r['logs'][0]['ip_address']==='127.0.0.1','Security source');
[$r]=$run(['q'=>'no such activity']); checkLogView($r['total']===0 && $r['pages']===1,'Empty results');
$f=activityLogFilters(['q'=>[],'from'=>"2026-01\0-01",'to'=>'2026-02-30','size'=>'11','source'=>[],'page'=>'-1','action'=>[]]);
checkLogView($f['from']==='' && $f['to']==='' && $f['size']===25 && $f['page']===1 && $f['q']==='', 'Malformed query inputs');
$f=activityLogFilters(['from'=>'2026-11-05','to'=>'2026-11-01']); checkLogView($f['from']==='2026-11-01','Reversed dates normalize');
foreach(['password=example','{"access_token":"example"}','Authorization: Bearer example','Temporary password is example','https://user:example@example.test'] as $description) {
    $row=['description'=>$description,'action'=>'UPDATE','table_name'=>'users'];
    checkLogView(str_contains(activityLogDescription($row),'hidden'),'Suppress credential-bearing legacy descriptions');
}
checkLogView(activityLogDescription(['description'=>'Changed own password; previous credentials revoked.','action'=>'PASSWORD_CHANGED','table_name'=>'users'])==='Changed own password; previous credentials revoked.','Keep safe audit descriptions');
echo "$checks activity log checks passed. No persistent data changed.\n";
