<?php
require_once __DIR__.'/../includes/person-photo-check.php';
$checks = 0;
function checkPhoto(bool $ok, string $message): void {
    global $checks;
    if (!$ok) throw new RuntimeException($message);
    $checks++;
}
$profiles = [
    ['profile_key'=>'person:1','name'=>'Existing Person','image'=>'/uploads/example.jpg'],
    ['profile_key'=>'office:2','name'=>'Other Person','image'=>'/uploads/other.jpg'],
];
$r = personPhotoDecision(['person:1'=>0.55,'office:2'=>0.4], $profiles);
checkPhoto(!$r['ok'] && count($r['matches']) === 2, 'High similarity blocks and includes all comparisons.');
checkPhoto(strpos($r['message'], '(Existing Person)') !== false, 'Warning includes existing name.');
checkPhoto($r['matches'][0]['high_confidence'], 'Upper threshold is inclusive.');
$r = personPhotoDecision(['person:1'=>0.363,'office:2'=>0.1], $profiles);
checkPhoto($r['ok'] && count($r['matches']) === 1, 'Lower threshold warns without blocking.');
$r = personPhotoDecision(['person:1'=>0.3629,'office:2'=>-1], $profiles);
checkPhoto($r['ok'] && !$r['matches'], 'Below threshold is clear.');
foreach ([[], ['person:1'=>NAN,'office:2'=>0], ['person:1'=>1.1,'office:2'=>0]] as $scores) {
    try { personPhotoDecision($scores, $profiles); throw new LogicException('Incomplete or invalid results accepted.'); }
    catch (RuntimeException $expected) { $checks++; }
}
checkPhoto(personPhotoName('  Sir   MICH ') === personPhotoName('sir mich'), 'Names normalize case and whitespace.');
foreach (['/config/config.php', '/uploads/../../config/config.php', 'https://example.com/photo.jpg', '//example.com/a.jpg'] as $path) {
    checkPhoto(personPhotoPath($path) === null, 'Unsafe paths rejected.');
}
checkPhoto(!personPhotoUnavailable()['ok'], 'Runtime outage fails closed.');
// Test the complete policy against isolated tables, without touching application data.
$db = class_exists('Pdo\\Sqlite') ? new Pdo\Sqlite('sqlite::memory:') : new PDO('sqlite::memory:');
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$concat = static fn(...$parts) => implode('', $parts);
if (method_exists($db, 'createFunction')) $db->createFunction('CONCAT', $concat);
else $db->sqliteCreateFunction('CONCAT', $concat);
function getDB(): PDO { global $db; return $db; }
function rbacIsSuper(): bool { return ($_SESSION['user_role'] ?? '') === 'Super Admin'; }
$db->exec('CREATE TABLE admin_settings(setting_key TEXT PRIMARY KEY,setting_value TEXT);
CREATE TABLE media_library(media_id INTEGER,file_path TEXT);
CREATE TABLE admin_sections(section_key TEXT PRIMARY KEY);
CREATE TABLE admin_people(person_id INTEGER,name TEXT,media_id INTEGER,image_path TEXT,section_key TEXT);
CREATE TABLE admin_offices(office_id INTEGER,head_name TEXT,media_id INTEGER,image_path TEXT,section_key TEXT);
CREATE TABLE departments(dept_id INTEGER,dean_name TEXT,dean_media_id INTEGER);
CREATE TABLE homepage_president(id INTEGER,name TEXT,media_id INTEGER);');
$db->exec("INSERT INTO admin_sections VALUES('trustees'),('administration'),('corporate'),('services')");
$image = '/assets/images/test-image.png';
checkPhoto(personPhotoPath($image) !== null, 'Fixture exists.');
$db->prepare("INSERT INTO admin_people VALUES(1,?,NULL,?,'trustees')")->execute(['Sir Mich', $image]);
$db->prepare("INSERT INTO admin_offices VALUES(2,?,NULL,?,'trustees')")->execute(['Sir Mich', $image]);
$compare = static function ($path, $profiles) {
    return ['complete'=>true,'faces'=>1,'scores'=>array_fill_keys(array_column($profiles,'profile_key'), 0.8)];
};
$_SESSION = ['user_role'=>'Content Editor'];
$r = personPhotoCheck(personPhotoPath($image), 'Sir CJ', 'person:0', $compare, 'trustees');
checkPhoto(!$r['ok'] && !$r['can_review'] && count($r['matches']) === 2, 'Different entries block within the same page.');
$same = personPhotoCheck(personPhotoPath($image), ' sir   MICH ', 'person:1', $compare, 'trustees');
checkPhoto(!$same['ok'] && count($same['matches']) === 1, 'Same name does not bypass duplicates within a page.');
$own = personPhotoCheck(personPhotoPath($image), 'Renamed Individual', 'person:1', $compare, 'trustees');
checkPhoto(count($own['matches']) === 1 && $own['matches'][0]['key'] === 'office:2', 'Target profile excluded from comparisons.');
$db->exec("INSERT INTO admin_people VALUES(3,'Missing Photo',NULL,'/uploads/not-present.jpg','trustees')");
$missing = personPhotoCheck(personPhotoPath($image), 'Sir Mich', 'person:1', $compare, 'trustees');
checkPhoto($missing['coverage_notice'] !== '', 'Missing legacy images do not silently count as compared.');
$_SESSION['user_role'] = 'Super Admin';
$r = personPhotoCheck(personPhotoPath($image), 'Sir CJ', 'person:0', $compare, 'trustees');
$_SESSION['person_photo_reviews'][$r['review_key']] = time()+900;
checkPhoto(personPhotoCheck(personPhotoPath($image),'Sir CJ','person:0',$compare, 'trustees')['ok'], 'Authorized bound review unlocks save.');
$_SESSION['user_role'] = 'Content Editor';
checkPhoto(!personPhotoCheck(personPhotoPath($image),'Sir CJ','person:0',$compare, 'trustees')['ok'], 'Role downgrade invalidates review.');
$_SESSION['user_role'] = 'Super Admin';
checkPhoto(!personPhotoCheck(personPhotoPath($image),'Someone Else','person:0',$compare, 'trustees')['ok'], 'Review cannot authorize another name.');
$db->exec("UPDATE admin_offices SET head_name='Changed Person'");
checkPhoto(!personPhotoCheck(personPhotoPath($image),'Sir CJ','person:0',$compare, 'trustees')['ok'], 'Inventory change invalidates review.');
$r = personPhotoCheck(personPhotoPath($image),'Sir CJ','person:0',$compare, 'trustees');
$_SESSION['person_photo_reviews'][$r['review_key']] = time()-1;
checkPhoto(!personPhotoCheck(personPhotoPath($image),'Sir CJ','person:0',$compare, 'trustees')['ok'], 'Expired review does not unlock.');
$r = personPhotoCheck(personPhotoPath($image),'Sir CJ','person:0',static function () { throw new RuntimeException('test'); }, 'trustees');
checkPhoto(!$r['ok'] && !$r['checked'], 'Comparison failure blocks saves.');
// Page boundaries apply before inference and review fingerprints.
$db->exec("UPDATE admin_offices SET section_key='administration'");
$own = personPhotoCheck(personPhotoPath($image), 'Sir Mich', 'person:1', $compare, 'administration');
checkPhoto($own['ok'] && !$own['matches'], 'Own portrait excluded; client cannot override persisted section.');
checkPhoto(personPhotoCheck(personPhotoPath($image),'New','person:0',$compare,'administration')['ok'] === false, 'New entry compares only destination section.');
checkPhoto(personPhotoCheck(personPhotoPath($image),'New','person:0',$compare,'corporate')['ok'], 'Other pages allow the same face.');
checkPhoto(personPhotoCheck(personPhotoPath($image),'New','office:0',$compare,'services')['ok'], 'Student services is independent.');
checkPhoto(personPhotoCheck(personPhotoPath($image),'President','president:1',$compare)['ok'], 'Homepage excludes administration photos.');
$db->exec("INSERT INTO departments VALUES(10,'Dean',NULL),(11,'Other Dean',NULL)");
checkPhoto(personPhotoCheck(personPhotoPath($image),'Dean','dean:10',$compare)['ok'], 'Department page is independent.');
foreach (['', 'unknown'] as $section) {
    checkPhoto(personPhotoCheck(personPhotoPath($image),'New','person:0',$compare,$section)['reason'] === 'invalid_scope', 'Missing or unknown scope fails closed.');
}
checkPhoto(personPhotoCheck(personPhotoPath($image),'New','person:999',$compare,'trustees')['reason'] === 'invalid_scope', 'Unknown target cannot evade scoping.');

// The real persisted setting must bypass comparison before inventory/worker access.
$db->exec("INSERT INTO admin_settings VALUES('ai_image_checker_enabled','0')");
$workerCalled=false;
$disabled=personPhotoCheck(personPhotoPath($image),'Sir CJ','person:0',static function()use(&$workerCalled){$workerCalled=true;throw new RuntimeException('Worker must not run while disabled');});
checkPhoto($disabled===['checked'=>false,'ok'=>true,'reason'=>'disabled','message'=>''],'Disabled checker returns an explicit successful skip.');
checkPhoto(!$workerCalled,'Disabled checker never runs face comparison.');
checkPhoto(!isset($disabled['matches'],$disabled['review_key']),'Disabled checks expose no duplicate matches or review tokens.');
checkPhoto(personPhotoCheck(personPhotoPath($image),'','person:0',$compare, 'trustees')['reason']==='disabled','Disabled checks do not require a name for face processing.');
$db->exec("UPDATE admin_settings SET setting_value='1'");
checkPhoto(!personPhotoCheck(personPhotoPath($image),'Sir CJ','person:0',$compare, 'trustees')['ok'],'Re-enabling restores duplicate blocking without a process restart.');
$db->exec("UPDATE admin_settings SET setting_value='0'");
// With checking off there must be no attempt to read the comparison inventory.
$db->exec('DROP TABLE admin_people');
checkPhoto(personPhotoCheck(personPhotoPath($image),'Sir CJ','person:0',$compare, 'trustees')['reason']==='disabled','Disabled checks skip inventory preparation too.');

echo "Personnel photo policy: $checks checks passed.\n";
