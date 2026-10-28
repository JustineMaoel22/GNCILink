<?php
/**
 * GNC Admin Panel – Programs manager (Content Editor -> Programs).
 *
 * One box per program. Everything on that program's public page is edited
 * here EXCEPT the Bulletin Board, which fills itself with the announcements
 * and events posted for the program.
 *
 *   Details & colour  – name, department, colour theme, visibility, Core Values switch, pictures
 *   Page text         – every heading and paragraph on the page
 *   Badges            – the little icons in the top banner
 *   Core Values       – only for programs that turn it on (e.g. BSMLS)
 *   Why Choose        – the text cards (title + description)
 *   Curriculum        – the curriculum PDF
 *   Facilities        – facility pictures (name + optional description)
 *   Uniform           – uniform pictures
 *   Careers           – career cards
 *
 * Expects from the including page: $csrfToken, and admin-functions.php loaded.
 * Saves through /admin/action/program-page-handler.php.
 * Needs sql/program-page-migration.sql.
 */
require_once __DIR__ . '/program-page-public.php';

if (empty($csrfToken)) {
    $csrfToken = generateCSRFToken();
}

$pgPrograms = [];
$pgError    = null;
try {
    getDB()->query("SELECT page_content, is_published, core_values_enabled, hero_media_id, seal_media_id, program_name FROM programs LIMIT 1");
    getDB()->query("SELECT 1 FROM program_items LIMIT 1");
    $ids = getDB()->query("SELECT program_id FROM programs ORDER BY dept_id IS NULL, dept_id ASC, program_id ASC")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ids as $id) {
        $p = getProgramPageById((int)$id);
        if ($p) $pgPrograms[] = $p;
    }
} catch (Throwable $e) {
    $pgError = 'The program page tables are not set up yet. Run sql/program-page-migration.sql in phpMyAdmin, then refresh this page.';
    error_log('Program manager: ' . $e->getMessage());
}

$pgDepartments = [];
try { $pgDepartments = getDepartments(); } catch (Throwable $e) { $pgDepartments = []; }
$pgPresets = programPageThemePresets();
$pgLimits  = programPageItemLimits();

/** Which preset (if any) an accent colour belongs to. */
$pgPresetOf = function (string $accent) use ($pgPresets): string {
    foreach ($pgPresets as $key => $p) {
        if (strcasecmp($p['accent'], $accent) === 0) return $key;
    }
    return 'custom';
};

$pgItemsJson = function (array $items): array {
    return array_map(function (array $i): array {
        return [
            'item_id' => (int)$i['item_id'], 'title' => (string)$i['title'], 'subtitle' => (string)($i['subtitle'] ?? ''),
            'body' => (string)($i['body'] ?? ''), 'icon' => (string)($i['icon'] ?? ''), 'letter' => (string)($i['letter'] ?? ''),
            'image_url' => $i['image_url'] ?? null, 'has_upload' => (bool)($i['has_upload'] ?? false),
        ];
    }, $items);
};

$pgState = [];
foreach ($pgPrograms as $p) {
    $pgState[(int)$p['program_id']] = [
        'program' => [
            'program_id' => (int)$p['program_id'], 'program_name' => $p['program_name'], 'slug' => $p['slug'],
            'published' => (bool)$p['published'], 'core_enabled' => (bool)$p['core_enabled'],
            'theme_accent' => $p['theme_accent'], 'hero_url' => $p['hero_url'], 'hero_upload' => (bool)$p['has_hero_upload'],
            'seal_url' => $p['seal_url'], 'seal_upload' => (bool)$p['has_seal_upload'],
            'pdf_url' => $p['pdf_url'] ?? null, 'pdf_name' => $p['pdf_name'] ?? null,
            'pdf_size' => isset($p['pdf_size']) ? (int)$p['pdf_size'] : null,
        ],
        'items' => [
            'badge'      => $pgItemsJson($p['items']['badge']),
            'core_value' => $pgItemsJson($p['items']['core_value']),
            'why'        => $pgItemsJson($p['items']['why']),
            'career'     => $pgItemsJson($p['items']['career']),
            'uniform'    => $pgItemsJson($p['items']['uniform']),
            'facility'   => $pgItemsJson($p['items']['facility']),
        ],
    ];
}

$e = function ($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };

/** A text input / textarea row bound to one page_content field. */
$pgField = function (string $key, string $label, string $value, string $kind = 'text', string $help = '', string $placeholder = '') use ($e) {
    $max = programPageContentFields()[$key]['max'] ?? 255;
    echo '<div class="pgm-field"><label class="pgm-label">' . $e($label) . '</label>';
    if ($kind === 'long') {
        echo '<textarea class="form-control" rows="3" maxlength="' . (int)$max . '" data-field="c_' . $e($key) . '" placeholder="' . $e($placeholder) . '">' . $e($value) . '</textarea>';
    } elseif ($kind === 'icon') {
        $parts = explode(':', $value, 2);
        $set   = ($parts[0] ?? '') === 'bi' ? 'bi' : 'material';
        $name  = $parts[1] ?? '';
        echo '<div class="pgm-iconpick" data-icon-field="c_' . $e($key) . '">'
           . '<span class="pgm-iconpreview" role="button" tabindex="0" title="Click to choose an icon"></span>'
           . '<button type="button" class="btn btn-outline-secondary btn-sm pgm-icon-choose">Choose icon</button>'
           . '<button type="button" class="pgm-link pgm-icon-advtoggle">Use another icon</button>'
           . '<span class="pgm-icon-adv" hidden>'
           . '<select class="form-select form-select-sm pgm-icon-set"><option value="material"' . ($set === 'material' ? ' selected' : '') . '>Material</option><option value="bi"' . ($set === 'bi' ? ' selected' : '') . '>Bootstrap</option></select>'
           . '<input type="text" class="form-control form-control-sm pgm-icon-name" list="pgm-icon-suggest" maxlength="50" placeholder="icon name, e.g. school" value="' . $e($name) . '">'
           . '</span>'
           . '<input type="hidden" data-field="c_' . $e($key) . '" value="' . $e($value) . '">'
           . '</div>';
    } else {
        echo '<input type="text" class="form-control" maxlength="' . (int)$max . '" data-field="c_' . $e($key) . '" value="' . $e($value) . '" placeholder="' . $e($placeholder) . '">';
    }
    if ($help !== '') echo '<div class="pgm-help">' . $e($help) . '</div>';
    echo '</div>';
};
?>

<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,100..700,0,0&display=block">
<datalist id="pgm-icon-suggest">
    <?php foreach (['shield_with_heart','health_and_safety','school','workspace_premium','stethoscope','medical_information','groups','balance','trophy','psychology','lightbulb','handshake','verified','favorite','diversity_1','science','code','engineering','restaurant','flight','payments','menu_book','rocket_launch','public','shield','star','visibility','flag','volunteer_activism','history_edu','computer','calculate','local_hospital','biotech'] as $ic): ?>
    <option value="<?= $e($ic) ?>"></option>
    <?php endforeach; ?>
</datalist>

<?php
$pgLiveCount   = count(array_filter($pgPrograms, function ($p) { return !empty($p['published']); }));
$pgHiddenCount = count($pgPrograms) - $pgLiveCount;
?>
<div class="data-card mb-3 pgm-intro">
    <div class="pgm-intro-row">
        <div class="pgm-intro-main">
            <strong class="pgm-title"><i class="bi bi-mortarboard"></i> Programs</strong>
            <p class="pgm-intro-text">
                Every program has its own page on the website. <b>Click a program below to edit it.</b>
                Your changes go live as soon as you press <b>Save</b>.
            </p>
            <div class="pgm-steps">
                <span><b>1</b> Click a program</span>
                <span><b>2</b> Pick a section and change it</span>
                <span><b>3</b> Press Save</span>
            </div>
        </div>
        <?php if (!$pgError): ?>
        <button type="button" class="btn-gnc-gold pgm-add-main" id="pgm-add-btn"><i class="bi bi-plus-lg"></i> Add a Program</button>
        <?php endif; ?>
    </div>
</div>

<?php if ($pgError): ?>
    <div class="alert alert-warning"><?= $e($pgError) ?></div>
<?php else: ?>

