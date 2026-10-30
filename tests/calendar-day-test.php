<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/event-display.php';
$db=new PDO('sqlite::memory:');$db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$db->exec("CREATE TABLE categories(category_id INTEGER,category_name TEXT,category_color TEXT);
CREATE TABLE events(event_id INTEGER,title TEXT,start_date TEXT,end_date TEXT,status TEXT,category_id INTEGER,location TEXT,description TEXT,private_note TEXT);
INSERT INTO categories VALUES(1,'Holiday','#EABA3B'),(2,'School Event','#1F5E2C'),(3,'Suspension',NULL);
INSERT INTO events VALUES
(1,'Holiday','2026-11-01 00:00:00','2026-11-03 00:00:00','published',1,'','Full holiday description','PRIVATE'),
(2,'<img src=x onerror=alert(1)>','2026-11-02 09:00:00','2026-11-02 11:00:00','published',2,'Gym','First line\nSecond line','PRIVATE'),
(3,'Suspension','2026-11-02 00:00:00',NULL,'published',3,'','Weather suspension','PRIVATE'),
(4,'Draft','2026-11-02 00:00:00',NULL,'draft',2,'','Hidden','PRIVATE'),
(5,'Archived','2026-11-02 00:00:00',NULL,'archived',2,'','Hidden','PRIVATE'),
(6,'Pending','2026-11-02 00:00:00',NULL,'pending',2,'','Hidden','PRIVATE');");
function dayCheck(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
$scope=['condition'=>'','params'=>[]];
$events=publicEventsForDate($db,'2026-11-02',$scope);
dayCheck(count($events)===3,'All published activities on a day, excluding draft/pending/archived');
dayCheck(!str_contains(json_encode($events),'PRIVATE'),'Only whitelisted public fields are returned');
dayCheck(count(publicEventsForDate($db,'2026-11-03',$scope))===1,'Multi-day activity included on final day');
dayCheck(publicEventsForDate($db,'2026-11-04',$scope)===[],'Empty date');
dayCheck(count(publicEventsForDate($db,'2026-11-02',$scope,'School Event'))===1,'Page category restriction respected');
dayCheck(publicEventsForDate($db,'2026-11-02',$scope,"' OR 1=1 --")===[],'Category parameter cannot alter SQL');
dayCheck(count(publicEventsForDate($db,'2026-11-02',['condition'=>' AND e.event_id=?','params'=>[2]]))===1,'Department/program SQL scope carried through');
dayCheck(publicEventCategoryColor(null,'Suspension')==='#B3312D'&&publicEventCategoryColor('#abc')==='#aabbcc','Category colors shared with calendar');
dayCheck(publicEventCategoryColor('url(evil)','Holiday')==='#EABA3B','Unsafe category colors rejected');
echo "PASS: complete public details, multiple events, multi-day boundaries, empty dates, category/scope filters, private status/field exclusion and safe colors.\n";
