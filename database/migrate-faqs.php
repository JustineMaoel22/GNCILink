<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
$db=getDB();
// Reuse the existing chatbot FAQ table and preserve all existing questions.
$db->exec('CREATE TABLE IF NOT EXISTS faqs(faq_id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,category_id INT DEFAULT NULL,question TEXT NOT NULL,answer LONGTEXT NOT NULL,display_order INT NOT NULL DEFAULT 0,created_by INT DEFAULT NULL,created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$db->exec('CREATE TABLE IF NOT EXISTS faq_page(page_id TINYINT UNSIGNED NOT NULL PRIMARY KEY,content TEXT NOT NULL,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
$texts=['title'=>'Frequently Asked Questions','intro'=>'Find helpful information about Guagua National Colleges.','empty_title'=>'No FAQs published yet','empty_description'=>'Please check back soon, or contact the school for assistance.'];
$db->prepare('INSERT IGNORE INTO faq_page(page_id,content) VALUES(1,?)')->execute([json_encode($texts,JSON_UNESCAPED_UNICODE)]);
echo "FAQ page storage is ready. Existing FAQs preserved; no questions or answers seeded.\n";
