<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/responsive-backgrounds.php';
require_once __DIR__.'/../includes/content-submissions.php';
function checkBackground($condition,$message){if(!$condition)throw new RuntimeException($message);}
function rejectsBackground(callable $fn){try{$fn();}catch(InvalidArgumentException $e){return;}throw new RuntimeException('Unsafe background input accepted.');}
foreach(['#hero','div.hero-slide','section.quote-banner-box::before','#main > div:nth-of-type(2) > img:nth-of-type(1)'] as $selector)checkBackground(responsiveBackgroundSelector($selector)===$selector,'Valid selector');
foreach(['body{color:red}','#hero,body','div[style]','div::before{background:url(x)}',''] as $selector)rejectsBackground(fn()=>responsiveBackgroundSelector($selector));
foreach(['/config/config.php','/uploads/../../config/config.php','https://outside.test/image.jpg','/assets/images/%00.jpg'] as $path)rejectsBackground(fn()=>responsiveBackgroundNormalize(['kind'=>'background','path'=>$path]));
foreach([['x'=>101],['zoom'=>0],['fit'=>'stretch'],['kind'=>'video'],['css'=>'display:none']] as $bad)rejectsBackground(fn()=>responsiveBackgroundNormalize(array_merge(['kind'=>'background','path'=>''],$bad)));
$frame=responsiveBackgroundNormalize(['kind'=>'image','path'=>'','x'=>12,'y'=>34,'zoom'=>1.5,'fit'=>'contain']);
checkBackground($frame['x']===12.0&&$frame['fit']==='contain','Crop metadata preserved');
checkBackground(responsiveBackgroundRoute('/pages/academics/college/program/bael?ce_preview=1')==='/pages/academics/college/program.php?slug=bael','Clean program URLs share settings');
checkBackground(responsiveBackgroundRoute('/pages/student%20life/student organization and leadership program/student-org.php?p=2&category=3&ce_preview=1')==='/pages/student life/student organization and leadership program/student-org.php','Organization filters share background settings');
checkBackground(rbacShouldQueue('/admin/action/responsive-background-handler.php',[]),'Existing approval queue includes device backgrounds');
echo "PASS: background selectors, crop values, unsafe paths, route normalization and approval routing.\n";
