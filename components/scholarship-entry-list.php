<?php
// Included by the two scholarship tabs. All entry content is database-managed.
$renderScholarshipEntry = static function (array $entry): void { ?>
    <article class="sch-entry" data-scholarship-entry="<?= ceAttr($entry['id']) ?>">
        <h2 data-sch-entry-prop="title"<?= ceField('scholarship-entry', ['key'=>$entry['id'].'_title','label'=>'Scholarship title','max'=>255]) ?>><?= ceAttr($entry['title']) ?></h2>
        <div class="sch-entry-description" data-sch-entry-prop="description"<?= ceField('scholarship-entry', ['key'=>$entry['id'].'_description','label'=>'Scholarship details','max'=>6000,'multiline'=>true,'optional'=>true]) ?>><?= ceAttr($entry['description']) ?></div>
        <a class="sch-entry-link" href="<?= ceAttr($entry['url']) ?>"<?= $entry['url'] === '' ? ' hidden' : '' ?>>Learn more <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
        <div class="sch-pdf"<?= empty($entry['pdf']) ? ' hidden' : '' ?>>
            <div class="sch-pdf-actions"><span>PDF Document</span><a data-sch-pdf-open href="<?= ceAttr($entry['pdf'] ?? '') ?>" target="_blank" rel="noopener">Open PDF</a><a data-sch-pdf-download href="<?= ceAttr($entry['pdf'] ?? '') ?>" download>Download PDF</a></div>
            <iframe class="sch-pdf-frame" title="<?= ceAttr($entry['title']) ?> (PDF)" loading="lazy"<?= !empty($entry['pdf']) ? ' src="' . ceAttr($entry['pdf']) . '#view=FitH"' : '' ?>></iframe>
            <p class="sch-pdf-note">If the PDF does not display on your device, use Open PDF or Download PDF.</p>
        </div>
    </article>
<?php }; ?>
<?php $schVisibleEntries = array_filter($schEntries, static fn($entry) => $entry['category'] === $schEntryCategory); ?>
<div class="sch-entry-grid" data-scholarship-list="<?= ceAttr($schEntryCategory) ?>"<?= !$schVisibleEntries ? ' hidden' : '' ?>>
    <?php foreach ($schVisibleEntries as $entry) $renderScholarshipEntry($entry); ?>
</div>
<?php if (cePreviewCanManage()): ?>
    <button type="button" class="sch-add-entry" data-ve-scholarship-add="<?= ceAttr($schEntryCategory) ?>"><i class="bi bi-plus-lg" aria-hidden="true"></i> Add scholarship</button>
    <template id="sch-entry-template-<?= ceAttr($schEntryCategory) ?>"><?php $renderScholarshipEntry(['id'=>'0000000000000000','title'=>'','description'=>'','url'=>'']); ?></template>
<?php endif; ?>
