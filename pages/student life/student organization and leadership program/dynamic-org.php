<?php
require_once __DIR__.'/../../../components/department-public.php';
$orgPreview=cePreviewCanManage(); $org=null;
foreach (studentOrganizations($orgPreview) as $row) if ($row['slug'] === (string)($_GET['slug']??'')) { $org=$row; break; }
if (!$org) http_response_code(404);
$settings=studentOrgPageContent(); $gallery=$org?studentOrganizationGallery((int)$org['org_id']):[];
$orgH=static fn($value)=>htmlspecialchars((string)$value,ENT_QUOTES,'UTF-8');
$mark=static function($key) { [$label,$max]=studentOrganizationFields()[$key]; return ceField('org-field',['key'=>$key,'label'=>$label,'max'=>$max,'multiline'=>$max>255,'rich'=>in_array($key,['description','about_text','vision','mission'],true),'optional'=>in_array($key,['vision','mission','facebook_url'],true)]); };
$media=static function($path,$label,$key) use($orgH,$orgPreview) {
    $attrs=ceManageMark('data-ve-org-media="'.$key.'" data-warning-image data-warning-label="'.$orgH($label).'"');
    if ($path) return '<img src="'.$orgH($path).'" alt="'.$orgH($label).'"'.$attrs.'>';
    return $orgPreview?'<span class="be-empty-media org-empty-media" role="button" tabindex="0" aria-label="'.$orgH($label).'"'.$attrs.'><i class="bi bi-image" aria-hidden="true"></i><span>Add '.$orgH(strtolower($label)).'</span></span>':'';
};
$facebook=$org?publicDepartmentFacebookEmbedUrl($org['facebook_url']??''):null;
$settingMark=static fn($key)=>ceField('org-setting',['key'=>$key,'max'=>$settings[$key]['max'],'label'=>$settings[$key]['label']]);
?>
<!DOCTYPE html><html lang="en"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0"><title><?= $orgH($org['name']??'Organization Not Found') ?></title>
<link href="/assets/css/bootstrap.min.css" rel="stylesheet"><link href="/assets/css/navbar-style.css" rel="stylesheet"><link href="/assets/css/footer-style.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
<link href="/assets/css/dynamic-org-style.css?v=<?= filemtime(__DIR__.'/../../../assets/css/dynamic-org-style.css') ?>" rel="stylesheet"><link href="/assets/css/basic-education-content.css" rel="stylesheet">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined&amp;display=block">
<link rel="icon" href="/assets/images/logos/gnc-logo-v1.svg"></head><body>
<?php $activeSection='student-life'; include __DIR__.'/../../../components/navbar.php'; ?>
<main>
<?php if (!$org): ?>
<section class="org-not-found"><h1>Organization not found</h1><p>This organization may be unpublished or no longer available.</p><a href="<?= $orgH(studentOrganizationsUrl()) ?>">View student organizations</a></section>
<?php else: ?>
<section class="dynamic-org-intro"><div class="dynamic-org-hero"><div class="dynamic-org-hero-inner">
<nav class="dynamic-org-breadcrumb" aria-label="breadcrumb"><a href="<?= $orgH(studentOrganizationsUrl().($orgPreview?'?ce_preview=1':'')) ?>">Student Organizations</a><i class="bi bi-chevron-right" aria-hidden="true"></i><span><?= $orgH($org['name']) ?></span></nav>
<h1<?= $mark('name') ?>><?= $orgH($org['name']) ?></h1><div class="cms-rich"<?= $mark('description') ?>><?= renderSectionContent($org['description']) ?></div>
<?php if ($orgPreview): ?><button type="button" class="org-assign-category" data-ve-org-assign-category>Assign category<?= $org['category_name']?': '.$orgH($org['category_name']):'' ?></button><?php endif; ?>
<?php if ($orgPreview && (!empty($org['archived_at']) || $org['status']==='draft')): ?><small class="org-unpublished"><?= !empty($org['archived_at'])?'Archived — hidden from visitors':'Unpublished — visible only in the editor' ?></small><?php endif; ?>
</div></div></section>
<section class="dynamic-org-about org-overview"><div class="org-about-copy"><span class="org-eyebrow">Overview</span><h2>About <?= $orgH($org['name']) ?></h2><div class="cms-rich"<?= $mark('about_text') ?>><?= renderSectionContent($org['about_text']) ?></div></div>
<?php if ($org['logo_url']||$orgPreview): ?><div class="org-logo-frame" data-image-frame-fit="contain"><?= $media($org['logo_url'],'Organization logo','logo') ?></div><?php endif; ?>
</section>
<?php if ($org['vision']||$org['mission']||$orgPreview): ?>
<section class="org-values" aria-label="Vision and mission">
    <article><div class="org-value-head"><?= studentOrgIcon('vision_icon','org-value-icon') ?><h2>Vision</h2></div><div class="cms-rich"<?= $mark('vision') ?>><?= renderSectionContent($org['vision']) ?></div></article>
    <article><div class="org-value-head"><?= studentOrgIcon('mission_icon','org-value-icon') ?><h2>Mission</h2></div><div class="cms-rich"<?= $mark('mission') ?>><?= renderSectionContent($org['mission']) ?></div></article>
