<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/admin-functions.php';
initSession();requireLogin();requirePermission('manage_page_content');
$db=getDB();$ready=personnelEnabled($db);$error='';$notice='';
if (($_SERVER['REQUEST_METHOD']??'GET')==='POST') {
    try {
        if (!rbacIsSuper()) throw new RuntimeException('Only Super Admin can link personnel identities.',403);
        if (!verifyCSRFToken(is_string($_POST['csrf_token']??null)?$_POST['csrf_token']:'')) throw new RuntimeException('Refresh the page and try again.',403);
        if (($_POST['action']??'')!=='publish') throw new InvalidArgumentException('Invalid identity review action.');
        if (($_POST['confirmed']??'')!=='1') throw new InvalidArgumentException('Confirm that you have reviewed the selected profiles.');
        $profile=$_POST['profile']??'';$target=filter_var($_POST['personnel_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>0]]);
        if (!is_string($profile)||$target===false) throw new InvalidArgumentException('Choose a valid person.');
        personnelLink($db,$profile,$target);
        $_SESSION['personnel_notice']='Personnel identity updated. Refresh any open editor to load the shared name.';
        header('Location: /admin/personnel.php');exit;
    } catch (Throwable $e) {
        http_response_code($e instanceof PDOException?503:($e->getCode()===403?403:422));
        $error=$e instanceof PDOException?'Could not update this identity. Please try again.':$e->getMessage();
    }
}
$notice=$_SESSION['personnel_notice']??'';unset($_SESSION['personnel_notice']);
$people=$ready?$db->query('SELECT personnel_id,full_name FROM personnel ORDER BY full_name,personnel_id')->fetchAll(PDO::FETCH_ASSOC):[];
$profiles=$ready?personnelProfiles($db):[];$photos=[];
if ($ready) foreach(personPhotoInventory($db) as $photo) $photos[$photo['profile_key']]=$photo['image'];
$labels=['person'=>'Administration','office'=>'Office head','dean'=>'Department head','president'=>'Homepage president'];
$contexts=['person'=>'role','office'=>'office_name','dean'=>'dept_name','president'=>'title'];
foreach(personnelDefinitions() as $kind=>$d) {
    if(!$ready)break;
    foreach($db->query("SELECT {$d['id']} AS record_id,{$contexts[$kind]} AS context FROM {$d['table']}")->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key=$kind.':'.$row['record_id'];$profiles[$key]['context']=$labels[$kind].($row['context']?' — '.$row['context']:'');
    }
}
$directory=[];
foreach($people as $person) {
    $id=(int)$person['personnel_id'];$uses=[];$image='';
    foreach($profiles as $key=>$profile) if((int)$profile['personnel_id']===$id) {
        $uses[]=$profile['context']??'';
        if(!$image&&personPhotoPath($photos[$key]??null))$image='/'.ltrim($photos[$key],'/');
    }
    $directory[$id]=['name'=>$person['full_name'],'context'=>implode(' / ',array_slice($uses,0,2)),'uses'=>count($uses),'image'=>$image];
}
function personnelEscape($value): string {return htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');}
include __DIR__.'/../components/header-admin.php';
?>
<main class="container-fluid py-4 personnel-manager" style="max-width:1100px">
    <a href="/admin/content-edit.php?page=administration" class="btn btn-outline-secondary mb-3">Back to Administration</a>
    <h1>Personnel identities</h1>
    <p class="text-muted">Link profiles only when you have verified that they represent the same person. Linked profiles share a name; their roles, contact details and photos stay in their current sections.</p>
    <?php if($error): ?><div class="alert alert-danger" role="alert"><?= personnelEscape($error) ?></div><?php endif; ?>
    <?php if($notice): ?><div class="alert alert-success" role="status"><?= personnelEscape($notice) ?></div><?php endif; ?>
    <?php if(!$ready): ?><div class="alert alert-warning">Shared personnel storage has not been enabled. Run the verified maintenance migration before linking profiles.</div><?php else: ?>
    <label for="personnel-search" class="form-label">Find a profile</label>
    <input id="personnel-search" type="search" class="form-control mb-3" placeholder="Search by name, role or office">
    <p class="small text-muted">Identical names remain separate until reviewed. To correct a mistaken link, choose “Create a separate person”. Name changes in the content editor apply to all linked profiles.</p>
    <?php foreach($profiles as $key=>$profile): $id=(int)$profile['personnel_id']; ?>
    <details class="data-card p-3 mb-2 personnel-profile" data-search="<?= personnelEscape(mb_strtolower(($profile['full_name']??'').' '.($profile['context']??''))) ?>">
        <summary><strong><?= personnelEscape($profile['full_name']?:'Unnamed profile') ?></strong><span class="d-block small text-muted"><?= personnelEscape($profile['context']??'') ?><?= $id&&($directory[$id]['uses']??0)>1?' · '.$directory[$id]['uses'].' linked profiles':'' ?></span></summary>
        <?php if(rbacIsSuper()): ?>
        <form method="post" class="personnel-link-form mt-3" data-current="<?= $id ?>">
            <input type="hidden" name="action" value="publish">
            <input type="hidden" name="csrf_token" value="<?= personnelEscape(generateCSRFToken()) ?>">
            <input type="hidden" name="profile" value="<?= personnelEscape($key) ?>">
            <label class="form-label">Person represented by this profile
                <select name="personnel_id" class="form-select" required>
                    <?php foreach($directory as $personId=>$person): ?><option value="<?= $personId ?>" <?= $personId===$id?'selected':'' ?>><?= personnelEscape($person['name'].($person['context']?' — '.$person['context']:'')) ?></option><?php endforeach; ?>
                    <option value="0" <?= !$id?'selected':'' ?>>Create a separate person</option>
                </select>
            </label>
            <div class="row g-3 my-2"><figure class="col-6 m-0"><figcaption class="small">Current profile</figcaption><?php if(personPhotoPath($photos[$key]??null)): ?><img src="<?= personnelEscape('/'.ltrim($photos[$key],'/')) ?>" alt="<?= personnelEscape($profile['full_name']) ?>" class="w-100 rounded" style="height:150px;object-fit:contain"><?php endif; ?><span><?= personnelEscape($profile['full_name']) ?></span></figure><figure class="col-6 m-0"><figcaption class="small">Selected person</figcaption><img class="personnel-match-photo w-100 rounded" alt="" style="height:150px;object-fit:contain" hidden><span class="personnel-match-name"></span></figure></div>
            <label class="d-block mb-2"><input type="checkbox" name="confirmed" value="1" required> I reviewed these profiles and verified this identity assignment.</label>
            <button class="btn btn-success" type="submit">Save reviewed identity</button>
        </form>
        <?php else: ?><p class="mt-2">Ask a Super Admin to review identity links.</p><?php endif; ?>
    </details>
    <?php endforeach; endif; ?>
</main>
<script>
(function(){
    const people=<?= json_encode($directory,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?>;
    const search=document.getElementById('personnel-search');
    if(search)search.addEventListener('input',()=>document.querySelectorAll('.personnel-profile').forEach(row=>row.hidden=!row.dataset.search.includes(search.value.trim().toLowerCase())));
    document.querySelectorAll('.personnel-link-form').forEach(form=>{
        const select=form.querySelector('select'),checkbox=form.querySelector('[name=confirmed]');
        function preview(){const person=people[select.value],image=form.querySelector('.personnel-match-photo');image.hidden=!person?.image;if(person?.image)image.src=person.image;else image.removeAttribute('src');image.alt=person?.name||'';form.querySelector('.personnel-match-name').textContent=person?.name||'A separate identity for the current profile';checkbox.checked=false;}
        select.addEventListener('change',preview);preview();
    });
})();
</script>
<?php include __DIR__.'/../components/footer-admin.php'; ?>
