<?php
/** Run only on local development: clones schema (no original data), then drops its own test DB. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../database/content-list-migration.php';

$db = getDB();
$source = (string)$db->query('SELECT DATABASE()')->fetchColumn();
if (!in_array(DB_HOST, ['db','localhost','127.0.0.1'], true)) {
    fwrite(STDERR, "Run this test against a local development server.\n"); exit(1);
}
$testDatabase = 'gnci_normalization_test_' . bin2hex(random_bytes(6));
$created = false;
$checks = 0;
function normalizedCheck(bool $ok, string $message): void {
    global $checks;
    if (!$ok) throw new RuntimeException($message);
    $checks++;
}
function normalizedOfficeServices(array $sections, int $officeId): ?array {
    foreach ($sections as $section) foreach ($section['offices'] as $office) {
        if ($office['id'] === $officeId) return $office['services'];
    }
    return null;
}
try {
    $schema = [];
    foreach ($db->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_NUM) as $table) {
        $quoted = '`' . str_replace('`', '``', $table[0]) . '`';
        $schema[] = $db->query("SHOW CREATE TABLE $quoted")->fetch(PDO::FETCH_NUM)[1];
    }
    $db->exec("CREATE DATABASE `$testDatabase` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $created = true;
    $db->exec("USE `$testDatabase`");
    // Foreign keys are checked again before inserting any fixtures.
    $db->exec('SET FOREIGN_KEY_CHECKS=0');
    try { foreach ($schema as $ddl) $db->exec($ddl); }
    finally { $db->exec('SET FOREIGN_KEY_CHECKS=1'); }
    // Start from a legacy schema even if the source development DB is already migrated.
    foreach (['office_services', 'achievement_achievers'] as $kind) {
        $definition = contentListDefinition($kind);
        if (!contentListLegacyColumnExists($db, $definition)) {
            $db->exec("ALTER TABLE {$definition['parent']} ADD COLUMN {$definition['legacy']} TEXT NULL");
        }
    }
    require_once __DIR__ . '/../admin/admin-functions.php';
    $_SESSION = ['user_role'=>'Super Admin'];
    $db->exec("INSERT INTO users (first_name,last_name,email,password,role,status) VALUES ('Normalization','Test','normalization@example.invalid','unused-test-password','Super Admin','active')");
    $_SESSION['user_id'] = (int)$db->lastInsertId();
    $db->exec("INSERT INTO admin_sections (section_key,title,layout) VALUES ('normalization-test','Test offices','offices')");
    normalizedCheck(!contentListsEnabled($db, true), 'Legacy schema starts disabled.');
    $officeSave = static function (int $id, string $services, string $name = 'Normalization office'): void {
        saveAdministrationOffice($id, 'normalization-test', $name, 'Test head', 'Head', '', '', '', '', $services, null, false);
    };
    $officeSave(0, " Counselling \r\nRecords\r\nCounselling ");
    $officeId = (int)$db->query("SELECT office_id FROM admin_offices WHERE section_key='normalization-test' LIMIT 1")->fetchColumn();
    normalizedCheck(normalizedOfficeServices(fetchAdministrationSections(), $officeId) === ['Counselling','Records','Counselling'], 'Legacy office create/read preserves duplicates.');
    $data = ['title'=>'Normalization achievement','description'=>'Test achievement','program_organization'=>'Test group','category_label'=>'Test','academic_year'=>'2026-2027','status'=>'published','is_featured'=>1,'achievers'=>" José Santos \r\n李明\nJosé Santos "];
    $achievementId = saveStudentAchievement($db, 0, $data, '/test-fixture-cover.jpg');
    normalizedCheck((int)studentAchievementById($db,$achievementId)['is_featured'] === 1, 'Featured status survives save.');
    $beforeOffice = $db->query('SELECT office_id,updated_at FROM admin_offices')->fetchAll();
    $beforeAchievement = $db->query('SELECT achievement_id,updated_at FROM student_achievements')->fetchAll();
    contentListMigrationApply($db);
    normalizedCheck(contentListsEnabled($db), 'Backfill activates normalized readers.');
    normalizedCheck($db->query('SELECT office_id,updated_at FROM admin_offices')->fetchAll() === $beforeOffice, 'Migration preserves office IDs and update timestamps.');
    normalizedCheck($db->query('SELECT achievement_id,updated_at FROM student_achievements')->fetchAll() === $beforeAchievement, 'Migration preserves achievement IDs and update timestamps.');
    normalizedCheck(normalizedOfficeServices(fetchAdministrationSections(), $officeId) === ['Counselling','Records','Counselling'], 'Office list backfills in order.');
    normalizedCheck(studentAchievementById($db,$achievementId)['achievers'] === "José Santos\n李明\nJosé Santos", 'Names backfill with Unicode and duplicates intact.');
    $officeSave($officeId, 'Changed service');
    contentListMigrationApply($db);
    normalizedCheck(normalizedOfficeServices(fetchAdministrationSections(), $officeId) === ['Changed service'], 'Reapplying cannot overwrite newer edits with legacy text.');
    $longService = str_repeat('s', 4500);
    $officeSave($officeId, $longService);
    normalizedCheck(normalizedOfficeServices(fetchAdministrationSections(), $officeId) === [$longService], 'Long service text is not truncated by aggregation limits.');
    // Empty lists must not fall back to the retained, now stale, legacy text.
    $officeSave($officeId, '');
    $data['achievers'] = '';
    saveStudentAchievement($db,$achievementId,$data,'/test-fixture-cover.jpg');
    normalizedCheck(normalizedOfficeServices(fetchAdministrationSections(), $officeId) === [], 'Clearing office services remains empty.');
    normalizedCheck(studentAchievementById($db,$achievementId)['achievers'] === '', 'Clearing names remains empty.');
    $officeSave($officeId, "Service two\nService one");
    $data['achievers'] = "Ana\nBen";
    saveStudentAchievement($db,$achievementId,$data,'/test-fixture-cover.jpg');
    unset($data['achievers']);
    saveStudentAchievement($db,$achievementId,$data,'/test-fixture-cover.jpg');
    normalizedCheck(studentAchievementById($db,$achievementId)['achievers'] === "Ana\nBen", 'Omitted names on a partial edit are retained.');
    // Existing approval/editor transactions must retain control of commit/rollback.
    $db->beginTransaction();
    $officeSave($officeId, 'Outer transaction edit');
    normalizedCheck($db->inTransaction(), 'Office save does not commit its caller transaction.');
    $db->rollBack();
    normalizedCheck(normalizedOfficeServices(fetchAdministrationSections(), $officeId) === ['Service two','Service one'], 'Outer rollback restores child rows.');
    // Simulate a database failure after the parent UPDATE and child DELETE.
    $db->exec("CREATE TRIGGER normalization_test_fail_service BEFORE INSERT ON admin_office_services FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Test child failure'");
    $db->beginTransaction();
    try {
        $officeSave($officeId, 'Must fail', 'Must roll back');
        throw new LogicException('Expected an office save failure.');
    } catch (Exception $expected) {
        normalizedCheck(!($expected instanceof LogicException), 'Child failure reaches the caller.');
    }
    normalizedCheck($db->inTransaction(), 'Failed nested save keeps the outer transaction open.');
    $db->commit();
    $db->exec('DROP TRIGGER normalization_test_fail_service');
    normalizedCheck(normalizedOfficeServices(fetchAdministrationSections(), $officeId) === ['Service two','Service one'], 'Child insert failure restores old services.');
    normalizedCheck($db->query('SELECT office_name FROM admin_offices LIMIT 1')->fetchColumn() === 'Normalization office', 'Child failure restores parent fields.');
    // Foreign keys reject orphan entries and cascade only their parent-owned rows.
    try {
        contentListTransaction($db, static fn() => contentListReplace($db,'office_services',2147483647,'Orphan'));
        throw new LogicException('An orphan was accepted.');
    } catch (PDOException $expected) { $checks++; }
    contentListMigrationFinalize($db);
    normalizedCheck(!contentListLegacyColumnExists($db,contentListDefinition('office_services')), 'Finalize removes legacy services.');
    normalizedCheck(!contentListLegacyColumnExists($db,contentListDefinition('achievement_achievers')), 'Finalize removes legacy names.');
    contentListMigrationFinalize($db);
    $officeSave($officeId, "After finalize\nRecords");
    $data['achievers'] = "After finalize\n李明";
    saveStudentAchievement($db,$achievementId,$data,'/test-fixture-cover.jpg');
    normalizedCheck(normalizedOfficeServices(fetchAdministrationSections(), $officeId) === ['After finalize','Records'], 'Office editing works after legacy column removal.');
    normalizedCheck(studentAchievementListing($db,[],true)['items'][0]['achievers'] === "After finalize\n李明", 'Listing payload uses normalized names.');
    normalizedCheck(studentAchievementById($db,$achievementId)['achievers'] === "After finalize\n李明", 'Article details use normalized names.');
    require_once __DIR__ . '/../chatbot/inc/retriever.php';
    $retriever = new GncbotRetriever($db);
    $answer = $retriever->retrieve('What services does the normalization office offer?');
    normalizedCheck(str_contains($answer['context'], 'After finalize') && str_contains($answer['context'], 'Records'), 'Chatbot retrieves normalized services after legacy column removal.');
    normalizedCheck($retriever->dbErrors === 0, 'Chatbot retrieval has no database errors.');
    $db->exec("CREATE TRIGGER normalization_test_fail_name BEFORE INSERT ON student_achievement_achievers FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Test name failure'");
    $failedData = $data;
    $failedData['title'] = 'Must roll back';
    try {
        saveStudentAchievement($db,$achievementId,$failedData,'/test-fixture-cover.jpg');
        throw new LogicException('Expected an achievement save failure.');
    } catch (PDOException $expected) { $checks++; }
    $db->exec('DROP TRIGGER normalization_test_fail_name');
    normalizedCheck(studentAchievementById($db,$achievementId)['title'] === $data['title'], 'Name insert failure rolls back the parent update.');
    normalizedCheck(studentAchievementById($db,$achievementId)['achievers'] === "After finalize\n李明", 'Name insert failure restores old names.');
    $officeSave(0, 'New after finalize', 'Second office');
    $secondOffice = (int)$db->query("SELECT office_id FROM admin_offices WHERE office_name='Second office'")->fetchColumn();
    normalizedCheck(normalizedOfficeServices(fetchAdministrationSections(), $secondOffice) === ['New after finalize'], 'New office creation works after finalize.');
    $secondAchievement = saveStudentAchievement($db,0,$data,'/test-fixture-cover.jpg');
    normalizedCheck(studentAchievementById($db,$secondAchievement)['achievers'] === "After finalize\n李明", 'New achievement creation works after finalize.');
    setAdministrationArchived('office',$secondOffice,true);
    normalizedCheck(normalizedOfficeServices(fetchAdministrationSections(),$secondOffice) === null, 'Archived office stays hidden.');
    normalizedCheck(normalizedOfficeServices(fetchAdministrationSections(true),$secondOffice) === ['New after finalize'], 'Archived editor retains its services.');
    setAdministrationArchived('office',$secondOffice,false);
    deleteAdministrationOffice($secondOffice);
    $query = $db->prepare('SELECT COUNT(*) FROM admin_office_services WHERE office_id=?'); $query->execute([$secondOffice]);
    normalizedCheck((int)$query->fetchColumn() === 0, 'Deleting an office cascades its services.');
    $db->prepare('DELETE FROM student_achievements WHERE achievement_id=?')->execute([$secondAchievement]);
    $query = $db->prepare('SELECT COUNT(*) FROM student_achievement_achievers WHERE achievement_id=?'); $query->execute([$secondAchievement]);
    normalizedCheck((int)$query->fetchColumn() === 0, 'Deleting an achievement cascades its names.');
    // Downgrade must reconstruct CURRENT content, rather than restoring a stale snapshot.
    contentListMigrationRollback($db);
    normalizedCheck(!contentListsEnabled($db), 'Rollback disables normalized readers.');
    normalizedCheck(studentAchievementById($db,$achievementId)['achievers'] === "After finalize\n李明", 'Rollback reconstructs current names.');
    normalizedCheck(normalizedOfficeServices(fetchAdministrationSections(),$officeId) === ['After finalize','Records'], 'Rollback reconstructs current services.');
    $officeSave($officeId, 'Legacy edit after rollback');
    $db->exec("CREATE TRIGGER normalization_test_fail_backfill BEFORE INSERT ON admin_office_services FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Test backfill failure'");
    try {
        contentListMigrationApply($db);
        throw new LogicException('Expected a migration failure.');
    } catch (PDOException $expected) { $checks++; }
    $db->exec('DROP TRIGGER normalization_test_fail_backfill');
    normalizedCheck(!contentListsEnabled($db,true), 'Failed backfill cannot activate normalized readers.');
    normalizedCheck(normalizedOfficeServices(fetchAdministrationSections(),$officeId) === ['Legacy edit after rollback'], 'Failed backfill preserves legacy content.');
    contentListMigrationApply($db);
    normalizedCheck(normalizedOfficeServices(fetchAdministrationSections(),$officeId) === ['Legacy edit after rollback'], 'Reapply honors post-rollback edits.');
    echo "PASS: $checks normalization checks (legacy, backfill, CRUD, clear, Unicode, duplicates, nested rollback, child failure, FK, finalize, downgrade, reapply).\n";
} finally {
    if ($db->inTransaction()) $db->rollBack();
    if ($created && preg_match('/^gnci_normalization_test_[a-f0-9]{12}$/D', $testDatabase) && $testDatabase !== $source) {
        $db->exec('USE `' . str_replace('`','``',$source) . '`');
        $db->exec("DROP DATABASE `$testDatabase`");
        echo "Removed isolated test database. Original records were never copied or changed.\n";
    }
}
