<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../admin/admin-functions.php';
require_once __DIR__.'/../includes/content-submissions.php';
require_once __DIR__.'/../includes/publish-confirmation.php';
$db = getDB(); $checks = 0;
function publishCheck(bool $ok, string $message): void {
    global $checks;
    if (!$ok) throw new RuntimeException($message);
    $checks++;
}
// Shadow production tables only for this connection; no real records change.
$db->exec('CREATE TEMPORARY TABLE users(user_id INT PRIMARY KEY,password VARCHAR(255),status VARCHAR(20),role VARCHAR(30),credential_version INT,locked_until DATETIME NULL)');
$db->exec('CREATE TEMPORARY TABLE auth_rate_limits(bucket_hash CHAR(64) PRIMARY KEY,attempts INT,expires_at DATETIME)');
$db->exec('CREATE TEMPORARY TABLE auth_security_logs(user_id INT,action VARCHAR(100),ip_address VARCHAR(45),description VARCHAR(255))');
$password = 'Test-only-publish-'.bin2hex(random_bytes(12));
$db->prepare("INSERT INTO users VALUES(1,?,'active','Super Admin',3,NULL)")->execute([password_hash($password, PASSWORD_DEFAULT)]);
$_SESSION = ['user_id'=>1,'user_role'=>'Super Admin','credential_version'=>3];
try { issuePublishConfirmation('incorrect'); throw new LogicException('Incorrect password accepted.'); }
catch (InvalidArgumentException $expected) { $checks++; }
$token = issuePublishConfirmation($password);
publishCheck(publishConfirmationValid($token), 'Correct password grants publication confirmation.');
publishCheck(!str_contains(json_encode($_SESSION), $password), 'Session never stores the password.');
publishCheck(!publishConfirmationValid(str_repeat('a',64)), 'Unissued token rejected.');
$_SESSION['user_id']=2;
publishCheck(!publishConfirmationValid($token), 'Confirmation is account-bound.');
$_SESSION['user_id']=1; $_SESSION['credential_version']=4;
publishCheck(!publishConfirmationValid($token), 'Credential change invalidates confirmation.');
$_SESSION['credential_version']=3;
$_SESSION['publish_confirmations'][hash('sha256',$token)]['expires']=time()-1;
publishCheck(!publishConfirmationValid($token), 'Expired token rejected.');
foreach (['/admin/action/create-news.php','/admin/action/create-announcement.php','/admin/events.php'] as $route) {
    publishCheck(publishNeedsConfirmation($route,['action'=>'create','status'=>'published']), 'Published posts require password confirmation.');
    publishCheck(!publishNeedsConfirmation($route,['action'=>'create','status'=>'draft']), 'Post drafts need no password.');
    publishCheck(!publishNeedsConfirmation($route,['action'=>'create','status'=>'pending']), 'Pending posts need no password.');
}
publishCheck(publishNeedsConfirmation('/admin/action/program-page-handler.php',['action'=>'save_program_items']), 'Live program edits protected.');
publishCheck(publishNeedsConfirmation('/admin/submissions.php',['action'=>'approve']), 'Approval publication protected.');
publishCheck(!publishNeedsConfirmation('/admin/submissions.php',['action'=>'reject']), 'Rejection needs no publication password.');
publishCheck(!publishNeedsConfirmation('/admin/action/editor-draft-handler.php',['action'=>'save']), 'Private editor draft remains independent.');
publishCheck(publishNeedsConfirmation('/admin/action/editor-draft-handler.php',['action'=>'record_publication']), 'Publication summary requires the publishing confirmation');
publishCheck(!publishNeedsConfirmation('/admin/settings.php',['action'=>'save_page_suggestions']), 'Preferences remain independent.');
publishCheck(!publishNeedsConfirmation('/admin/action/page-review-handler.php',['action'=>'save_draft']), 'Section drafts remain independent.');
publishCheck(publishNeedsConfirmation('/admin/events.php',['ajax_action'=>'import_confirm','import_status'=>'published']), 'Published calendar imports protected.');
publishCheck(!publishNeedsConfirmation('/admin/events.php',['ajax_action'=>'import_confirm','import_status'=>'draft']), 'Draft calendar imports remain independent.');
$_SESSION['user_role']='Content Editor';
try { issuePublishConfirmation($password); throw new LogicException('Editor granted confirmation.'); }
catch (RuntimeException $expected) { $checks++; }
$_SESSION['user_role']='Super Admin';
for ($i=0;$i<5;$i++) {
    try { issuePublishConfirmation('incorrect'); throw new LogicException('Incorrect password accepted.'); }
    catch (InvalidArgumentException $expected) {}
}
try { issuePublishConfirmation($password); throw new LogicException('Rate limit bypassed.'); }
catch (RuntimeException $expected) { publishCheck(str_contains($expected->getMessage(),'Too many'), 'Repeated failures are rate-limited.'); }
echo "$checks publishing confirmation checks passed. No persistent data changed.\n";
