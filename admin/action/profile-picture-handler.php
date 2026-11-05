<?php
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../admin-functions.php';
initSession();requireLogin();
require_once __DIR__.'/../../includes/profile-picture.php';
header('Cache-Control: no-store');
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){header('Allow: POST');rbacFail('Use the profile form to change your picture.',405);}
try{
    if(!authCsrfValid())rbacFail('Your session token expired. Reload and try again.',403);
    if(!is_string($_POST['profile_action']??null))throw new InvalidArgumentException('Choose a valid profile action.');
    saveOwnProfilePicture($_POST['profile_action'],is_array($_FILES['profile_picture']??null)?$_FILES['profile_picture']:null);
    $_SESSION['profile_picture_notice']=$_POST['profile_action']==='remove'?'Profile picture removed.':'Profile picture updated.';
}catch(InvalidArgumentException $e){$_SESSION['profile_picture_error']=$e->getMessage();}
catch(Throwable $e){error_log('Profile picture update failed.');$_SESSION['profile_picture_error']='Your profile picture could not be saved. Try again or contact your administrator.';}
header('Location: /admin/settings.php#profile-picture',true,303);exit;
