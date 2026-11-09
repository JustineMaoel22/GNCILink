<?php
if (!isset($calendarImportReady, $db, $currentUser)) { http_response_code(404); exit; }
$calendarImportSeries = $calendarImportReady ? $db->query("SELECT series_id,name,start_year,status FROM calendar_import_series WHERE status<>'deleted' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC) : [];
?>
<div class="modal fade" id="calendarImportModal" tabindex="-1" aria-labelledby="calendarImportHeading" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header ci-header">
                <div class="ci-header-main">
                    <h5 class="modal-title" id="calendarImportHeading">Import Calendar of Activities</h5>
                    <?php if ($calendarImportReady): ?>
                    <nav class="ci-steps" aria-label="Import progress">
                        <?php foreach ([1=>'Upload document',2=>'Review & approve',3=>'Confirm import'] as $number=>$label): ?>
                        <button type="button" data-ci-step="<?= $number ?>" class="ci-step<?= $number===1?' active':'' ?>"<?= $number===1?' aria-current="step"':'' ?>><span class="ci-step-num" aria-hidden="true"><?= $number ?></span><span class="ci-step-label"><?= $label ?></span></button>
                        <?php endforeach; ?>
                    </nav>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="calendarImportError" class="alert alert-danger d-none" role="alert"></div>
                <?php if (!$calendarImportReady): ?>
                <div class="alert alert-warning">Calendar import needs a database update. Ask the developer to apply <code>database/calendar-import-migration.sql</code>.</div>
                <?php else: ?>
                <div id="ciStepHint" class="ci-step-hint" role="status"></div>
                <div id="calendarImportFileStatus" class="ci-file-status" role="status">Choose your calendar document to get started.</div>
                <div id="calendarImportSummary" class="ci-summary d-none" role="group" aria-label="Filter activities by status"></div>
                <div id="ciSavedReviews" class="ci-card d-none">
                    <label class="form-label fw-semibold" for="ciSavedReview"><i class="bi bi-clock-history me-1" aria-hidden="true"></i>Resume a saved review</label>
                    <div class="d-flex flex-wrap gap-2">
                        <select id="ciSavedReview" class="form-select w-auto flex-grow-1" aria-describedby="ciSavedReviewHelp"></select>
                        <button type="button" class="btn btn-gnc-gold" id="ciResumeReview">Resume review</button>
                        <button type="button" class="btn btn-outline-danger" id="ciDiscardReview">Discard</button>
                    </div>
                    <p id="ciSavedReviewHelp" class="form-text mb-0">Private to your account. Saved reviews are available for 30 days after the last save. Saving does not publish events.</p>
                </div>
                <form id="calendarImportUpload" enctype="multipart/form-data" class="ci-card">
                    <p class="ci-lead">Upload a Word document (<code>.docx</code>) with your calendar in tables, lists, or paragraphs. Event details (title, date, time, venue, description) are grouped automatically, and anything unclear is flagged for you to review before importing.</p>
                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <label for="calendarImportSeries" class="form-label">Calendar to import</label>
                            <select class="form-select" name="series_id" id="calendarImportSeries">
                                <option value="">New yearly calendar</option>
                                <?php foreach ($calendarImportSeries as $series): ?>
                                <?php if ($series['status'] !== 'active') continue; ?>
                                <option value="<?= (int)$series['series_id'] ?>"><?= htmlspecialchars($series['name']) ?> (<?= (int)$series['start_year'] ?>) — revised document</option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text">For a revised document, select its original calendar. Existing event categories will be kept. Possible removals require your approval.</div>
                        </div>
                        <div class="col-sm-8"><label for="calendarImportName" class="form-label">Calendar name</label><input class="form-control" id="calendarImportName" name="calendar_name" maxlength="200" required placeholder="e.g. School Calendar of Activities 2026–2027"></div>
                        <div class="col-sm-4"><label for="calendarImportYear" class="form-label">Starting year</label><input class="form-control" id="calendarImportYear" type="number" name="start_year" min="1900" max="2050" value="<?= (int)date('Y') ?>" required><div class="form-text">Used for dates without a year.</div></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="calendarImportFile">Word document (.docx, up to 5 MB)</label>
                        <div class="ci-dropzone" id="calendarImportDropzone">
                            <i class="bi bi-cloud-arrow-up ci-dropzone-icon" aria-hidden="true"></i>
                            <div class="ci-dropzone-text">
                                <strong>Drag &amp; drop your Word calendar (.docx) here</strong> or <span class="ci-dropzone-browse">click to browse</span>
                            </div>
                            <div class="ci-dropzone-sub small text-muted" id="calendarImportFileMeta">Supports tables, bulleted lists, and paragraphs up to 5 MB</div>
                            <input class="form-control ci-dropzone-input" id="calendarImportFile" name="calendar_file" type="file" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required>
                        </div>
                    </div>
                    <button class="btn btn-gnc-gold btn-lg px-4" type="submit" id="calendarImportExtract"><i class="bi bi-magic me-1" aria-hidden="true"></i> Extract Activities</button>
                </form>
                <details id="calendarImportManagement" class="ci-card ci-manage">
                    <summary>Manage imported calendars</summary>
                    <p class="small text-muted mt-2">Archive hides all events in the calendar while keeping their records. Delete permanently removes its imported events. Manually created events are preserved. Both actions require confirmation and any necessary approval.</p>
                    <label for="calendarManageSeries" class="form-label">Imported calendar</label>
                    <select class="form-select mb-2" id="calendarManageSeries">
                        <option value="">Choose a calendar</option>
                        <?php foreach ($calendarImportSeries as $series): ?>
                        <option value="<?= (int)$series['series_id'] ?>" data-status="<?= htmlspecialchars($series['status']) ?>"><?= htmlspecialchars($series['name']) ?> (<?= (int)$series['start_year'] ?>)<?= $series['status']==='archived'?' — Archived':'' ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="d-flex gap-2 flex-wrap">
                        <button type="button" class="btn btn-outline-secondary" id="calendarManageArchive">Archive Calendar</button>
                        <?php if (hasPermission('delete_event')): ?><button type="button" class="btn btn-outline-danger" id="calendarManageDelete">Delete Calendar</button><?php endif; ?>
                    </div>
                </details>
                <div id="calendarManagePreview" class="d-none">
                    <h6 id="calendarManageHeading"></h6>
                    <p id="calendarManageExplanation"></p>
                    <div class="table-responsive"><table class="table"><thead><tr><th>Affected event</th><th>Starts</th><th>Ends</th><th>Location</th></tr></thead><tbody id="calendarManageRows"></tbody></table></div>
                    <label class="form-check"><input type="checkbox" class="form-check-input" id="calendarManageReviewed"><span class="form-check-label" id="calendarManageReviewLabel"></span></label>
                    <?php if (!hasPermission('publish_event')): ?><p class="small text-muted mt-2">This request will be sent to Super Admin for approval. Events stay unchanged until approved.</p><?php endif; ?>
                </div>
                <div id="calendarImportPreview" class="d-none">
                    <div class="ci-review-head">
                        <div class="ci-review-intro">
                            <h6 id="calendarImportReviewHeading">Review and approve activities</h6>
                            <p id="calendarImportReviewHelp"></p>
                        </div>
                        <div class="ci-progress-box">
                            <div class="d-flex justify-content-between small mb-1"><strong id="ciProgressLabel">Review progress</strong><span id="calendarImportCount" class="text-muted"></span></div>
                            <div class="progress ci-progress" role="progressbar" aria-labelledby="ciProgressLabel" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" id="ciProgress"><div class="progress-bar" id="ciProgressBar" style="width:0%"></div></div>
                            <div class="ci-progress-actions">
                                <button type="button" class="btn btn-sm btn-warning" id="ciReviewNext"><i class="bi bi-flag me-1" aria-hidden="true"></i><span>Review flagged</span></button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="calendarImportSelectValid">Approve all without warnings</button>
                            </div>
                        </div>
                    </div>
                    <div class="ci-toolbar">
                        <label class="ci-search"><span class="visually-hidden">Search event title</span><i class="bi bi-search" aria-hidden="true"></i><input id="ciSearch" class="form-control" type="search" placeholder="Search title, venue, department…"></label>
                        <label class="ci-sort"><span class="visually-hidden">Sort activities</span><select id="ciSort" class="form-select"><option value="date">Sort: Date</option><option value="title">Sort: Title</option><option value="category">Sort: Category</option></select></label>
                        <div class="dropdown ci-filters">
                            <button type="button" id="ciToggleFilters" class="btn btn-outline-secondary" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false"><i class="bi bi-funnel me-1" aria-hidden="true"></i><span id="ciFiltersLabel">Advanced filters</span></button>
                            <div class="dropdown-menu dropdown-menu-end ci-filter-menu">
                                <label><span>Month</span><select id="ciMonth" class="form-select form-select-sm"><option value="">All months</option><?php for($m=1;$m<=12;$m++): ?><option value="<?= sprintf('%02d',$m) ?>"><?= date('F',mktime(0,0,0,$m,1,2026)) ?></option><?php endfor; ?></select></label>
                                <label><span>Year</span><select id="ciYear" class="form-select form-select-sm"><option value="">All years</option></select></label>
                                <label><span>Department</span><select id="ciDepartment" class="form-select form-select-sm"><option value="">All departments</option></select></label>
                                <label><span>Category</span><select id="ciCategory" class="form-select form-select-sm"><option value="">All categories</option><option value="uncategorized">Uncategorized</option></select></label>
                                <label><span>Status</span><select id="ciStatus" class="form-select form-select-sm"><option value="">All statuses</option><option value="needs">Needs Review</option><option value="ready">Ready</option><option value="approved">Approved</option><option value="excluded">Excluded</option></select></label>
                                <label><span>Issue type</span><select id="ciIssue" class="form-select form-select-sm"><option value="">All issue types</option><option value="category">Category needs review</option><option value="date">Unclear date</option><option value="tba">TBA / unclear time or venue</option><option value="duplicate">Possible duplicate</option><option value="audience">Department or program</option><option value="other">Other review notes</option></select></label>
                                <label class="form-check mb-0"><input id="ciWarningsOnly" class="form-check-input" type="checkbox"><span class="form-check-label small">Warnings only</span></label>
                            </div>
                        </div>
                        <button type="button" id="ciResetFilters" class="btn btn-link btn-sm text-decoration-none d-none">Reset filters</button>
                    </div>
                    <div id="ciFeedback" class="ci-feedback" role="status" aria-live="polite"></div>
                    <div class="table-responsive ci-table-wrap">
                        <table class="table align-middle ci-table">
                            <thead><tr><th scope="col"><input type="checkbox" id="ciSelectVisible" class="form-check-input" aria-label="Select all activities on this page"></th><th scope="col">Event</th><th scope="col">Date &amp; Time</th><th scope="col">Category</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Actions</span></th></tr></thead>
                            <tbody id="calendarImportRows"></tbody>
                        </table>
                    </div>
                    <div class="ci-pagination"><label class="d-flex gap-2 align-items-center small">Rows per page <select id="ciPageSize" class="form-select form-select-sm"><?php foreach([10,20,50,100] as $size): ?><option<?= $size===20?' selected':'' ?>><?= $size ?></option><?php endforeach; ?></select></label><span id="ciPageInfo" class="small text-muted" aria-live="polite"></span><div class="d-flex gap-1"><button type="button" id="ciFirst" class="btn btn-sm btn-outline-secondary" aria-label="First page"><i class="bi bi-chevron-double-left" aria-hidden="true"></i></button><button type="button" id="ciPrevious" class="btn btn-sm btn-outline-secondary"><i class="bi bi-chevron-left" aria-hidden="true"></i> Previous</button><button type="button" id="ciNext" class="btn btn-sm btn-outline-secondary">Next <i class="bi bi-chevron-right" aria-hidden="true"></i></button><button type="button" id="ciLast" class="btn btn-sm btn-outline-secondary" aria-label="Last page"><i class="bi bi-chevron-double-right" aria-hidden="true"></i></button></div></div>
                    <div class="ci-bulk d-none" id="ciBulk" role="region" aria-label="Bulk actions for selected activities">
                        <span id="ciSelectedCount" class="ci-selected-count">0 selected</span>
                        <button type="button" class="btn btn-sm btn-link" id="ciSelectMatching">Select all matching</button>
                        <span class="ci-bulk-spacer"></span>
                        <button type="button" class="btn btn-sm btn-success" id="ciApproveSelected">Approve selected</button>
                        <label class="ci-bulk-category"><span class="visually-hidden">Bulk category</span><select class="form-select form-select-sm" id="ciBulkCategory"><option value="">Choose category…</option></select></label>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="ciApplyCategory">Assign category</button>
                        <div class="dropdown dropup">
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" id="ciBulkMore">More <i class="bi bi-chevron-up" aria-hidden="true"></i></button>
                            <div class="dropdown-menu dropdown-menu-end ci-bulk-menu">
                                <button type="button" class="dropdown-item" id="ciMergeSelected">Merge selected</button>
                                <button type="button" class="dropdown-item" id="ciExcludeSelected">Exclude selected</button>
                                <button type="button" class="dropdown-item text-danger" id="ciRemoveSelected">Remove selected</button>
                                <div class="dropdown-divider"></div>
                                <div class="ci-bulk-audience">
                                    <span class="small fw-semibold">Department / program</span>
                                    <label><span class="visually-hidden">Department for selected activities</span><select class="form-select form-select-sm" id="ciBulkDepartment"><option value="">All departments</option></select></label>
                                    <label><span class="visually-hidden">Program for selected activities</span><select class="form-select form-select-sm" id="ciBulkProgram"><option value="ALL">Everyone (all programs)</option></select></label>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" id="ciApplyAudience">Assign department/program</button>
                                </div>
                            </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-link text-secondary" id="ciClearSelection">Clear selection</button>
                    </div>
                </div>
                <div id="ciFinal" class="d-none ci-final">
                    <i class="bi bi-calendar-check" aria-hidden="true"></i><h5>Ready to import?</h5><p id="ciFinalSummary"></p>
                    <?php if (hasPermission('publish_event')): ?>
                    <div class="ci-publish-choice mb-3 p-3 rounded border bg-light">
                        <label class="form-label fw-semibold mb-2" for="ciImportStatus">How should approved events be saved?</label>
                        <select id="ciImportStatus" class="form-select">
                            <option value="published" selected>Publish live on website (visible immediately on the public calendar)</option>
                            <option value="draft">Save as drafts (review or publish later from Events)</option>
                        </select>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info">Approved activities will be submitted to Super Admin for publishing approval. Existing published content stays unchanged until approved.</div>
                    <?php endif; ?>
                    <p class="small text-muted">Only approved rows are included. Pending and excluded activities will not be saved. Approved removals will archive their existing events.</p>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer ci-footer">
                <div class="ci-footer-info me-auto">
                    <span id="ciFooterSummary" class="ci-footer-summary"></span>
                    <span id="ciReviewSaveStatus" class="small text-muted" role="status" aria-live="polite"></span>
                </div>
                <button class="btn btn-link text-secondary" type="button" id="calendarImportCancel" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-outline-secondary d-none" type="button" id="ciSaveReview"><i class="bi bi-save me-1" aria-hidden="true"></i>Save progress</button>
                <button class="btn btn-outline-secondary d-none" type="button" id="ciBack">Back</button>
                <button class="btn btn-gnc-gold btn-lg ci-primary d-none" type="button" id="ciContinue">Continue</button>
                <button class="btn btn-gnc-gold btn-lg ci-primary d-none" type="button" id="calendarImportConfirm">Confirm Import</button>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/calendar-import.js?v=<?= filemtime(__DIR__ . '/../../assets/js/calendar-import.js') ?>" defer></script>
<link rel="stylesheet" href="/assets/css/calendar-import.css?v=<?= filemtime(__DIR__ . '/../../assets/css/calendar-import.css') ?>">
