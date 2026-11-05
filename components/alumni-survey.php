<?php
if (isset($alumni['survey_title'])):
    $surveyImage = responsiveBackgroundPath(trim(strip_tags($alumni['survey_image'] ?? '')));
    $surveyBackgrounds = responsiveBackgroundLoad(getDB(), $_SERVER['REQUEST_URI'] ?? '/pages/alumni.php');
    $surveyHasQr = !empty($surveyBackgrounds['#alumni-survey-qr']['desktop']['path']);
?>
<section class="alumni-survey-banner" id="alumni-survey" aria-labelledby="alumni-survey-title"<?= $surveyImage ? ' data-bg="' . $escape($surveyImage) . '" style="background-image:url(&quot;' . $escape($surveyImage) . '&quot;)"' : '' ?>>
    <span class="alumni-survey-circle" aria-hidden="true"></span>
    <?php if ($preview): ?><button type="button" class="alumni-background-edit" data-ve-background-for="alumni-survey"><i class="bi bi-image" aria-hidden="true"></i> Change background</button><?php endif; ?>
    <div class="alumni-survey-inner">
        <div class="alumni-survey-copy">
            <p<?= $mark('survey_label', 'Survey banner label') ?>><?= $plain('survey_label') ?></p>
            <h2 id="alumni-survey-title"><span<?= $mark('survey_title', 'Survey banner heading') ?>><?= $plain('survey_title') ?></span><em<?= $mark('survey_subtitle', 'Survey banner second line') ?>><?= $plain('survey_subtitle') ?></em></h2>
        </div>
        <?php if ($preview || $surveyHasQr): ?>
        <figure class="alumni-survey-code">
            <p<?= $mark('survey_scan_label', 'Survey QR heading') ?>><?= $plain('survey_scan_label') ?></p>
            <div class="alumni-survey-qr" id="alumni-survey-qr" data-shared-image data-ve-alumni-qr role="img" aria-label="Alumni survey QR code">
                <?php if ($preview): ?><span class="alumni-survey-qr-placeholder"><i class="bi bi-qr-code" aria-hidden="true"></i> Upload QR code</span><?php endif; ?>
            </div>
            <figcaption<?= $mark('survey_caption', 'Survey QR caption') ?>><?= $plain('survey_caption') ?></figcaption>
            <?php if ($preview): ?><button type="button" class="alumni-survey-qr-edit" data-ve-background-for="alumni-survey-qr"><i class="bi bi-upload" aria-hidden="true"></i> Replace QR code</button><?php endif; ?>
        </figure>
        <?php endif; ?>
    </div>
</section>
<?php endif; ?>
