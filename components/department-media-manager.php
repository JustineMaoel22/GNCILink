<?php
/**
 * Shared College and Basic Education department manager.
 * Rendered inside the Content Editor's department settings drawer.
 * College Departments also provides an "Add New Department" form.
 *
 * Expects from the including page: $csrfToken, and admin-functions.php loaded.
 * Saves through page-content-handler.php:
 *   save_department_details  – create (dept_id=0) or update name/description/page fields
 *   save_department_media    – logo upload / replace / remove
 *   save_department_dean     – Head of Office details + photo
 *   save_program             – a program's card image (Programs Offered section of each department)
 *   archive_department       – hide from / restore to the public website
 *   delete_department        – permanently remove (programs/announcements are detached, not deleted)
 */
require_once __DIR__ . '/department-public.php';
$departmentManagerIsBasic = $departmentManagerIsBasic ?? false;
$departmentManagerTitle = $departmentManagerIsBasic ? 'Basic Education Departments' : 'College Departments';
// Both managers share the same cards and editing controls, scoped to their section.
// Keep archived departments available so managers can restore them.
$basicDepartmentSlugs = ['casa-gradeschool', 'hs-monte', 'jhs-regular', 'shs-regular'];
$departments = array_values(array_filter(getDepartments(), static function ($department) use ($departmentManagerIsBasic, $basicDepartmentSlugs) {
    return $departmentManagerIsBasic
        ? in_array((string)($department['slug'] ?? ''), $basicDepartmentSlugs, true)
        : publicDepartmentIsCollege($department);
}));
if ($departmentManagerIsBasic) {
    usort($departments, static function ($a, $b) use ($basicDepartmentSlugs) {
        return array_search($a['slug'], $basicDepartmentSlugs, true) <=> array_search($b['slug'], $basicDepartmentSlugs, true);
    });
}
// Safety net: never post an empty CSRF token, even if the including page
// didn't pass $csrfToken into scope.
if (empty($csrfToken)) {
    $csrfToken = generateCSRFToken();
}
?>

<div class="dept-media-intro data-card mb-3">
    <div class="dept-intro-row">
        <div class="dept-intro-main">
            <strong class="dept-intro-title"><i class="bi bi-diagram-3"></i> <?= htmlspecialchars($departmentManagerTitle) ?></strong>
            <p class="dept-intro-text">
                Each box below is one department on the website. <b>Click a department to edit it.</b>
                Your changes show on the website as soon as you press <b>Save</b>.
            </p>
            <div class="dept-steps">
                <span><b>1</b> Click a department</span>
                <span><b>2</b> Pick a section and change it</span>
                <span><b>3</b> Press Save</span>
            </div>
        </div>
        <?php if (!$departmentManagerIsBasic): ?>
        <button type="button" class="btn-gnc-gold dept-add-main" id="btn-add-department">
            <i class="bi bi-plus-lg"></i> Add a New Department
        </button>
        <?php endif; ?>
    </div>
</div>

<!-- ── Create form ── -->
<?php if (!$departmentManagerIsBasic): ?>
<div class="data-card mb-3" id="dept-create-card" style="display:none">
    <div class="data-card-header">
        <span class="data-card-title"><i class="bi bi-building-add me-1"></i> Add a New Department</span>
    </div>
    <div class="dept-pad">
        <p class="dept-help mt-0 mb-3">Type the name to get started. You can add the logo, programs and Head of Office right after.</p>
        <div class="row g-3">
            <div class="col-lg-5">
                <label class="dept-label" for="create-name">Department name <span class="dept-req">*</span></label>
                <input type="text" class="form-control" id="create-name" maxlength="255" autocomplete="off"
                       placeholder="e.g. College of Hospitality Management">
            </div>
            <div class="col-lg-7">
                <label class="dept-label" for="create-description">Short description <span class="dept-opt">(optional)</span></label>
                <input type="text" class="form-control" id="create-description" maxlength="2000"
                       placeholder="One or two sentences about the department...">
                <div class="dept-help">You can write the full text later, in “About this department”.</div>
            </div>
        </div>
        <div class="alert d-none mt-3 mb-0" id="create-alert"></div>
        <div class="d-flex justify-content-end gap-2 mt-3">
            <button type="button" class="btn btn-outline-secondary" id="btn-create-cancel">Cancel</button>
            <button type="button" class="btn-gnc-primary" id="btn-create-save">Create Department</button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (empty($departments)): ?>
    <div class="data-card mb-3">
        <div class="empty-state">
            <i class="bi bi-building"></i>
            <?php if ($departmentManagerIsBasic): ?>
            <p>No Basic Education departments found.</p>
            <?php else: ?>
            <p>There are no departments yet. Press <b>Add a New Department</b> above to create the first one — it appears on the website as soon as it is saved.</p>
            <?php endif; ?>
        </div>
    </div>
<?php else: foreach ($departments as $d):
    $payload = departmentAdminPayload($d);

    // Programs that belong to this department (for the "Programs" tab).
    $deptPrograms  = [];
    $programsError = false;
    try {
        $stmtP = getDB()->prepare(PROGRAM_FULL_SELECT_SQL . " WHERE p.dept_id = ? ORDER BY p.program_id ASC");
        $stmtP->execute([(int)$d['dept_id']]);
        foreach ($stmtP->fetchAll(PDO::FETCH_ASSOC) as $progRow) {
            $deptPrograms[] = programFullAdminPayload(hydrateProgramFull($progRow));
        }
    } catch (Exception $e) {
        error_log('Department manager programs error: ' . $e->getMessage());
        $programsError = true;
    }
    $isArchived = !empty($d['archived_at']);
    $viewUrl = $departmentManagerIsBasic
        ? '/pages/academics/basic-edu/basic-edu-department/' . rawurlencode((string)$d['slug']) . '.php'
        : '/pages/academics/college/college-department.php?slug=' . rawurlencode((string)$d['slug']);
