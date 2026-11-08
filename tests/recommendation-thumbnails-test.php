<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../components/recommendation-catalog.php';
$db=getDB();
// Shadow only this connection's program records; never update published content.
$db->exec("CREATE TEMPORARY TABLE rec_program_source AS SELECT p.* FROM programs p LEFT JOIN departments d ON d.dept_id=p.dept_id WHERE p.is_published=1 AND (d.dept_id IS NULL OR (d.archived_at IS NULL AND d.department_type<>'basic-education')) LIMIT 1");
$db->exec('CREATE TEMPORARY TABLE programs AS SELECT * FROM rec_program_source');
$id=$db->query('SELECT program_id FROM programs')->fetchColumn();
if(!$id)throw new RuntimeException('A published program is required for this test.');
$db->exec('CREATE TEMPORARY TABLE program_items (item_id INT,program_id INT,item_type VARCHAR(20),image_path VARCHAR(255),image_media_id INT NULL,display_order INT)');
$insert=$db->prepare("INSERT INTO program_items VALUES (?,?, 'hero_slide',?,NULL,?)");
$image=function()use($db,$id){foreach(gncRecCatalog($db) as $item)if($item['id']==='program:'.$id)return $item['image'];throw new RuntimeException('Program missing from catalog');};
$old=$image();
$insert->execute([1,$id,'/uploads/program-banners/video.mp4',0]);
$insert->execute([2,$id,'https://example.com/unsafe.jpg',1]);
if($image()!==$old)throw new RuntimeException('Invalid/video slides must retain fallback');
$insert->execute([3,$id,'/uploads/program-banners/new-banner.webp',2]);
$insert->execute([4,$id,'/uploads/program-banners/later-banner.jpg',3]);
if($image()!=='/uploads/program-banners/new-banner.webp')throw new RuntimeException('First current picture must replace legacy thumbnail');
$db->exec("UPDATE program_items SET image_path='/uploads/program-banners/replaced-banner.png' WHERE item_id=3");
if($image()!=='/uploads/program-banners/replaced-banner.png')throw new RuntimeException('Replacement banner not reflected');
$db->exec('DELETE FROM program_items');
if($image()!==$old)throw new RuntimeException('Removing slides must restore legacy fallback');
echo "PASS: current banner selection, ordering, replacement, video/unsafe URL exclusion and legacy fallback. No persistent data changed.\n";
