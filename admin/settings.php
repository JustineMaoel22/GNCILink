<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/admin-functions.php';
initSession();requireLogin();
header('Cache-Control: no-store, private');
$canManageWebsiteSettings=hasPermission('manage_settings');
$directSave=rbacIsSuper();
$ajax=($_SERVER['HTTP_X_REQUESTED_WITH']??'')==='XMLHttpRequest';
$jsonResponse=$directSave&&($ajax||!empty($_SESSION['_rbac_worker']));
$error='';$failedAction='';
$definitions=[
    'save_page_suggestions'=>['key'=>'page_suggestions_enabled','read'=>'pageSuggestionsEnabled','save'=>'savePageSuggestionsSetting','section'=>'website-preferences','title'=>'Suggested pages popup'],
    'save_ai_image_checker'=>['key'=>'ai_image_checker_enabled','read'=>'aiImageCheckerEnabled','save'=>'saveAiImageCheckerSetting','section'=>'ai-features','title'=>'AI image checker'],
    'save_student_email_url'=>['key'=>'student_email_url','read'=>'studentEmailUrl','save'=>'saveStudentEmailUrlSetting','section'=>'student-email','title'=>'Student email link','type'=>'url'],
];
if($directSave)$definitions=array_slice($definitions,0,1,true)+[
    'save_page_suggestion_delay'=>['key'=>'page_suggestion_delay_seconds','read'=>'pageSuggestionDelay','save'=>'savePageSuggestionDelaySetting','section'=>'page-suggestion-timing','title'=>'Page suggestion delay','type'=>'number'],
]+array_slice($definitions,1,null,true);
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!$canManageWebsiteSettings)rbacFail('Your role cannot change website settings.',403);
    try{
        $action=$_POST['action']??'save_ai_image_checker';
        if(!is_string($action)||!isset($definitions[$action]))throw new InvalidArgumentException('Choose a valid settings action.');
        $definition=$definitions[$action];$failedAction=$action;
        $definition['save']($_POST);
        if($jsonResponse){
            $stored=$definition['read']();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success'=>true,'value'=>isset($definition['type'])?(string)$stored:($stored?'1':'0'),'message'=>$definition['title'].' saved.']);exit;
        }
        $_SESSION['settings_saved']=$action;
        header('Location: /admin/settings.php#'.$definition['section'],true,303);exit;
    }catch(InvalidArgumentException $exception){http_response_code(422);$error=$exception->getMessage();}
    catch(Throwable $exception){http_response_code(500);error_log('Admin settings save could not be confirmed.');$error='Could not confirm the save. Your selection has been kept. Retry or reload to check the current setting.';}
    if($jsonResponse){header('Content-Type: application/json; charset=utf-8');echo json_encode(['success'=>false,'error'=>$error]);exit;}
}
$states=[];
if($canManageWebsiteSettings)foreach($definitions as $action=>$definition){
    $value=null;
    try{$value=$definition['read']();}catch(Throwable $exception){error_log('Admin setting unavailable: '.$definition['key']);}
    $selected=$value;
    if($failedAction===$action&&is_string($_POST[$definition['key']]??null)&&in_array($_POST[$definition['key']],['0','1'],true))$selected=$_POST[$definition['key']]==='1';
    if($failedAction===$action&&isset($definition['type'])&&is_string($_POST[$definition['key']]??null))$selected=$_POST[$definition['key']];
    $states[$action]=['saved'=>$value,'selected'=>$selected];
}
$connectionLabel='Status unavailable';$connectionTone='neutral';
if($canManageWebsiteSettings){
    try{
        // The stored connection is authoritative for configuration, not live token health.
        $connection=getDB()->query('SELECT enabled FROM facebook_publishing_connection WHERE connection_id=1')->fetchColumn();
        $connected=$connection!==false&&(int)$connection===1;
        $connectionLabel=$connected?'Connected':'Disconnected';$connectionTone=$connected?'success':'neutral';
    }catch(PDOException $exception){if($exception->getCode()==='42S02')$connectionLabel='Needs setup';}
}
$saved=$_SESSION['settings_saved']??null;unset($_SESSION['settings_saved']);
$pageTitle='Settings';require __DIR__.'/../components/header-admin.php';
$e=fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
?>
<link rel="stylesheet" href="/assets/css/admin-settings.css?v=<?= (int)filemtime(__DIR__.'/../assets/css/admin-settings.css') ?>">
<div class="settings-page">
<div class="page-header"><div><h1>Settings</h1><p>Manage account security, connected services and website preferences.</p></div><a class="settings-help" href="/admin/help.php?topic=account-security"><i class="bi bi-question-circle" aria-hidden="true"></i> Settings help</a></div>
<?php if($saved): ?><div class="alert alert-success" role="status"><?= $e(is_string($saved)&&isset($definitions[$saved])?$definitions[$saved]['title'].' saved.':'Settings saved.') ?></div><?php endif; ?>
<?php if($error): ?><div class="alert alert-danger" role="alert"><?= $e($error) ?></div><?php endif; ?>
<?php if($canManageWebsiteSettings&&!$directSave): ?><p class="settings-approval-note"><i class="bi bi-info-circle" aria-hidden="true"></i> Website changes are submitted for Super Admin approval. The current website stays unchanged until approval.</p><?php endif; ?>
<section class="data-card settings-card" id="account-security" aria-labelledby="account-security-title">
<header class="settings-section-heading"><span class="settings-icon"><i class="bi bi-shield-lock" aria-hidden="true"></i></span><div><h2 id="account-security-title">Account &amp; Security</h2><p>Keep your own account secure.</p></div><span class="settings-scope">Your account</span></header>
<div class="settings-profile" id="profile-picture">
<?php if(isset($_SESSION['profile_picture_notice'])): ?><p class="settings-feedback-success" role="status"><?= $e($_SESSION['profile_picture_notice']) ?></p><?php unset($_SESSION['profile_picture_notice']);endif; ?>
<?php if(isset($_SESSION['profile_picture_error'])): ?><p class="settings-feedback-error" role="alert"><?= $e($_SESSION['profile_picture_error']) ?></p><?php unset($_SESSION['profile_picture_error']);endif; ?>
<div class="settings-profile-row"><div class="settings-profile-preview"><?php if($currentProfilePicture): ?><img src="<?= $e($currentProfilePicture) ?>" alt="Your current profile picture"><?php else: ?><span aria-hidden="true"><?= $e(strtoupper(substr($currentUser['first_name']??'A',0,1))) ?></span><?php endif; ?></div><div><h3>Profile picture</h3><p>Personalize your account picture in the admin header. This changes only your own profile.</p></div></div>
<form action="/admin/action/profile-picture-handler.php" method="post" enctype="multipart/form-data" class="settings-profile-form"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="profile_action" value="upload"><label for="profile-picture-file">Choose a new picture</label><input type="file" class="form-control" id="profile-picture-file" data-image-frame-shape="circle" data-image-upload-hint="true" name="profile_picture" accept="image/jpeg,image/png,image/webp" aria-describedby="profile-picture-help" required><p id="profile-picture-help">JPG, PNG or WebP, up to 5 MB. Pictures are resized and shown in a circle.</p><button class="btn btn-gnc" type="submit">Save profile picture</button></form>
<?php if($currentProfilePicture): ?><form action="/admin/action/profile-picture-handler.php" method="post" class="settings-profile-remove"><input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="profile_action" value="remove"><button class="btn btn-outline-secondary" type="submit">Remove picture</button></form><?php endif; ?>
</div>
<div class="settings-config-row"><div><h3>Password</h3><p>Change your password using your current password. Choose a unique password you do not use elsewhere.</p><p class="settings-impact"><i class="bi bi-shield-check" aria-hidden="true"></i> Changing it signs out other active sessions and revokes remembered devices.</p></div><a class="btn btn-gnc" href="/admin/account-password.php">Change Password <i class="bi bi-arrow-right" aria-hidden="true"></i></a></div>
</section>
<section class="data-card settings-card" id="appearance" aria-labelledby="appearance-title">
<header class="settings-section-heading"><span class="settings-icon"><i class="bi bi-fonts" aria-hidden="true"></i></span><div><h2 id="appearance-title">Appearance</h2><p>Make the admin panel easier to read.</p></div><span class="settings-scope">This browser</span></header>
<div class="settings-config-row"><div><h3><label for="admin-font-size">Admin font size</label></h3><p id="admin-font-size-help">Choose your preferred text size. Changes apply immediately and are remembered for your account in this browser.</p></div><select id="admin-font-size" class="form-select settings-font-size" aria-describedby="admin-font-size-help admin-font-size-status"><option value="standard">Standard (100%)</option><option value="large">Larger (112.5%)</option><option value="extra-large">Extra large (125%)</option></select></div>
<p class="settings-feedback" id="admin-font-size-status" role="status">Changes save automatically.</p>
</section>
<?php if($canManageWebsiteSettings): ?>
<section class="data-card settings-card" id="integrations" aria-labelledby="integrations-title">
<header class="settings-section-heading"><span class="settings-icon"><i class="bi bi-plug" aria-hidden="true"></i></span><div><h2 id="integrations-title">Integrations</h2><p>Manage connected services.</p></div><span class="settings-scope">Website-wide</span></header>
<div class="settings-config-row"><div><h3>Facebook <span class="settings-badge settings-badge-<?= $connectionTone ?>"><?= $e($connectionLabel) ?></span></h3><p>Connect the school Facebook Page and review announcement publishing deliveries.</p><p class="settings-detail">Status reflects the saved connection. Open integration management to review access and publishing details.</p></div><a class="btn btn-outline-secondary" href="/admin/facebook-integration.php">Manage Facebook Integration <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></div>
</section>
<?php foreach($definitions as $action=>$definition):
    $isAi=$action==='save_ai_image_checker';$isUrl=($definition['type']??'')==='url';$isNumber=($definition['type']??'')==='number';$autoSave=$directSave&&!isset($definition['type']);$state=$states[$action];$known=$state['saved']!==null;$checked=$state['selected']===true;
