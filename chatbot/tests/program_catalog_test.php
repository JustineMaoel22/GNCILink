<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../inc/retriever.php';
// Exercise catalogue assembly without database access or a paid model call.
$class = new ReflectionClass(GncbotRetriever::class);
$bot = $class->newInstanceWithoutConstructor();
$departments = [];
foreach (range(1, 7) as $id) $departments[$id] = ['dept_name' => $id === 7 ? 'College of Criminology' : 'College ' . $id, 'department_type' => 'college'];
$departments[8] = ['dept_name' => 'Basic Education', 'department_type' => 'basic-education'];
foreach (['departments' => $departments, 'programRows' => ['TEST' => ['dept_id' => 1, 'pdf_path' => null]],
    'programNames' => ['TEST' => 'Published program name'], 'pages' => gncbot_page_urls()] as $key => $value) $class->getProperty($key)->setValue($bot, $value);
$list = $class->getMethod('programList');
$list->invoke($bot, null);
$context = implode("\n", $class->getProperty('blocks')->getValue($bot));
foreach (['College of Criminology:', 'no published program details', 'Published program name', 'College 6:'] as $expected) {
    if (!str_contains($context, $expected)) throw new RuntimeException('Missing catalogue information: ' . $expected);
}
if (str_contains($context, 'Basic Education:')) throw new RuntimeException('Unrelated basic education department included in college list.');
$class->getProperty('blocks')->setValue($bot, []);
$list->invoke($bot, 7);
$context = implode("\n", $class->getProperty('blocks')->getValue($bot));
if (!str_contains($context, 'College of Criminology:') || str_contains($context, 'College 1:')) throw new RuntimeException('Department filter failed.');
echo "PASS: all seven college departments included, empty department explained, published names retained, department filter respected.\n";
