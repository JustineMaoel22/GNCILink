<?php
$voices = alumniVoicesRead();
$voiceTextMark = static fn($key) => ceTextLayoutMark('data-ve-field="alumni-voice-text" data-ve-key="' . ceAttr($key) . '"');
if ($preview || $voices['items']):
?>
<section class="alumni-voices<?= $preview ? ' alumni-voices-editor' : '' ?>" id="alumni-voices">
    <header class="alumni-voices-heading">
        <p<?= $mark('voices_label', 'Alumni voices label') ?>><?= $plain('voices_label') ?></p>
        <h2><span<?= $mark('voices_title', 'Alumni voices heading') ?>><?= $plain('voices_title') ?></span><em<?= $mark('voices_subtitle', 'Alumni voices second line') ?>><?= $plain('voices_subtitle') ?></em></h2>
    </header>
    <?php if ($preview): ?>
    <div class="alumni-voices-tools"><small>Click the quote, name, batch, or role on a card to edit it directly. The first entry is featured. Use Save Draft to keep your work, then Publish or Submit for approval when ready. Preview shows the website layout.</small></div>
    <p data-alumni-voices-empty<?= $voices['items']?' hidden':'' ?>><?= $voices['ready']?'Add your first alumni voice with a photo, quote, name, batch, and description.':'Run the alumni page migration to enable this section.' ?></p>
    <?php endif; ?>
    <div class="alumni-voices-grid" data-alumni-voices-grid>
        <?php foreach ($voices['items'] as $voice): ?>
        <article class="alumni-voice" data-gnc-layout-scope="<?= $escape($voice['id']) ?>">
            <img class="alumni-voice-image" src="<?= $escape($voice['image']) ?>" alt="<?= $escape($voice['alt']) ?>" loading="lazy">
            <div class="alumni-voice-body"><blockquote<?= $voiceTextMark('quote') ?>><?= $escape($voice['quote']) ?></blockquote><footer><h3<?= $voiceTextMark('name') ?>><?= $escape($voice['name']) ?></h3><p<?= $voiceTextMark('batch') ?>><?= $escape($voice['batch']) ?></p><em<?= $voiceTextMark('description') ?>><?= $escape($voice['description']) ?></em></footer></div>
        </article>
        <?php endforeach; ?>
        <?php if ($preview): ?><button type="button" class="ve-add-tile alumni-voice-add" data-alumni-voice-add<?= !$voices['ready']?' disabled':'' ?>><span class="alumni-voice-add-icon" aria-hidden="true">+</span><span>Add alumni voice</span></button><?php endif; ?>
    </div>
    <?php if ($preview): ?>
    <template id="alumni-voice-template">
        <article class="alumni-voice">
            <div class="alumni-voice-canvas">
                <img class="alumni-voice-image" loading="lazy" alt="">
                <div class="alumni-voice-body"><blockquote<?= $voiceTextMark('quote') ?>></blockquote><footer><h3<?= $voiceTextMark('name') ?>></h3><p<?= $voiceTextMark('batch') ?>></p><em<?= $voiceTextMark('description') ?>></em></footer></div>
            </div>
            <div class="alumni-voice-editor-tools" role="group" aria-label="Alumni entry controls">
            <div class="alumni-voice-editor-heading"><span class="alumni-voice-badge" data-alumni-voice-number></span><span class="alumni-voice-badge" data-alumni-voice-state></span></div>
            <div class="alumni-voice-photo-actions">
                <button type="button" class="alumni-voice-photo-edit" data-alumni-voice-photo>Add photo</button>
                <button type="button" data-alumni-voice-position title="Adjust the photo crop and zoom">Reposition photo</button>
            </div>
            <details class="alumni-voice-alt"><summary title="Edit photo description"><i class="bi bi-card-text" aria-hidden="true"></i><span class="alumni-voice-action-label">Photo description</span></summary><span data-alumni-voice-alt></span></details>
            <div class="alumni-voice-controls"><button type="button" data-alumni-voice-feature title="Move this entry to the featured first position">☆ Set as featured</button><button type="button" data-alumni-voice-up title="Move one position earlier">↑ Move up</button><button type="button" data-alumni-voice-down title="Move one position later">↓ Move down</button><button type="button" data-alumni-voice-delete>Remove entry</button></div>
            </div>
        </article>
    </template>
    <script type="application/json" id="alumniVoicesData"><?= json_encode($voices,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_THROW_ON_ERROR) ?></script>
    <?php endif; ?>
</section>
<?php endif; ?>
