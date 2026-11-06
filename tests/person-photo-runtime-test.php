<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../includes/person-photo-check.php';
$portrait = realpath(__DIR__.'/../uploads/administration/086f12f12d75f5d4788185497d0df834.jpg');
$result = personPhotoWorker($portrait, [['profile_key'=>'test:1','path'=>$portrait]]);
if (($result['faces'] ?? 0) !== 1 || ($result['scores']['test:1'] ?? 0) < 0.55) {
    throw new RuntimeException('PHP face worker did not detect a repeated portrait.');
}
echo "PHP to Python face runtime: local inference passed.\n";
