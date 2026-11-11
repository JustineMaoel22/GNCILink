<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/config.php';
$options = getopt('', ['apply', 'archive:']);
if (!isset($options['apply'], $options['archive'])) throw new RuntimeException('Use --apply --archive with a new private test archive path.');
$db = getDB();
// Connection-local tables shadow real content: application records are untouched.
$ddl = $db->query('SHOW CREATE TABLE tuition_fee_content')->fetch(PDO::FETCH_NUM)[1];
$db->exec(preg_replace('/^CREATE TABLE/', 'CREATE TEMPORARY TABLE', $ddl, 1));
$seed = $db->prepare('INSERT INTO tuition_fee_content (field_key,content) VALUES (?,?)');
foreach ([
    'scholarship_image_photo_1'=>'/uploads/scholarships/retired.jpg',
    'scholarship_image_photo_4'=>'/uploads/scholarships/requirements.png',
    'scholarship_entries'=>'[{"id":"abcdef0123456789","pdf":"/uploads/scholarships/keep.pdf"}]',
    'scholarship_categories'=>'[{"id":"gnc","label":"GNC"}]',
    'scholarship_text_copy_1'=>'Scholarship',
    'scholarship_text_requirement_0'=>'Existing requirement',
    'tuition_image_photo_1'=>'/uploads/tuition/keep.jpg',
] as $key=>$value) $seed->execute([$key,$value]);
$original = $db->query('SELECT * FROM tuition_fee_content ORDER BY field_key')->fetchAll(PDO::FETCH_ASSOC);
require __DIR__ . '/../scripts/cleanup-scholarship-photos.php';
$remaining = $db->query('SELECT * FROM tuition_fee_content ORDER BY field_key')->fetchAll(PDO::FETCH_ASSOC);
if (count($remaining) !== 5) throw new RuntimeException('Wrong number of remaining records.');
$archive = json_decode(file_get_contents($options['archive']), true, 32, JSON_THROW_ON_ERROR);
if (count($archive['rows']) !== 2) throw new RuntimeException('Incorrect rollback archive.');
$insert('tuition_fee_content', $archive['rows']);
if ($db->query('SELECT * FROM tuition_fee_content ORDER BY field_key')->fetchAll(PDO::FETCH_ASSOC) !== $original) throw new RuntimeException('Restored records differ.');
echo "PASS: only scholarship photo references deleted; PDFs, categories, requirements and tuition content preserved; full archived rows restored. Temporary tables only.\n";
