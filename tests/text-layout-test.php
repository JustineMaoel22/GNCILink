<?php
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
require_once __DIR__.'/../includes/text-layout.php';
function tlCheck($test,$message){if(!$test)throw new RuntimeException($message);}
tlCheck(textLayoutRoute('/index.php?ce_preview=1&_ve=3')==='/', 'Home canonical route');
tlCheck(textLayoutRoute('/pages/student%20life/athletics-sports.php?section=a')==='/pages/student life/athletics-sports.php','Encoded public paths');
foreach (['//evil.example/pages/a.php','/admin/settings.php','/pages/../config.php','https://evil.example/'] as $bad){try{textLayoutRoute($bad);throw new RuntimeException('Accepted bad route');}catch(InvalidArgumentException $e){}}
$id=str_repeat('a',24);$box=['x'=>3.5,'y'=>-2,'w'=>40,'h'=>110,'fontSize'=>36];
foreach ([7,201,'36',null] as $badSize) {
    try {textLayoutNormalize([$id=>['desktop'=>array_merge($box,['fontSize'=>$badSize])]]);throw new RuntimeException('Accepted invalid text size');}
    catch(InvalidArgumentException $e) {}
}
$legacy=$box;unset($legacy['fontSize']);
tlCheck(!isset(textLayoutNormalize([$id=>['desktop'=>$legacy]])[$id]['desktop']['fontSize']),'Existing placements remain compatible');
foreach ([[$id=>['phone'=>$box]],[$id=>['desktop'=>array_merge($box,['w'=>101])]],[$id=>['desktop'=>array_merge($box,['x'=>'4'])]],['bad selector'=>['desktop'=>$box]],[$id=>['desktop'=>array_merge($box,['css'=>'color:red'])]]] as $bad){try{textLayoutNormalize($bad);throw new RuntimeException('Accepted invalid layout');}catch(InvalidArgumentException $e){}}
$db=getDB();$route='/pages/academics/college/program.php?slug=__layout-test-'.bin2hex(random_bytes(8));
try {
    textLayoutSave($db,$route,[$id=>['desktop'=>$box]],1);
    textLayoutSave($db,$route,[$id=>['mobile'=>['x'=>0,'y'=>0,'w'=>95,'h'=>50]]],1);
    $saved=textLayoutLoad($db,$route);tlCheck(count($saved[$id])===2,'Device updates merge');tlCheck($saved[$id]['desktop']['w']===40.0,'Stored round trip');
    tlCheck($saved[$id]['desktop']['fontSize']===36.0&&!isset($saved[$id]['mobile']['fontSize']),'Text size persists for its selected device');
    textLayoutSave($db,$route,[$id=>['desktop'=>null]],1);tlCheck(!isset(textLayoutLoad($db,$route)[$id]['desktop']),'Reset removes one breakpoint');
    tlCheck(textLayoutLoad($db,'/pages/academics/college/program.php?slug=__other')===[],'Different pages isolated');
} finally {$db->prepare('DELETE FROM text_layout_settings WHERE page_key=?')->execute([hash('sha256',textLayoutRoute($route))]);}
echo "PASS: canonical routes, numeric validation, device merge/reset, page isolation, persisted metadata; isolated test row removed.\n";
