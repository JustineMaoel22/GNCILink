<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/text-layout.php';
function inlineCheck($ok,$label){if(!$ok)throw new RuntimeException($label);}
$id=str_repeat('b',24);$run=['start'=>2,'end'=>5,'text'=>'abc','size'=>24,'color'=>'#176b42'];
$normalized=textLayoutNormalize([$id=>['desktop'=>['runs'=>[$run]]]]);
inlineCheck($normalized[$id]['desktop']['runs'][0]['color']==='#176B42','Selected styles are normalized');
inlineCheck(textLayoutRuns([['start'=>0,'end'=>2,'text'=>'😀','size'=>48]])[0]['end']===2,'Offsets support JavaScript Unicode selections');
foreach([['size'=>201],['size'=>'24'],['color'=>'red'],['color'=>'#fff;position:fixed'],['text'=>'mismatch'],['start'=>-1],['onclick'=>'bad()']] as $bad){$rejected=false;try{textLayoutRuns([array_merge($run,$bad)]);}catch(InvalidArgumentException $e){$rejected=true;}inlineCheck($rejected,'Invalid selected-text input rejected');}
$rejected=false;try{textLayoutRuns([$run,$run]);}catch(InvalidArgumentException $e){$rejected=true;}inlineCheck($rejected,'Overlapping styles rejected');
if(($argv[1]??'')!=='database'){echo "PASS: inline style validation, safe HEX values, size limits, Unicode and overlapping ranges.\n";exit;}
$db=getDB();$route='/pages/about/gnc-history.php?inline_style_test='.bin2hex(random_bytes(6));
try{
    textLayoutSave($db,$route,[$id=>['desktop'=>['runs'=>[$run]],'mobile'=>['runs'=>[$run]]]],1);
    $saved=textLayoutLoad($db,$route);inlineCheck($saved[$id]['mobile']['runs'][0]['size']===24.0,'Selected size persists');inlineCheck($saved[$id]['desktop']['runs'][0]['text']==='abc','Selected text anchor persists');
    textLayoutSave($db,$route,[$id=>['desktop'=>null]],1);inlineCheck(isset(textLayoutLoad($db,$route)[$id]['mobile']),'Other device metadata is preserved');
}finally{$db->prepare('DELETE FROM text_layout_settings WHERE page_key=?')->execute([hash('sha256',textLayoutRoute($route))]);}
echo "PASS: validated inline sizes/colors persist and reset through existing layout storage; isolated test row removed.\n";