<!-- ── Add a program ── -->
<div class="data-card mb-3" id="pgm-create-card" style="display:none">
    <div class="data-card-header"><span class="data-card-title"><i class="bi bi-plus-circle me-1"></i> Add a new program</span></div>
    <div class="pgm-pad">
        <p class="pgm-help mt-0 mb-3">Fill in these few things. You will write the rest of the page right after.</p>
        <div class="row g-3">
            <div class="col-lg-8">
                <label class="pgm-label" for="pgm-new-name">Program name <span class="pgm-req">*</span></label>
                <input type="text" class="form-control" id="pgm-new-name" maxlength="255" autocomplete="off" placeholder="e.g. Bachelor of Science in Nursing">
                <div class="pgm-help">Write the full name. The big title on the page is made from it.</div>
            </div>
            <div class="col-lg-4">
                <label class="pgm-label" for="pgm-new-code">Short code <span class="pgm-req">*</span></label>
                <input type="text" class="form-control" id="pgm-new-code" maxlength="10" autocomplete="off" placeholder="e.g. BSN">
                <div class="pgm-help">A short nickname (2–10 letters or numbers). It <b>can't be changed later</b>.</div>
            </div>
            <div class="col-lg-6">
                <label class="pgm-label" for="pgm-new-dept">Which department is it under?</label>
                <select class="form-select" id="pgm-new-dept">
                    <option value="0">— not sure yet —</option>
                    <?php foreach ($pgDepartments as $d): ?>
                    <option value="<?= (int)$d['dept_id'] ?>"><?= $e($d['dept_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-lg-6 d-flex align-items-end">
                <label class="pgm-toggle w-100"><input type="checkbox" id="pgm-new-core"><span class="pgm-switch"></span><span class="pgm-toggle-text"><b>Has a Core Values section</b><small>You can change this later.</small></span></label>
            </div>
            <div class="col-12">
                <label class="pgm-label">Page colour</label>
                <div class="pgm-theme" id="pgm-new-theme">
                    <?php foreach ($pgPresets as $key => $pr): ?>
                    <label class="pgm-swatch" title="<?= $e($pr['label']) ?>">
                        <input type="radio" name="pgm-new-theme" value="<?= $e($key) ?>"<?= $key === 'green' ? ' checked' : '' ?>>
                        <span style="background:<?= $e($pr['accent']) ?>"></span><em><?= $e($pr['label']) ?></em>
                    </label>
                    <?php endforeach; ?>
                    <label class="pgm-swatch" title="Pick any colour">
                        <input type="radio" name="pgm-new-theme" value="custom">
                        <span class="pgm-swatch-custom"><input type="color" id="pgm-new-color" value="#307056"></span><em>Custom</em>
                    </label>
                </div>
            </div>
        </div>
        <div class="alert d-none mt-3 mb-0" id="pgm-create-alert" role="alert"></div>
        <div class="d-flex gap-2 mt-3">
            <button type="button" class="btn-gnc-gold" id="pgm-create-save"><i class="bi bi-check-lg"></i> Add Program</button>
            <button type="button" class="btn btn-outline-secondary" id="pgm-create-cancel">Cancel</button>
        </div>
        <div class="pgm-help mt-2">The new program shows on the website right away, with only the sections you fill in. To keep it private while you work, untick “Show on the website” after adding it.</div>
    </div>
</div>

<?php if (!empty($pgPrograms)): ?>
<!-- ── Search & filter ── -->
<div class="pgm-toolbar">
    <div class="pgm-search">
        <i class="bi bi-search" aria-hidden="true"></i>
        <input type="search" id="pgm-search" class="form-control" placeholder="Search by program name, code or department…" aria-label="Search programs" autocomplete="off">
    </div>
    <div class="pgm-filters" role="group" aria-label="Filter programs">
        <button type="button" class="pgm-filter is-active" data-filter="all">All <span data-count="all"><?= count($pgPrograms) ?></span></button>
        <button type="button" class="pgm-filter" data-filter="live">On the website <span data-count="live"><?= $pgLiveCount ?></span></button>
        <button type="button" class="pgm-filter" data-filter="hidden">Hidden <span data-count="hidden"><?= $pgHiddenCount ?></span></button>
    </div>
</div>
<div class="pgm-noresults" id="pgm-noresults" hidden><i class="bi bi-search"></i> No programs match. Try a different word, or choose “All”.</div>
<?php endif; ?>

<!-- ── One box per program (grouped by department) ── -->
<div id="pgm-list">
<?php
$pgTabs = [
    ['details',    'bi-sliders',          'Basics'],
    ['text',       'bi-fonts',            'Headings & text'],
    ['badge',      'bi-patch-check',      'Banner badges'],
    ['core_value', 'bi-stars',            'Core values'],
    ['why',        'bi-hand-thumbs-up',   'Why choose us'],
    ['curriculum', 'bi-file-earmark-pdf', 'Curriculum PDF'],
    ['facility',   'bi-building',         'Facilities'],
    ['uniform',    'bi-person-badge',     'Uniform photos'],
    ['career',     'bi-briefcase',        'Careers'],
];
$pgPrevDept = null;
foreach ($pgPrograms as $p):
    $pid     = (int)$p['program_id'];
    $c       = $p['content'];
    $preset  = $pgPresetOf($p['theme_accent']);
    $deptKey = (int)($p['dept_id'] ?? 0);
    $deptLbl = !empty($p['dept_name']) ? $p['dept_name'] : 'No department yet';
    if ($pgPrevDept !== $deptKey):
        if ($pgPrevDept !== null) echo '</section>';
        $pgPrevDept = $deptKey;
        echo '<section class="pgm-group-dept"><h3 class="pgm-dept-title"><i class="bi bi-diagram-3"></i> ' . $e($deptLbl) . '</h3>';
    endif;
?>
<div class="data-card pgm-card mb-3" id="program-<?= $pid ?>" data-pid="<?= $pid ?>" data-slug="<?= $e($p['slug'] ?? '') ?>" data-status="<?= $p['published'] ? 'live' : 'hidden' ?>" data-search="<?= $e(mb_strtolower($p['program_name'] . ' ' . $p['program_code'] . ' ' . $deptLbl)) ?>">
    <button type="button" class="pgm-head" aria-expanded="false">
        <span class="pgm-dot" style="background:<?= $e($p['theme_accent']) ?>"></span>
        <span class="pgm-head-main">
            <strong class="pgm-name"><?= $e($p['program_name']) ?></strong>
            <small><?= $e($p['program_code']) ?><?= !empty($p['dept_name']) ? ' · ' . $e($p['dept_name']) : ' · no department' ?></small>
        </span>
        <span class="pgm-status <?= $p['published'] ? 'is-live' : 'is-hidden' ?>"><?= $p['published'] ? 'On the website' : 'Hidden' ?></span>
        <span class="pgm-edit-hint"><span class="pgm-edit-open">Edit</span><span class="pgm-edit-close">Close</span></span>
        <i class="bi bi-chevron-down pgm-chev"></i>
    </button>

    <div class="pgm-body" hidden>
        <div class="pgm-bodytop">
            <a class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener" href="/pages/academics/college/program.php?slug=<?= $e(rawurlencode($p['slug'])) ?>"><i class="bi bi-box-arrow-up-right"></i> See this page on the website</a>
            <span class="pgm-help">Opens in a new tab. Press Save first to see your latest changes.</span>
        </div>

        <div class="pgm-tabs" role="tablist" aria-label="Sections of this program's page">
            <?php foreach ($pgTabs as $i => [$tKey, $tIcon, $tLabel]): ?>
            <button type="button" role="tab" class="pgm-tab<?= $i === 0 ? ' is-active' : '' ?><?= $tKey === 'core_value' ? ' pgm-tab-core' : '' ?>" data-tab="<?= $e($tKey) ?>"<?= ($tKey === 'core_value' && !$p['core_enabled']) ? ' hidden' : '' ?>>
                <i class="bi <?= $e($tIcon) ?>" aria-hidden="true"></i><span class="pgm-tab-label"><?= $e($tLabel) ?></span><span class="pgm-tab-count"></span><span class="pgm-tab-dot" hidden title="Not saved yet"></span>
            </button>
            <?php endforeach; ?>
        </div>
        <div class="pgm-tabs-note">An orange dot on a section means it has changes that are not saved yet.</div>

        <!-- Basics + headings & text share ONE save button -->
        <form class="pgm-form" autocomplete="off" onsubmit="return false">
            <div class="pgm-panel" data-panel="details">

                <div class="pgm-block">
                    <div class="pgm-block-title"><i class="bi bi-eye"></i> Visibility</div>
                    <div class="row g-3">
                        <div class="col-lg-6">
                            <label class="pgm-toggle"><input type="checkbox" data-field="is_published"<?= $p['published'] ? ' checked' : '' ?>><span class="pgm-switch"></span><span class="pgm-toggle-text"><b>Show on the website</b><small>Turn off to hide this page and its card. Nothing is deleted.</small></span></label>
                        </div>
                        <div class="col-lg-6">
                            <label class="pgm-toggle"><input type="checkbox" data-field="core_values_enabled"<?= $p['core_enabled'] ? ' checked' : '' ?>><span class="pgm-switch"></span><span class="pgm-toggle-text"><b>Has a Core Values section</b><small>Adds a “Core values” section to the page, and a tab here to write them.</small></span></label>
                        </div>
                    </div>
                </div>

                <div class="pgm-block">
                    <div class="pgm-block-title"><i class="bi bi-card-heading"></i> Name &amp; department <span class="pgm-chip" title="The code is fixed once the program is created">Code: <?= $e($p['program_code']) ?></span></div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="pgm-label">Program name <span class="pgm-req">*</span></label>
                            <input type="text" class="form-control" maxlength="255" data-field="program_name" value="<?= $e($p['program_name']) ?>">
                            <div class="pgm-help">Write the full name. The big title is made from it (for example “Nursing” from “Bachelor of Science in Nursing”).</div>
                        </div>
                        <div class="col-lg-6">
                            <label class="pgm-label">Department</label>
                            <select class="form-select" data-field="dept_id">
                                <option value="0">— none —</option>
                                <?php foreach ($pgDepartments as $d): ?>
                                <option value="<?= (int)$d['dept_id'] ?>"<?= (int)($p['dept_id'] ?? 0) === (int)$d['dept_id'] ? ' selected' : '' ?>><?= $e($d['dept_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="pgm-help">The department's page lists this program.</div>
                        </div>
                        <div class="col-12">
                            <label class="pgm-label">Short description <span class="pgm-opt">(optional)</span></label>
                            <textarea class="form-control" rows="2" maxlength="5000" data-field="description"><?= $e($p['description'] ?? '') ?></textarea>
                            <div class="pgm-help">Shown on this program's card in the department page. The banner introduction is edited separately below.</div>
                        </div>
                    </div>
                </div>

                <div class="pgm-block">
                    <div class="pgm-block-title"><i class="bi bi-palette"></i> Colour &amp; pictures</div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="pgm-label">Page colour</label>
                            <div class="pgm-theme" data-theme-box>
                                <?php foreach ($pgPresets as $key => $pr): ?>
                                <label class="pgm-swatch" title="<?= $e($pr['label']) ?>">
                                    <input type="radio" name="theme-<?= $pid ?>" value="<?= $e($key) ?>" data-field="theme"<?= $preset === $key ? ' checked' : '' ?>>
                                    <span style="background:<?= $e($pr['accent']) ?>"></span><em><?= $e($pr['label']) ?></em>
                                </label>
                                <?php endforeach; ?>
                                <label class="pgm-swatch" title="Pick any colour">
                                    <input type="radio" name="theme-<?= $pid ?>" value="custom" data-field="theme"<?= $preset === 'custom' ? ' checked' : '' ?>>
                                    <span class="pgm-swatch-custom"><input type="color" data-field="theme_custom" value="<?= $e(strtolower($p['theme_accent'])) ?>"></span><em>Custom</em>
                                </label>
                            </div>
                            <div class="pgm-help">Titles, icons, bars and buttons on this program's page use this colour.</div>
                        </div>

                        <div class="col-lg-6">
                            <label class="pgm-label">Banner picture</label>
                            <div class="pgm-media" data-media="hero">
                                <div class="pgm-media-preview"></div>
                                <div class="pgm-media-actions">
                                    <label class="btn btn-outline-secondary btn-sm mb-0"><i class="bi bi-image"></i> Choose picture<input type="file" accept="image/jpeg,image/png,image/webp,image/gif" hidden></label>
                                    <button type="button" class="btn btn-outline-danger btn-sm pgm-media-remove"><i class="bi bi-x-lg"></i> Remove</button>
                                </div>
                            </div>
                            <div class="pgm-help">The wide picture behind the title. JPG, PNG or WEBP, up to 5 MB.</div>
                        </div>
                        <div class="col-lg-6">
                            <label class="pgm-label">Seal / logo <span class="pgm-opt">(optional)</span></label>
                            <div class="pgm-media" data-media="seal">
                                <div class="pgm-media-preview"></div>
                                <div class="pgm-media-actions">
                                    <label class="btn btn-outline-secondary btn-sm mb-0"><i class="bi bi-image"></i> Choose picture<input type="file" accept="image/jpeg,image/png,image/webp,image/gif,image/svg+xml,.svg" hidden></label>
                                    <button type="button" class="btn btn-outline-danger btn-sm pgm-media-remove"><i class="bi bi-x-lg"></i> Remove</button>
                                </div>
                            </div>
                            <div class="pgm-help">Shown beside “About the Program”. Leave it empty to give the About text the full width. Up to 5 MB.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="pgm-panel" data-panel="text" hidden>
                <p class="pgm-help mt-0 mb-3">Each box below is one part of the page, from top to bottom. Click a title to open it. Anything you leave empty is skipped, or the standard wording is used.</p>

                <details class="pgm-sec" open>
                    <summary><span class="pgm-sec-num"></span> Top of the page <small>The big banner</small></summary>
                    <div class="pgm-sec-body"><div class="row g-3">
                        <div class="col-12"><?php $pgField('subtitle', 'Line under the title', $c['subtitle'], 'text', 'The short italic line, for example a motto. Leave empty to hide it.'); ?></div>
                        <div class="col-12"><?php $pgField('hero_desc', 'Banner paragraph', $c['hero_desc'], 'long', 'Optional. Leave empty to hide the introduction in the banner.'); ?></div>
                    </div></div>
                </details>

                <details class="pgm-sec">
                    <summary><span class="pgm-sec-num"></span> About, Vision &amp; Mission <small>What the program is about</small></summary>
                    <div class="pgm-sec-body"><div class="row g-3">
                        <div class="col-lg-6"><?php $pgField('about_title', 'About heading', $c['about_title'], 'text', '', 'About the Program'); ?></div>
                        <div class="col-12"><?php $pgField('about_text', 'About the program', $c['about_text'], 'long', 'Leave About, Vision and Mission all empty to hide this whole part of the page.'); ?></div>
                        <div class="col-lg-6"><?php $pgField('vision_title', 'Vision heading', $c['vision_title'], 'text', '', 'Vision'); ?></div>
                        <div class="col-lg-6"><?php $pgField('vision_icon', 'Vision icon', $c['vision_icon'], 'icon'); ?></div>
                        <div class="col-12"><?php $pgField('vision_text', 'Vision text', $c['vision_text'], 'long'); ?></div>
                        <div class="col-lg-6"><?php $pgField('mission_title', 'Mission heading', $c['mission_title'], 'text', '', 'Mission'); ?></div>
                        <div class="col-lg-6"><?php $pgField('mission_icon', 'Mission icon', $c['mission_icon'], 'icon'); ?></div>
                        <div class="col-12"><?php $pgField('mission_text', 'Mission text', $c['mission_text'], 'long'); ?></div>
                    </div></div>
                </details>

                <details class="pgm-sec pgm-core-sec"<?= $p['core_enabled'] ? '' : ' hidden' ?>>
                    <summary><span class="pgm-sec-num"></span> Core Values <small>Heading only. Write the values in the “Core values” tab</small></summary>
                    <div class="pgm-sec-body"><div class="row g-3">
                        <div class="col-lg-6"><?php $pgField('core_title', 'Heading', $c['core_title'], 'text', '', 'Core Values'); ?></div>
                        <div class="col-12"><?php $pgField('core_intro', 'Line under the heading', $c['core_intro'], 'text'); ?></div>
                    </div></div>
                </details>

                <details class="pgm-sec">
                    <summary><span class="pgm-sec-num"></span> Why Choose <small>Heading and introduction</small></summary>
                    <div class="pgm-sec-body"><div class="row g-3">
                        <div class="col-12"><?php $pgField('why_title', 'Heading', $c['why_title'], 'text', '', 'Why Choose This Program at GNCI?'); ?></div>
                        <div class="col-12"><?php $pgField('why_intro', 'Introduction', $c['why_intro'], 'long'); ?></div>
                    </div></div>
                </details>

                <details class="pgm-sec">
                    <summary><span class="pgm-sec-num"></span> Curriculum, Facilities, Uniform &amp; Careers <small>Their headings</small></summary>
                    <div class="pgm-sec-body"><div class="row g-3">
                        <div class="col-lg-6"><?php $pgField('curriculum_title', 'Curriculum heading', $c['curriculum_title'], 'text', '', 'Program Curriculum'); ?></div>
                        <div class="col-lg-6"><?php $pgField('curriculum_intro', 'Text shown when there is no PDF yet', $c['curriculum_intro'], 'text', '', 'Curriculum details coming soon.'); ?></div>
                        <div class="col-lg-6"><?php $pgField('uniform_title', 'Uniform heading', $c['uniform_title'], 'text', '', 'Uniform'); ?></div>
                        <div class="col-12"><?php $pgField('uniform_text', 'Uniform text', $c['uniform_text'], 'long'); ?></div>
                        <div class="col-lg-6"><?php $pgField('career_title', 'Careers heading', $c['career_title'], 'text', '', 'Career Opportunities'); ?></div>
                        <div class="col-lg-6"><?php $pgField('facility_title', 'Facilities heading', $c['facility_title'], 'text', '', 'Facilities'); ?></div>
                    </div></div>
                </details>

                <details class="pgm-sec">
                    <summary><span class="pgm-sec-num"></span> Bottom banner <small>The “Apply now” call to action</small></summary>
                    <div class="pgm-sec-body"><div class="row g-3">
                        <div class="col-lg-9"><?php $pgField('cta_title', 'Title', $c['cta_title'], 'text', '', 'Make a difference. Begin Your Journey with GNCI.'); ?></div>
                        <div class="col-lg-3"><?php $pgField('cta_icon', 'Icon', $c['cta_icon'], 'icon'); ?></div>
                        <div class="col-12"><?php $pgField('cta_text', 'Text', $c['cta_text'], 'text'); ?></div>
                        <div class="col-lg-4"><?php $pgField('cta_button', 'Button label', $c['cta_button'], 'text', '', 'Apply Now'); ?></div>
                        <div class="col-lg-8"><?php $pgField('cta_link', 'Where the button goes', $c['cta_link'], 'text', 'Start with / for a page on this website (like /admissions/apply.php), or https:// for another website.', '/admissions/apply.php'); ?></div>
                    </div></div>
                </details>

                <div class="pgm-help mt-3"><i class="bi bi-info-circle"></i> The <b>Bulletin Board</b> between Careers and the bottom banner fills itself with this program's announcements and events. There is nothing to edit.</div>
            </div>

            <div class="pgm-savebar" data-savebar="form">
                <button data-content-publish type="button" class="btn-gnc-gold pgm-save-form"><i class="bi bi-check-lg"></i> Save</button>
                <button type="button" class="btn btn-outline-secondary pgm-reset-form"><i class="bi bi-arrow-counterclockwise"></i> Undo changes</button>
                <span class="pgm-dirty" hidden>Not saved yet</span>
                <div class="alert d-none mb-0 pgm-alert" role="alert"></div>
            </div>
        </form>

        <!-- Repeating lists -->
        <?php
        $listHelp = [
            'badge'      => ['Banner badges', 'The small icon boxes in the top banner, for example “4 Years · Program”.'],
            'core_value' => ['Core values', 'One card per value. The big letter is the first letter of the title unless you change it.'],
            'why'        => ['Why choose us', 'Cards that tell students why to pick this program. Each has a title and a few sentences.'],
            'career'     => ['Careers', 'Jobs graduates can go into. Each has a small picture, a title and a description.'],
            'uniform'    => ['Uniform photos', 'Photos of the uniform. Visitors can click a photo to enlarge it.'],
            'facility'   => ['Facilities', 'Photos of the labs, rooms and equipment students of THIS program use. Each needs a name and a picture (JPG, PNG or WEBP, up to 5 MB); the description is optional. Press “Upload several pictures” to add many at once.'],
        ];
        foreach ($listHelp as $type => [$label, $help]): ?>
        <div class="pgm-panel" data-panel="<?= $e($type) ?>" hidden>
            <p class="pgm-help mb-3"><?= $e($help) ?> You can add up to <?= (int)$pgLimits[$type] ?>. A section with nothing in it is hidden on the website.</p>
            <div class="pgm-items" data-list="<?= $e($type) ?>"></div>
            <div class="pgm-savebar">
                <button type="button" class="btn btn-outline-secondary pgm-add-item"><i class="bi bi-plus-lg"></i> Add one</button>
                <?php if ($type === 'facility'): ?>
                <label class="btn btn-outline-secondary mb-0 pgm-multi-add"><i class="bi bi-images"></i> Upload several pictures<input type="file" multiple accept="image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp" hidden class="pgm-multi-input"></label>
                <?php endif; ?>
                <button data-content-publish type="button" class="btn-gnc-gold pgm-save-items"><i class="bi bi-check-lg"></i> Save <?= $e(mb_strtolower($label)) ?></button>
                <button type="button" class="btn btn-outline-secondary pgm-undo-items"><i class="bi bi-arrow-counterclockwise"></i> Undo changes</button>
                <span class="pgm-limit"></span>
                <span class="pgm-dirty" hidden>Not saved yet</span>
                <div class="alert d-none mb-0 pgm-alert" role="alert"></div>
            </div>
        </div>
        <?php endforeach; ?>

        <div class="pgm-panel" data-panel="curriculum" hidden>
            <p class="pgm-help mb-3">The PDF appears in the Program Curriculum section so visitors can open or download it. PDF files only, up to 20 MB.</p>
            <div class="pgm-pdf">
                <i class="bi bi-file-earmark-pdf pgm-pdf-icon"></i>
                <div class="pgm-pdf-info"><strong class="pgm-pdf-name">No PDF yet</strong><small class="pgm-pdf-size"></small></div>
                <a class="btn btn-outline-secondary btn-sm pgm-pdf-view" target="_blank" rel="noopener" hidden>View</a>
                <label class="btn btn-outline-secondary btn-sm mb-0"><i class="bi bi-upload"></i> Choose PDF<input type="file" accept="application/pdf,.pdf" hidden class="pgm-pdf-input"></label>
                <button type="button" class="btn btn-outline-danger btn-sm pgm-pdf-remove" hidden><i class="bi bi-x-lg"></i> Remove</button>
            </div>
            <div class="pgm-savebar">
                <button data-content-publish type="button" class="btn-gnc-gold pgm-save-pdf" disabled><i class="bi bi-check-lg"></i> Save PDF</button>
                <span class="pgm-dirty" hidden>Not saved yet</span>
                <div class="alert d-none mb-0 pgm-alert" role="alert"></div>
            </div>
        </div>

        <details class="pgm-more">
            <summary><i class="bi bi-three-dots"></i> More options</summary>
            <div class="pgm-danger">
                <button type="button" class="btn btn-outline-danger btn-sm pgm-delete"><i class="bi bi-trash"></i> Delete this program</button>
                <span class="pgm-help">Deletes the program and its whole page for good. To only hide it for now, turn off “Show on the website” in Basics instead.</span>
            </div>
        </details>
    </div>
</div>
<?php endforeach; if ($pgPrevDept !== null) echo '</section>'; ?>

<?php if (empty($pgPrograms)): ?>
    <div class="data-card"><div class="empty-state"><i class="bi bi-mortarboard"></i><p>No programs yet. Press "Add a Program" to create the first one.</p></div></div>
<?php endif; ?>
</div>

<style>
.pgm-pad { padding: 0 1.25rem 1.25rem; }
.pgm-title { font-size: 1.05rem; }
.pgm-intro-text { font-size: .95rem; color: #555; max-width: 75ch; }
.pgm-steps { display: flex; flex-wrap: wrap; gap: .5rem; }
.pgm-steps span { display: inline-flex; align-items: center; gap: .45rem; background: #f6f1e4; border-radius: 999px; padding: .3rem .8rem .3rem .35rem; font-size: .85rem; color: #444; }
.pgm-steps b { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%; background: var(--gnc-green, #1F5E2C); color: #fff; font-size: .75rem; }
.pgm-label { display: block; font-size: .9rem; font-weight: 600; color: #222; margin-bottom: .3rem; }
.pgm-req { color: #c0392b; }
.pgm-opt { font-weight: 400; color: #777; font-size: .82rem; }
.pgm-help { font-size: .82rem; color: #6b6b66; margin-top: .3rem; line-height: 1.45; }
.pgm-field { margin: 0; }
.pgm-check { display: flex; align-items: center; gap: .5rem; font-size: .92rem; cursor: pointer; margin: 0; }
.pgm-check input { width: 1.1rem; height: 1.1rem; }

.pgm-card { overflow: hidden; overflow: clip; }
.pgm-head { display: flex; align-items: center; gap: .85rem; width: 100%; padding: 1rem 1.25rem; background: none; border: 0; text-align: left; cursor: pointer; }
.pgm-head:hover { background: #faf8f2; }
.pgm-dot { width: 18px; height: 18px; border-radius: 50%; flex: 0 0 18px; box-shadow: 0 0 0 3px rgba(0,0,0,.06); }
.pgm-head-main { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.pgm-name { font-size: 1rem; line-height: 1.25; }
.pgm-head-main small { color: #777; }
.pgm-status { font-size: .75rem; font-weight: 700; border-radius: 999px; padding: .2rem .65rem; }
.pgm-status.is-live { background: #e4f3e8; color: #1d6b34; }
.pgm-status.is-hidden { background: #eee; color: #666; }
.pgm-chev { transition: transform .2s; color: #888; }
.pgm-head[aria-expanded="true"] .pgm-chev { transform: rotate(180deg); }
.pgm-body { padding: 0 1.25rem 1.25rem; border-top: 1px solid #eee; }
.pgm-tabs { display: flex; flex-wrap: wrap; gap: .35rem; margin: 1rem 0; }
.pgm-tab { border: 1px solid #ddd; background: #fff; border-radius: 999px; padding: .35rem .9rem; font-size: .85rem; cursor: pointer; color: #444; }
.pgm-tab:hover { border-color: #bbb; }
.pgm-tab.is-active { background: var(--gnc-green, #1F5E2C); border-color: var(--gnc-green, #1F5E2C); color: #fff; }
.pgm-tab[hidden] { display: none; }
.pgm-panel[hidden], .pgm-body[hidden], .pgm-group[hidden], .pgm-core-text[hidden] { display: none; }
.pgm-group { margin: 1.6rem 0 .7rem; padding-bottom: .35rem; border-bottom: 2px solid #f0e8d0; font-weight: 700; font-size: .85rem; letter-spacing: .04em; text-transform: uppercase; color: var(--gnc-green, #1F5E2C); }
.pgm-group:first-child { margin-top: 0; }
.pgm-savebar { display: flex; flex-wrap: wrap; align-items: center; gap: .6rem; margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid #eee; }
.pgm-savebar .alert { padding: .4rem .8rem; font-size: .88rem; }
.pgm-dirty { font-size: .82rem; font-weight: 600; color: #a35a00; background: #fff3dc; border-radius: 6px; padding: .2rem .6rem; }
.pgm-dirty[hidden] { display: none; }
.pgm-danger { margin-top: 1.5rem; padding-top: 1rem; border-top: 1px dashed #e3c7c3; display: flex; flex-wrap: wrap; align-items: center; gap: .75rem; }

.pgm-theme { display: flex; flex-wrap: wrap; gap: .5rem; }
.pgm-swatch { display: flex; flex-direction: column; align-items: center; gap: .25rem; cursor: pointer; margin: 0; }
.pgm-swatch input[type=radio] { position: absolute; opacity: 0; pointer-events: none; }
.pgm-swatch > span { width: 40px; height: 40px; border-radius: 50%; border: 3px solid #fff; box-shadow: 0 0 0 1px rgba(0,0,0,.2); display: block; position: relative; overflow: hidden; }
.pgm-swatch input:checked + span { box-shadow: 0 0 0 3px var(--gnc-green, #1F5E2C); }
.pgm-swatch input:focus-visible + span { outline: 2px solid #000; outline-offset: 2px; }
.pgm-swatch em { font-style: normal; font-size: .72rem; color: #555; }
.pgm-swatch-custom input[type=color] { position: absolute; inset: -6px; width: 52px; height: 52px; border: 0; padding: 0; cursor: pointer; background: none; }

.pgm-media { display: flex; align-items: center; gap: .9rem; border: 1px solid rgba(0,0,0,.1); border-radius: 10px; padding: .7rem; }
.pgm-media-preview { width: 110px; height: 66px; flex: 0 0 110px; border-radius: 8px; background: #f1f1f1 center/contain no-repeat; display: flex; align-items: center; justify-content: center; color: #aaa; font-size: .75rem; text-align: center; }
.pgm-media-actions { display: flex; flex-wrap: wrap; gap: .4rem; }

.pgm-iconpick { display: flex; align-items: center; gap: .4rem; }
.pgm-iconpick .form-select { width: 105px; flex: 0 0 105px; }
.pgm-iconpreview { width: 34px; height: 34px; flex: 0 0 34px; display: inline-flex; align-items: center; justify-content: center; background: #f4f4f1; border-radius: 8px; font-size: 1.4rem; color: #333; overflow: hidden; }
.pgm-iconpreview .material-symbols-outlined, .pgm-iconpreview i { font-size: 1.4rem; line-height: 1; }

.pgm-items { display: flex; flex-direction: column; gap: .75rem; }
.pgm-item { display: flex; gap: .8rem; align-items: flex-start; border: 1px solid #e6e6e0; border-radius: 12px; padding: .8rem; background: #fcfcfa; }
.pgm-item-num { flex: 0 0 26px; height: 26px; border-radius: 50%; background: var(--gnc-green, #1F5E2C); color: #fff; font-size: .78rem; display: flex; align-items: center; justify-content: center; margin-top: .3rem; }
.pgm-item-fields { flex: 1; min-width: 0; display: grid; grid-template-columns: repeat(12, 1fr); gap: .5rem; }
.pgm-item-fields .f-sm { grid-column: span 3; } .pgm-item-fields .f-md { grid-column: span 6; } .pgm-item-fields .f-lg { grid-column: span 12; }
.pgm-item-fields small { display: block; font-size: .74rem; color: #777; margin-bottom: .15rem; }
.pgm-item-img { display: flex; align-items: center; gap: .6rem; }
.pgm-item-img .thumb { width: 84px; height: 56px; border-radius: 8px; background: #eee center/cover no-repeat; flex: 0 0 84px; display: flex; align-items: center; justify-content: center; color: #aaa; font-size: .7rem; }
.pgm-item-tools { display: flex; flex-direction: column; gap: .25rem; }
.pgm-item-tools button { width: 32px; height: 30px; padding: 0; line-height: 1; }
.pgm-empty { color: #888; font-size: .9rem; padding: .9rem; border: 1px dashed #ddd; border-radius: 10px; text-align: center; }

.pgm-pdf { display: flex; flex-wrap: wrap; align-items: center; gap: .7rem; border: 1px solid #e6e6e0; border-radius: 12px; padding: .8rem 1rem; }
.pgm-pdf-icon { font-size: 1.8rem; color: #c0392b; }
.pgm-pdf-info { flex: 1; min-width: 160px; display: flex; flex-direction: column; }
.pgm-pdf-info small { color: #777; }
.pgm-pdf [hidden] { display: none !important; }

/* Icon picker pop-up (opens on hover, stays open after a click) */
.pgm-iconpreview { cursor: pointer; transition: background .15s; }
.pgm-iconpreview:hover { background: #e7efe9; }
.pgm-iconpop { position: fixed; z-index: 3000; width: 340px; max-width: calc(100vw - 16px); background: #fff; border: 1px solid #dcdcd4; border-radius: 12px; box-shadow: 0 12px 32px rgba(0,0,0,.18); padding: .6rem; }
.pgm-iconpop[hidden] { display: none; }
.pgm-iconpop-top { display: flex; gap: .4rem; margin-bottom: .5rem; }
.pgm-iconpop-top input { flex: 1; }
.pgm-iconpop-grid { display: grid; grid-template-columns: repeat(8, 1fr); gap: 4px; max-height: 240px; overflow-y: auto; }
.pgm-iconpop-grid button { aspect-ratio: 1; border: 1px solid transparent; background: #f6f6f2; border-radius: 8px; padding: 0; display: flex; align-items: center; justify-content: center; cursor: pointer; color: #222; }
.pgm-iconpop-grid button:hover, .pgm-iconpop-grid button:focus-visible { background: #e1eee6; border-color: var(--gnc-green, #1F5E2C); outline: none; }
.pgm-iconpop-grid button.is-current { background: var(--gnc-green, #1F5E2C); color: #fff; }
.pgm-iconpop-grid .material-symbols-outlined, .pgm-iconpop-grid i { font-size: 1.35rem; line-height: 1; }
.pgm-iconpop-empty { grid-column: 1 / -1; text-align: center; color: #888; font-size: .85rem; padding: 1rem 0; }
.pgm-iconpop-hint { font-size: .74rem; color: #777; margin-top: .45rem; }

/* ═════════ Friendlier layout (v2) ═════════ */
.pgm-intro { padding: 1.25rem 1.5rem; }
.pgm-intro-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem 1.5rem; flex-wrap: wrap; }
.pgm-intro-main { flex: 1; min-width: 260px; }
.pgm-title { display: inline-flex; align-items: center; gap: .45rem; font-size: 1.2rem; }
.pgm-intro-text { margin: .35rem 0 .85rem; }
.pgm-add-main { flex: none; white-space: nowrap; display: inline-flex; align-items: center; gap: .45rem; padding: .7rem 1.25rem; font-size: 1rem; }
.pgm-link { background: none; border: 0; padding: 0; color: #1a6aa8; font-size: .82rem; text-decoration: underline; cursor: pointer; }

/* search + filter */
.pgm-toolbar { display: flex; flex-wrap: wrap; align-items: center; gap: .75rem 1rem; margin-bottom: 1rem; }
.pgm-search { position: relative; flex: 1 1 280px; max-width: 460px; }
.pgm-search i { position: absolute; left: .85rem; top: 50%; transform: translateY(-50%); color: #888; pointer-events: none; }
.pgm-search input { padding-left: 2.4rem; }
.pgm-filters { display: flex; flex-wrap: wrap; gap: .4rem; }
.pgm-filter { border: 1px solid #ddd; background: #fff; border-radius: 999px; padding: .4rem .9rem; font-size: .88rem; color: #444; cursor: pointer; }
.pgm-filter:hover { border-color: #bbb; }
.pgm-filter span { display: inline-block; min-width: 1.5em; margin-left: .3rem; padding: 0 .45em; border-radius: 999px; background: #eee; font-size: .75rem; font-weight: 700; text-align: center; }
.pgm-filter.is-active { background: var(--gnc-green, #1F5E2C); border-color: var(--gnc-green, #1F5E2C); color: #fff; }
.pgm-filter.is-active span { background: rgba(255,255,255,.25); }
.pgm-noresults { text-align: center; padding: 2rem 1rem; color: #777; border: 1px dashed #ddd; border-radius: 12px; margin-bottom: 1rem; }
.pgm-noresults[hidden], .pgm-card[hidden], .pgm-group-dept[hidden], .pgm-sec[hidden] { display: none !important; }
.pgm-dept-title { display: flex; align-items: center; gap: .5rem; margin: 1.5rem 0 .6rem; font-size: .8rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #6b6b66; }
.pgm-group-dept:first-child .pgm-dept-title { margin-top: .25rem; }

/* program header row */
.pgm-head[aria-expanded="true"] { background: #faf8f2; }
.pgm-status { display: inline-flex; align-items: center; white-space: nowrap; }
.pgm-status::before { content: ''; width: 8px; height: 8px; border-radius: 50%; background: currentColor; margin-right: .4rem; }
.pgm-edit-hint { font-size: .82rem; font-weight: 600; color: var(--gnc-green, #1F5E2C); white-space: nowrap; }
.pgm-edit-close, .pgm-head[aria-expanded="true"] .pgm-edit-open { display: none; }
.pgm-head[aria-expanded="true"] .pgm-edit-close { display: inline; }
.pgm-bodytop { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem .9rem; padding-top: 1rem; }
.pgm-bodytop .pgm-help { margin: 0; }

/* section tabs */
.pgm-tabs { margin: 1rem 0 .4rem; gap: .4rem; }
.pgm-tab { display: inline-flex; align-items: center; gap: .4rem; padding: .5rem 1rem; font-size: .9rem; }
.pgm-tab-count { background: rgba(0,0,0,.08); border-radius: 999px; padding: 0 .5em; font-size: .72rem; font-weight: 700; }
.pgm-tab-count:empty { display: none; }
.pgm-tab.is-active .pgm-tab-count { background: rgba(255,255,255,.28); }
.pgm-tab-dot { width: 9px; height: 9px; border-radius: 50%; background: #f08a00; box-shadow: 0 0 0 2px #fff; }
.pgm-tab-dot[hidden] { display: none; }
.pgm-tabs-note { font-size: .8rem; color: #888; margin-bottom: 1.1rem; }

/* grouped blocks in Basics */
.pgm-block { border: 1px solid #ebe7dc; border-radius: 12px; padding: 1rem 1.1rem 1.15rem; margin-bottom: 1rem; background: #fff; }
.pgm-block-title { display: flex; align-items: center; gap: .5rem; font-weight: 700; font-size: .95rem; color: var(--gnc-green, #1F5E2C); margin-bottom: .9rem; }
.pgm-chip { margin-left: auto; font-size: .75rem; font-weight: 600; background: #f1ede6; color: #555; border-radius: 999px; padding: .15rem .7rem; }

/* on/off switches */
.pgm-toggle { position: relative; display: flex; align-items: flex-start; gap: .8rem; padding: .8rem .9rem; border: 1px solid #e6e1d8; border-radius: 12px; background: #fcfbf8; cursor: pointer; margin: 0; }
.pgm-toggle:hover { border-color: #cfc8b8; }
.pgm-toggle input { position: absolute; opacity: 0; pointer-events: none; }
.pgm-switch { flex: 0 0 44px; height: 24px; border-radius: 999px; background: #c9c9c2; position: relative; transition: background .2s; margin-top: 2px; }
.pgm-switch::after { content: ''; position: absolute; top: 3px; left: 3px; width: 18px; height: 18px; border-radius: 50%; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.3); transition: transform .2s; }
.pgm-toggle input:checked + .pgm-switch { background: var(--gnc-green, #1F5E2C); }
.pgm-toggle input:checked + .pgm-switch::after { transform: translateX(20px); }
.pgm-toggle input:focus-visible + .pgm-switch { outline: 2px solid #000; outline-offset: 2px; }
.pgm-toggle-text { display: flex; flex-direction: column; font-size: .92rem; }
.pgm-toggle-text small { color: #6b6b66; font-size: .8rem; line-height: 1.4; margin-top: .15rem; }

/* page-text accordions */
.pgm-panel[data-panel="text"] { counter-reset: pgsec; }
.pgm-sec { border: 1px solid #e6e1d8; border-radius: 12px; margin-bottom: .7rem; background: #fff; overflow: hidden; overflow: clip; }
.pgm-sec > summary { list-style: none; cursor: pointer; display: flex; align-items: center; gap: .7rem; padding: .85rem 1rem; font-weight: 700; font-size: .95rem; }
.pgm-sec > summary::-webkit-details-marker { display: none; }
.pgm-sec > summary:hover { background: #faf8f2; }
.pgm-sec > summary small { font-weight: 400; color: #777; font-size: .8rem; }
.pgm-sec > summary::after { content: ''; margin-left: auto; flex: none; width: 8px; height: 8px; border-right: 2px solid #888; border-bottom: 2px solid #888; transform: rotate(45deg); transition: transform .2s; }
.pgm-sec[open] > summary::after { transform: rotate(-135deg); }
.pgm-sec-num { flex: 0 0 24px; height: 24px; border-radius: 50%; background: var(--gnc-green, #1F5E2C); color: #fff; font-size: .78rem; display: inline-flex; align-items: center; justify-content: center; }
.pgm-sec-num::before { counter-increment: pgsec; content: counter(pgsec); }
.pgm-sec-body { padding: 1rem 1rem 1.15rem; border-top: 1px solid #eee; }

/* icon chooser */
.pgm-iconpick { flex-wrap: wrap; gap: .5rem; }
.pgm-iconpreview { width: 42px; height: 42px; flex: 0 0 42px; }
.pgm-icon-adv { display: inline-flex; align-items: center; gap: .4rem; flex: 1 1 230px; }
.pgm-icon-adv[hidden] { display: none; }

/* Save bar stays in view while you scroll */
.pgm-savebar { position: sticky; bottom: 0; z-index: 20; background: #fff; margin-left: -1.25rem; margin-right: -1.25rem; padding: .85rem 1.25rem; border-top: 1px solid #e6e1d8; box-shadow: 0 -8px 16px -10px rgba(0,0,0,.15); }
.pgm-savebar:has(.pgm-dirty:not([hidden])) { border-top-color: #f0b35a; background: #fffaf0; }
.pgm-savebar .btn-gnc-gold { padding: .55rem 1.3rem; }
.pgm-limit { margin-left: auto; font-size: .82rem; color: #777; }
.pgm-item { padding: 1rem; }
.pgm-empty { padding: 1.6rem 1rem; font-size: .95rem; }

.pgm-more { margin-top: 1.5rem; border-top: 1px dashed #e3e0d6; padding-top: .7rem; }
.pgm-more > summary { cursor: pointer; color: #777; font-size: .85rem; list-style: none; display: inline-flex; align-items: center; gap: .4rem; }
.pgm-more > summary::-webkit-details-marker { display: none; }
.pgm-more .pgm-danger { margin-top: .8rem; border-top: 0; padding-top: 0; }

@media (max-width: 575px) {
    .pgm-intro { padding: 1rem; }
    .pgm-add-main { width: 100%; justify-content: center; }
    .pgm-edit-hint { display: none; }
    .pgm-search { max-width: none; }
    .pgm-savebar { margin-left: -1rem; margin-right: -1rem; padding: .75rem 1rem; }
}

@media (max-width: 767px) {
    .pgm-item { flex-wrap: wrap; }
    .pgm-item-fields .f-sm, .pgm-item-fields .f-md { grid-column: span 12; }
    .pgm-media { flex-wrap: wrap; }
}
</style>

<script>
(function () {
    var AJAX_URL = '/admin/action/program-page-handler.php';
    var csrf  = <?= json_encode($csrfToken ?? '') ?>;
    var STATE = <?= json_encode($pgState, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?>;
    var LIMITS = <?= json_encode($pgLimits) ?>;
    var IMG_MAX = 5 * 1024 * 1024, FAC_IMG_MAX = 5 * 1024 * 1024, PDF_MAX = 20 * 1024 * 1024;   // FAC_IMG_MAX: facility pictures (keep in step with PROGRAM_FACILITY_IMAGE_MAX_BYTES in admin-functions.php)
    var IMG_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/svg+xml'];
    var FAC_TYPES = ['image/jpeg', 'image/png', 'image/webp'];   // facility pictures: JPG, PNG, WEBP only
    function facTypeOk(f) { return FAC_TYPES.indexOf(f.type) !== -1 && /\.(jpe?g|png|webp)$/i.test(f.name); }
    function nameFromFile(n) { n = n.replace(/\.[^.]+$/, '').replace(/[-_]+/g, ' ').replace(/\s+/g, ' ').trim(); return n ? n.charAt(0).toUpperCase() + n.slice(1, 120) : 'Facility'; }
    var applyFilter = function () {};   // replaced by the search box code below

    function post(fd) {
        fd.append('csrf_token', csrf);
        return fetch(AJAX_URL, { method: 'POST', body: fd }).then(function (res) {
            return res.json().catch(function () { return { success: false, error: 'Unexpected response from the server.' }; });
        }).catch(function () { return { success: false, error: 'Network error. Please try again.' }; });
    }
    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }
    function showAlert(el, msg, bad) {
        if (window.ContentEditorNotice && window.ContentEditorNotice(msg, bad)) { el.classList.add('d-none'); return; }
        el.className = 'alert mb-0 pgm-alert ' + (bad ? 'alert-danger' : 'alert-success');
        el.textContent = msg;
        if (!bad) setTimeout(function () { el.classList.add('d-none'); }, 4000);
    }
    function hideAlert(el) { el.classList.add('d-none'); }
    function fmtSize(b) { return b > 1048576 ? (b / 1048576).toFixed(1) + ' MB' : Math.max(1, Math.round(b / 1024)) + ' KB'; }

    function iconHtml(spec) {
        var m = /^(material|bi):([A-Za-z0-9_-]+)$/.exec(spec || '');
        if (!m) return '<span class="material-symbols-outlined">star</span>';
        return m[1] === 'bi' ? '<i class="bi bi-' + m[2] + '"></i>' : '<span class="material-symbols-outlined">' + m[2] + '</span>';
    }


    /* ───────────── Icon picker pop-up ───────────── */
    var MATERIAL_ICONS = ['shield_with_heart','health_and_safety','shield','verified_user','favorite','volunteer_activism','school','workspace_premium','stethoscope','medical_information','local_hospital','monitor_heart','medication','vaccines','local_pharmacy','biotech','science','psychology','psychiatry','clinical_notes','groups','diversity_1','diversity_3','handshake','balance','trophy','emoji_events','military_tech','verified','lightbulb','target','track_changes','visibility','flag','star','rocket_launch','public','language','travel_explore','eco','menu_book','auto_stories','history_edu','cast_for_education','code','computer','engineering','architecture','construction','calculate','account_balance','payments','finance_mode','trending_up','restaurant','hotel','flight','category','model_training'];
    var BI_ICONS = ['mortarboard','mortarboard-fill','book','journal-text','award','trophy','stars','star','heart','heart-pulse','shield-check','shield-plus','patch-check','lightbulb','people','person-workspace','globe','rocket','building','buildings','bank','briefcase','calculator','cpu','laptop','hospital','bandaid','capsule','clipboard2-pulse','graph-up','pie-chart','tools','translate','bullseye','compass','flag','gem','hand-thumbs-up','key','palette','pencil','puzzle','tree','droplet','camera','geo-alt','chat-heart'];
    var pop = null, popTimer = null, popFor = null;

    function ensurePop() {
        if (pop) return pop;
        pop = document.createElement('div');
        pop.className = 'pgm-iconpop'; pop.hidden = true;
        pop.innerHTML = '<div class="pgm-iconpop-top"><input type="text" class="form-control form-control-sm" placeholder="Search icons…" aria-label="Search icons"></div>' +
            '<div class="pgm-iconpop-grid" role="listbox"></div>' +
            '<div class="pgm-iconpop-hint">Can\'t find the one you want? Press “Use another icon” next to the picture and type an icon name from fonts.google.com/icons or icons.getbootstrap.com.</div>';
        document.body.appendChild(pop);
        pop.querySelector('input').addEventListener('input', function () { fillPop(); });
        document.addEventListener('click', function (ev) {
            if (pop.hidden) return;
            if (!pop.contains(ev.target) && !(popFor && popFor.box.contains(ev.target))) closePop();
        });
        document.addEventListener('keydown', function (ev) { if (ev.key === 'Escape') closePop(); });
        // Scrolling the page moves the pop-up with its icon; scrolling the icon list itself leaves it alone.
        window.addEventListener('scroll', function (ev) {
            if (pop.hidden || (ev.target instanceof Node && pop.contains(ev.target))) return;
            positionPop();
        }, true);
        window.addEventListener('resize', function () { if (!pop.hidden) positionPop(); });
        return pop;
    }
    function closePop() { if (pop) pop.hidden = true; popFor = null; }
    function scheduleClose() { clearTimeout(popTimer); popTimer = setTimeout(closePop, 280); }

    function fillPop() {
        var q = pop.querySelector('input').value.trim().toLowerCase();
        var all = MATERIAL_ICONS.map(function (n) { return { s: 'material', n: n }; })
            .concat(BI_ICONS.map(function (n) { return { s: 'bi', n: n }; }));
        var list = all.filter(function (x) { return !q || x.n.indexOf(q) !== -1 || x.n.replace(/[-_]/g, ' ').indexOf(q) !== -1; });
        var curName = popFor.name.value.trim().toLowerCase(), curSet = popFor.set.value;
        var grid = pop.querySelector('.pgm-iconpop-grid');
        grid.innerHTML = list.length ? list.map(function (x) {
            var cur = x.n === curName && x.s === curSet;
            return '<button type="button" data-s="' + x.s + '" data-n="' + x.n + '" title="' + x.n.replace(/[-_]/g, ' ') + '"' + (cur ? ' class="is-current"' : '') + '>' + iconHtml(x.s + ':' + x.n) + '</button>';
        }).join('') : '<div class="pgm-iconpop-empty">No match. Try another word.</div>';
        grid.querySelectorAll('button').forEach(function (b) {
            b.addEventListener('click', function () {
                popFor.set.value = b.getAttribute('data-s');
                popFor.name.value = b.getAttribute('data-n');
                popFor.set.dispatchEvent(new Event('change', { bubbles: true }));
                popFor.name.dispatchEvent(new Event('input', { bubbles: true }));
                closePop();
            });
        });
    }
    function openPop(ctx) {
        ensurePop();
        clearTimeout(popTimer);
        popFor = ctx;
        pop.querySelector('input').value = '';
        fillPop();
        pop.hidden = false;
        positionPop();
    }
    function positionPop() {
        if (!popFor) return;
        var r = popFor.preview.getBoundingClientRect(), w = pop.offsetWidth, h = pop.offsetHeight;
        // The icon scrolled completely out of view: nothing left to attach to.
        if (r.bottom < 0 || r.top > window.innerHeight) { closePop(); return; }
        var left = Math.max(8, Math.min(r.left, window.innerWidth - w - 8));
        var top = r.bottom + 6;
        if (top + h > window.innerHeight - 8) top = Math.max(8, r.top - h - 6);
        pop.style.left = left + 'px'; pop.style.top = top + 'px';
    }
    /* Make one .pgm-iconpick open the pop-up when its picture or "Choose icon" button is clicked. */
    function bindPicker(box) {
        var ctx = { box: box, preview: box.querySelector('.pgm-iconpreview'), set: box.querySelector('.pgm-icon-set'), name: box.querySelector('.pgm-icon-name') };
        var enter = function () { openPop(ctx); };
        ctx.preview.addEventListener('click', enter);
        ctx.preview.addEventListener('keydown', function (ev) { if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); enter(); } });
        var choose = box.querySelector('.pgm-icon-choose');
        if (choose) choose.addEventListener('click', function () { if (popFor === ctx && pop && !pop.hidden) closePop(); else enter(); });
        var adv = box.querySelector('.pgm-icon-adv'), tog = box.querySelector('.pgm-icon-advtoggle');
        if (adv && tog) tog.addEventListener('click', function () { adv.hidden = !adv.hidden; tog.textContent = adv.hidden ? 'Use another icon' : 'Hide'; });
        ctx.set.addEventListener('change', function () { if (popFor === ctx && !pop.hidden) fillPop(); });
    }

    /* ───────────── Add a program ───────────── */
    var addBtn = document.getElementById('pgm-add-btn'), createCard = document.getElementById('pgm-create-card');
    if (addBtn) {
        var cAlert = document.getElementById('pgm-create-alert');
        addBtn.addEventListener('click', function () {
            createCard.style.display = createCard.style.display === 'none' ? '' : 'none';
            if (createCard.style.display !== 'none') { document.getElementById('pgm-new-name').focus(); createCard.scrollIntoView({ block: 'nearest', behavior: 'smooth' }); }
        });
        document.getElementById('pgm-create-cancel').addEventListener('click', function () { createCard.style.display = 'none'; });
        document.getElementById('pgm-new-color').addEventListener('input', function () {
            createCard.querySelector('input[name="pgm-new-theme"][value="custom"]').checked = true;
        });
        document.getElementById('pgm-create-save').addEventListener('click', function () {
            var btn = this;
            var fd = new FormData();
            fd.append('action', 'create_program');
            fd.append('program_code', document.getElementById('pgm-new-code').value);
            fd.append('program_name', document.getElementById('pgm-new-name').value);
            fd.append('dept_id', document.getElementById('pgm-new-dept').value);
            fd.append('theme', createCard.querySelector('input[name="pgm-new-theme"]:checked').value);
            fd.append('theme_custom', document.getElementById('pgm-new-color').value);
            if (document.getElementById('pgm-new-core').checked) fd.append('core_values_enabled', '1');
            btn.disabled = true;
            post(fd).then(function (data) {
                if (data.success) {
                    window.__pgmLeaving = true;
                    location.hash = 'program-' + data.program_id;
                    location.reload();
                } else {
                    cAlert.className = 'alert alert-danger mt-3 mb-0'; cAlert.textContent = data.error || 'Could not add the program.';
                    btn.disabled = false;
                }
            });
        });
    }

    /* ───────────── One program ───────────── */
    document.querySelectorAll('.pgm-card').forEach(setupCard);

    function setupCard(card) {
        var pid = card.getAttribute('data-pid');
        var st = STATE[pid];
        var head = card.querySelector('.pgm-head'), body = card.querySelector('.pgm-body');

        head.addEventListener('click', function () {
            var open = body.hidden;
            body.hidden = !open;
            head.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        /* tabs */
        var tabs = card.querySelectorAll('.pgm-tab');
        function showTab(name) {
            tabs.forEach(function (t) { t.classList.toggle('is-active', t.getAttribute('data-tab') === name); });
            // details + text live in the one <form>; the form itself stays visible for both
            var form = card.querySelector('.pgm-form');
            var inForm = (name === 'details' || name === 'text');
            form.hidden = !inForm;
            card.querySelectorAll('.pgm-form .pgm-panel').forEach(function (p) { p.hidden = p.getAttribute('data-panel') !== name; });
            card.querySelectorAll(':scope > .pgm-body > .pgm-panel').forEach(function (p) { p.hidden = p.getAttribute('data-panel') !== name; });
        }
        tabs.forEach(function (t) { t.addEventListener('click', function () { showTab(t.getAttribute('data-tab')); }); });
        showTab('details');

        /* orange dot on a section's tab while it has changes that are not saved */
        function setDot(n, on) {
            var t = card.querySelector('.pgm-tab[data-tab="' + n + '"]');
            if (t) t.querySelector('.pgm-tab-dot').hidden = !on;
        }
        function syncDots() {
            var formOn = !card.querySelector('.pgm-form .pgm-dirty').hidden;
            setDot('details', formOn); setDot('text', formOn);
            card.querySelectorAll(':scope > .pgm-body > .pgm-panel').forEach(function (panel) {
                var d = panel.querySelector('.pgm-dirty');
                setDot(panel.getAttribute('data-panel'), !!d && !d.hidden);
            });
        }
        var dotWatcher = new MutationObserver(syncDots);
        card.querySelectorAll('.pgm-dirty').forEach(function (d) { dotWatcher.observe(d, { attributes: true, attributeFilter: ['hidden'] }); });

        /* ── details + text form ── */
        var form = card.querySelector('.pgm-form');
        var formDirty = form.querySelector('.pgm-dirty'), formAlert = form.querySelector('.pgm-alert');
        var heroPending = { file: null, remove: false }, sealPending = { file: null, remove: false };
        var previewUrls = [];

        function field(name) { return form.querySelector('[data-field="' + name + '"]'); }
        function markDirty() { formDirty.hidden = false; }
        function markClean() { formDirty.hidden = true; }

        form.addEventListener('input', function (ev) {
            if (ev.target.matches('[data-field], .pgm-icon-name, .pgm-icon-set, input[type=file]')) markDirty();
        });
        form.addEventListener('change', function (ev) {
            if (ev.target.matches('[data-field], .pgm-icon-set')) markDirty();
        });

        // Core values switch shows/hides the tab + its text fields
        var coreBox = field('core_values_enabled');
        function applyCore() {
            card.querySelector('.pgm-tab-core').hidden = !coreBox.checked;
            form.querySelectorAll('.pgm-core-sec').forEach(function (n) { n.hidden = !coreBox.checked; });
            if (!coreBox.checked && card.querySelector('.pgm-tab-core.is-active')) showTab('details');
        }
        coreBox.addEventListener('change', applyCore);

        // Custom colour picker selects the "Custom" radio
        form.querySelector('[data-field="theme_custom"]').addEventListener('input', function () {
            form.querySelector('input[data-field="theme"][value="custom"]').checked = true; markDirty();
        });

        // Icon pickers
        form.querySelectorAll('.pgm-iconpick').forEach(function (box) {
            var set = box.querySelector('.pgm-icon-set'), name = box.querySelector('.pgm-icon-name');
            var hidden = box.querySelector('input[type=hidden]'), prev = box.querySelector('.pgm-iconpreview');
            function sync() {
                var n = name.value.trim().toLowerCase().replace(/[^a-z0-9_-]/g, '');
                hidden.value = n ? set.value + ':' + n : '';
                prev.innerHTML = iconHtml(hidden.value);
            }
            set.addEventListener('change', sync); name.addEventListener('input', sync); sync();
            bindPicker(box);
        });

        // Banner + seal pictures
        function setupMedia(kind, pending) {
            var box = form.querySelector('[data-media="' + kind + '"]');
            var prev = box.querySelector('.pgm-media-preview'), input = box.querySelector('input[type=file]');
            var rm = box.querySelector('.pgm-media-remove');
            function render() {
                var url = null;
                if (pending.file) { url = URL.createObjectURL(pending.file); previewUrls.push(url); }
                else if (!pending.remove) url = st.program[kind + '_url'];
                prev.style.backgroundImage = url ? 'url("' + url.replace(/"/g, '%22') + '")' : '';
                prev.textContent = url ? '' : 'No picture';
                rm.hidden = !url;
            }
            input.addEventListener('change', function () {
                var f = input.files[0]; input.value = '';
                if (!f) return;
                if (IMG_TYPES.indexOf(f.type) === -1 && !/\.svg$/i.test(f.name)) { showAlert(formAlert, 'That file type isn\'t allowed. Use JPG, PNG, WEBP' + (kind === 'seal' ? ' or SVG' : '') + '.', true); return; }
                if (f.size > IMG_MAX) { showAlert(formAlert, 'That picture is too large. Maximum size is 5 MB.', true); return; }
                hideAlert(formAlert); pending.file = f; pending.remove = false; render(); markDirty();
            });
            rm.addEventListener('click', function () { pending.file = null; pending.remove = true; render(); markDirty(); });
            pending.render = render;
            render();
        }
        setupMedia('hero', heroPending);
        setupMedia('seal', sealPending);

        // Undo = restore the page as it was loaded / last saved
        var snapshot = null;
        function takeSnapshot() {
            snapshot = {};
            form.querySelectorAll('[data-field]').forEach(function (el) {
                var key = el.getAttribute('data-field') + '|' + (el.type === 'radio' ? el.value : '');
                snapshot[key] = (el.type === 'checkbox' || el.type === 'radio') ? el.checked : el.value;
            });
        }
        function restoreSnapshot() {
            form.querySelectorAll('[data-field]').forEach(function (el) {
                var key = el.getAttribute('data-field') + '|' + (el.type === 'radio' ? el.value : '');
                if (!(key in snapshot)) return;
                if (el.type === 'checkbox' || el.type === 'radio') el.checked = snapshot[key]; else el.value = snapshot[key];
            });
            form.querySelectorAll('.pgm-iconpick').forEach(function (box) {
                var parts = box.querySelector('input[type=hidden]').value.split(':');
                box.querySelector('.pgm-icon-set').value = parts[0] === 'bi' ? 'bi' : 'material';
                box.querySelector('.pgm-icon-name').value = parts[1] || '';
                box.querySelector('.pgm-iconpreview').innerHTML = iconHtml(box.querySelector('input[type=hidden]').value);
            });
            heroPending.file = null; heroPending.remove = false; sealPending.file = null; sealPending.remove = false;
            heroPending.render(); sealPending.render(); applyCore(); markClean(); hideAlert(formAlert);
        }
        takeSnapshot();
        form.querySelector('.pgm-reset-form').addEventListener('click', restoreSnapshot);

        form.querySelector('.pgm-save-form').addEventListener('click', function () {
            var btn = this, original = btn.innerHTML;
            var fd = new FormData();
            fd.append('action', 'save_program_details');
            fd.append('program_id', pid);
            form.querySelectorAll('[data-field]').forEach(function (el) {
                var n = el.getAttribute('data-field');
                if (el.type === 'radio') { if (el.checked) fd.append(n, el.value); }
                else if (el.type === 'checkbox') { if (el.checked) fd.append(n, '1'); }
                else fd.append(n, el.value);
            });
            if (heroPending.file) fd.append('hero', heroPending.file);
            if (heroPending.remove) fd.append('remove_hero', '1');
            if (sealPending.file) fd.append('seal', sealPending.file);
            if (sealPending.remove) fd.append('remove_seal', '1');

            btn.disabled = true; btn.textContent = 'Saving…';
            post(fd).then(function (data) {
                if (data.success) {
                    var p = data.program;
                    st.program = Object.assign(st.program, {
                        program_name: p.program_name, published: p.published, core_enabled: p.core_enabled, theme_accent: p.theme_accent,
                        hero_url: p.hero_url, hero_upload: p.hero_upload, seal_url: p.seal_url, seal_upload: p.seal_upload
                    });
                    heroPending.file = null; heroPending.remove = false; sealPending.file = null; sealPending.remove = false;
                    heroPending.render(); sealPending.render();
                    // header
                    card.querySelector('.pgm-name').textContent = p.program_name;
                    card.querySelector('.pgm-dot').style.background = p.theme_accent;
                    var s = card.querySelector('.pgm-status');
                    s.textContent = p.published ? 'On the website' : 'Hidden';
                    s.className = 'pgm-status ' + (p.published ? 'is-live' : 'is-hidden');
                    card.setAttribute('data-status', p.published ? 'live' : 'hidden');
                    card.setAttribute('data-search', (p.program_name + ' ' + card.querySelector('.pgm-head-main small').textContent).toLowerCase());
                    applyFilter();
                    // colour: keep the radio/picker in sync with what was stored
                    var cp = form.querySelector('[data-field="theme_custom"]'); cp.value = p.theme_accent.toLowerCase();
                    takeSnapshot(); markClean();
                    showAlert(formAlert, 'Saved! The program page on the website is updated.', false);
                } else {
                    showAlert(formAlert, data.error || 'Could not save.', true);
                }
            }).then(function () { btn.disabled = false; btn.innerHTML = original; });
        });

        /* ── repeating lists ── */
        card.querySelectorAll('.pgm-panel[data-panel]').forEach(function (panel) {
            var type = panel.getAttribute('data-panel');
            if (!panel.querySelector('.pgm-items')) return;
            setupList(panel, type);
        });

        function setupList(panel, type) {
            var listEl = panel.querySelector('.pgm-items');
            var dirty = panel.querySelector('.pgm-dirty'), alertEl = panel.querySelector('.pgm-alert');
            var addBtn = panel.querySelector('.pgm-add-item'), saveBtn = panel.querySelector('.pgm-save-items');
            var rows = st.items[type].map(function (r) { return Object.assign({ file: null, remove: false }, r); });
            var hasImg = (type === 'career' || type === 'uniform' || type === 'facility');   // Why choose us: text only
            var imgRequired = (type === 'uniform' || type === 'facility');

            function dirtyOn() { dirty.hidden = false; }

            function inputHtml(label, cls, attrs, value, long) {
                return '<div class="' + cls + '"><small>' + esc(label) + '</small>' +
                    (long ? '<textarea class="form-control form-control-sm" rows="2" ' + attrs + '>' + esc(value) + '</textarea>'
                          : '<input type="text" class="form-control form-control-sm" ' + attrs + ' value="' + esc(value) + '">') + '</div>';
            }

            function render() {
                listEl.innerHTML = '';
                if (!rows.length) {
                    listEl.innerHTML = '<div class="pgm-empty">Nothing here yet. Press “Add one” to create the first.</div>';
                }
                rows.forEach(function (r, i) {
                    var el = document.createElement('div');
                    el.className = 'pgm-item';
                    var html = '<div class="pgm-item-num">' + (i + 1) + '</div><div class="pgm-item-fields">';

                    if (type === 'badge' || type === 'core_value') {
                        var parts = (r.icon || '').split(':');
                        html += '<div class="f-md"><small>Icon</small><div class="pgm-iconpick">' +
                            '<span class="pgm-iconpreview" role="button" tabindex="0" title="Click to choose an icon">' + iconHtml(r.icon) + '</span>' +
                            '<button type="button" class="btn btn-outline-secondary btn-sm pgm-icon-choose">Choose icon</button>' +
                            '<button type="button" class="pgm-link pgm-icon-advtoggle">Use another icon</button>' +
                            '<span class="pgm-icon-adv" hidden>' +
                            '<select class="form-select form-select-sm pgm-icon-set"><option value="material"' + (parts[0] !== 'bi' ? ' selected' : '') + '>Material</option><option value="bi"' + (parts[0] === 'bi' ? ' selected' : '') + '>Bootstrap</option></select>' +
                            '<input type="text" class="form-control form-control-sm pgm-icon-name" list="pgm-icon-suggest" maxlength="50" placeholder="icon name, e.g. school" value="' + esc(parts[1] || '') + '"></span></div></div>';
                    }
                    if (type === 'core_value') {
                        html += inputHtml('Big letter', 'f-sm', 'data-k="letter" maxlength="2"', r.letter);
                        html += inputHtml('Title *', 'f-md', 'data-k="title" maxlength="255"', r.title);
                        html += inputHtml('Description', 'f-lg', 'data-k="body" maxlength="2000"', r.body, true);
                    } else if (type === 'badge') {
                        html += inputHtml('Bold text *', 'f-sm', 'data-k="title" maxlength="255" placeholder="4 Years"', r.title);
                        html += inputHtml('Small text', 'f-sm', 'data-k="subtitle" maxlength="255" placeholder="Program"', r.subtitle);
                    } else if (type === 'why' || type === 'career') {
                        html += inputHtml('Title *', 'f-lg', 'data-k="title" maxlength="255"', r.title);
                        html += inputHtml('Description', 'f-lg', 'data-k="body" maxlength="2000"', r.body, true);
                    } else if (type === 'uniform') {
                        html += inputHtml('Name (shown when the picture is enlarged)', 'f-lg', 'data-k="title" maxlength="255" placeholder="Female Uniform"', r.title);
                    } else if (type === 'facility') {
                        html += inputHtml('Facility name *', 'f-lg', 'data-k="title" maxlength="255" placeholder="Computer Laboratory"', r.title);
                        html += inputHtml('Description (optional)', 'f-lg', 'data-k="body" maxlength="500"', r.body, true);
                    }
                    if (hasImg) {
                        html += '<div class="f-lg pgm-item-img"><div class="thumb">No picture</div>' +
                            '<label class="btn btn-outline-secondary btn-sm mb-0">' + (imgRequired ? 'Choose picture *' : 'Choose picture') + '<input type="file" accept="' + (type === 'facility' ? 'image/jpeg,image/png,image/webp,.jpg,.jpeg,.png,.webp' : 'image/jpeg,image/png,image/webp,image/gif,image/svg+xml,.svg') + '" hidden></label>' +
                            '<button type="button" class="btn btn-outline-danger btn-sm pgm-img-rm"' + (imgRequired ? ' hidden' : '') + '>Remove</button></div>';
                    }
                    html += '</div><div class="pgm-item-tools">' +
                        '<button type="button" class="btn btn-outline-secondary btn-sm" data-act="up" title="Move up"' + (i === 0 ? ' disabled' : '') + '><i class="bi bi-arrow-up"></i></button>' +
                        '<button type="button" class="btn btn-outline-secondary btn-sm" data-act="down" title="Move down"' + (i === rows.length - 1 ? ' disabled' : '') + '><i class="bi bi-arrow-down"></i></button>' +
                        '<button type="button" class="btn btn-outline-danger btn-sm" data-act="del" title="Delete"><i class="bi bi-trash"></i></button></div>';
                    el.innerHTML = html;

                    // text fields
                    el.querySelectorAll('[data-k]').forEach(function (inp) {
                        inp.addEventListener('input', function () { r[inp.getAttribute('data-k')] = inp.value; dirtyOn(); });
                    });
                    // icon
                    var pick = el.querySelector('.pgm-iconpick');
                    if (pick) {
                        var set = pick.querySelector('.pgm-icon-set'), nm = pick.querySelector('.pgm-icon-name'), pv = pick.querySelector('.pgm-iconpreview');
                        var sync = function () {
                            var n = nm.value.trim().toLowerCase().replace(/[^a-z0-9_-]/g, '');
                            r.icon = n ? set.value + ':' + n : ''; pv.innerHTML = iconHtml(r.icon); dirtyOn();
                        };
                        set.addEventListener('change', sync); nm.addEventListener('input', sync);
                        bindPicker(pick);
                    }
                    // picture
                    if (hasImg) {
                        var thumb = el.querySelector('.thumb'), fileIn = el.querySelector('input[type=file]'), rm = el.querySelector('.pgm-img-rm');
                        if (type === 'uniform') fileIn.setAttribute('data-image-frame-ratio', 'natural');
                        var showThumb = function () {
                            var url = r.file ? URL.createObjectURL(r.file) : (r.remove ? null : r.image_url);
                            thumb.style.backgroundImage = url ? 'url("' + url.replace(/"/g, '%22') + '")' : '';
                            thumb.textContent = url ? '' : 'No picture';
                            if (!imgRequired) rm.hidden = !url; else rm.hidden = true;
                        };
                        fileIn.addEventListener('change', function () {
                            var f = fileIn.files[0]; fileIn.value = '';
                            if (!f) return;
                            if (type === 'facility') {
                                if (!facTypeOk(f)) { showAlert(alertEl, 'That file type isn\'t allowed. Facility pictures must be JPG, PNG or WEBP.', true); return; }
                            } else if (IMG_TYPES.indexOf(f.type) === -1 && !/\.svg$/i.test(f.name)) { showAlert(alertEl, 'That file type isn\'t allowed. Use JPG, PNG, WEBP or SVG.', true); return; }
                            if (f.size > (type === 'facility' ? FAC_IMG_MAX : IMG_MAX)) { showAlert(alertEl, 'That picture is too large. Maximum size is ' + (type === 'facility' ? '5' : '5') + ' MB.', true); return; }
                            hideAlert(alertEl); r.file = f; r.remove = false; showThumb(); dirtyOn();
                        });
                        rm.addEventListener('click', function () { r.file = null; r.remove = true; showThumb(); dirtyOn(); });
                        showThumb();
                    }
                    // move / delete
                    el.querySelectorAll('[data-act]').forEach(function (b) {
                        b.addEventListener('click', function () {
                            var act = b.getAttribute('data-act');
                            if (act === 'del') { rows.splice(i, 1); }
                            else if (act === 'up' && i > 0) { rows.splice(i - 1, 0, rows.splice(i, 1)[0]); }
                            else if (act === 'down' && i < rows.length - 1) { rows.splice(i + 1, 0, rows.splice(i, 1)[0]); }
                            dirtyOn(); render();
                        });
                    });
                    listEl.appendChild(el);
                });
                addBtn.disabled = rows.length >= LIMITS[type];
                var lim = panel.querySelector('.pgm-limit');
                if (lim) lim.textContent = rows.length + ' of ' + LIMITS[type] + ' added';
                var tc = card.querySelector('.pgm-tab[data-tab="' + type + '"] .pgm-tab-count');
                if (tc) tc.textContent = rows.length ? String(rows.length) : '';
            }

            addBtn.addEventListener('click', function () {
                if (rows.length >= LIMITS[type]) return;
                rows.push({ item_id: 0, title: '', subtitle: '', body: '', icon: type === 'badge' ? 'material:school' : (type === 'core_value' ? 'material:star' : ''), letter: '', image_url: null, has_upload: false, file: null, remove: false });
                dirtyOn(); render();
                var last = listEl.querySelector('.pgm-item:last-child [data-k="title"]'); if (last) last.focus();
            });

            var multi = panel.querySelector('.pgm-multi-input');
            if (multi) multi.addEventListener('change', function () {
                var files = Array.prototype.slice.call(multi.files); multi.value = '';
                var added = 0, skipped = [];
                files.forEach(function (f) {
                    if (rows.length >= LIMITS[type]) { skipped.push(f.name + ' (limit of ' + LIMITS[type] + ' reached)'); return; }
                    if (!facTypeOk(f)) { skipped.push(f.name + ' (not JPG, PNG or WEBP)'); return; }
                    if (f.size > FAC_IMG_MAX) { skipped.push(f.name + ' (over 5 MB)'); return; }
                    rows.push({ item_id: 0, title: nameFromFile(f.name), subtitle: '', body: '', icon: '', letter: '', image_url: null, has_upload: false, file: f, remove: false });
                    added++;
                });
                if (added) { dirtyOn(); render(); }
                if (skipped.length) showAlert(alertEl, (added ? added + ' added. ' : '') + 'Skipped: ' + skipped.join(', ') + '.', true);
                else if (added) { hideAlert(alertEl); }
            });

            panel.querySelector('.pgm-undo-items').addEventListener('click', function () {
                rows = st.items[type].map(function (r) { return Object.assign({ file: null, remove: false }, r); });
                dirty.hidden = true; hideAlert(alertEl); render();
            });

            saveBtn.addEventListener('click', function () {
                var btn = this, original = btn.innerHTML;
                var fd = new FormData();
                fd.append('action', 'save_program_items');
                fd.append('program_id', pid);
                fd.append('item_type', type);
                fd.append('items', JSON.stringify(rows.map(function (r) {
                    return { item_id: r.item_id, title: r.title, subtitle: r.subtitle, body: r.body, icon: r.icon, letter: r.letter, remove_image: !!r.remove };
                })));
                rows.forEach(function (r, n) { if (r.file) fd.append('file_' + n, r.file); });
                btn.disabled = true; btn.textContent = 'Saving…';
                post(fd).then(function (data) {
                    if (data.success) {
                        rows = data.items.map(function (r) { return Object.assign({ file: null, remove: false }, r); });
                        st.items[type] = data.items;
                        dirty.hidden = true; render();
                        showAlert(alertEl, 'Saved! The website is updated.', false);
                        /* lets the Content Editor refresh its live preview */
                        document.dispatchEvent(new CustomEvent('pgm:items-saved', { detail: { type: type, pid: pid } }));
                    } else {
                        showAlert(alertEl, data.error || 'Could not save.', true);
                    }
                }).then(function () { btn.disabled = false; btn.innerHTML = original; });
            });

            /* The Content Editor's slide strip saved this list: take its result so this tab is never out of date. */
            if (type === 'facility') {
                document.addEventListener('ve:facilities-changed', function (ev) {
                    var d = ev.detail || {};
                    if (String(d.pid) !== String(pid) || !Array.isArray(d.items)) return;
                    st.items[type] = d.items;
                    rows = d.items.map(function (r) { return Object.assign({ file: null, remove: false }, r); });
                    dirty.hidden = true; hideAlert(alertEl); render();
                });
            }

            render();
        }

        /* ── curriculum PDF ── */
        (function () {
            var panel = card.querySelector('[data-panel="curriculum"]');
            var nameEl = panel.querySelector('.pgm-pdf-name'), sizeEl = panel.querySelector('.pgm-pdf-size');
            var view = panel.querySelector('.pgm-pdf-view'), input = panel.querySelector('.pgm-pdf-input');
            var rm = panel.querySelector('.pgm-pdf-remove'), save = panel.querySelector('.pgm-save-pdf');
            var dirty = panel.querySelector('.pgm-dirty'), alertEl = panel.querySelector('.pgm-alert');
            var pending = { file: null, remove: false };
            function render() {
                var p = st.program;
                if (pending.file) { nameEl.textContent = pending.file.name; sizeEl.textContent = fmtSize(pending.file.size) + ' · will be uploaded when you save'; view.hidden = true; rm.hidden = false; }
                else if (pending.remove) { nameEl.textContent = 'No PDF'; sizeEl.textContent = 'The PDF will be removed when you save'; view.hidden = true; rm.hidden = true; }
                else if (p.pdf_url) { nameEl.textContent = p.pdf_name || 'Curriculum.pdf'; sizeEl.textContent = p.pdf_size ? fmtSize(p.pdf_size) : ''; view.href = p.pdf_url; view.hidden = false; rm.hidden = false; }
                else { nameEl.textContent = 'No PDF yet'; sizeEl.textContent = ''; view.hidden = true; rm.hidden = true; }
                var changed = !!pending.file || pending.remove;
                save.disabled = !changed; dirty.hidden = !changed;
                var tc = card.querySelector('.pgm-tab[data-tab="curriculum"] .pgm-tab-count');
                if (tc) tc.textContent = (p.pdf_url && !pending.remove) || pending.file ? '✓' : '';
            }
            input.addEventListener('change', function () {
                var f = input.files[0]; input.value = '';
                if (!f) return;
                if (f.type !== 'application/pdf' && !/\.pdf$/i.test(f.name)) { showAlert(alertEl, 'Only PDF files can be uploaded here.', true); return; }
                if (f.size > PDF_MAX) { showAlert(alertEl, 'That PDF is too large. Maximum size is 20 MB.', true); return; }
                hideAlert(alertEl); pending.file = f; pending.remove = false; render();
            });
            rm.addEventListener('click', function () {
                if (pending.file) { pending.file = null; } else { pending.remove = true; }
                render();
            });
            save.addEventListener('click', function () {
                var btn = this, original = btn.innerHTML;
                var fd = new FormData();
                fd.append('action', 'save_program_pdf'); fd.append('program_id', pid);
                if (pending.file) fd.append('pdf', pending.file);
                if (pending.remove) fd.append('remove_pdf', '1');
                btn.disabled = true; btn.textContent = 'Saving…';
                post(fd).then(function (data) {
                    if (data.success) {
                        st.program.pdf_url = data.program.pdf_url; st.program.pdf_name = data.program.pdf_name; st.program.pdf_size = data.program.pdf_size;
                        pending = { file: null, remove: false }; render();
                        showAlert(alertEl, 'Saved! The curriculum on the website is updated.', false);
                    } else {
                        showAlert(alertEl, data.error || 'Could not save.', true); btn.disabled = false;
                    }
                }).then(function () { btn.innerHTML = original; });
            });
            render();
        })();

        /* ── delete ── */
        card.querySelector('.pgm-delete').addEventListener('click', async function () {
            var name = st.program.program_name;
            if (!await window.AdminDialogs.confirm('Delete "' + name + '" and its whole page for good?\n\nThis cannot be undone. (To only hide it, untick "Show this program on the website" instead.)')) return;
            var btn = this; btn.disabled = true;
            var fd = new FormData(); fd.append('action', 'delete_program'); fd.append('program_id', pid);
            post(fd).then(function (data) {
                if (data.success) { card.remove(); applyFilter(); }
                else { window.AdminDialogs.alert(data.error || 'Could not delete the program.'); btn.disabled = false; }
            });
        });
    }

    /* ───────────── Search & filter ───────────── */
    (function () {
        var box = document.getElementById('pgm-search');
        if (!box) return;
        var current = 'all', none = document.getElementById('pgm-noresults');
        var chips = document.querySelectorAll('.pgm-filter');
        applyFilter = function () {
            var q = box.value.trim().toLowerCase(), shown = 0, counts = { all: 0, live: 0, hidden: 0 };
            document.querySelectorAll('.pgm-card').forEach(function (c) {
                var st = c.getAttribute('data-status');
                counts.all++; counts[st]++;
                var ok = (current === 'all' || st === current) && (!q || (c.getAttribute('data-search') || '').indexOf(q) !== -1);
                c.hidden = !ok; if (ok) shown++;
            });
            document.querySelectorAll('.pgm-group-dept').forEach(function (g) { g.hidden = !g.querySelector('.pgm-card:not([hidden])'); });
            if (none) none.hidden = shown > 0 || counts.all === 0;
            chips.forEach(function (b) { var n = b.querySelector('[data-count]'); if (n) n.textContent = counts[b.getAttribute('data-filter')]; });
        };
        box.addEventListener('input', applyFilter);
        chips.forEach(function (b) {
            b.addEventListener('click', function () {
                current = b.getAttribute('data-filter');
                chips.forEach(function (x) { x.classList.toggle('is-active', x === b); });
                applyFilter();
            });
        });
    })();

    /* Warn before leaving the page with changes that are not saved */
    window.addEventListener('beforeunload', function (ev) {
        if (window.__pgmLeaving || window.__veLeaving) return;
        if (document.querySelector('.pgm-dirty:not([hidden])')) { ev.preventDefault(); ev.returnValue = ''; }
    });

    // Open the program named in the address (#program-12), e.g. right after adding one
    var m = /^#program-(\d+)$/.exec(location.hash);
    if (m) {
        var target = document.getElementById('program-' + m[1]);
        if (target) { target.querySelector('.pgm-head').click(); target.scrollIntoView({ block: 'start' }); }
    }
})();
</script>
<?php endif; ?>
