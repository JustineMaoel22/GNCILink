<?php
if(PHP_SAPI!=='cli')exit;
require_once __DIR__.'/../config/config.php';
$db=getDB();
echo json_encode(['pages'=>$db->query("SELECT p.slug,p.title,p.page_type,COUNT(s.section_id) AS sections FROM pages p LEFT JOIN page_sections s ON s.page_id=p.page_id WHERE p.status='active' GROUP BY p.page_id,p.slug,p.title,p.page_type ORDER BY p.slug")->fetchAll(PDO::FETCH_ASSOC),'departments'=>$db->query('SELECT slug,dept_name FROM departments ORDER BY dept_id')->fetchAll(PDO::FETCH_ASSOC),'program_routes'=>$db->query('SELECT slug,program_code FROM programs ORDER BY program_id')->fetchAll(PDO::FETCH_ASSOC)],JSON_UNESCAPED_SLASHES);
