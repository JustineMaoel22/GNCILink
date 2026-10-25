<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
$db=getDB();
$db->exec('CREATE TABLE IF NOT EXISTS community_activity_page (page_id TINYINT UNSIGNED NOT NULL PRIMARY KEY,content MEDIUMTEXT NOT NULL,updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
// Preserve the current header; all page copy becomes editable. No activities are seeded.
$content=['texts'=>[
    'hero_title'=>'Community Extension Activities',
    'hero_description'=>'Explore GNCI’s community initiatives that inspire service and create positive change.',
    'heading_first'=>'Community','heading_accent'=>'Extension','heading_last'=>'Activities',
    'intro'=>"Learning becomes meaningful when it improves the lives around us.\nOur students turn curiosity, compassion, and teamwork into lasting community action.",
    'list_heading'=>'Ways we serve','empty_title'=>'No activities available yet',
    'empty_description'=>'Community extension activities will appear here when they are published.',
], 'hero_image'=>'/assets/images/admission-header.png','activities'=>[]];
$db->prepare('INSERT IGNORE INTO community_activity_page(page_id,content) VALUES(1,?)')->execute([json_encode($content,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
echo "Community activity storage is ready. Existing data was preserved; no activities were seeded.\n";
