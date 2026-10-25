<?php
require_once __DIR__.'/../includes/image-upload.php';
$source=file_get_contents(__DIR__.'/image-upload-before.php');
$start=strpos($source,'function imageUploadOptimizePath(');
$end=strpos($source,'/** All storage handlers', $start);
eval(str_replace('function imageUploadOptimizePath(', 'function baselineImageUploadOptimizePath(', substr($source,$start,$end-$start)));
$input=tempnam(sys_get_temp_dir(),'gnc-opt-bench-');$copy=tempnam(sys_get_temp_dir(),'gnc-opt-bench-');
try{
    $im=new Imagick();$im->readImageBlob("P6\n3000 2400\n255\n".random_bytes(3000*2400*3));$im->setImageFormat('png');$im->writeImage($input);$im->clear();
    foreach(['before'=>'baselineImageUploadOptimizePath','current'=>'imageUploadOptimizePath'] as $label=>$fn){copy($input,$copy);$time=microtime(true);$fn($copy);clearstatcache(true,$copy);echo json_encode(['version'=>$label,'seconds'=>round(microtime(true)-$time,3),'input_mb'=>round(filesize($input)/1048576,2),'output_mb'=>round(filesize($copy)/1048576,2)]),PHP_EOL;}
}finally{unlink($input);unlink($copy);}
