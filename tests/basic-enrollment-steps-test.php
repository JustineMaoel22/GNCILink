<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin/admin-functions.php';
require_once __DIR__ . '/../components/enrollment-procedures-content.php';
function stepCheck(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function stepRefused(callable $work, string $message): void {
    try { $work(); } catch (InvalidArgumentException $e) { return; }
    throw new RuntimeException($message);
}
$db = getDB();
// Temporary tables shadow the real tables on this connection; live content and logs stay untouched.
foreach (['basic_enrollment_steps','enrollment_illustrations'] as $table) {
    $ddl = $db->query('SHOW CREATE TABLE ' . $table)->fetch(PDO::FETCH_NUM)[1];
    $db->exec(str_replace('CREATE TABLE', 'CREATE TEMPORARY TABLE', $ddl));
}
$db->exec('CREATE TEMPORARY TABLE activity_logs (user_id INT, action VARCHAR(50), table_name VARCHAR(100), record_id INT, description TEXT, created_at DATETIME)');
$_SESSION = ['user_id'=>1, 'user_role'=>'Super Admin'];
$a = ['step_id'=>str_repeat('a',32), 'title'=>'Registrar & Admissions', 'location'=>'Office', 'instruction'=>"Submit approved documents.\nKeep your receipt.", 'illustration'=>'/assets/images/svg/enrollment-procedures/fresh-1.svg'];
$b = ['step_id'=>str_repeat('b',32), 'title'=>'Second step', 'location'=>'', 'instruction'=>'Approved instructions.', 'illustration'=>''];
$empty = basicEnrollmentRevision([]);
publishBasicEnrollmentSteps([$a,$b], $empty);
stepCheck(basicEnrollmentSteps() === [$a,$b], 'Create persists ordered steps and existing Storyset illustration');
stepRefused(fn()=>publishBasicEnrollmentSteps([], $empty), 'Stale revision must not overwrite published steps');
$a['title'] = 'Edited title';
publishBasicEnrollmentSteps([$b,$a], basicEnrollmentRevision(basicEnrollmentSteps()));
stepCheck(basicEnrollmentSteps() === [$b,$a], 'Edit and reorder persist');
publishBasicEnrollmentSteps([$a], basicEnrollmentRevision(basicEnrollmentSteps()));
stepCheck(basicEnrollmentSteps() === [$a], 'Remove deletes only omitted steps');
foreach ([array_merge($a,['title'=>'']), array_merge($a,['instruction'=>'']), array_merge($a,['title'=>str_repeat('x',151)]), array_merge($a,['illustration'=>'javascript:alert(1)']), array_merge($a,['illustration'=>'/uploads/not-owned.svg']), array_merge($a,['step_id'=>'../bad'])] as $bad) stepRefused(fn()=>basicEnrollmentValidateSteps([$bad],[$a]), 'Invalid input refused');
stepRefused(fn()=>basicEnrollmentValidateSteps([$a,$a],[]), 'Duplicate IDs refused');
stepRefused(fn()=>basicEnrollmentValidateSteps(array_fill(0,41,$a),[]), 'Excessive step count refused');
$before = basicEnrollmentSteps();
$bad = array_merge($b,['title'=>'']);
stepRefused(fn()=>publishBasicEnrollmentSteps([$a,$bad], basicEnrollmentRevision($before)), 'Invalid batch refused');
stepCheck(basicEnrollmentSteps() === $before, 'Invalid batch leaves published content intact');
// Render the public template against the same temporary collection.
$_SERVER['DOCUMENT_ROOT'] = dirname(__DIR__);
$_SERVER['REQUEST_URI'] = '/pages/admission/enrollment-procedures.php';
$testSession = $_SESSION; $_SESSION = [];
$html = (static function (): string { ob_start(); require __DIR__ . '/../pages/admission/enrollment-procedures.php'; return ob_get_clean(); })();
$_SESSION = $testSession;
stepCheck(str_contains($html, 'Edited title') && str_contains($html, 'Keep your receipt.'), 'Public template renders the published collection');
stepCheck(str_contains($html, 'enrollment-step-bar--green') && str_contains($html, 'Illustrations by'), 'Existing enrollment cards and Storyset credit rendered');
$_SESSION['user_role'] = 'Content Editor';
stepRefused(fn()=>publishBasicEnrollmentSteps([], basicEnrollmentRevision($before)), 'Text-only editor cannot publish steps');
$_SESSION['user_role'] = 'Super Admin';
publishBasicEnrollmentSteps([], basicEnrollmentRevision($before));
stepCheck(basicEnrollmentSteps() === [], 'Publishing an empty collection removes optional cards');
echo "PASS: isolated create/edit/reorder/remove, empty collection, stale revision, authorization, and malformed input checks. Live content unchanged.\n";
