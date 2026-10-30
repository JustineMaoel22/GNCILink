<?php
require_once __DIR__.'/text-layout.php';
require_once __DIR__.'/image-framing.php';

function responsiveBackgroundRoute(string $route): string {
    $route=textLayoutRoute($route);
    $parts=parse_url($route);parse_str($parts['query']??'',$query);
    if(preg_match('~/student-org\.php$~',$parts['path']))foreach(['p','category','archived'] as $key)unset($query[$key]);
    return $parts['path'].($query?'?'.http_build_query($query,'','&',PHP_QUERY_RFC3986):'');
}
function responsiveBackgroundSelector(string $selector): string {
    if(strlen($selector)>1000||!preg_match('/\A(?:[a-zA-Z][a-zA-Z0-9-]*|#[a-zA-Z0-9_-]+)(?:(?:[.#][a-zA-Z0-9_-]+)|(?::nth-of-type\([1-9][0-9]{0,3}\))|(?: > (?:[a-zA-Z][a-zA-Z0-9-]*|#[a-zA-Z0-9_-]+)))*(?:::before|::after)?\z/D',$selector))throw new InvalidArgumentException('Invalid background section. Refresh the editor.');
    return $selector;
}
function responsiveBackgroundNormalize(array $value): array {
    if(array_diff(array_keys($value),['path','x','y','zoom','fit','kind','upload']))throw new InvalidArgumentException('Invalid background settings.');
    $frame=imageFramingNormalize($value);
    if(!in_array($value['kind']??'', ['background','image'],true)||!in_array($value['fit']??'cover',['cover','contain'],true))throw new InvalidArgumentException('Choose Cover or Contain for the background.');
    $path=$value['path']??'';
    if(!is_string($path)||($path!==''&&!responsiveBackgroundPath($path)))throw new InvalidArgumentException('Choose an image stored on this website.');
    return $frame+['fit'=>$value['fit']??'cover','kind'=>$value['kind'],'path'=>$path];
}
function responsiveBackgroundPath(string $path): ?string {
    $decoded=rawurldecode($path);
    if(!preg_match('~^/(uploads|assets/images)/[^?\x00-\x1f<>"\'\\\\]+\.(jpe?g|png|webp|gif|svg)$~iD',$decoded)||str_contains($decoded,'..'))return null;
    $root=realpath(dirname(__DIR__).(str_starts_with($decoded,'/uploads/')?'/uploads':'/assets/images'));$file=realpath(dirname(__DIR__).$decoded);
    return $root&&$file&&is_file($file)&&str_starts_with(str_replace('\\','/',$file),str_replace('\\','/',$root).'/')?$path:null;
}
function responsiveBackgroundLoad(PDO $db,string $route): array {
    try {
        $stmt=$db->prepare('SELECT selector,device,settings FROM responsive_background_settings WHERE page_key=?');$stmt->execute([hash('sha256',responsiveBackgroundRoute($route))]);$out=[];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row)$out[$row['selector']][$row['device']]=responsiveBackgroundNormalize(json_decode($row['settings'],true,16,JSON_THROW_ON_ERROR));
        return $out;
    } catch(PDOException $e){if($e->getCode()==='42S02')return [];throw $e;}
}