</section>
<?php endif; ?>
<?php if ($gallery||$orgPreview): ?><section class="org-gallery-section"><span class="org-eyebrow">Gallery</span><h2<?= $settingMark('gallery_title') ?>><?= $orgH($settings['gallery_title']['value']) ?></h2>
<div class="org-gallery"><?php foreach ($gallery as $image): ?><a href="<?= $orgH($image['image_url']) ?>" target="_blank" rel="noopener" aria-label="View gallery photo"<?= ceManageMark('data-ve-org-gallery="'.(int)$image['image_id'].'"') ?>><img src="<?= $orgH($image['image_url']) ?>" alt="<?= $orgH($org['name']) ?> gallery photo" loading="lazy"></a><?php endforeach; ?>
<?php if ($orgPreview): ?><button type="button" class="org-gallery-add" data-ve-org-gallery-add data-warning-placeholder="true">+ Add gallery image</button><?php endif; ?></div></section><?php endif; ?>
<?php if ($facebook||$orgPreview): ?><section class="org-facebook" data-warning-optional="true"><span class="org-eyebrow"<?= $settingMark('facebook_eyebrow') ?>><?= $orgH($settings['facebook_eyebrow']['value']) ?></span><h2<?= $settingMark('facebook_title') ?>><?= $orgH($settings['facebook_title']['value']) ?></h2>
<p<?= $settingMark('facebook_description') ?>><?= $orgH($settings['facebook_description']['value']) ?></p>
<div class="org-facebook-slot">
<?php if ($facebook): ?><div class="org-fb-box"><iframe title="<?= $orgH($org['name']) ?> Facebook page" <?= gncPrivacyFrameSource($facebook) ?> width="500" height="130" scrolling="no" loading="lazy" allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
<?php if ($orgPreview): ?><button type="button" class="org-fb-edit" data-ve-org-facebook aria-label="Edit Facebook page URL" title="Click to change the Facebook page"></button><?php endif; ?></div>
<?php elseif ($orgPreview): ?><button type="button" class="org-fb-add" data-ve-org-facebook><span aria-hidden="true">+</span><span>Add the Facebook page link</span></button><?php endif; ?>
</div></section><?php endif; ?>
<?php endif; ?>
</main>
<?= $org?ceData('veOrgData',['fields'=>$settings,'organization'=>$org,'icons'=>academicFeatureIconChoices(),'categories'=>studentOrgCategories(),'gallery'=>$gallery,'listingUrl'=>studentOrganizationsUrl()]):'' ?>
<?php include __DIR__.'/../../../components/footer.php'; ?>
</body></html>