?>
    <details class="data-card mb-3 dept-media-card dept-acc" id="dept-<?= (int)$d['dept_id'] ?>"
         data-dept-id="<?= (int)$d['dept_id'] ?>"
         data-public-url="<?= htmlspecialchars($viewUrl, ENT_QUOTES, 'UTF-8') ?>"
         data-current="<?= htmlspecialchars(json_encode($payload), ENT_QUOTES, 'UTF-8') ?>"
         <?= count($departments) === 1 ? 'open' : '' ?>>

        <summary class="dept-sum">
            <span class="dept-sum-logo">
                <img class="sum-logo-img" alt="" hidden>
                <i class="bi bi-building sum-logo-ph"></i>
            </span>
            <span class="dept-sum-main">
                <span class="dept-title-text dept-sum-name"><?= htmlspecialchars($d['dept_name']) ?></span>
                <span class="dept-sum-sub">
                    <span class="badge-status dept-archived-badge <?= $isArchived ? 'draft' : 'published' ?>"><?= $isArchived ? 'Hidden from website' : 'Showing on website' ?></span>
                    <span class="dept-sum-count"><?= count($deptPrograms) ?> program<?= count($deptPrograms) === 1 ? '' : 's' ?></span>
                </span>
            </span>
            <span class="dept-unsaved-pill" hidden><i class="bi bi-exclamation-circle"></i> Unsaved changes</span>
            <span class="dept-sum-open"><span class="dso-open">Edit</span><span class="dso-close">Close</span> <i class="bi bi-chevron-down dept-sum-chevron"></i></span>
        </summary>

        <div class="dept-body">

            <div class="dept-hidden-note" hidden>
                <i class="bi bi-eye-slash"></i>
                <span><b>This department is hidden.</b> Visitors can't see it on the website.</span>
                <button type="button" class="btn btn-sm btn-outline-secondary btn-note-show">Show it again</button>
            </div>
            <div class="dept-bodytop">
                <a class="btn btn-outline-secondary btn-sm dept-view-link" href="<?= htmlspecialchars($viewUrl) ?>" target="_blank" rel="noopener">
                    <i class="bi bi-box-arrow-up-right"></i> See this page on the website
                </a>
                <span class="dept-help">Opens in a new tab. Save first to see your latest changes.</span>
            </div>

            <!-- ── Tabs ── -->
            <div class="dept-tabs" role="tablist">
                <button type="button" class="dept-tab" data-tab="text" data-label="Page text">
                    <i class="bi bi-fonts"></i> Page text <span class="dept-tab-dot" hidden title="Unsaved changes"></span>
                </button>
                <button type="button" class="dept-tab" data-tab="logo" data-label="Logo">
                    <i class="bi bi-image"></i> Logo <span class="dept-tab-dot" hidden title="Unsaved changes"></span>
                </button>
                <button type="button" class="dept-tab" data-tab="programs" data-label="Programs">
                    <i class="bi bi-mortarboard"></i> Programs <span class="dept-tab-dot" hidden title="Unsaved changes"></span>
                </button>
                <button type="button" class="dept-tab" data-tab="dean" data-label="Head of Office">
                    <i class="bi bi-person-badge"></i> Head of Office <span class="dept-tab-dot" hidden title="Unsaved changes"></span>
                </button>
                <button type="button" class="dept-tab" data-tab="more" data-label="Hide or Delete">
                    <i class="bi bi-gear"></i> Hide or Delete
                </button>
            </div>

            <!-- ═════ Tab: Page text ═════ -->
            <div class="dept-panel" data-panel="text">
                <p class="dept-panel-intro">The words and details that describe this department on the website. Press <b>Save page text</b> when you are done.</p>

                <div class="dept-block">
                    <div class="dept-block-title"><i class="bi bi-card-heading"></i> Name &amp; list card <small>How this department looks in the <?= htmlspecialchars($departmentManagerTitle) ?> list</small></div>
                    <div class="row g-4">
                        <div class="col-lg-7">
                            <div class="row g-3">
                                <div class="col-12">
                                    <label class="dept-label">Department name <span class="dept-req">*</span></label>
                                    <input type="text" class="form-control details-name-input" maxlength="255" autocomplete="off"
                                           placeholder="Department name">
                                    <div class="dept-help">The title shown for this department everywhere on the website.</div>
                                </div>
                                <div class="col-12">
                                    <label class="dept-label">Card color <span class="dept-opt">(optional)</span></label>
                                    <div class="dept-swatches" role="group" aria-label="Card color">
                                        <button type="button" class="dept-swatch" data-color="#14609A" style="background:#14609A" title="Blue" aria-label="Blue"></button>
                                        <button type="button" class="dept-swatch" data-color="#1F5E2C" style="background:#1F5E2C" title="Green" aria-label="Green"></button>
                                        <button type="button" class="dept-swatch" data-color="#307056" style="background:#307056" title="Teal" aria-label="Teal"></button>
                                        <button type="button" class="dept-swatch" data-color="#FD541B" style="background:#FD541B" title="Orange" aria-label="Orange"></button>
                                        <button type="button" class="dept-swatch" data-color="#8C1D2F" style="background:#8C1D2F" title="Maroon" aria-label="Maroon"></button>
                                        <button type="button" class="dept-swatch" data-color="#5B3A8C" style="background:#5B3A8C" title="Purple" aria-label="Purple"></button>
                                        <button type="button" class="dept-swatch" data-color="#FED018" style="background:#FED018" title="Gold" aria-label="Gold"></button>
                                        <label class="dept-swatch dept-swatch-custom" title="Pick any color">
                                            <input type="color" class="dept-color-picker details-theme-picker" value="#1F5E2C" aria-label="Pick any color">
                                            <i class="bi bi-plus-lg" aria-hidden="true"></i>
                                        </label>
                                        <button type="button" class="btn btn-sm btn-outline-secondary btn-theme-clear">Use default color</button>
                                    </div>
                                    <div class="dept-color-warn" hidden><i class="bi bi-exclamation-triangle"></i> This color is quite light, so the white button text may be hard to read. A darker color is easier on the eyes.</div>
                                    <div class="dept-help">The color of this department's “View Department” button in the list. If unsure, leave the default.</div>
                                    <details class="dept-tip">
                                        <summary>I have a color code (like #1F5E2C)</summary>
                                        <input type="text" class="form-control details-theme-input mt-2" maxlength="7" placeholder="#1F5E2C" aria-label="Color code">
                                    </details>
                                </div>
                                <div class="col-12">
                                    <label class="dept-label">Short description <span class="dept-opt">(optional)</span></label>
                                    <textarea class="form-control details-description-input" rows="2" maxlength="2000"
                                              placeholder="A short summary for the department list..."></textarea>
                                    <div class="dept-help">The list card shows the first sentences of <b>“About this department”</b> below. This short description is used on the card <b>only when About is empty</b>.</div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-5">
                            <div class="dpv-label"><i class="bi bi-eye"></i> Preview <small>Updates as you type</small></div>
                            <div class="dpv-card">
                                <div class="dpv-body">
                                    <div class="dpv-logo"><img class="dpv-logo-img" alt="" hidden><i class="bi bi-building dpv-logo-ph"></i></div>
                                    <div class="dpv-title">Department name</div>
                                    <span class="dpv-underline"></span>
                                    <p class="dpv-desc"></p>
                                </div>
                                <div class="dpv-btn">View Department <i class="bi bi-arrow-right"></i></div>
                            </div>
                            <div class="dept-help">The logo comes from the Logo tab.</div>
                        </div>
                    </div>
                </div>

                <div class="dept-block">
                    <div class="dept-block-title"><i class="bi bi-file-text"></i> Department page <small>What visitors read after they open the department</small></div>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="dept-label">About this department</label>
                            <textarea class="form-control details-about-input" rows="6"
                                      placeholder="The full overview shown on the department page..."></textarea>
                            <div class="dept-help">The website shows this exactly as you type it. Press Enter for a new line, and leave an empty line between paragraphs.</div>
                            <details class="dept-tip">
                                <summary>How do I make a heading or a bullet list?</summary>
                                <div class="dept-tip-body">
                                    End a line with a colon and it becomes a <b>bold heading</b>. The lines right under it become a <b>bullet list</b>. For example, typing:
                                    <pre>The college offers:
Quality teaching
Modern facilities</pre>
                                    shows a bold heading “The college offers:” with two bullet points under it.
                                </div>
                            </details>
                        </div>
                        <div class="col-lg-6">
                            <label class="dept-label">Office name <span class="dept-opt">(optional)</span></label>
                            <input type="text" class="form-control details-office-input" maxlength="100"
                                   placeholder="e.g. CBA Office">
                            <div class="dept-help">The label above the office location in the “Get Connected” box.</div>
                        </div>
                        <div class="col-lg-6">
                            <label class="dept-label">Facebook page link <span class="dept-opt">(optional)</span></label>
                            <input type="text" class="form-control details-facebook-input" maxlength="255"
                                   placeholder="https://www.facebook.com/your-page">
                            <div class="dept-help">Paste the link to the department's Facebook page. Leave empty to hide the Facebook box.</div>
                        </div>
                    </div>
                </div>

                <div class="details-alert alert d-none mt-3 mb-0"></div>
                <div class="dept-actions">
                    <button type="button" class="btn btn-outline-secondary btn-details-reset" disabled><i class="bi bi-arrow-counterclockwise"></i> Undo changes</button>
                    <button data-content-publish type="button" class="btn-gnc-primary btn-details-save" disabled>Save page text</button>
                </div>
            </div>

            <!-- ═════ Tab: Logo ═════ -->
            <div class="dept-panel" data-panel="logo" hidden>
                <p class="dept-panel-intro">The department's logo, shown on its card and at the top of its page.</p>
                <div class="dept-media-field">
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div class="dept-logo-preview">
                            <img class="dept-logo-img" alt="" hidden>
                            <i class="bi bi-image dept-logo-placeholder"></i>
                        </div>
                        <div class="flex-grow-1" style="min-width:180px">
                            <div class="dept-file-name text-truncate dept-logo-name">No logo</div>
                            <div class="dept-help mt-0 dept-logo-hint">JPG, PNG, GIF, WEBP or SVG · max 5 MB</div>
                            <div class="d-flex gap-2 mt-2 flex-wrap">
                                <button type="button" class="btn btn-outline-secondary btn-pick-logo">Upload Logo</button>
                                <button type="button" class="btn btn-outline-danger btn-remove-logo">Remove</button>
                                <input type="file" class="dept-logo-input" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml,.svg" hidden>
                            </div>
                        </div>
                    </div>
                    <div class="dept-help mt-3">Tip: a square image on a transparent background (PNG) looks best.</div>
                </div>
                <div class="logo-alert alert d-none mt-3 mb-0"></div>
                <div class="dept-actions">
                    <button type="button" class="btn btn-outline-secondary btn-logo-reset" disabled>Undo changes</button>
                    <button data-content-publish type="button" class="btn-gnc-primary btn-logo-save" disabled>Save logo</button>
                </div>
            </div>

            <!-- ═════ Tab: Programs ═════ -->
            <div class="dept-panel" data-panel="programs" hidden>
                <p class="dept-panel-intro">
                    The picture on each program's card on this department's page.
                    Best size: landscape, about 800 × 380 px · JPG, PNG or WEBP · max 5 MB.
                    <span class="d-block dept-help">To change a program's name, page or colors, go to <b>Content Editor → Programs</b>.</span>
                </p>
                <?php if ($programsError): ?>
                    <div class="alert alert-warning mb-0">
                        Programs couldn't be loaded. If this is a fresh install, run
                        <code>sql/college-department-page-migration.sql</code> (it adds the program image column).
                    </div>
                <?php elseif (empty($deptPrograms)): ?>
                    <div class="dept-empty-note"><i class="bi bi-info-circle"></i> No programs are assigned to this department yet.</div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-3">
                    <?php foreach ($deptPrograms as $prog): ?>
                        <div class="dept-media-field prog-row" data-program="<?= htmlspecialchars(json_encode($prog), ENT_QUOTES, 'UTF-8') ?>">
                            <div class="d-flex align-items-start gap-3 flex-wrap">
                                <div class="prog-thumb">
                                    <img class="prog-img" alt="" hidden>
                                    <i class="bi bi-image prog-placeholder"></i>
                                </div>
                                <div class="flex-grow-1" style="min-width:200px">
                                    <div class="dept-file-name"><?= htmlspecialchars((string)$prog['name']) ?></div>
                                    <div class="dept-help mt-0 text-truncate prog-img-name">No image</div>
                                    <div class="d-flex gap-2 mt-2 flex-wrap">
                                        <button type="button" class="btn btn-outline-secondary btn-pick-prog-img">Upload Image</button>
                                        <button type="button" class="btn btn-outline-danger btn-remove-prog-img">Remove</button>
                                        <input type="file" class="prog-img-input" accept="image/jpeg,image/png,image/webp" hidden>
                                    </div>
                                </div>
                            </div>
                            <div class="prog-alert alert d-none mt-3 mb-0"></div>
                            <div class="dept-actions">
                                <button type="button" class="btn btn-outline-secondary btn-prog-reset" disabled>Undo changes</button>
                                <button data-content-publish type="button" class="btn-gnc-primary btn-prog-save" disabled>Save image</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- ═════ Tab: Head of Office ═════ -->
            <div class="dept-panel" data-panel="dean" hidden>
                <p class="dept-panel-intro">The Dean (or head) of this department, shown in the "Head of Office" part of its page.</p>
                <div class="row g-3">
                    <div class="col-md-4">
                        <div class="dept-media-field">
                            <div class="dept-media-label"><i class="bi bi-image"></i> Photo</div>
                            <div class="d-flex align-items-center gap-3">
                                <div class="dept-logo-preview dean-photo-preview">
                                    <img class="dean-photo-img" alt="" hidden>
                                    <i class="bi bi-person dean-photo-placeholder"></i>
                                </div>
                                <div class="flex-grow-1" style="min-width:0">
                                    <div class="dept-file-name text-truncate dean-photo-name">No photo</div>
                                    <div class="dept-help mt-0 dean-photo-hint">JPG, PNG or WEBP · max 5 MB</div>
                                </div>
                            </div>
                            <div class="d-flex gap-2 mt-2 flex-wrap">
                                <button type="button" class="btn btn-outline-secondary btn-pick-dean-photo">Upload Photo</button>
                                <button type="button" class="btn btn-outline-danger btn-remove-dean-photo">Remove</button>
                                <input type="file" class="dean-photo-input" data-photo-check accept="image/jpeg,image/png,image/webp" hidden>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <div class="dept-media-field">
                            <div class="dept-media-label"><i class="bi bi-person-lines-fill"></i> Name &amp; introduction</div>
                            <div class="row g-3">
                                <div class="col-sm-8">
                                    <label class="dept-label">Full name</label>
                                    <input type="text" class="form-control dean-name-input" placeholder="e.g. Annaliza V. Maris, LPT, MA">
                                </div>
                                <div class="col-sm-4">
                                    <label class="dept-label">Position</label>
                                    <input type="text" class="form-control dean-title-input" placeholder="e.g. Dean">
                                </div>
                                <div class="col-12">
                                    <label class="dept-label">Short introduction</label>
                                    <textarea class="form-control dean-bio-input" rows="4" placeholder="A short introduction..."></textarea>
                                    <div class="dept-help">Shown next to the photo, under "Meet the Dean".</div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="dept-media-field">
                            <div class="dept-media-label"><i class="bi bi-diagram-2"></i> Contact details <span class="dept-opt">(shown in "Get Connected")</span></div>
                            <div class="row g-3">
                                <div class="col-sm-6 col-lg-3">
                                    <label class="dept-label">Phone number</label>
                                    <input type="text" class="form-control dean-phone-input" placeholder="(045) 900-4473 Loc. 120">
                                </div>
                                <div class="col-sm-6 col-lg-3">
                                    <label class="dept-label">Email address</label>
                                    <input type="email" class="form-control dean-email-input" placeholder="info@gnc.edu.ph">
                                </div>
                                <div class="col-sm-6 col-lg-3">
                                    <label class="dept-label">Office location</label>
                                    <input type="text" class="form-control dean-office-input" placeholder="Limlingan Hall, Floor 2, Room 202">
                                </div>
                                <div class="col-sm-6 col-lg-3">
                                    <label class="dept-label">Office hours</label>
                                    <input type="text" class="form-control dean-hours-input" placeholder="Monday - Friday, 8:00AM - 4:00PM">
                                </div>
                            </div>
                            <div class="dept-help mt-2">Leave a box empty and it simply won't be shown.</div>
                        </div>
                    </div>
                </div>
                <div class="dean-alert alert d-none mt-3 mb-0"></div>
                <div class="dept-actions">
                    <button type="button" class="btn btn-outline-secondary btn-dean-reset" disabled>Undo changes</button>
                    <button data-content-publish type="button" class="btn-gnc-primary btn-dean-save" disabled>Save Head of Office</button>
                </div>
            </div>

            <!-- ═════ Tab: Hide or Delete ═════ -->
            <div class="dept-panel" data-panel="more" hidden>
                <div class="dept-setting">
                    <div class="dept-setting-text">
                        <strong>Show or hide on the website</strong>
                        <p class="dept-help mt-1 mb-0 dept-vis-text"></p>
                    </div>
                    <button type="button" class="btn btn-outline-secondary btn-dept-archive"></button>
                </div>

                <div class="dept-setting dept-setting-danger">
                    <div class="dept-setting-text">
                        <strong>Delete this department</strong>
                        <p class="dept-help mt-1 mb-0">Removes it for good — this can't be undone.
                            Its programs and announcements are kept; they just won't be listed under this department anymore.
                            If you only want it off the website for now, use <b>Hide</b> above instead.</p>
                    </div>
                    <button type="button" class="btn btn-outline-danger btn-dept-delete">
                        <i class="bi bi-trash"></i> Delete Department
                    </button>
                </div>
                <div class="more-alert alert d-none mt-3 mb-0"></div>
            </div>

            <!-- ── Unsaved-changes bar (appears only when something is edited) ── -->
            <div class="dept-savebar" hidden>
                <span class="dept-savebar-text"><i class="bi bi-exclamation-circle"></i> Not saved yet: <b class="dept-savebar-list"></b></span>
                <button data-content-publish type="button" class="btn-gnc-primary btn-save-all">Save everything</button>
            </div>
        </div>
    </details>
<?php endforeach; endif; ?>


<style>
.dept-media-field {
    border: 1px solid rgba(0, 0, 0, .1);
    border-radius: 10px;
    padding: .85rem;
    height: 100%;
}
.dept-media-label {
    font-size: .8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--gnc-green, #1F5E2C);
    margin-bottom: .6rem;
}
.dean-media-label {
    font-size: .8rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: var(--gnc-green, #1F5E2C);
}
.dept-logo-preview {
    width: 72px;
    height: 72px;
    flex: 0 0 72px;
    border-radius: 10px;
    background: #f1f1f1;
    color: #aaa;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    font-size: 1.6rem;
}
.dept-logo-preview img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 4px;
}
.dept-pad { padding: 0 1.25rem 1.25rem; }
.dept-intro-title { font-size: 1.05rem; }
.dept-intro-text { font-size: .95rem; color: #555; max-width: 70ch; }
.dept-steps { display: flex; flex-wrap: wrap; gap: .5rem; }
.dept-steps span {
    display: inline-flex; align-items: center; gap: .45rem;
    background: #f6f1e4; border-radius: 999px; padding: .3rem .8rem .3rem .35rem;
    font-size: .85rem; color: #444;
}
.dept-steps b {
    display: inline-flex; align-items: center; justify-content: center;
    width: 22px; height: 22px; border-radius: 50%;
    background: var(--gnc-green, #1F5E2C); color: #fff; font-size: .75rem;
}

/* Forms: bigger, calmer, plain-language */
.dept-label { display: block; font-size: .9rem; font-weight: 600; color: #222; margin-bottom: .3rem; }
.dept-req { color: #c0392b; }
.dept-opt { font-weight: 400; color: #777; font-size: .82rem; text-transform: none; letter-spacing: 0; }
.dept-help { font-size: .82rem; color: #6b6b66; margin-top: .3rem; line-height: 1.45; }
.dept-file-name { font-weight: 600; font-size: .92rem; }
.dept-panel .form-control, .dept-pad .form-control { font-size: .95rem; padding: .55rem .75rem; }
.dept-panel .btn, .dept-panel .btn-gnc-primary, .dept-pad .btn, .dept-pad .btn-gnc-primary { min-height: 40px; }
.dept-color-picker { width: 44px; height: 40px; padding: 2px; border: 1px solid #ccc; border-radius: 8px; background: #fff; flex: none; cursor: pointer; }

/* Collapsible department cards */
.dept-acc { padding: 0; }
.dept-sum {
    list-style: none; display: flex; align-items: center; gap: 14px;
    padding: 14px 18px; cursor: pointer; min-height: 68px; border-radius: 12px;
}
.dept-sum::-webkit-details-marker { display: none; }
.dept-sum:hover { background: rgba(31, 94, 44, .05); }
.dept-sum:focus-visible { outline: 3px solid var(--gnc-gold, #e9b93c); outline-offset: -3px; }
.dept-sum-logo {
    flex: 0 0 46px; width: 46px; height: 46px; border-radius: 50%;
    background: #f1f1f1; color: #aaa; display: flex; align-items: center; justify-content: center;
    overflow: hidden; font-size: 1.2rem;
}
.dept-sum-logo img { width: 100%; height: 100%; object-fit: contain; padding: 3px; }
.dept-sum-main { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 4px; }
.dept-sum-name { font-weight: 700; font-size: 1.02rem; color: #1b1b1b; overflow-wrap: anywhere; }
.dept-sum-sub { display: flex; align-items: center; flex-wrap: wrap; gap: 10px; font-size: .82rem; color: #6b6b66; }
.dept-unsaved-pill {
    background: #fff3cd; color: #8a6100; border: 1px solid #f0d98a;
    border-radius: 999px; padding: .2rem .7rem; font-size: .78rem; font-weight: 600; white-space: nowrap;
}
.dept-unsaved-pill[hidden] { display: none; }
.dept-sum-open { font-size: .85rem; font-weight: 600; color: var(--gnc-green, #1F5E2C); white-space: nowrap; }
.dept-sum-chevron { transition: transform .2s ease; display: inline-block; margin-left: 4px; }
.dept-acc[open] > .dept-sum { border-bottom: 1px solid rgba(0,0,0,.08); border-radius: 12px 12px 0 0; }
.dept-acc[open] > .dept-sum .dept-sum-chevron { transform: rotate(180deg); }
.dept-body { padding: 0 1.25rem 1.25rem; }

/* Tabs */
.dept-tabs {
    display: flex; gap: 6px; overflow-x: auto; padding: 14px 0 0;
    border-bottom: 2px solid #ece7dc; margin-bottom: 1.1rem; scrollbar-width: thin;
}
.dept-tab {
    flex: none; display: inline-flex; align-items: center; gap: 7px;
    background: none; border: 0; border-bottom: 3px solid transparent; margin-bottom: -2px;
    padding: .65rem .95rem; font-size: .92rem; font-weight: 600; color: #555;
    min-height: 46px; cursor: pointer; border-radius: 8px 8px 0 0;
}
.dept-tab:hover { background: rgba(31, 94, 44, .06); color: #222; }
.dept-tab.active { color: var(--gnc-green, #1F5E2C); border-bottom-color: var(--gnc-gold, #e9b93c); background: rgba(233, 185, 60, .1); }
.dept-tab:focus-visible { outline: 3px solid var(--gnc-gold, #e9b93c); outline-offset: -3px; }
.dept-tab-dot { width: 9px; height: 9px; border-radius: 50%; background: #e0a100; display: inline-block; }
.dept-tab-dot[hidden] { display: none; }
.dept-panel[hidden] { display: none; }
.dept-panel-intro { font-size: .92rem; color: #555; margin: 0 0 1rem; }
.dept-actions { display: flex; justify-content: flex-end; gap: .5rem; margin-top: 1rem; flex-wrap: wrap; }
.dept-empty-note { background: #f7f5ef; border-radius: 10px; padding: 1rem; color: #6b6b66; font-size: .92rem; }

/* Hide / delete tab */
.dept-setting {
    display: flex; align-items: center; justify-content: space-between; gap: 1rem; flex-wrap: wrap;
    border: 1px solid rgba(0,0,0,.1); border-radius: 10px; padding: 1rem; margin-bottom: .85rem;
}
.dept-setting-text { flex: 1 1 280px; min-width: 0; }
.dept-setting-danger { border-color: #efc4c0; background: #fdf6f5; }

/* Sticky "unsaved changes" bar */
.dept-savebar {
    position: sticky; bottom: 0; z-index: 5; margin-top: 1.25rem;
    display: flex; align-items: center; justify-content: space-between; gap: .75rem; flex-wrap: wrap;
    background: #fff8e1; border: 1px solid #f0d98a; border-radius: 10px;
    padding: .7rem 1rem; box-shadow: 0 -4px 14px rgba(0,0,0,.08);
}
.dept-savebar[hidden] { display: none; }
.dept-savebar-text { font-size: .9rem; color: #6b4e00; }

@media (max-width: 575px) {
    .dept-sum { padding: 12px; gap: 10px; }
    .dept-sum-open { display: none; }
    .dept-body { padding: 0 .85rem 1rem; }
    .dept-actions .btn, .dept-actions .btn-gnc-primary { flex: 1 1 auto; }
}
@media (prefers-reduced-motion: reduce) { .dept-sum-chevron { transition: none; } }

.prog-thumb {
    width: 200px;
    height: 95px;
    flex: 0 0 200px;
    border-radius: 8px;
    background: #f1f1f1;
    color: #aaa;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    font-size: 1.8rem;
}
.prog-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover; /* same crop the public card uses */
}
@media (max-width: 575px) {
    .prog-thumb { width: 100%; flex-basis: 100%; height: 140px; }
}

/* ═════════ Friendlier layout (v2) ═════════ */
.dept-media-intro { padding: 1.25rem 1.5rem; }
.dept-intro-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem 1.5rem; flex-wrap: wrap; }
.dept-intro-main { flex: 1; min-width: 260px; }
.dept-intro-title { display: inline-flex; align-items: center; gap: .45rem; font-size: 1.2rem; }
.dept-intro-text { margin: .35rem 0 .85rem; }
.dept-add-main { flex: none; white-space: nowrap; display: inline-flex; align-items: center; gap: .45rem; padding: .7rem 1.25rem; font-size: 1rem; }

.dso-close, .dept-acc[open] .dso-open { display: none; }
.dept-acc[open] .dso-close { display: inline; }

.dept-bodytop { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem .9rem; padding-top: 1rem; }
.dept-bodytop .dept-help { margin: 0; }
.dept-bodytop[hidden], .dept-hidden-note[hidden], .dept-color-warn[hidden] { display: none !important; }
.dept-hidden-note {
    display: flex; align-items: center; flex-wrap: wrap; gap: .5rem .8rem;
    margin-top: 1rem; padding: .7rem .9rem; border-radius: 10px;
    background: #f1f1ef; border: 1px solid #dcdcd4; color: #444; font-size: .9rem;
}
.dept-hidden-note .btn { margin-left: auto; }

.dept-block { border: 1px solid #ebe7dc; border-radius: 12px; padding: 1rem 1.1rem 1.2rem; margin-bottom: 1rem; background: #fff; }
.dept-block-title { display: flex; align-items: baseline; flex-wrap: wrap; gap: .2rem .55rem; font-weight: 700; font-size: .98rem; color: var(--gnc-green, #1F5E2C); margin-bottom: 1rem; }
.dept-block-title i { align-self: center; }
.dept-block-title small { font-weight: 400; color: #777; font-size: .8rem; }

/* colour swatches */
.dept-swatches { display: flex; flex-wrap: wrap; align-items: center; gap: .5rem; }
.dept-swatch {
    position: relative; width: 34px; height: 34px; padding: 0; border-radius: 50%;
    border: 3px solid #fff; box-shadow: 0 0 0 1px rgba(0,0,0,.25); cursor: pointer;
    display: inline-flex; align-items: center; justify-content: center; overflow: hidden; flex: none;
}
.dept-swatch:hover { transform: scale(1.08); }
.dept-swatch:focus-visible { outline: 3px solid var(--gnc-gold, #e9b93c); outline-offset: 2px; }
.dept-swatch.is-on { box-shadow: 0 0 0 3px var(--gnc-green, #1F5E2C); }
.dept-swatch-custom { background: conic-gradient(#e33, #fb3, #3c3, #3cf, #63f, #e3c, #e33); color: #fff; margin: 0; }
.dept-swatch-custom i { font-size: .8rem; text-shadow: 0 0 3px rgba(0,0,0,.6); pointer-events: none; }
.dept-swatch-custom input[type=color] { position: absolute; inset: -8px; width: 50px; height: 50px; opacity: 0; cursor: pointer; border: 0; padding: 0; }
.dept-color-warn { margin-top: .5rem; padding: .45rem .7rem; border-radius: 8px; background: #fff3dc; color: #7a4a00; font-size: .82rem; }
.dept-tip { margin-top: .5rem; font-size: .85rem; }
.dept-tip > summary { cursor: pointer; color: #1a6aa8; list-style: none; display: inline-block; text-decoration: underline; }
.dept-tip > summary::-webkit-details-marker { display: none; }
.dept-tip-body { margin-top: .5rem; padding: .7rem .9rem; border-radius: 10px; background: #f7f5ef; color: #444; line-height: 1.5; }
.dept-tip-body pre { margin: .5rem 0; padding: .55rem .75rem; background: #fff; border: 1px solid #e6e1d8; border-radius: 8px; font-size: .85rem; white-space: pre-wrap; }

/* live preview of the list card */
.dpv-label { font-size: .8rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: var(--gnc-green, #1F5E2C); margin-bottom: .5rem; }
.dpv-label small { text-transform: none; letter-spacing: 0; font-weight: 400; color: #777; margin-left: .3rem; }
.dpv-card { --accent: #14609A; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(40,30,10,.1), 0 6px 18px rgba(40,30,10,.08); max-width: 340px; }
.dpv-body { padding: 18px 20px 16px; text-align: center; }
.dpv-logo { width: 110px; height: 110px; margin: 0 auto 12px; border-radius: 50%; background: #f1ede6; color: #bbb; display: flex; align-items: center; justify-content: center; overflow: hidden; font-size: 2rem; }
.dpv-logo img { width: 100%; height: 100%; object-fit: contain; }
.dpv-title { font-family: 'Noto Serif', serif; font-weight: 700; font-size: 1rem; line-height: 1.3; color: #1b1b1b; overflow-wrap: anywhere; }
.dpv-underline { display: block; width: 40px; height: 3px; border-radius: 2px; background: var(--gnc-gold, #d3a63a); margin: 9px auto 11px; }
.dpv-desc { margin: 0; font-size: .8rem; line-height: 1.55; color: #333; text-align: justify; min-height: 1.5em; }
.dpv-btn { background: var(--accent); color: #fff; text-align: center; padding: .7rem 1rem; font-size: .85rem; font-weight: 500; transition: background .2s; }
.dpv-btn i { margin-left: .3rem; }

.dept-actions .btn-gnc-primary { padding: .55rem 1.3rem; }
.dept-savebar { background: #fffaf0; border-color: #f0b35a; }

@media (max-width: 575px) {
    .dept-media-intro { padding: 1rem; }
    .dept-add-main { width: 100%; justify-content: center; }
    .dept-hidden-note .btn { margin-left: 0; width: 100%; }
}

/* ── Delete confirmation dialog (replaces the browser's confirm()) ── */
.gnc-confirm-overlay {
    position: fixed; inset: 0; z-index: 2100;
    display: flex; align-items: center; justify-content: center;
    padding: 1rem;
    background: rgba(12, 22, 17, .55);
    backdrop-filter: blur(3px);
    opacity: 0; transition: opacity .18s ease;
}
.gnc-confirm-overlay.is-open { opacity: 1; }
.gnc-confirm-dialog {
    width: 100%; max-width: 460px;
    background: #fff; border-radius: 16px;
    box-shadow: 0 24px 60px rgba(0, 0, 0, .35);
    padding: 1.6rem 1.6rem 1.25rem;
    text-align: center;
    transform: translateY(14px) scale(.96);
    transition: transform .22s cubic-bezier(.22, .61, .36, 1);
}
.gnc-confirm-overlay.is-open .gnc-confirm-dialog { transform: none; }
.gnc-confirm-icon {
    width: 64px; height: 64px; margin: 0 auto 1rem;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    background: #fdecea; color: #b3261e; font-size: 1.7rem;
    box-shadow: 0 0 0 8px #fdf6f5;
}
.gnc-confirm-title { font-size: 1.2rem; font-weight: 700; color: #1b1b1b; margin: 0 0 .35rem; }
.gnc-confirm-name {
    display: inline-block; max-width: 100%;
    margin: 0 0 .9rem; padding: .25rem .75rem;
    border-radius: 999px; background: #f4f1ea; color: #333;
    font-weight: 600; font-size: .9rem;
    overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.gnc-confirm-points {
    list-style: none; margin: 0 0 1rem; padding: .75rem .9rem;
    text-align: left; font-size: .875rem; color: #444;
    background: #fdf6f5; border: 1px solid #efc4c0; border-radius: 10px;
}
.gnc-confirm-points li { display: flex; gap: .55rem; align-items: flex-start; padding: .2rem 0; }
.gnc-confirm-points i { margin-top: .15rem; flex: none; }
.gnc-confirm-points .bad { color: #b3261e; }
.gnc-confirm-points .ok  { color: var(--gnc-green, #1F5E2C); }
.gnc-confirm-error { display: none; margin: 0 0 .9rem; font-size: .85rem; text-align: left; }
.gnc-confirm-error.show { display: block; }
.gnc-confirm-actions { display: flex; gap: .6rem; justify-content: center; flex-wrap: wrap; }
.gnc-confirm-actions .btn { min-width: 130px; }
.gnc-confirm-hide {
    display: block; margin: .85rem auto 0; padding: 0; border: 0; background: none;
    font-size: .85rem; color: var(--gnc-green, #1F5E2C); text-decoration: underline; cursor: pointer;
}
.gnc-confirm-hide:hover { color: #0b4228; }
body.gnc-confirm-open { overflow: hidden; }
@media (prefers-reduced-motion: reduce) {
    .gnc-confirm-overlay, .gnc-confirm-dialog { transition: none; }
}
</style>

<script>
(function () {
    var AJAX_URL = '/admin/action/page-content-handler.php';
    var csrf = <?= json_encode($csrfToken ?? '') ?>;
    var LOGO_MAX = 5 * 1024 * 1024;
    var LOGO_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];

    function apiPost(fields) {
        var fd = new FormData();
        fd.append('csrf_token', csrf);
        Object.keys(fields).forEach(function (k) { fd.append(k, fields[k]); });
        return fetch(AJAX_URL, { method: 'POST', body: fd }).then(function (res) {
            return res.json().catch(function () { return { success: false, error: 'Unexpected response from the server.' }; });
        });
    }

    /* ---------- Delete confirmation dialog ---------- */
    var DeleteModal = (function () {
        var overlay, dialog, nameEl, errEl, delBtnEl, cancelBtnEl, hideBtnEl, opts, lastFocus, busy = false;

        function build() {
            overlay = document.createElement('div');
            overlay.className = 'gnc-confirm-overlay';
            overlay.hidden = true;
            overlay.innerHTML =
                '<div class="gnc-confirm-dialog" role="alertdialog" aria-modal="true" aria-labelledby="gncConfirmTitle" aria-describedby="gncConfirmDesc">' +
                    '<div class="gnc-confirm-icon" aria-hidden="true"><i class="bi bi-trash3"></i></div>' +
                    '<h5 class="gnc-confirm-title" id="gncConfirmTitle">Delete this department?</h5>' +
                    '<div class="gnc-confirm-name"></div>' +
                    '<ul class="gnc-confirm-points" id="gncConfirmDesc">' +
                        '<li><i class="bi bi-x-circle-fill bad"></i><span>It will disappear from the website and <strong>this cannot be undone</strong>.</span></li>' +
                        '<li><i class="bi bi-check-circle-fill ok"></i><span>Its programs and announcements are <strong>not deleted</strong> — they just won\'t be listed under this department anymore.</span></li>' +
                    '</ul>' +
                    '<div class="gnc-confirm-error alert alert-danger py-2 mb-3" role="alert"></div>' +
                    '<div class="gnc-confirm-actions">' +
                        '<button type="button" class="btn btn-outline-secondary gnc-confirm-cancel">Cancel</button>' +
                        '<button type="button" class="btn btn-danger gnc-confirm-delete"><i class="bi bi-trash"></i> Delete department</button>' +
                    '</div>' +
                    '<button type="button" class="gnc-confirm-hide">Just hide it from the website instead</button>' +
                '</div>';
            document.body.appendChild(overlay);

            dialog      = overlay.querySelector('.gnc-confirm-dialog');
            nameEl      = overlay.querySelector('.gnc-confirm-name');
            errEl       = overlay.querySelector('.gnc-confirm-error');
            delBtnEl    = overlay.querySelector('.gnc-confirm-delete');
            cancelBtnEl = overlay.querySelector('.gnc-confirm-cancel');
            hideBtnEl   = overlay.querySelector('.gnc-confirm-hide');

            cancelBtnEl.addEventListener('click', close);
            overlay.addEventListener('mousedown', function (e) { if (e.target === overlay) close(); });
            hideBtnEl.addEventListener('click', function () {
                var cb = opts && opts.onHide;
                close();
                if (cb) cb();
            });
            delBtnEl.addEventListener('click', confirm);
            overlay.addEventListener('keydown', onKey);
        }

        function focusables() {
            return Array.prototype.filter.call(
                dialog.querySelectorAll('button'),
                function (b) { return !b.disabled && b.offsetParent !== null; }
            );
        }
        function onKey(e) {
            if (e.key === 'Escape') { e.preventDefault(); close(); return; }
            if (e.key !== 'Tab') return;
            var f = focusables();
            if (!f.length) return;
            var first = f[0], last = f[f.length - 1];
            if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
            else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
        }

        function setBusy(on) {
            busy = on;
            delBtnEl.disabled = on;
            cancelBtnEl.disabled = on;
            hideBtnEl.disabled = on;
            delBtnEl.innerHTML = on
                ? '<span class="spinner-border spinner-border-sm me-1" aria-hidden="true"></span> Deleting…'
                : '<i class="bi bi-trash"></i> Delete department';
        }

        function confirm() {
            if (busy || !opts) return;
            errEl.classList.remove('show');
            setBusy(true);
            Promise.resolve(opts.onConfirm()).then(function (res) {
                if (res && res.ok) {
                    setBusy(false);
                    close(true);
                } else {
                    setBusy(false);
                    errEl.textContent = (res && res.error) || 'Could not delete the department.';
                    errEl.classList.add('show');
                    delBtnEl.focus();
                }
            });
        }

        function open(o) {
            if (!overlay) build();
            opts = o;
            lastFocus = document.activeElement;
            nameEl.textContent = '“' + (o.name || 'this department') + '”';
            nameEl.title = o.name || '';
            hideBtnEl.style.display = o.canHide ? '' : 'none';
            errEl.classList.remove('show');
            setBusy(false);
            overlay.hidden = false;
            document.body.classList.add('gnc-confirm-open');
            void overlay.offsetWidth; // let the transition run
            overlay.classList.add('is-open');
            cancelBtnEl.focus(); // safest default for a destructive action
        }

        function close(deleted) {
            if (busy || !overlay || overlay.hidden) return;
            overlay.classList.remove('is-open');
            document.body.classList.remove('gnc-confirm-open');
            setTimeout(function () { overlay.hidden = true; }, 200);
            if (!deleted && lastFocus && lastFocus.focus) lastFocus.focus();
        }

        return { open: open };
    })();

    function showAlert(el, msg, isError) {
        el.className = 'alert mt-3 mb-0 ' + (isError ? 'alert-danger' : 'alert-success');
        el.textContent = msg;
        if (!isError) setTimeout(function () { el.classList.add('d-none'); }, 5000);
    }
    function hideAlert(el) { el.classList.add('d-none'); }

    // Make two text values comparable. Browsers always report a textarea's value with
    // "\n" line breaks, but text that went through a form post comes back from the
    // server with "\r\n", and the server also trims it. Without this, a saved
    // multi-line field (like "About this department") looked permanently "unsaved".
    function norm(v) {
        return String(v == null ? '' : v).replace(/\r\n?/g, '\n').trim();
    }

    // ── Create form ──
    var createCard  = document.getElementById('dept-create-card');
    var createAlert = document.getElementById('create-alert');
    var addBtn      = document.getElementById('btn-add-department');

    if (addBtn) {
    addBtn.addEventListener('click', function () {
        createCard.style.display = '';
        addBtn.disabled = true;
        document.getElementById('create-name').focus();
    });
    document.getElementById('btn-create-cancel').addEventListener('click', function () {
        createCard.style.display = 'none';
        addBtn.disabled = false;
        document.getElementById('create-name').value = '';
        document.getElementById('create-description').value = '';
        hideAlert(createAlert);
    });
    document.getElementById('btn-create-save').addEventListener('click', function () {
        var name = document.getElementById('create-name').value.trim();
        if (name === '') { showAlert(createAlert, 'Please enter a department name.', true); return; }

        var btn = this, original = btn.textContent;
        btn.disabled = true; btn.textContent = 'Creating…';

        apiPost({
            action: 'save_department_details',
            dept_id: '0',
            dept_name: name,
            description: document.getElementById('create-description').value,
            about_text: '', facebook_url: '', office_label: '', theme_color: ''
        }).then(function (data) {
            if (data.success) {
                var newId = data.department && (data.department.dept_id || data.department.id);
                window.__deptLeaving = true;
                if (newId) window.location.hash = 'dept-' + newId;
                window.location.reload(); // show the new department card
            } else {
                showAlert(createAlert, data.error || 'Could not create the department.', true);
                btn.disabled = false; btn.textContent = original;
            }
        }).catch(function () {
            showAlert(createAlert, 'Network error. Please try again.', true);
            btn.disabled = false; btn.textContent = original;
        });
    });

    }

    // ── One card per department ──
    document.querySelectorAll('.dept-media-card').forEach(function (card) {
        var current = JSON.parse(card.dataset.current);
        var $ = function (sel) { return card.querySelector(sel); };

        /* ---------- Details ---------- */
        var dEls = {
            dept_name:    $('.details-name-input'),
            description:  $('.details-description-input'),
            about_text:   $('.details-about-input'),
            facebook_url: $('.details-facebook-input'),
            office_label: $('.details-office-input'),
            theme_color:  $('.details-theme-input')
        };
        var dSave = $('.btn-details-save'), dReset = $('.btn-details-reset'), dAlert = $('.details-alert');
        var titleText = $('.dept-title-text'), viewLink = $('.dept-view-link');

        // The server calls the name `name`; the form calls it `dept_name`. Accept both.
        function savedVal(k) {
            if (k === 'dept_name') return current.dept_name || current.name || '';
            return current[k] || '';
        }
        function fillDetails() {
            Object.keys(dEls).forEach(function (k) { dEls[k].value = savedVal(k); });
            if (viewLink && card.dataset.publicUrl) viewLink.href = card.dataset.publicUrl;
            if (typeof syncPicker === 'function') syncPicker();
        }
        function detailsDirty() {
            return Object.keys(dEls).some(function (k) { return norm(dEls[k].value) !== norm(savedVal(k)); });
        }
        function renderDetails() {
            dSave.disabled = !detailsDirty();
            dReset.disabled = !detailsDirty();
            renderPreview();
        }

        /* ---------- Live preview of the list card ---------- */
        var pvCard = $('.dpv-card'), pvTitle = $('.dpv-title'), pvDesc = $('.dpv-desc'),
            pvImg = $('.dpv-logo-img'), pvPh = $('.dpv-logo-ph');
        // Same rule as the website: the first two sentences of "About", or the short description when About is empty.
        function previewText() {
            var src = norm(dEls.about_text.value) || norm(dEls.description.value);
            var text = src.replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim();
            if (!text) return '';
            var max = 160, trimmed = false;
            var sentences = text.replace(/([.!?])\s+/g, '$1\u0001').split('\u0001');
            if (sentences.length > 2) { text = sentences.slice(0, 2).join(' '); trimmed = true; }
            if (text.length > max) {
                var cut = text.slice(0, max), sp = cut.lastIndexOf(' ');
                if (sp > max * 0.6) cut = cut.slice(0, sp);
                return cut.replace(/[\s.,;:\-]+$/, '') + '...';
            }
            return trimmed ? text.replace(/\.+$/, '') + '...' : text;
        }
        function renderPreview() {
            if (!pvCard) return;
            pvTitle.textContent = dEls.dept_name.value.trim() || 'Department name';
            pvDesc.textContent = previewText() || 'The first sentences of “About this department” appear here.';
            var col = dEls.theme_color.value.trim();
            pvCard.style.setProperty('--accent', /^#[0-9a-fA-F]{6}$/.test(col) ? col : '#14609A');
            var url = logoPreviewUrl || (!logoPending.remove && current.logo_url) || null;
            if (url) { pvImg.src = url; pvImg.hidden = false; pvPh.hidden = true; }
            else     { pvImg.removeAttribute('src'); pvImg.hidden = true; pvPh.hidden = false; }
        }
        Object.keys(dEls).forEach(function (k) {
            dEls[k].addEventListener('input', function () { hideAlert(dAlert); renderDetails(); });
        });
        dReset.addEventListener('click', function () { fillDetails(); hideAlert(dAlert); renderDetails(); });
        dSave.addEventListener('click', function () {
            if (dEls.dept_name.value.trim() === '') { showAlert(dAlert, 'Please enter a department name.', true); return; }

            var btn = this, original = btn.textContent;
            btn.disabled = true; dReset.disabled = true; btn.textContent = 'Saving…';

            var fields = { action: 'save_department_details', dept_id: current.dept_id };
            Object.keys(dEls).forEach(function (k) { fields[k] = dEls[k].value; });

            apiPost(fields).then(function (data) {
                if (data.success) {
                    // Keep what was typed, then let the server's cleaned-up values win.
                    current = Object.assign({}, current, fields, data.department || {});
                    current.dept_name = (data.department && (data.department.dept_name || data.department.name)) || fields.dept_name;
                    current.name = current.dept_name;
                    fillDetails();
                    titleText.textContent = current.dept_name || '';
                    renderDetails();
                    showAlert(dAlert, 'Saved! The website now shows your changes.', false);
                } else {
                    showAlert(dAlert, data.error || 'Could not save.', true);
                    renderDetails(); // re-enable, keep what was typed
                }
            }).catch(function () {
                showAlert(dAlert, 'Network error. Please try again.', true);
                renderDetails();
            }).finally(function () { btn.textContent = original; });
        });

        /* ---------- Logo ---------- */
        var logoPending = { file: null, remove: false };
        var logoPreviewUrl = null;
        var logoImg = $('.dept-logo-img'), logoPh = $('.dept-logo-placeholder');
        var logoName = $('.dept-logo-name'), logoHint = $('.dept-logo-hint');
        var pickLogo = $('.btn-pick-logo'), rmLogo = $('.btn-remove-logo'), logoInput = $('.dept-logo-input');
        var logoSave = $('.btn-logo-save'), logoReset = $('.btn-logo-reset'), logoAlert = $('.logo-alert');

        function revokeLogoPreview() {
            if (logoPreviewUrl) { URL.revokeObjectURL(logoPreviewUrl); logoPreviewUrl = null; }
        }
        function renderLogo() {
            var showUrl = null, name = 'No logo', hint = 'JPG, PNG, GIF, WEBP or SVG · max 5 MB';
            if (logoPending.file) {
                revokeLogoPreview();
                logoPreviewUrl = URL.createObjectURL(logoPending.file);
                showUrl = logoPreviewUrl; name = logoPending.file.name; hint = 'New logo — not saved yet';
            } else if (logoPending.remove) {
                hint = 'Will be removed when you save';
            } else if (current.logo_url) {
                showUrl = current.logo_url; name = current.logo_name || 'Current logo'; hint = 'Current logo';
            }
            if (showUrl) { logoImg.src = showUrl; logoImg.hidden = false; logoPh.hidden = true; }
            else         { logoImg.removeAttribute('src'); logoImg.hidden = true; logoPh.hidden = false; }
            logoName.textContent = name; logoName.title = name; logoHint.textContent = hint;
            pickLogo.textContent = showUrl ? 'Replace Logo' : 'Upload Logo';
            rmLogo.disabled = !showUrl;

            var dirty = !!(logoPending.file || logoPending.remove);
            logoSave.disabled = !dirty; logoReset.disabled = !dirty;
            if (typeof renderSummaryLogo === 'function') renderSummaryLogo();
            if (typeof renderPreview === 'function') renderPreview();
        }
        pickLogo.addEventListener('click', function () { logoInput.click(); });
        logoInput.addEventListener('change', function () {
            var f = logoInput.files[0]; logoInput.value = '';
            if (!f) return;
            var isSvg = /\.svg$/i.test(f.name);
            if (LOGO_TYPES.indexOf(f.type) === -1 && !isSvg) { showAlert(logoAlert, 'Invalid image type. Allowed: JPG, PNG, GIF, WEBP, SVG.', true); return; }
            if (f.size > LOGO_MAX) { showAlert(logoAlert, 'The logo is too large. Maximum size is 5 MB.', true); return; }
            hideAlert(logoAlert); logoPending = { file: f, remove: false }; renderLogo();
        });
        rmLogo.addEventListener('click', function () {
            hideAlert(logoAlert); logoPending = { file: null, remove: !!current.logo_url }; renderLogo();
        });
        logoReset.addEventListener('click', function () {
            logoPending = { file: null, remove: false }; hideAlert(logoAlert); renderLogo();
        });
        logoSave.addEventListener('click', function () {
            var fd = new FormData();
            fd.append('action', 'save_department_media');
            fd.append('csrf_token', csrf);
            fd.append('dept_id', current.dept_id);
            if (logoPending.file) fd.append('logo', logoPending.file);
            if (logoPending.remove) fd.append('remove_logo', '1');

            var btn = this, original = btn.textContent;
            btn.disabled = true; logoReset.disabled = true; btn.textContent = 'Saving…';

            fetch(AJAX_URL, { method: 'POST', body: fd }).then(function (res) {
                return res.json().catch(function () { return { success: false, error: 'Unexpected response from the server.' }; });
            }).then(function (data) {
                if (data.success) {
                    current = data.department;
                    logoPending = { file: null, remove: false };
                    revokeLogoPreview();
                    renderLogo();
                    showAlert(logoAlert, 'Saved. The new logo is now live on every page that shows this department.', false);
                } else {
                    showAlert(logoAlert, data.error || 'Could not save.', true);
                    renderLogo();
                }
            }).catch(function () {
                showAlert(logoAlert, 'Network error. Please try again.', true);
                renderLogo();
            }).finally(function () { btn.textContent = original; });
        });

        /* ---------- Head of Office ---------- */
        var deanPending = { file: null, remove: false };
        var deanPreviewUrl = null;
        var photoImg = $('.dean-photo-img'), photoPh = $('.dean-photo-placeholder');
        var photoName = $('.dean-photo-name'), photoHint = $('.dean-photo-hint');
        var pickPhoto = $('.btn-pick-dean-photo'), rmPhoto = $('.btn-remove-dean-photo'), photoInput = $('.dean-photo-input');
        var deanEls = {
            'dean_name':   $('.dean-name-input'), 'dean_title':  $('.dean-title-input'),
            'dean_bio':    $('.dean-bio-input'),  'dean_phone':  $('.dean-phone-input'),
            'dean_email':  $('.dean-email-input'),'dean_office': $('.dean-office-input'),
            'dean_hours':  $('.dean-hours-input')
        };
        var deanSave = $('.btn-dean-save'), deanReset = $('.btn-dean-reset'), deanAlert = $('.dean-alert');

        function revokeDeanPreview() {
            if (deanPreviewUrl) { URL.revokeObjectURL(deanPreviewUrl); deanPreviewUrl = null; }
        }
        function fillDean() {
            Object.keys(deanEls).forEach(function (k) { deanEls[k].value = current[k] || ''; });
        }
        function deanDirty() {
            var textDirty = Object.keys(deanEls).some(function (k) { return norm(deanEls[k].value) !== norm(current[k]); });
            return !!(textDirty || deanPending.file || deanPending.remove);
        }
        function renderDean() {
            var showUrl = null, pName = 'No photo', pHint = 'JPG, PNG or WEBP · max 5 MB';
            if (deanPending.file) {
                revokeDeanPreview();
                deanPreviewUrl = URL.createObjectURL(deanPending.file);
                showUrl = deanPreviewUrl; pName = deanPending.file.name; pHint = 'New photo — not saved yet';
            } else if (deanPending.remove) {
                pHint = 'Will be removed when you save';
            } else if (current.dean_photo_url) {
                showUrl = current.dean_photo_url; pName = current.dean_photo_name || 'Current photo'; pHint = 'Current photo';
            }
            if (showUrl) { photoImg.src = showUrl; photoImg.hidden = false; photoPh.hidden = true; }
            else         { photoImg.removeAttribute('src'); photoImg.hidden = true; photoPh.hidden = false; }
            photoName.textContent = pName; photoName.title = pName; photoHint.textContent = pHint;
            pickPhoto.textContent = showUrl ? 'Replace Photo' : 'Upload Photo';
            rmPhoto.disabled = !showUrl;

            deanSave.disabled = !deanDirty();
            deanReset.disabled = !deanDirty();
        }
        pickPhoto.addEventListener('click', function () { photoInput.click(); });
        photoInput.addEventListener('change', function () {
            var f = photoInput.files[0]; photoInput.value = '';
            if (!f) return;
            if (['image/jpeg', 'image/png', 'image/webp'].indexOf(f.type) === -1) {
                showAlert(deanAlert, 'Invalid image type. Allowed: JPG, PNG, WEBP.', true); return;
            }
            if (f.size > LOGO_MAX) { showAlert(deanAlert, 'The photo is too large. Maximum size is 5 MB.', true); return; }
            hideAlert(deanAlert); deanPending = { file: f, remove: false }; renderDean();
        });
        rmPhoto.addEventListener('click', function () {
            hideAlert(deanAlert); deanPending = { file: null, remove: !!current.dean_photo_url }; renderDean();
        });
        Object.keys(deanEls).forEach(function (k) {
            deanEls[k].addEventListener('input', function () { hideAlert(deanAlert); renderDean(); });
        });
        deanReset.addEventListener('click', function () {
            deanPending = { file: null, remove: false }; fillDean(); hideAlert(deanAlert); renderDean();
        });
        deanSave.addEventListener('click', function () {
            var fd = new FormData();
            fd.append('action', 'save_department_dean');
            fd.append('csrf_token', csrf);
            fd.append('dept_id', current.dept_id);
            Object.keys(deanEls).forEach(function (k) { fd.append(k, deanEls[k].value); });
            if (deanPending.file) fd.append('dean_photo', deanPending.file);
            if (deanPending.remove) fd.append('remove_photo', '1');

            var btn = this, original = btn.textContent;
            btn.disabled = true; deanReset.disabled = true; btn.textContent = 'Saving…';

            fetch(AJAX_URL, { method: 'POST', body: fd }).then(function (res) {
                return res.json().catch(function () { return { success: false, error: 'Unexpected response from the server.' }; });
            }).then(function (data) {
                if (data.success) {
                    current = data.department;
                    deanPending = { file: null, remove: false };
                    revokeDeanPreview();
                    fillDean();
                    renderDean();
                    showAlert(deanAlert, 'Saved! The website now shows your changes.', false);
                } else {
                    showAlert(deanAlert, data.error || 'Could not save.', true);
                    renderDean();
                }
            }).catch(function () {
                showAlert(deanAlert, 'Network error. Please try again.', true);
                renderDean();
            }).finally(function () { btn.textContent = original; });
        });

        /* ---------- Programs Offered: card images ---------- */
        card.querySelectorAll('.prog-row').forEach(function (row) {
            var prog = JSON.parse(row.dataset.program);
            var pending = { file: null, remove: false }, previewUrl = null;
            var q = function (sel) { return row.querySelector(sel); };
            var img = q('.prog-img'), ph = q('.prog-placeholder'), nameEl = q('.prog-img-name');
            var pick = q('.btn-pick-prog-img'), rm = q('.btn-remove-prog-img'), input = q('.prog-img-input');
            var pSave = q('.btn-prog-save'), pReset = q('.btn-prog-reset'), pAlert = q('.prog-alert');
            var PROG_MAX = 5 * 1024 * 1024, PROG_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

            function revokePreview() {
                if (previewUrl) { URL.revokeObjectURL(previewUrl); previewUrl = null; }
            }
            function render() {
                var src = null, label = 'No image';
                if (pending.file) {
                    if (!previewUrl) previewUrl = URL.createObjectURL(pending.file);
                    src = previewUrl; label = pending.file.name + ' (not saved yet)';
                } else if (pending.remove) {
                    label = 'Image will be removed';
                } else if (prog.image_url) {
                    src = prog.image_url; label = prog.image_name || 'Current image';
                }
                if (src) { img.src = src; img.hidden = false; ph.hidden = true; }
                else { img.removeAttribute('src'); img.hidden = true; ph.hidden = false; }
                nameEl.textContent = label;
                var dirty = !!pending.file || pending.remove;
                pSave.disabled = !dirty; pReset.disabled = !dirty;
                rm.disabled = !pending.file && (pending.remove || !prog.image_url);
                pick.textContent = (prog.image_url || pending.file) ? 'Replace Image' : 'Upload Image';
            }

            pick.addEventListener('click', function () { input.click(); });
            input.addEventListener('change', function () {
                var f = input.files && input.files[0];
                input.value = '';
                if (!f) return;
                if (PROG_TYPES.indexOf(f.type) === -1) { showAlert(pAlert, 'Invalid image type. Allowed: JPG, PNG, WEBP.', true); return; }
                if (f.size > PROG_MAX) { showAlert(pAlert, 'That image is too large. Maximum size is 5 MB.', true); return; }
                hideAlert(pAlert); revokePreview(); pending = { file: f, remove: false }; render();
            });
            rm.addEventListener('click', function () {
                hideAlert(pAlert); revokePreview();
                pending = { file: null, remove: !!prog.image_url }; render();
            });
            pReset.addEventListener('click', function () {
                revokePreview(); pending = { file: null, remove: false }; hideAlert(pAlert); render();
            });
            pSave.addEventListener('click', function () {
                var fd = new FormData();
                fd.append('action', 'save_program');
                fd.append('csrf_token', csrf);
                fd.append('program_id', prog.program_id);
                fd.append('program_code', prog.program_code);
                fd.append('dept_id', prog.dept_id || current.dept_id);
                fd.append('description', prog.description || ''); // unchanged — save_program rewrites it
                if (pending.file) fd.append('image', pending.file);
                if (pending.remove) fd.append('remove_image', '1');

                var btn = this, original = btn.textContent;
                btn.disabled = true; pReset.disabled = true; btn.textContent = 'Saving…';

                fetch(AJAX_URL, { method: 'POST', body: fd }).then(function (res) {
                    return res.json().catch(function () { return { success: false, error: 'Unexpected response from the server.' }; });
                }).then(function (data) {
                    if (data.success) {
                        prog = data.program;
                        revokePreview(); pending = { file: null, remove: false };
                        render();
                        showAlert(pAlert, 'Saved! The program card on the website now shows this picture.', false);
                    } else {
                        showAlert(pAlert, data.error || 'Could not save.', true);
                        render();
                    }
                }).catch(function () {
                    showAlert(pAlert, 'Network error. Please try again.', true);
                    render();
                }).finally(function () { btn.textContent = original; });
            });

            render();
        });

        /* ---------- Friendlier UX: summary logo, tabs, colour picker, unsaved-changes tracking ---------- */
        var sumImg = $('.sum-logo-img'), sumPh = $('.sum-logo-ph');
        function renderSummaryLogo() {
            if (current.logo_url) { sumImg.src = current.logo_url; sumImg.hidden = false; sumPh.hidden = true; }
            else { sumImg.removeAttribute('src'); sumImg.hidden = true; sumPh.hidden = false; }
        }

        // Tabs
        var tabBtns = card.querySelectorAll('.dept-tab'), tabPanels = card.querySelectorAll('.dept-panel');
        function showTab(name) {
            tabBtns.forEach(function (t) {
                var on = t.dataset.tab === name;
                t.classList.toggle('active', on);
                t.setAttribute('aria-selected', on ? 'true' : 'false');
            });
            tabPanels.forEach(function (p) { p.hidden = p.dataset.panel !== name; });
        }
        tabBtns.forEach(function (t) { t.addEventListener('click', function () { showTab(t.dataset.tab); }); });
        showTab('text');

        // Colour picker <-> hex text box
        var themeText = dEls.theme_color, themePick = $('.details-theme-picker'), themeClear = $('.btn-theme-clear');
        var swatches = card.querySelectorAll('.dept-swatch[data-color]'), customSwatch = $('.dept-swatch-custom'), colorWarn = $('.dept-color-warn');
        function lightness(hex) {
            var n = parseInt(hex.slice(1), 16);
            var c = [(n >> 16) & 255, (n >> 8) & 255, n & 255].map(function (v) {
                v /= 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
            });
            return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2];
        }
        function syncPicker() {
            var v = themeText.value.trim(), ok = /^#[0-9a-fA-F]{6}$/.test(v), onPreset = false;
            if (ok) themePick.value = v;
            swatches.forEach(function (b) {
                var on = ok && b.dataset.color.toLowerCase() === v.toLowerCase();
                b.classList.toggle('is-on', on); if (on) onPreset = true;
            });
            customSwatch.classList.toggle('is-on', ok && !onPreset);
            colorWarn.hidden = !(ok && 1.05 / (lightness(v) + 0.05) < 3);
        }
        swatches.forEach(function (b) {
            b.addEventListener('click', function () {
                themeText.value = b.dataset.color;
                themeText.dispatchEvent(new Event('input', { bubbles: true }));
            });
        });
        themePick.addEventListener('input', function () {
            themeText.value = themePick.value.toUpperCase();
            themeText.dispatchEvent(new Event('input', { bubbles: true }));
        });
        themeText.addEventListener('input', syncPicker);
        themeClear.addEventListener('click', function () {
            themeText.value = '';
            themeText.dispatchEvent(new Event('input', { bubbles: true }));
        });

        // Unsaved-changes tracking: a section is "unsaved" while its Save button is enabled.
        var SAVE_SEL = '.btn-details-save, .btn-logo-save, .btn-prog-save, .btn-dean-save';
        var saveBar = $('.dept-savebar'), saveBarList = $('.dept-savebar-list'), unsavedPill = $('.dept-unsaved-pill');
        function enabledSaves() {
            return Array.prototype.filter.call(card.querySelectorAll(SAVE_SEL), function (b) { return !b.disabled; });
        }
        function refreshDirty() {
            var labels = [];
            tabBtns.forEach(function (t) {
                var panel = card.querySelector('.dept-panel[data-panel="' + t.dataset.tab + '"]');
                var dot = t.querySelector('.dept-tab-dot');
                var dirty = Array.prototype.some.call(panel.querySelectorAll(SAVE_SEL), function (b) { return !b.disabled; });
                if (dot) dot.hidden = !dirty;
                if (dirty) labels.push(t.dataset.label);
            });
            saveBar.hidden = labels.length === 0;
            unsavedPill.hidden = labels.length === 0;
            saveBarList.textContent = labels.join(', ');
            card.dataset.dirty = labels.length ? '1' : '';
        }
        new MutationObserver(refreshDirty).observe(card, { subtree: true, attributes: true, attributeFilter: ['disabled'] });
        $('.btn-save-all').addEventListener('click', function () {
            enabledSaves().forEach(function (b) { b.click(); });
        });
        setTimeout(refreshDirty, 0);

        /* ---------- Archive / Delete ---------- */
        var archBtn = $('.btn-dept-archive'), delBtn = $('.btn-dept-delete'), archBadge = $('.dept-archived-badge'), visText = $('.dept-vis-text');
        var moreAlert = $('.more-alert'), hiddenNote = $('.dept-hidden-note'), bodyTop = $('.dept-bodytop');
        $('.btn-note-show').addEventListener('click', function () { archBtn.click(); });

        function renderArchive() {
            archBtn.innerHTML = current.archived
                ? '<i class="bi bi-eye"></i> Show on website again'
                : '<i class="bi bi-eye-slash"></i> Hide from website';
            archBadge.classList.toggle('draft', !!current.archived);
            archBadge.classList.toggle('published', !current.archived);
            archBadge.textContent = current.archived ? 'Hidden from website' : 'Showing on website';
            hiddenNote.hidden = !current.archived;
            bodyTop.hidden = !!current.archived;   // a hidden department has no public page to open
            visText.textContent = current.archived
                ? 'This department is hidden — visitors can\'t see it. Nothing is lost; press the button to show it again.'
                : 'This department is showing on the website. Hide it if you don\'t want visitors to see it for now — you can show it again any time.';
        }
        archBtn.addEventListener('click', function () {
            var btn = this;
            btn.disabled = true;
            apiPost({
                action: 'archive_department',
                dept_id: current.dept_id,
                archived: current.archived ? '0' : '1'
            }).then(function (data) {
                if (data.success) {
                    current = data.department;
                    renderArchive();
                    btn.disabled = false;
                    showAlert(moreAlert, current.archived ? 'Done. This department is now hidden from the website.' : 'Done. This department is showing on the website again.', false);
                } else {
                    showAlert(moreAlert, data.error || 'Could not update the department.', true);
                    btn.disabled = false;
                }
            }).catch(function () {
                showAlert(moreAlert, 'Network error. Please try again.', true);
                btn.disabled = false;
            });
        });
        delBtn.addEventListener('click', function () {
            DeleteModal.open({
                name: current.name || 'this department',
                canHide: !current.archived,
                onHide: function () { archBtn.click(); },
                onConfirm: function () {
                    return apiPost({ action: 'delete_department', dept_id: current.dept_id }).then(function (data) {
                        if (data.success) {
                            card.style.transition = 'opacity .3s';
                            card.style.opacity = '0';
                            setTimeout(function () { card.remove(); }, 300);
                            return { ok: true };
                        }
                        return { ok: false, error: data.error || 'Could not delete the department.' };
                    }).catch(function () {
                        return { ok: false, error: 'Network error. Please try again.' };
                    });
                }
            });
        });

        /* ---------- Init ---------- */
        fillDetails(); renderDetails();
        renderLogo();
        fillDean(); renderDean();
        renderArchive();
    });

    // Open the department named in the address (#dept-12), e.g. right after creating one.
    var hm = /^#dept-(\d+)$/.exec(window.location.hash);
    if (hm) {
        var target = document.getElementById('dept-' + hm[1]);
        if (target) { target.open = true; target.scrollIntoView({ block: 'start' }); }
    }

    // Warn before leaving the page with edits that haven't been saved.
    window.addEventListener('beforeunload', function (e) {
        if (window.__deptLeaving || window.__veLeaving) return;
        if (document.querySelector('.dept-media-card[data-dirty="1"]')) {
            e.preventDefault();
            e.returnValue = '';
        }
    });
})();
</script>
