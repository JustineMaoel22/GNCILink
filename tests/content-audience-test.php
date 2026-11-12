<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../admin/admin-functions.php';
define('CURRENT_PROGRAM', 'A1');
require_once __DIR__.'/../components/bulletin-board.php';
require_once __DIR__.'/../includes/content-submissions.php';
$db = getDB();
$db->exec('CREATE TEMPORARY TABLE departments (dept_id INT PRIMARY KEY, dept_name VARCHAR(50)) ENGINE=InnoDB');
$db->exec("INSERT INTO departments VALUES (1,'One'),(2,'Two'),(3,'Other')");
$db->exec('CREATE TEMPORARY TABLE programs (program_code VARCHAR(20) PRIMARY KEY, program_name VARCHAR(50), dept_id INT) ENGINE=InnoDB');
$db->exec("INSERT INTO programs VALUES ('A1','Alpha One',1),('A2','Alpha Two',1),('B1','Beta One',2),('B2','Beta Two',2),('C','Other',3)");
$db->exec('CREATE TEMPORARY TABLE announcements (announcement_id INT PRIMARY KEY, title VARCHAR(50), content TEXT, slug VARCHAR(50), status VARCHAR(20), program VARCHAR(20), dept_id INT NULL, published_at DATETIME, created_at DATETIME) ENGINE=InnoDB');
foreach (['announcement', 'news'] as $type) {
    $db->exec("CREATE TEMPORARY TABLE {$type}_departments ({$type}_id INT, dept_id INT, PRIMARY KEY ({$type}_id,dept_id)) ENGINE=InnoDB");
    $db->exec("CREATE TEMPORARY TABLE {$type}_programs ({$type}_id INT, program_code VARCHAR(20), PRIMARY KEY ({$type}_id,program_code)) ENGINE=InnoDB");
}
$map = getProgramDepartmentMap();
$cases = [
    [[1], [], ['A1','A2']],
    [[1], ['A1','A2'], ['A1','A2']],
    [[1], ['A1'], ['A1']],
    [[1,2], ['B1'], ['A1','A2','B1']],
    [[1,2], ['A1'], ['A1','B1','B2']],
    [[1,2], ['A1','B1'], ['A1','B1']],
    [[], ['A1'], ['A1']],
    [[], [], ['A1','A2','B1','B2','C']],
];
$check = static function ($ok, $message) { if (!$ok) throw new RuntimeException($message); };
$db->beginTransaction();
try {
    foreach ($cases as $index => [$departments, $programs, $expected]) {
        $id = $index + 1;
        $audience = validatedContentAudience(array_map('strval', $departments), $programs);
        $db->prepare("INSERT INTO announcements VALUES (?, 'Notice','<p>Notice</p>',?,'published',?,?,NOW(),NOW())")->execute([$id, 'notice-'.$id, $audience['primary_program'], $audience['primary_dept_id']]);
        saveAnnouncementAudience($db, $id, $departments, $programs);
        saveNewsAudience($db, $id, $audience);
        $read = getNewsAudiencesBatch($db, [['news_id'=>$id]])[$id];
        $check($read['dept_ids'] === $departments && $read['programs'] === $programs, 'News audience round trip failed.');
        $readAnnouncement = getAnnouncementAudience($db, $id, $audience['primary_dept_id'], $audience['primary_program']);
        $check($readAnnouncement === $read, 'Announcement and News audience round trips differ.');
        foreach (array_keys($map) as $program) {
            $check(contentAudienceMatches($read, $program, null, $map) === in_array($program, $expected, true), 'Program filtering mismatch for case '.$id.' / '.$program);
            $bulletinIds = array_column(gnc_get_global_announcements($program, 50), 'announcement_id');
            $check(in_array($id, array_map('intval', $bulletinIds), true) === in_array($program, $expected, true), 'Bulletin filtering mismatch for case '.$id.' / '.$program);
        }
        foreach ([1,2,3] as $department) {
            $expectedDepartment = false;
            foreach ($expected as $program) if ((int)$map[$program] === $department) $expectedDepartment = true;
            $check(contentAudienceMatches($read, null, $department, $map) === $expectedDepartment, 'Department filtering mismatch.');
        }
    }
    // Reopening and clearing a previous program restriction must retain the department.
    saveNewsAudience($db, 3, validatedContentAudience(['1'], []));
    $read = getNewsAudiencesBatch($db, [['news_id'=>3]])[3];
    $check($read === ['dept_ids'=>[1], 'programs'=>[]], 'Cleared News programs were not preserved.');
    $check(!contentAudienceMatches($read, 'B1', null, $map), 'Empty programs must not expand to other departments.');
    foreach ([[['999'],[]], [['1'],['B1']], [['1'],['INVALID']], [[['bad']],[]]] as [$departments,$programs]) {
        $rejected = false;
        try { validatedContentAudience($departments,$programs); } catch (InvalidArgumentException $e) { $rejected = true; }
        $check($rejected, 'Invalid department/program selection was accepted.');
    }
    foreach ([['dept_ids'=>['1','2'],'programs'=>['B1']],['dept_ids'=>['1'],'programs'=>[]],['dept_ids'=>[],'programs'=>[]]] as $selection) {
        // Approval replay flattens POST fields for cURL. Empty arrays are omitted,
        // so the explicit marker must retain clearing instead of restoring legacy values.
        $selection['audience_present']='1';
        parse_str(http_build_query(rbacFlattenFields($selection)), $replayed);
        $restored=validatedContentAudience($replayed['dept_ids']??[], $replayed['programs']??[]);
        $check($restored['dept_ids']===array_map('intval',$selection['dept_ids']) && $restored['programs']===$selection['programs'], 'Approval replay changed the audience.');
    }
    $db->rollBack();
    $check((int)$db->query('SELECT COUNT(*) FROM news_departments')->fetchColumn() === 0, 'Audience writes must roll back.');
    echo "PASS: audience round trips, department-local restrictions, cleared/all/specific programs, defaults, visitor matching, bulletin SQL, validation and rollback. Temporary fixtures only.\n";
} finally { if ($db->inTransaction()) $db->rollBack(); }
