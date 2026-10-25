<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../admin/admin-functions.php';
$db=getDB();
// Connection-local temporary tables leave all published content untouched.
$db->exec('CREATE TEMPORARY TABLE faq_page(page_id TINYINT PRIMARY KEY,content TEXT,updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP)');
$db->exec('CREATE TEMPORARY TABLE faqs(faq_id INT PRIMARY KEY,question TEXT,answer LONGTEXT,display_order INT)');
$texts=['title'=>'Frequently Asked Questions','intro'=>'FAQ fixture introduction','empty_title'=>'No FAQs published yet','empty_description'=>'Check back soon.'];
$db->prepare('INSERT INTO faq_page(page_id,content) VALUES(1,?)')->execute([json_encode($texts)]);
if(($argv[1]??'')!=='empty'){
    $insert=$db->prepare('INSERT INTO faqs VALUES(?,?,?,?)');
    foreach(range(1,6) as $i)$insert->execute([$i,'Authored question '.$i.'?','Answer '.$i." with <script>literal text</script>\nA second line.",$i]);
}
$_GET=[];$_SERVER['REQUEST_URI']='/pages/faq.php';
include __DIR__.'/../pages/faq.php';
