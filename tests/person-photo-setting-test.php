<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../admin/admin-functions.php';
$db=getDB();
// Shadow only this connection's setting; the application's switch is untouched.
$db->exec('CREATE TEMPORARY TABLE admin_settings(setting_key VARCHAR(100) PRIMARY KEY,setting_value TEXT)');
$db->exec("INSERT INTO admin_settings VALUES('ai_image_checker_enabled','0')");
$image='/assets/images/test-image.png';
$path=personPhotoPath($image);
if(!$path)throw new RuntimeException('Test image unavailable.');
$result=personPhotoCheck($path,'Retained photo test','person:0',static function(){throw new RuntimeException('Disabled comparison must not run.');});
if(!$result['ok']||$result['checked']||$result['reason']!=='disabled')throw new RuntimeException('Face check did not honor the persisted setting.');
personPhotoEnforce(null,false,$image,'Retained photo test','person:0');
$basic=photoCheckImage($path);
if(!$basic['ok']||$basic['checked']||$basic['reason']!=='disabled')throw new RuntimeException('AI check did not honor the same setting.');
if(photoCheckImage(__FILE__)['reason']!=='invalid_image')throw new RuntimeException('Disabling the checker must not bypass image validation.');
try{personPhotoEnforce(null,false,'/config/config.php','Invalid photo','person:0');throw new LogicException('Unsafe retained photo accepted.');}
catch(RuntimeException $expected){}
$db->exec("UPDATE admin_settings SET setting_value='1'");
if(!aiImageCheckerEnabled())throw new RuntimeException('Re-enabling must be visible immediately.');
echo "PASS: persisted Off skips face and AI checks, retained-photo saves honor Off, invalid/non-image paths remain rejected, and On is read immediately. Only a temporary settings table used.\n";
