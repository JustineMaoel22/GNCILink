<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../admin/admin-functions.php';
$_SESSION=['user_role'=>'Super Admin'];
$checks=0;
function rejectContact(callable $save): void {
    global $checks;
    try {$save();throw new LogicException('Invalid number was accepted.');}
    catch (InvalidArgumentException $expected) {
        if (!str_contains($expected->getMessage(),'complete mobile number')) throw $expected;
        $checks++;
    }
}
$office=getDB()->query('SELECT * FROM admin_offices ORDER BY office_id LIMIT 1')->fetch(PDO::FETCH_ASSOC);
if (!$office) throw new RuntimeException('Local office fixture required.');
rejectContact(static fn()=>saveAdministrationOffice((int)$office['office_id'],$office['section_key'],$office['office_name'],$office['head_name'],
    $office['head_role']??'','not a telephone',$office['email']??'',$office['location']??'',$office['hours']??'','',null,false));
$dept=getDB()->query('SELECT dept_id FROM departments ORDER BY dept_id LIMIT 1')->fetchColumn();
$department=getDepartmentById((int)$dept);
if (!$department) throw new RuntimeException('Local department fixture required.');
rejectContact(static fn()=>saveDepartmentDean((int)$dept,$department['dean_name']??'',$department['dean_title']??'',$department['dean_bio']??'',
    'not a telephone',$department['dean_email']??'',$department['dean_office']??'',$department['dean_hours']??'',null,false));
$_POST['allow_incomplete']='1';
rejectContact(static fn()=>saveSectionDraft('contact-us','contact_phone','not a telephone'));
echo "Contact domain enforcement: $checks invalid saves rejected before persistence.\n";
