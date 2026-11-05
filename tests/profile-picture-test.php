<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';require_once __DIR__.'/../admin/admin-functions.php';require_once __DIR__.'/../includes/profile-picture.php';
$db=getDB();$checks=0;
function profileCheck(bool $condition,string $message): void{global $checks;if(!$condition)throw new RuntimeException($message);$checks++;}
$db->exec('CREATE TEMPORARY TABLE admin_settings(setting_key VARCHAR(100) PRIMARY KEY,setting_value TEXT) ENGINE=InnoDB');
$db->exec('CREATE TEMPORARY TABLE activity_logs(log_id INT AUTO_INCREMENT PRIMARY KEY,user_id INT,action VARCHAR(255),table_name VARCHAR(100),record_id INT,description TEXT,created_at DATETIME) ENGINE=InnoDB');
$path=tempnam(sys_get_temp_dir(),'gnci-profile-');
try{
    file_put_contents($path,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aN1sAAAAASUVORK5CYII='));
    $image=new Imagick();$image->newImage(900,600,'green','png');$image->setImageProperty('comment','Private EXIF-style metadata');$image->writeImage($path);$image->clear();
    $encoded=profilePictureEncode($path);$info=getimagesizefromstring(base64_decode($encoded));
    profileCheck($info[0]<=256&&$info[1]<=256&&$info[2]===IMAGETYPE_JPEG,'Reencode and resize');
    profileCheck(!str_contains(base64_decode($encoded),'Private EXIF-style metadata'),'Strip metadata');
    profileCheck(strlen($encoded)<=64000,'Fits existing text storage');
    foreach(['Super Admin','Content Editor','The Mirror'] as $role){
        $_SESSION=['user_id'=>1,'user_role'=>$role];
        $db->prepare('INSERT INTO admin_settings VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute(['user_profile_picture_1',$encoded]);
        $db->prepare('INSERT INTO admin_settings VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)')->execute(['user_profile_picture_2',$encoded]);
        profileCheck(str_starts_with(profilePictureUrl(1),'data:image/jpeg;base64,'),'Read saved picture');
        saveOwnProfilePicture('remove',null);
        profileCheck(profilePictureUrl(1)===null&&profilePictureUrl(2)!==null,'Remove only own picture for '.$role);
        profileCheck(rbacRouteAllowed($role,'/admin/action/profile-picture-handler.php'),'Role route access');
    }
    foreach(['upload','bad-action'] as $action){try{saveOwnProfilePicture($action,null);throw new LogicException('Expected invalid request');}catch(InvalidArgumentException $e){profileCheck(true,'Reject missing file or invalid action');}}
    try{saveOwnProfilePicture('upload',['tmp_name'=>$path,'error'=>UPLOAD_ERR_OK]);throw new LogicException('Expected forged upload rejection');}catch(InvalidArgumentException $e){profileCheck(true,'Reject forged upload');}
    file_put_contents($path,'<svg onload="alert(1)"></svg>');
    try{profilePictureEncode($path);throw new LogicException('Expected invalid image rejection');}catch(InvalidArgumentException $e){profileCheck(true,'Reject SVG');}
    $_SESSION['must_change_password']=true;
    try{saveOwnProfilePicture('remove',null);throw new LogicException('Expected password restriction');}catch(RuntimeException $e){profileCheck(true,'Password change restriction');}
    profileCheck((int)$db->query('SELECT COUNT(*) FROM activity_logs')->fetchColumn()===3,'Audit profile updates');
    echo "$checks profile picture checks passed. No persistent data changed.\n";
}finally{unlink($path);}
