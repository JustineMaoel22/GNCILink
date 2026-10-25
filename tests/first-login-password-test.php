<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/first-login-password.php';
function checkPassword(bool $ok,string $label): void{if(!$ok)throw new RuntimeException($label);}
$seen=[];
for($i=0;$i<64;$i++){$password=generateTemporaryPassword();validateNewPassword($password,$password);checkPassword(strlen($password)===12,'Temporary password length');checkPassword(!isset($seen[$password]),'Temporary passwords must not be fixed/reused');$seen[$password]=true;}
validateNewPassword('lantern orchard violet river','lantern orchard violet river');
foreach([['short','short'],['Password123456!','Password123456!'],['aaaaaaaaaaaa','aaaaaaaaaaaa'],[str_repeat('A',73).'a1!',str_repeat('A',73).'a1!'],["Control\0Password9!","Control\0Password9!"],['Valid-test-password-2026!','different']] as [$password,$confirm]){
 try{validateNewPassword($password,$confirm);throw new RuntimeException('Invalid password accepted');}catch(InvalidArgumentException $expected){}
}
echo "PASS: secure temporary-password shape/uniqueness and length, class, control-character and confirmation validation. No accounts or passwords persisted.\n";
