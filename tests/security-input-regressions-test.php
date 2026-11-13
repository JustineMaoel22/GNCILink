<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
// Extract only the named pure function; do not execute public routes or connect to storage.
function regressionFunction(string $file, string $name): string {
    $tokens=token_get_all(file_get_contents(__DIR__.'/../'.$file));$found=false;$capture=false;$depth=0;$code='';
    foreach($tokens as $i=>$token){
        if(is_array($token)&&$token[0]===T_FUNCTION){
            $next=$i+1;while(isset($tokens[$next])&&is_array($tokens[$next])&&$tokens[$next][0]===T_WHITESPACE)$next++;
            $found=is_array($tokens[$next]??null)&&$tokens[$next][1]===$name;if($found)$capture=true;
        }
        if(!$capture)continue;$code.=is_array($token)?$token[1]:$token;
        if($token==='{')$depth++;if($token==='}'&&--$depth===0)return $code;
    }throw new RuntimeException('Function not found: '.$name);
}
require_once __DIR__.'/../admin/rich-text.php';
$checks=0;function securityCheck(bool $ok,string $label):void{global $checks;if(!$ok)throw new RuntimeException($label);$checks++;}
foreach(['announcement','news'] as $type){
    $name=$type.'SanitizeHtml';eval(regressionFunction($type.'-article.php',$name));
    foreach(['java&#x09;script:alert(1)','java&#x0a;script:alert(1)','java&#x0d;script:alert(1)','&#9;javascript:alert(1)','data:text/html,bad'] as $url){
        $clean=$name('<p>Safe</p><a href="'.$url.'">link</a><img src="'.$url.'">');
        $doc=new DOMDocument();@$doc->loadHTML($clean);
        foreach($doc->getElementsByTagName('a') as $a)securityCheck(!$a->hasAttribute('href'),$type.' rejects unsafe link');
        foreach($doc->getElementsByTagName('img') as $img)securityCheck(!$img->hasAttribute('src'),$type.' rejects unsafe image');
    }
    foreach(['/contact-us','https://example.org/path','guide.pdf','mailto:info@example.org'] as $url)securityCheck(str_contains($name('<a href="'.$url.'">safe</a>'),'href='),$type.' retains safe URL');
}
eval(regressionFunction('admin/events.php','evt_categoryColor'));
securityCheck(evt_categoryColor('#abc')==='#abc','Short hex retained');securityCheck(evt_categoryColor('#123456')==='#123456','Hex retained');
foreach(['red;display:none','" onmouseover="alert(1)','#1234567'] as $color)securityCheck(evt_categoryColor($color)==='#6c757d','Invalid color falls back');
$config=file_get_contents(__DIR__.'/../config/config.php');
foreach(['GNCBOT_IP_LIMIT'=>20,'GNCBOT_DAILY_LIMIT'=>3000] as $key=>$expected){
    $line=array_values(array_filter(explode("\n",$config),fn($line)=>str_starts_with($line,"define('".$key."',")))[0];
    $expression=substr(trim($line),strpos($line,',')+1,-2);$_ENV=[];$actual=eval('return '.$expression.';');securityCheck($actual===$expected,$key.' missing default');$_ENV[$key]='7';securityCheck(eval('return '.$expression.';')===7,$key.' configured limit');
}

$contact=file_get_contents(__DIR__.'/../pages/contact-us.php');
$start=strpos($contact,'    $redirectPath =');$end=strpos($contact,"    header('Location:",$start);$redirect=substr($contact,$start,$end-$start);
foreach(['/contact-us?x=1'=>'/contact-us','//evil.example/path'=>'/contact-us','/\\evil.example'=>'/contact-us','relative'=>'/contact-us'] as $uri=>$expected){$_SERVER['REQUEST_URI']=$uri;eval($redirect);securityCheck($redirectPath===$expected,'Contact redirect remains local');}
$events=file_get_contents(__DIR__.'/../admin/events.php');foreach(explode("\n",$events) as $line)if(str_starts_with($line,'$year ='))$yearCode=$line;
foreach(['999999999999999999'=>2100,'-9000'=>1970,'2026'=>2026] as $input=>$expected){$_GET['year']=(string)$input;eval($yearCode);securityCheck($year===$expected,'Calendar year bounded');}
$announcement=file_get_contents(__DIR__.'/../admin/announcement.php');$hydrate=substr($announcement,strpos($announcement,'// Auto-reopen the modal on load:'));
foreach(explode("\n",$hydrate) as $line)if(str_contains($line,'json_encode('))securityCheck(str_contains($line,'JSON_HEX_TAG'),'Modal JSON escaped');
echo "$checks security input checks passed without application data writes.\n";
