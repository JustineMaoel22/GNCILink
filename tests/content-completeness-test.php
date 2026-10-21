<?php
require_once __DIR__ . '/../admin/content-completeness.php';
function completenessCheck(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$fields = [
    ['id' => 'bio', 'section' => 'Head of Office', 'label' => 'Description', 'value' => '<p><br>&nbsp;&#8203;</p>'],
    ['id' => 'phone', 'section' => 'Contact information', 'label' => 'Phone number', 'value' => ''],
    ['id' => 'hours', 'section' => 'Office', 'label' => 'Hours', 'value' => 'Monday to Friday'],
    ['id' => 'optional', 'section' => 'Page', 'label' => 'Facebook', 'value' => '', 'optional' => true],
    ['id' => 'inactive', 'section' => 'Draft', 'label' => 'Title', 'value' => '', 'applicable' => false],
    ['id' => 'zero', 'section' => 'Program', 'label' => 'Value', 'value' => '0'],
];
$report = contentMissingInformation($fields);
completenessCheck(array_column($report, 'id') === ['bio', 'phone'], 'Only applicable empty content is reported');
completenessCheck($report[0]['section'] === 'Head of Office', 'Reports retain section names');
foreach ([['label' => 'Missing ID'], ['id' => 'x', 'label' => 'Label', 'section' => 'Page', 'value' => []]] as $invalid) {
    $rejected = false;
    try { contentMissingInformation([$invalid]); } catch (InvalidArgumentException $e) { $rejected = true; }
    completenessCheck($rejected, 'Malformed reports are rejected');
}
completenessCheck(contentMissingInformation([]) === [], 'Complete content produces no warnings');
echo "PASS: backend advisory checks detect empty rich text, preserve labels, exclude optional/inactive fields and reject malformed reports.\n";
