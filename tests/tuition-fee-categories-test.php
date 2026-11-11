<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../components/tuition-fee-documents.php';
require_once __DIR__ . '/../admin/admin-functions.php';
initSession();
$_SESSION['user_id'] = 1;
$_SESSION['user_role'] = 'Super Admin';
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
if (in_array($argv[1] ?? '', ['unauthorized','csrf'], true)) {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['categories'=>'[]'];
    if ($argv[1] === 'unauthorized') $_SESSION = [];
    register_shutdown_function(static function () {
        if (http_response_code() !== 403) throw new RuntimeException('Unauthorized category publish was not rejected.');
        echo "\nPASS: category write rejected with HTTP 403.\n";
    });
    include __DIR__ . '/../admin/action/tuition-fees-handler.php';
    exit;
}
$db = getDB();
// Only connection-local tables are written, including the audit log.
$db->exec('CREATE TEMPORARY TABLE tuition_fee_content (field_key VARCHAR(80) PRIMARY KEY, content TEXT NOT NULL)');
$db->exec('CREATE TEMPORARY TABLE tuition_fee_documents (document_id INT AUTO_INCREMENT PRIMARY KEY, category_key VARCHAR(40), title VARCHAR(255), file_path VARCHAR(500), file_size INT)');
$db->exec('CREATE TEMPORARY TABLE activity_logs (user_id INT, action VARCHAR(100), table_name VARCHAR(100), record_id INT NULL, description TEXT, created_at DATETIME)');
$db->exec("INSERT INTO tuition_fee_documents VALUES (1,'college','College fees','/uploads/tuition-fees/college/existing.pdf',100)");
$checks = 0;
function feeCheck(bool $ok, string $message): void { global $checks; if (!$ok) throw new RuntimeException($message); $checks++; }
$defaults = tuitionFeeCategoryDefinitions();
feeCheck(array_keys($defaults) === ['college','graduate-school','senior-high-school','basic-education'], 'Original order preserved without saved categories');
$entries = [];
foreach (array_reverse($defaults, true) as $key => $category) $entries[] = ['key'=>$key] + $category;
$entries[] = ['key'=>'short-courses','label'=>'Short Courses','icon'=>'folder'];
$validated = tuitionFeeValidateCategories($entries, $defaults);
feeCheck(array_keys($validated) === ['basic-education','senior-high-school','graduate-school','college','short-courses'], 'Add and reorder categories');
foreach ([
    [], array_slice($entries, 1), array_merge($entries, [$entries[0]]),
    array_merge($entries, [['key'=>'../unsafe','label'=>'Unsafe','icon'=>'folder']]),
    array_merge($entries, [['key'=>'another','label'=>' short courses ','icon'=>'folder']]),
    array_merge($entries, [['key'=>'another','label'=>'<script>bad</script>','icon'=>'folder']]),
    array_merge($entries, [['key'=>'another','label'=>'Another','icon'=>'invalid']]),
    array_merge($entries, [['key'=>'another','label'=>'','icon'=>'folder']]),
] as $invalid) {
    try { tuitionFeeValidateCategories($invalid, $defaults); throw new LogicException('Invalid categories accepted'); }
    catch (InvalidArgumentException $expected) { $checks++; }
}
$publish = static function (array $post): array {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['csrf_token'=>$_SESSION['csrf_token'],'texts'=>'{}','documents'=>'[]'] + $post;
    // Let each scenario supply a texts/documents payload.
    $_POST = array_replace($_POST, $post);
    http_response_code(200);
    ob_start();
    include __DIR__ . '/../admin/action/tuition-fees-handler.php';
    return json_decode(ob_get_clean(), true, 32, JSON_THROW_ON_ERROR);
};
$result = $publish(['categories'=>json_encode($entries)]);
feeCheck(!empty($result['success']), 'Real handler publishes new categories');
feeCheck(array_keys(tuitionFeeCategories()) === array_keys($validated), 'Public reader follows saved order');
feeCheck(tuitionFeeCategories()['college']['documents'][0]['url'] === '/uploads/tuition-fees/college/existing.pdf', 'Existing PDF preserved');
feeCheck(tuitionFeeCategories()['short-courses']['documents'] === [], 'New category has an empty document list');
$result = $publish(['texts'=>json_encode(['category_short-courses'=>'Short Programs'])]);
feeCheck(!empty($result['success']) && tuitionFeeCategories()['short-courses']['label'] === 'Short Programs', 'New category supports existing inline text edits');
$before = $db->query("SELECT content FROM tuition_fee_content WHERE field_key='fee_categories'")->fetchColumn();
$more = $entries;
$more[] = ['key'=>'another-category','label'=>'Another category','icon'=>'book'];
$result = $publish(['categories'=>json_encode($more), 'documents'=>json_encode([['id'=>0,'category'=>'another-category','title'=>'Missing PDF']])]);
feeCheck(isset($result['error']), 'Missing new PDF rejected');
feeCheck($before === $db->query("SELECT content FROM tuition_fee_content WHERE field_key='fee_categories'")->fetchColumn(), 'Failed document publish rolls category changes back');
$result = $publish(['categories'=>json_encode(array_slice($entries, 1))]);
feeCheck(isset($result['error']), 'Existing categories cannot be removed');
$withoutCollege = array_values(array_filter($entries, static fn($entry) => $entry['key'] !== 'college'));
$result = $publish(['categories'=>json_encode($withoutCollege), 'deleted_categories'=>json_encode(['college'])]);
feeCheck(!empty($result['success']), 'Explicit category deletion is published');
feeCheck(!isset(tuitionFeeCategories()['college']), 'Deleted category stays absent from the public reader');
feeCheck(array_key_first(tuitionFeeCategories()) === 'basic-education', 'A remaining category is available as the default');
feeCheck((int)$db->query('SELECT COUNT(*) FROM tuition_fee_documents WHERE document_id=1')->fetchColumn() === 1, 'Deleting a category retains its stored PDF record');
$result = $publish(['categories'=>json_encode([]), 'deleted_categories'=>json_encode(array_keys(tuitionFeeCategories()))]);
feeCheck(isset($result['error']), 'Deleting the last category is rejected');
$result = $publish(['categories'=>json_encode($withoutCollege), 'deleted_categories'=>json_encode(['unknown-category'])]);
feeCheck(isset($result['error']), 'Forged category deletions are rejected');
echo "$checks tuition category checks passed. No persistent data changed.\n";
