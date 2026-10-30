<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin/admin-functions.php';
require_once __DIR__ . '/../includes/event-audience.php';
require_once __DIR__ . '/../components/calendar-filters.php';
define('CURRENT_PROGRAM', 'EVENT_AUDIENCE_TEST');
require_once __DIR__ . '/../components/bulletin-board.php';
function audienceCheck(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$db = getDB();
audienceCheck(eventDepartmentsReady(), 'Run the department migration first');
$departments = array_values(array_filter(getDepartments(), static fn($d) => !empty($d['slug']) && empty($d['archived_at'])));
audienceCheck(count($departments) > 1, 'Two departments are needed for isolation checks');
$first = $departments[0]; $second = $departments[1];
$programs = $db->query('SELECT program_code, dept_id FROM programs')->fetchAll(PDO::FETCH_ASSOC);
$program = null;
foreach ($programs as $row) if ((int)$row['dept_id'] === (int)$first['dept_id']) { $program = $row['program_code']; break; }
audienceCheck($program !== null, 'A program in the first department is required');
validateEventProgramDepartment((int)$first['dept_id'], 'ALL');
validateEventProgramDepartment((int)$first['dept_id'], $program);
try { validateEventProgramDepartment((int)$second['dept_id'], $program); throw new RuntimeException('Mismatched department accepted'); }
catch (InvalidArgumentException $e) {}
try { normalizeEventDepartment('not-a-department'); throw new RuntimeException('Invalid department accepted'); }
catch (InvalidArgumentException $e) {}

// Transactional fixtures verify public filters without publishing real content.
$db->beginTransaction();
try {
    $insert = $db->prepare("INSERT INTO events (title,start_date,status,program,dept_id) VALUES (?,DATE_ADD(NOW(), INTERVAL 2 DAY),'published',?,?)");
    $ids = [];
    foreach ([['ALL', $first['dept_id']], ['ALL', $second['dept_id']], ['ALL', null], [$program, null]] as $i => [$code,$department]) {
        $insert->execute(['Event audience test ' . $i,$code,$department]); $ids[] = (int)$db->lastInsertId();
    }
    $matches = static function (array $query) use ($db, $ids): array {
        $filter = publicCalendarFilters($query);
        $stmt = $db->prepare('SELECT e.event_id FROM events e WHERE e.event_id IN (' . implode(',', $ids) . ')' . $filter['condition'] . ' ORDER BY e.event_id');
        $stmt->execute($filter['params']);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    };
    audienceCheck($matches(['department'=>$first['slug']]) === [$ids[0],$ids[3]], 'Department filter includes whole-department and legacy program events only');
    audienceCheck($matches(['program'=>$program]) === [$ids[0],$ids[3]], 'Program filter includes matching department-wide events');
    audienceCheck(count($matches([])) === 4, 'Unfiltered calendar includes all audiences');
    $bulletinIds = array_map('intval', array_column(gnc_get_global_events($program, 10000), 'event_id'));
    audienceCheck(in_array($ids[0],$bulletinIds,true) && in_array($ids[2],$bulletinIds,true) && in_array($ids[3],$bulletinIds,true), 'Program bulletin includes department, everyone and matching program');
    audienceCheck(!in_array($ids[1],$bulletinIds,true), 'Other department excluded from program bulletin');
} finally { $db->rollBack(); }

$testAdmin = $db->query("SELECT user_id,credential_version FROM users WHERE role='Super Admin' AND status='active' AND must_change_password=0 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$userId = (int)($testAdmin['user_id'] ?? 0);
audienceCheck($userId > 0, 'An active admin is required');
$sessionId = 'gnc-events-test-' . bin2hex(random_bytes(12));
session_id($sessionId); session_start();
$_SESSION = ['user_id'=>$userId,'user_role'=>'Super Admin','credential_version'=>(int)$testAdmin['credential_version'],'_initiated'=>true,'last_activity'=>time()];
$cookie = session_name() . '=' . $sessionId; session_write_close();
$request = static function (?array $body, string $cookie) {
    $curl = curl_init('http://localhost/admin/events.php');
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_COOKIE=>$cookie,CURLOPT_TIMEOUT=>20]);
    if ($body !== null) curl_setopt_array($curl,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body]);
    $result = curl_exec($curl);
    if ($result === false) throw new RuntimeException(curl_error($curl));
    $status = curl_getinfo($curl,CURLINFO_RESPONSE_CODE); curl_close($curl);
    return [$status,$result];
};
$createdId = null; $token = null;
try {
    [$status,$html] = $request(null,$cookie);
    audienceCheck($status === 200 && strpos($html,'id="eventDepartment"') !== false, 'Department dropdown renders');
    audienceCheck((bool)preg_match('/id="csrfToken" value="([^"]+)"/', $html,$match), 'Event CSRF token renders');
    $token = $match[1];
    $base = ['ajax_action'=>'create','csrf_token'=>$token,'title'=>'Temporary event department HTTP check','start_date'=>date('Y-m-d'),'status'=>'draft','dept_id'=>(string)$first['dept_id']];
    [$status,$response] = $request($base,$cookie); $data = json_decode($response,true);
    audienceCheck(!empty($data['success']), 'Department-only event saves without a program: ' . $response);
    $createdId = (int)$data['event_id'];
    [$status,$response] = $request(['ajax_action'=>'fetch_one','csrf_token'=>$token,'event_id'=>$createdId],$cookie);
    $saved = json_decode($response,true)['event'];
    audienceCheck((int)$saved['dept_id'] === (int)$first['dept_id'] && $saved['program'] === 'ALL', 'Department and optional program persist on edit');
    if(eventAudienceGroupsReady()) {
        foreach(['basic-education','college'] as $group) {
            [$status,$response]=$request(array_merge($base,['ajax_action'=>'update','event_id'=>$createdId,'dept_id'=>'group:'.$group]),$cookie);
            audienceCheck(!empty(json_decode($response,true)['success']),'Department group saves through normal Events editor');
            [$status,$response]=$request(['ajax_action'=>'fetch_one','csrf_token'=>$token,'event_id'=>$createdId],$cookie);
            $groupEvent=json_decode($response,true)['event'];
            audienceCheck($groupEvent['dept_id']===null&&$groupEvent['audience_group']===$group&&$groupEvent['status']==='draft','Group persists without publishing or duplicating the event');
            [$status,$response]=$request(['ajax_action'=>'update','csrf_token'=>$token,'event_id'=>$createdId,'title'=>$base['title'],'start_date'=>$base['start_date'],'status'=>'draft'],$cookie);
            audienceCheck(!empty(json_decode($response,true)['success']),'Edits omitting audience preserve the saved group');
        }
    }
    [$status,$response] = $request(array_merge($base,['ajax_action'=>'update','event_id'=>$createdId,'dept_id'=>(string)$second['dept_id']]),$cookie);
    audienceCheck(!empty(json_decode($response,true)['success']), 'Department can be changed without choosing a program');
    [$status,$response] = $request(array_merge($base,['dept_id'=>(string)$second['dept_id'],'program'=>$program]),$cookie);
    audienceCheck(empty(json_decode($response,true)['success']), 'Mismatched program is rejected');
    [$status,$response] = $request(array_merge($base,['dept_id'=>'999999999']),$cookie);
    audienceCheck(empty(json_decode($response,true)['success']), 'Invalid department is rejected');
    [$status,$response] = $request(array_merge($base,['csrf_token'=>'invalid']),$cookie);
    audienceCheck(empty(json_decode($response,true)['success']), 'Invalid CSRF is rejected');
    // Authorization refreshes identity from the database; changing only the
    // session role cannot represent a restricted user on current auth code.
    $restricted = $db->query("SELECT user_id,role,credential_version FROM users WHERE status='active' AND role IN ('The Mirror','Office Staff') AND must_change_password=0 LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($restricted) {
        session_id($sessionId); session_start();
        $_SESSION['user_id']=(int)$restricted['user_id']; $_SESSION['user_role']=$restricted['role']; $_SESSION['credential_version']=(int)$restricted['credential_version']; session_write_close();
        [$status,$response] = $request($base,$cookie);
        audienceCheck($status === 403 && empty(json_decode($response,true)['success']), 'Unauthorized event creation rejected');
    } else { echo "SKIP: restricted-role HTTP check requires an active The Mirror account.\n"; }
} finally {
    if ($createdId) $db->prepare("DELETE FROM events WHERE event_id=? AND title='Temporary event department HTTP check'")->execute([$createdId]);
    if ($token) $db->prepare('DELETE FROM csrf_tokens WHERE user_id=? AND token=?')->execute([$userId,$token]);
    session_id($sessionId); session_start(); $_SESSION=[]; session_destroy();
}
echo "PASS: department-only create/edit, optional program persistence, validation, CSRF, permissions, department/program calendar filtering and program bulletin isolation. Fixtures cleaned up.\n";
