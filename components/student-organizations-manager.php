<?php
if (!hasPermission('manage_page_content')) return;
$orgRows=studentOrganizations(true); $orgCategories=studentOrgCategories();
foreach ($orgRows as &$row) $row['gallery']=studentOrganizationGallery((int)$row['org_id']); unset($row);
$orgEscape=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');
?>
<div id="organizationManager" data-warning-scope>
    <p class="text-muted">Create an unpublished organization to preview it, then publish when ready. Names, photos, and page text can also be edited on the visual canvas.</p>
    <div class="d-flex justify-content-between align-items-center mb-3"><h3 class="h6 mb-0">Organizations</h3><button type="button" class="btn-gnc-primary btn-sm" id="orgAdd">Add Organization</button></div>
    <div class="org-management-list">
    <?php foreach ($orgRows as $index=>$row): ?>
        <div class="org-management-row"><div><strong><?= $orgEscape($row['name']) ?></strong><small><?= $orgEscape($row['category_name']?:'Uncategorized') ?> · <?= $row['status']==='published'?'Published':'Unpublished' ?></small></div><div class="org-management-actions">
            <button type="button" class="btn btn-sm btn-outline-secondary" data-org-edit="<?= (int)$row['org_id'] ?>">Edit</button>
            <a class="btn btn-sm btn-outline-secondary" href="?page=student-organizations&amp;sub=<?= rawurlencode($row['slug']) ?>">Preview</a>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-org-move="<?= (int)$row['org_id'] ?>" data-direction="-1" aria-label="Move <?= $orgEscape($row['name']) ?> up"<?= $index===0?' disabled':'' ?>>↑</button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-org-move="<?= (int)$row['org_id'] ?>" data-direction="1" aria-label="Move <?= $orgEscape($row['name']) ?> down"<?= $index===count($orgRows)-1?' disabled':'' ?>>↓</button>
            <button type="button" class="btn btn-sm btn-outline-danger" data-org-delete="<?= (int)$row['org_id'] ?>">Delete</button>
        </div></div>
    <?php endforeach; ?>
    <?php if (!$orgRows): ?><p class="text-muted">No organizations yet. Add your first organization to get started.</p><?php endif; ?>
    </div>
    <form id="orgEditForm" data-content-publish data-warning-section="Organization details" class="org-edit-form mt-4" hidden>
        <h3 class="h5" id="orgFormTitle">Add Organization</h3><input type="hidden" name="org_id" value="0"><input type="hidden" name="action" value="save">
        <?php foreach (studentOrganizationFields() as $key=>[$label,$max]): ?>
        <div class="mb-3"><label class="form-label" for="org-<?= $key ?>"><?= $orgEscape($label) ?><?= in_array($key,['vision','mission'],true)?' (optional)':'' ?></label>
        <?php if ($max>500): ?><textarea class="form-control" id="org-<?= $key ?>" name="<?= $key ?>" maxlength="<?= $max ?>" rows="<?= $key==='about_text'?5:3 ?>"<?= in_array($key,['vision','mission'],true)?' data-warning-optional="true"':'' ?>></textarea>
        <?php else: ?><input class="form-control" id="org-<?= $key ?>" name="<?= $key ?>" maxlength="<?= $max ?>"<?= $key==='name'?' required':'' ?>><?php endif; ?></div>
        <?php endforeach; ?>
        <div class="row"><div class="col-md-6 mb-3"><label class="form-label" for="org-category">Category</label><select class="form-select" id="org-category" name="category_id"><option value="">Uncategorized</option><?php foreach ($orgCategories as $category): ?><option value="<?= (int)$category['category_id'] ?>"><?= $orgEscape($category['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-6 mb-3"><label class="form-label" for="org-status">Status</label><select class="form-select" name="status" id="org-status"><option value="draft">Unpublished</option><option value="published">Published</option></select></div></div>
        <div class="row"><?php foreach (['logo','cover'] as $kind): ?><div class="col-md-6 mb-3"><label class="form-label" for="org-<?= $kind ?>">Organization <?= $kind ?></label>
        <div class="org-upload-preview" data-warning-image data-warning-label="Organization <?= $kind ?>" id="org-preview-<?= $kind ?>"></div>
        <input type="file" class="form-control" name="<?= $kind ?>" id="org-<?= $kind ?>" accept="image/jpeg,image/png,image/webp,image/gif" data-warning-optional="true">
        <label class="form-check mt-2"><input type="checkbox" class="form-check-input" name="remove_<?= $kind ?>" value="1"> Remove current <?= $kind ?></label></div><?php endforeach; ?></div>
        <div class="mb-3"><label class="form-label" for="org-gallery">Gallery images (optional, up to 10 total)</label><input type="file" class="form-control" id="org-gallery" name="gallery[]" accept="image/jpeg,image/png,image/webp,image/gif" multiple data-warning-optional="true"><div id="orgGalleryExisting" class="org-manager-gallery"></div></div>
        <p class="text-muted small">JPG, PNG, WEBP, or GIF · up to 5 MB per image. Empty descriptions and images produce a warning when publishing; you can publish anyway. Save as Unpublished to preview privately first.</p>
        <p id="orgFormError" class="text-danger" role="alert" hidden></p>
        <span class="org-manager-dirty text-warning" hidden>Unsaved organization changes</span>
        <div class="d-flex gap-2"><button type="submit" class="btn-gnc-primary" id="orgSave">Save organization</button><button type="button" class="btn btn-outline-secondary" id="orgCancel">Cancel</button></div>
    </form>
    <hr class="my-4"><h3 class="h6">Manage Categories</h3><p class="text-muted small">Deleting a category keeps its organizations and marks them as Uncategorized.</p>
    <div class="org-category-list"><?php foreach ($orgCategories as $category): ?><div class="d-flex gap-2 align-items-center mb-2"><span class="flex-grow-1"><?= $orgEscape($category['name']) ?></span><button type="button" class="btn btn-sm btn-outline-secondary" data-category-edit="<?= (int)$category['category_id'] ?>">Edit</button><button type="button" class="btn btn-sm btn-outline-danger" data-category-delete="<?= (int)$category['category_id'] ?>">Delete</button></div><?php endforeach; ?></div>
    <form id="orgCategoryForm" class="d-flex gap-2 mt-3"><input type="hidden" name="category_id" value="0"><input name="name" class="form-control" required maxlength="100" aria-label="Category name" placeholder="New category name"><button class="btn-gnc-primary" type="submit">Save category</button><button class="btn btn-outline-secondary" type="reset">Reset</button></form>
</div>
<style>
.org-management-row{display:flex;justify-content:space-between;gap:12px;padding:13px 0;border-bottom:1px solid #e4e9e4}.org-management-row strong,.org-management-row small{display:block}.org-management-row small{color:#6a776e}.org-management-actions{display:flex;align-items:center;flex-wrap:wrap;gap:5px}.org-upload-preview{height:130px;border:1px dashed #bacabd;border-radius:10px;margin-bottom:8px;display:flex;justify-content:center;background:#f3f6f1}.org-upload-preview img{max-width:100%;height:100%;object-fit:contain}.org-manager-gallery{display:flex;gap:12px;flex-wrap:wrap;margin-top:12px}.org-manager-gallery img{width:90px;height:70px;object-fit:cover;display:block}.org-edit-form{padding:18px;border:1px solid #dde6de;border-radius:12px;background:#fff}@media(max-width:600px){.org-management-row{flex-direction:column}#orgCategoryForm{flex-wrap:wrap}}
</style>
<script>
(function(){
    const root=document.getElementById('organizationManager'), form=document.getElementById('orgEditForm'), categoryForm=document.getElementById('orgCategoryForm');
    const records=<?= json_encode($orgRows,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    const categories=<?= json_encode($orgCategories,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) ?>;
    const csrf=<?= json_encode($csrfToken??generateCSRFToken()) ?>;
    let dirty=false,busy=false;const urls=[];
    function changed(value){dirty=value;window.__orgManagerDirty=value;root.querySelector('.org-manager-dirty').hidden=!value;}
    function discard(){return !dirty?Promise.resolve(true):window.AdminDialogs.confirm('Discard the unsaved organization form changes?');}
    function visualGuard(){return !window.OrganizationEditor||!window.OrganizationEditor.hasChanges()?Promise.resolve(true):window.AdminDialogs.confirm('You have unpublished edits on the visual canvas. Continuing will discard them. Continue?');}
    function showImage(kind,url){const box=document.getElementById('org-preview-'+kind);box.replaceChildren();box.style.opacity='';if(url){const image=document.createElement('img');image.src=url;image.alt='';box.append(image);}}
    async function edit(id){if(!await discard())return;form.reset();urls.splice(0).forEach(URL.revokeObjectURL);const record=records.find(row=>Number(row.org_id)===Number(id));
        form.elements.org_id.value=record?record.org_id:0;document.getElementById('orgFormTitle').textContent=record?'Edit Organization':'Add Organization';
        for(const key of ['name','description','about_text','vision','mission','facebook_url','category_id','status'])form.elements[key].value=record?record[key]||'':key==='status'?'draft':'';
        for(const kind of ['logo','cover'])showImage(kind,record&&record[kind+'_url']);
        const gallery=document.getElementById('orgGalleryExisting');gallery.replaceChildren();
        for(const item of record?record.gallery:[]){const label=document.createElement('label'),image=document.createElement('img'),box=document.createElement('input');image.src=item.image_url;image.alt='Current gallery image';box.type='checkbox';box.name='remove_gallery[]';box.value=item.image_id;label.append(image,box,' Remove');gallery.append(label);}
        changed(false);form.hidden=false;document.getElementById('orgFormError').hidden=true;form.scrollIntoView({behavior:'smooth',block:'start'});
    }
    async function post(fd){fd.set('csrf_token',csrf);const response=await fetch('/admin/action/student-organizations-handler.php',{method:'POST',credentials:'same-origin',body:fd});let result;try{result=await response.json();}catch(_){throw Error('The server could not read the request. Try smaller image files.');}if(!response.ok||!result.success)throw Error(result.error||'Could not save the change.');return result;}
    function reload(slug){changed(false);window.__veLeaving=true;location.href='?page=student-organizations'+(slug?'&sub='+encodeURIComponent(slug):'');}
    async function action(values){if(busy||!await discard()||!await visualGuard())return;busy=true;root.querySelectorAll('button').forEach(button=>button.disabled=true);try{await post(new URLSearchParams(values));reload();}catch(error){await window.AdminDialogs.alert(error.message);busy=false;root.querySelectorAll('button').forEach(button=>button.disabled=false);}}
    document.getElementById('orgAdd').addEventListener('click',()=>edit(0));
    document.getElementById('orgCancel').addEventListener('click',async()=>{if(await discard()){form.hidden=true;changed(false);}});
    document.addEventListener('org:edit',event=>edit(event.detail.id));
    form.addEventListener('input',()=>changed(true));form.addEventListener('change',()=>changed(true));
    for(const kind of ['logo','cover']){form.elements[kind].addEventListener('change',()=>{const file=form.elements[kind].files[0];if(file){const url=URL.createObjectURL(file);urls.push(url);showImage(kind,url);form.elements['remove_'+kind].checked=false;}});form.elements['remove_'+kind].addEventListener('change',()=>{document.getElementById('org-preview-'+kind).style.opacity=form.elements['remove_'+kind].checked?'.25':'';});}
    form.addEventListener('submit',async event=>{event.preventDefault();if(busy||!await visualGuard())return;busy=true;const button=document.getElementById('orgSave');button.disabled=true;const errorBox=document.getElementById('orgFormError');errorBox.hidden=true;
        try{const result=await post(new FormData(form));reload(result.organization.slug);}catch(error){errorBox.textContent=error.message;errorBox.hidden=false;busy=false;button.disabled=false;}});
    root.addEventListener('click',async event=>{const button=event.target.closest('button');if(!button)return;
        if(button.dataset.orgEdit){edit(button.dataset.orgEdit);return;}
        if(button.dataset.orgDelete){const record=records.find(row=>Number(row.org_id)===Number(button.dataset.orgDelete));if(await window.AdminDialogs.confirm('Delete “'+record.name+'” permanently? This removes its page and gallery.'))action({action:'delete',org_id:record.org_id});}
        if(button.dataset.orgMove)action({action:'move',org_id:button.dataset.orgMove,direction:button.dataset.direction});
        if(button.dataset.categoryEdit){const category=categories.find(row=>Number(row.category_id)===Number(button.dataset.categoryEdit));categoryForm.elements.category_id.value=category.category_id;categoryForm.elements.name.value=category.name;categoryForm.elements.name.focus();}
        if(button.dataset.categoryDelete&&await window.AdminDialogs.confirm('Delete this category? Its organizations will remain available as Uncategorized.'))action({action:'delete_category',category_id:button.dataset.categoryDelete});
    });
    categoryForm.addEventListener('submit',async event=>{event.preventDefault();action({action:'save_category',category_id:categoryForm.elements.category_id.value,name:categoryForm.elements.name.value});});
    categoryForm.addEventListener('reset',()=>{setTimeout(()=>categoryForm.elements.category_id.value='0',0);});
    window.addEventListener('beforeunload',event=>{if(dirty&&!window.__veLeaving){event.preventDefault();event.returnValue='';}});
})();
</script>