?>
<?php if($isNumber): ?>
<div id="page-suggestion-timing">
<?php else: ?>
<section class="data-card settings-card" id="<?= $definition['section'] ?>" aria-labelledby="<?= $definition['section'] ?>-title">
<header class="settings-section-heading"><span class="settings-icon"><i class="bi bi-<?= $isUrl?'envelope':($isAi?'image':'sliders') ?>" aria-hidden="true"></i></span><div><h2 id="<?= $definition['section'] ?>-title"><?= $isNumber?'Page Suggestions':($isUrl?'Student Email':($isAi?'AI Features':'Website Preferences')) ?></h2><p><?= $isUrl?'Choose where students access their school email.':($isAi?'Control optional photo checking.':'Choose how visitors experience the website.') ?></p></div><span class="settings-scope"><?= $isAi?'All administrators':'Public website' ?></span></header>
<?php endif; ?>
<form method="post" action="/admin/settings.php" class="settings-preference" data-settings-form data-auto-save="<?= $autoSave?'1':'0' ?>" data-direct-save="<?= $directSave?'1':'0' ?>" data-saved-value="<?= isset($definition['type'])?$e($state['saved']??''):($known?($state['saved']?'1':'0'):'') ?>" data-initial-error="<?= $failedAction===$action&&$error?'1':'0' ?>">
<input type="hidden" name="csrf_token" value="<?= $e($csrfToken) ?>"><input type="hidden" name="action" value="<?= $action ?>">
<?php if($isNumber): ?>
<div class="settings-config-row"><div><h3><label for="page_suggestion_delay_seconds">Page suggestion delay (seconds)</label></h3><p id="page-suggestion-delay-help">Wait this long after opening a public page before showing You May Also Like. Enter 5–3600 seconds (60 seconds = 1 minute). The default is 60 seconds. Applies on the next page load when the popup is enabled; other open dialogs may delay it further.</p><input type="number" class="form-control mt-3" id="page_suggestion_delay_seconds" name="page_suggestion_delay_seconds" data-settings-input value="<?= $e($state['selected']??'') ?>" min="5" max="3600" step="1" required aria-describedby="page-suggestion-delay-help"<?= !$known?' disabled':'' ?>></div></div>
<?php elseif($isUrl): ?>
<div class="settings-config-row"><div><h3><label for="student_email_url">Access Student Email URL</label></h3><p id="student-email-url-help">Use a complete http:// or https:// address. This updates the button in both desktop and mobile navigation.</p><input type="url" class="form-control mt-3" id="student_email_url" name="student_email_url" data-settings-input value="<?= $e($state['selected']??'') ?>" maxlength="2048" required aria-describedby="student-email-url-help"<?= !$known?' disabled':'' ?>></div></div>
<?php else: ?>
<input type="hidden" name="<?= $definition['key'] ?>" value="0">
<div class="settings-config-row"><div><h3 id="<?= $definition['key'] ?>-label"><?= $e($definition['title']) ?></h3><p id="<?= $definition['key'] ?>-description"><?= $isAi?'Check uploaded people photos for non-person images and signs of AI-generated content. The result is an estimate, not proof.':'Show visitors the You May Also Like popup with suggested pages. Turning it off hides the popup when pages are loaded.' ?></p><p class="settings-impact"><?= $isAi?'Applies to all admin users. File type and size checks stay active even when this checker is disabled.':'This setting affects the public website.' ?></p></div><label class="settings-toggle" for="<?= $definition['key'] ?>"><input type="checkbox" role="switch" id="<?= $definition['key'] ?>" name="<?= $definition['key'] ?>" value="1" aria-labelledby="<?= $definition['key'] ?>-label" aria-describedby="<?= $definition['key'] ?>-description"<?= $checked?' checked':'' ?><?= !$known?' disabled':'' ?>><span data-settings-value><?= $known?($checked?'Enabled':'Disabled'):'Unavailable' ?></span></label></div>
<?php endif; ?>
<?php if($isAi): ?><p class="settings-ai-notice" data-ai-notice<?= $checked?' hidden':'' ?>><i class="bi bi-exclamation-triangle" aria-hidden="true"></i> With this checker disabled, people photos will not be checked for unsuitable or AI-generated content. File type and size limits still apply.</p><?php endif; ?>
<?php if(!$known): ?><p class="settings-feedback settings-feedback-error" role="alert">The current setting could not be loaded. <a href="/admin/settings.php#<?= $definition['section'] ?>">Reload to try again</a>.</p><?php endif; ?>
<div class="settings-save-row"><p class="settings-change-status" data-settings-status role="status">Each setting is saved separately.</p><?php if(!$autoSave): ?><button class="btn btn-gnc" type="submit" data-save-label="<?= $directSave?'Save changes':'Submit for approval' ?>"<?= !$known?' disabled':'' ?>><?= $directSave?'Save changes':'Submit for approval' ?></button><?php else: ?><noscript><button class="btn btn-gnc" type="submit"<?= !$known?' disabled':'' ?>>Save changes</button></noscript><?php endif; ?></div>
<p class="settings-feedback" data-settings-feedback role="status" hidden></p>
</form>
<?php if($isNumber): ?></div><?php endif; ?>
<?php if($action!=='save_page_suggestions'||!$directSave): ?>
</section>
<?php endif; ?>
<?php endforeach; endif; ?>
</div>
<script src="/assets/js/admin-settings.js?v=<?= (int)filemtime(__DIR__.'/../assets/js/admin-settings.js') ?>"></script>
<?php require __DIR__.'/../components/footer-admin.php'; ?>
