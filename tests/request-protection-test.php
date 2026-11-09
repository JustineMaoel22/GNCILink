<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/request-protection.php';
$dir=sys_get_temp_dir().'/gnci-limit-test-'.bin2hex(random_bytes(8));
$checks=0;
function rpCheck(bool $ok,string $label): void {global $checks;if(!$ok)throw new RuntimeException($label);$checks++;}
try {
    rpCheck(gncRequestConsume('a',2,60,100,$dir)['allowed'],'first allowed');
    rpCheck(gncRequestConsume('a',2,60,101,$dir)['allowed'],'second allowed');
    $denied=gncRequestConsume('a',2,60,102,$dir);
    rpCheck(!$denied['allowed']&&$denied['retry_after']===58&&$denied['first_denial'],'quota and retry after');
    rpCheck(!gncRequestConsume('a',2,60,103,$dir)['first_denial'],'violations log once per window');
    rpCheck(gncRequestConsume('b',2,60,103,$dir)['allowed'],'identity isolation');
    rpCheck(gncRequestConsume('a',2,60,160,$dir)['allowed'],'window expires without extension');
    $broken=$dir.'/'.substr(hash('sha256','broken'),0,2).'.json';file_put_contents($broken,'broken');
    try {gncRequestConsume('broken',1,60,200,$dir);throw new LogicException('corrupt store must fail');}catch(RuntimeException $e){rpCheck(true,'corruption fails closed');}
    $_SERVER['REMOTE_ADDR']='192.0.2.1';$_SERVER['HTTP_X_FORWARDED_FOR']='198.51.100.10';
    putenv('REQUEST_CLIENT_IP_HEADER=HTTP_X_FORWARDED_FOR');putenv('REQUEST_TRUSTED_PROXIES=');
    rpCheck(gncRequestIp()==='192.0.2.1','forged proxy ignored');
    putenv('REQUEST_TRUSTED_PROXIES=192.0.2.1');rpCheck(gncRequestIp()==='198.51.100.10','explicit trusted proxy');
    foreach(glob(__DIR__.'/../admin/action/*.php') as $file) {
        $route='/admin/action/'.basename($file);
        rpCheck(gncRequestPolicy($route,'POST',[],[],false)!==null,'policy '.$route);
    }
    foreach(glob(__DIR__.'/../api/*.php') as $file) rpCheck(gncRequestPolicy('/api/'.basename($file),'GET',[],[],false)!==null,'public policy');
    rpCheck(gncRequestPolicy('/index.php','GET',[],[],false)===null,'normal navigation unaffected');
    rpCheck(gncRequestPolicy('/index.php','GET',['evt_month'=>'11'],[],false)[0]==='search-read','calendar queries covered');
    rpCheck(gncRequestPolicy('/admin/events.php','POST',[],[],true)[0]==='uploads','embedded uploads covered');
    rpCheck(gncRequestPolicy('/pages/contact-us.php','POST',[],[],false)[1]===5,'contact quota');
    echo "PASS: $checks request protection checks.\n";
} finally {
    // Only files created in this random isolated test directory.
    foreach(glob($dir.'/*.json')?:[] as $file)unlink($file);
    if(is_dir($dir))rmdir($dir);
}
