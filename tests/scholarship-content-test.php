<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../components/scholarship-content.php';
require_once __DIR__ . '/../includes/content-submissions.php';
function scholarshipCheck(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$db = getDB();
if (in_array($argv[1] ?? '', ['prepare_draft_test','cleanup_test_draft'], true)) {
    require_once __DIR__ . '/../includes/content-editor-drafts.php';
    $userId=(int)$db->query("SELECT user_id FROM users WHERE status='active' AND role='Super Admin' AND must_change_password=0 LIMIT 1")->fetchColumn();
    $key='scholarships:/pages/admission/scholarship.php';
    $draft=editorDraftLoad($db,$userId,$key);
    if ($draft && ($draft['snapshot']['state']['texts']['copy_1'] ?? '') === 'Scholarship browser fixture') editorDraftDelete($db,$userId,$key,(int)$draft['revision']);
    elseif ($draft && $argv[1] === 'prepare_draft_test') throw new RuntimeException('An authored scholarship draft exists; browser test stopped to preserve it.');
    exit;
}
$fixtureKeys = ['scholarship_text_copy_1','scholarship_text_incomingGrade7_0_pct','scholarship_text_requirement_0','scholarship_image_photo_1','scholarship_entries'];
if (($argv[1] ?? '') === 'snapshot') {
    $snapshot = [];
    foreach ($fixtureKeys as $key) {
        $stmt = $db->prepare('SELECT content FROM tuition_fee_content WHERE field_key=?'); $stmt->execute([$key]);
        $value = $stmt->fetchColumn(); $snapshot[$key] = $value === false ? null : $value;
    }
    echo base64_encode(json_encode($snapshot, JSON_THROW_ON_ERROR)); exit;
}
if (($argv[1] ?? '') === 'restore') {
    $snapshot = json_decode(base64_decode($argv[2] ?? '', true), true, 8, JSON_THROW_ON_ERROR);
    if (!is_array($snapshot) || array_keys($snapshot) !== $fixtureKeys) throw new RuntimeException('Invalid scholarship snapshot.');
    $db->beginTransaction();
    try {
        foreach ($snapshot as $key => $value) {
            if ($value === null) $db->prepare('DELETE FROM tuition_fee_content WHERE field_key=?')->execute([$key]);
            elseif (is_string($value)) $db->prepare('INSERT INTO tuition_fee_content(field_key,content) VALUES(?,?) ON DUPLICATE KEY UPDATE content=VALUES(content)')->execute([$key,$value]);
            else throw new RuntimeException('Invalid snapshot value.');
        }
        $db->commit();
    } catch (Throwable $error) { $db->rollBack(); throw $error; }
    exit;
}
if (($argv[1] ?? '') === 'remove_test_upload') {
    $url = $argv[2] ?? '';
    if (!preg_match('#^/uploads/scholarships/[a-f0-9]{32}\.(png|pdf)$#D', $url)) throw new RuntimeException('Invalid test upload.');
    $stmt=$db->prepare('SELECT 1 FROM tuition_fee_content WHERE content=?'); $stmt->execute([$url]);
    if ($stmt->fetchColumn()) throw new RuntimeException('Test upload is still used.');
    if (is_file(dirname(__DIR__).$url)) unlink(dirname(__DIR__).$url);
    $db->prepare('DELETE FROM image_display_settings WHERE image_path=?')->execute([$url]); exit;
}
// A temporary table isolates rendering and persistence checks from authored content.
$db->exec('CREATE TEMPORARY TABLE tuition_fee_content(field_key VARCHAR(80) PRIMARY KEY,content TEXT NOT NULL)');
$fields = scholarshipFields($db);
scholarshipCheck(rbacShouldQueue('/admin/action/scholarship-handler.php', ['texts'=>'{}']), 'Scholarship changes participate in the approval workflow');
scholarshipCheck(count($fields) === 73, 'All original scholarship fields are available');
$entry=['id'=>'a123456789abcdef','category'=>'government','title'=>'Government aid','description'=>'Published scholarship details','url'=>'https://example.com/scholarship'];
scholarshipCheck(scholarshipValidateEntries([$entry]) === [$entry], 'Valid scholarship entries');
foreach ([[array_merge($entry,['url'=>'javascript:alert(1)'])],[array_merge($entry,['url'=>'//evil.example'])],[array_merge($entry,['category'=>'invalid'])],[array_merge($entry,['title'=>''])],[$entry,$entry],[array_merge($entry,['id'=>'bad'])],array_fill(0,101,$entry)] as $bad) {
    try { scholarshipValidateEntries($bad); throw new RuntimeException('Accepted invalid scholarship entry'); }
    catch (InvalidArgumentException $expected) {}
}
foreach (['0','50','100'] as $value) scholarshipCheck(scholarshipValidateTexts(['incomingGrade7_0_pct'=>$value])['incomingGrade7_0_pct'] === $value, 'Valid percentage');
foreach ([['incomingGrade7_0_pct'=>'101'], ['incomingGrade7_0_pct'=>'-1'], ['incomingGrade7_0_pct'=>'1.5'], ['copy_1'=>[]], ['unknown'=>'bad'], ['copy_1'=>str_repeat('a',256)]] as $bad) {
    try { scholarshipValidateTexts($bad); throw new RuntimeException('Accepted invalid scholarship input'); }
    catch (InvalidArgumentException $expected) {}
}
$stmt=$db->prepare('INSERT INTO tuition_fee_content(field_key,content) VALUES(?,?)');
$stmt->execute(['scholarship_text_copy_1','Scholarship <script>alert(1)</script>']);
$stmt->execute(['scholarship_text_incomingGrade7_0_pct','75']);
$stmt->execute(['scholarship_text_requirement_0','A requirement from the database']);
$stmt->execute(['scholarship_entries',json_encode([$entry], JSON_THROW_ON_ERROR)]);
scholarshipCheck(scholarshipEntries($db) === [$entry], 'Added entries load from stored content');
scholarshipCheck(scholarshipCards(scholarshipFields($db))['incomingGrade7'][0]['pct'] === '75', 'Percentages use stored content');
$stmt->execute(['scholarship_image_photo_1','https://untrusted.example/photo.png']);
scholarshipCheck(scholarshipImages($db)['photo_1']['url'] === scholarshipImageDefaults()['photo_1']['default'], 'Untrusted photo paths rejected');
$_SERVER['DOCUMENT_ROOT']=dirname(__DIR__); $_SERVER['REQUEST_URI']='/pages/admission/scholarship.php'; $_GET=[];
ob_start(); include __DIR__.'/../pages/admission/scholarship.php'; $output=ob_get_clean();
scholarshipCheck(str_contains($output,'Scholarship &lt;script&gt;alert(1)&lt;/script&gt;'), 'Public content is escaped');
scholarshipCheck(str_contains($output,'A requirement from the database') && str_contains($output,'75 percent off'), 'Real page renders persisted requirements and percentages');
scholarshipCheck(str_contains($output,'Published scholarship details') && str_contains($output,'https://example.com/scholarship'), 'New scholarships render on the public page');
scholarshipCheck(!str_contains($output,'Government scholarship information will be posted here.'), 'Published government entries replace the default placeholder');
scholarshipCheck(!str_contains($output,'data-ve-scholarship-add='), 'Visitors do not receive Add controls');
scholarshipCheck(!str_contains($output,'id="veScholarshipData"') && !str_contains($output,'data-ve-field="scholarship-text"'), 'Visitors receive no editing controls');
echo "PASS: defaults, validation, stored content, public rendering, escaping, image allowlist, and preview-only controls.\n";
