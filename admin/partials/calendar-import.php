<?php
if (!isset($calendarImportReady, $db, $currentUser)) { http_response_code(404); exit; }
$calendarImportSeries = $calendarImportReady ? $db->query("SELECT series_id,name,start_year,status FROM calendar_import_series WHERE status<>'deleted' ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC) : [];
?>
<div class="modal fade" id="calendarImportModal" tabindex="-1" aria-labelledby="calendarImportHeading" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="calendarImportHeading">Import Calendar of Activities</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div id="calendarImportError" class="alert alert-danger d-none" role="alert"></div>
                <?php if (!$calendarImportReady): ?>
                <div class="alert alert-warning">Calendar import needs a database update. Ask the developer to apply <code>database/calendar-import-migration.sql</code>.</div>
                <?php else: ?>
                <nav class="ci-steps" aria-label="Import progress">
                    <?php foreach ([1=>'Upload document',2=>'Review activities',3=>'Verify & approve',4=>'Confirm import'] as $number=>$label): ?>
                    <button type="button" data-ci-step="<?= $number ?>" class="ci-step<?= $number===1?' active':'' ?>"<?= $number===1?' aria-current="step"':'' ?>><span><?= $number ?></span><?= $label ?></button>
                    <?php endforeach; ?>
                </nav>
                <div id="calendarImportFileStatus" class="ci-file-status" role="status">Step 1 of 4 · Choose your calendar document to get started.</div>
                <div id="calendarImportSummary" class="ci-summary d-none" aria-live="polite"></div>
                <form id="calendarImportUpload" enctype="multipart/form-data">
                    <p>Upload a Word document with Date and Activity columns. Description, Time and Location (or Venue / Place) columns are optional. Each activity on a separate line becomes its own event. Use month names or YYYY-MM-DD dates; unclear dates will need correction.</p>
                    <div class="mb-3">
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
                    <div class="row">
                        <div class="col-sm-8 mb-3"><label for="calendarImportName" class="form-label">Calendar name</label><input class="form-control" id="calendarImportName" name="calendar_name" maxlength="200" required placeholder="School Calendar of Activities 2026–2027"></div>
                        <div class="col-sm-4 mb-3"><label for="calendarImportYear" class="form-label">Starting year</label><input class="form-control" id="calendarImportYear" type="number" name="start_year" min="1900" max="2050" value="<?= (int)date('Y') ?>" required><div class="form-text">Used for dates without a year.</div></div>
                    </div>
                    <div class="mb-3"><label class="form-label" for="calendarImportFile">Word document (.docx, up to 5 MB)</label><input class="form-control" id="calendarImportFile" name="calendar_file" type="file" accept=".docx,application/vnd.openxmlformats-officedocument.wordprocessingml.document" required></div>
                    <button class="btn btn-gnc-gold" type="submit" id="calendarImportExtract">Extract Activities</button>
                </form>
                <div id="calendarImportManagement" class="border-top pt-3 mt-4">
                    <h6>Manage imported calendars</h6>
                    <p class="small text-muted">Archive hides all events in the calendar while keeping their records. Delete permanently removes its imported events. Manually created events are preserved. Both actions require confirmation and any necessary approval.</p>
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
                </div>
                <div id="calendarManagePreview" class="d-none">
                    <h6 id="calendarManageHeading"></h6>
                    <p id="calendarManageExplanation"></p>
                    <div class="table-responsive"><table class="table"><thead><tr><th>Affected event</th><th>Starts</th><th>Ends</th><th>Location</th></tr></thead><tbody id="calendarManageRows"></tbody></table></div>
                    <label class="form-check"><input type="checkbox" class="form-check-input" id="calendarManageReviewed"><span class="form-check-label" id="calendarManageReviewLabel"></span></label>
                    <?php if (!hasPermission('publish_event')): ?><p class="small text-muted mt-2">This request will be sent to Super Admin for approval. Events stay unchanged until approved.</p><?php endif; ?>
                </div>
                <div id="calendarImportPreview" class="d-none">
                    <div class="ci-section-heading"><div><h6 id="calendarImportReviewHeading">Review extracted activities</h6><p id="calendarImportReviewHelp">Use Edit for details. Category changes can be made directly in the table.</p></div><span id="calendarImportCount" class="small text-muted"></span></div>
                    <div class="ci-toolbar">
                        <label class="ci-search"><span class="visually-hidden">Search event title</span><input id="ciSearch" class="form-control form-control-sm" type="search" placeholder="Search activities…"></label>
                        <label><span class="visually-hidden">Month</span><select id="ciMonth" class="form-select form-select-sm"><option value="">All months</option><?php for($m=1;$m<=12;$m++): ?><option value="<?= sprintf('%02d',$m) ?>"><?= date('F',mktime(0,0,0,$m,1,2026)) ?></option><?php endfor; ?></select></label>
                        <label><span class="visually-hidden">Year</span><select id="ciYear" class="form-select form-select-sm"><option value="">All years</option></select></label>
                        <label><span class="visually-hidden">Category</span><select id="ciCategory" class="form-select form-select-sm"><option value="">All categories</option><option value="uncategorized">Uncategorized</option></select></label>
                        <label><span class="visually-hidden">Review status</span><select id="ciStatus" class="form-select form-select-sm"><option value="">All statuses</option><option value="needs">Needs Review</option><option value="ready">Ready</option><option value="approved">Approved</option><option value="excluded">Excluded</option></select></label>
                        <label><span class="visually-hidden">Sort activities</span><select id="ciSort" class="form-select form-select-sm"><option value="date">Sort: Date</option><option value="title">Sort: Title</option><option value="category">Sort: Category</option></select></label>
                        <label class="form-check mb-0"><input id="ciWarningsOnly" class="form-check-input" type="checkbox"><span class="form-check-label small">Warnings only</span></label>
                        <button type="button" id="ciToggleFilters" class="btn btn-sm btn-outline-secondary d-md-none" aria-expanded="false">Filters</button>
                    </div>
                    <div class="ci-bulk">
                        <span id="ciSelectedCount" class="small fw-semibold">0 selected</span>
                        <button type="button" id="ciToggleBulk" class="btn btn-sm btn-outline-secondary d-md-none ms-auto" aria-expanded="false">Bulk actions</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="ciSelectMatching">Select all matching</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="ciClearSelection">Clear selection</button>
                        <button type="button" class="btn btn-sm btn-outline-success" id="ciApproveSelected">Approve selected</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="ciExcludeSelected">Exclude selected</button>
                        <label><span class="visually-hidden">Bulk category</span><select class="form-select form-select-sm" id="ciBulkCategory"><option value="">Choose category…</option></select></label>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="ciApplyCategory">Assign to selected</button>
                        <label><span class="visually-hidden">Department for selected activities</span><select class="form-select form-select-sm" id="ciBulkDepartment"><option value="">All departments</option></select></label>
                        <label><span class="visually-hidden">Program for selected activities</span><select class="form-select form-select-sm" id="ciBulkProgram"><option value="ALL">Everyone (all programs)</option></select></label>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="ciApplyAudience">Assign department/program</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary ms-auto" id="calendarImportSelectValid">Approve all without warnings</button>
                    </div>
                    <div id="ciFeedback" class="small py-2" role="status"></div>
                    <div class="table-responsive ci-table-wrap">
                        <table class="table align-middle ci-table">
                            <thead><tr><th><input type="checkbox" id="ciSelectVisible" class="form-check-input" aria-label="Select all activities on this page"></th><th>Activity</th><th>Date</th><th>Category</th><th>Status</th><th>Action</th></tr></thead>
                            <tbody id="calendarImportRows"></tbody>
                        </table>
                    </div>
                    <div class="ci-pagination"><label class="d-flex gap-2 align-items-center small">Rows per page <select id="ciPageSize" class="form-select form-select-sm"><?php foreach([10,20,50,100] as $size): ?><option<?= $size===20?' selected':'' ?>><?= $size ?></option><?php endforeach; ?></select></label><span id="ciPageInfo" class="small text-muted" aria-live="polite"></span><div class="d-flex gap-2"><button type="button" id="ciPrevious" class="btn btn-sm btn-outline-secondary">Previous</button><button type="button" id="ciNext" class="btn btn-sm btn-outline-secondary">Next</button></div></div>
                </div>
                <div id="ciFinal" class="d-none ci-final">
                    <i class="bi bi-calendar-check" aria-hidden="true"></i><h5>Ready to import?</h5><p id="ciFinalSummary"></p>
                    <div class="alert alert-info"><?php if (hasPermission('publish_event')): ?>Approved activities will be saved as drafts. Publish them separately through Events when ready.<?php else: ?>Approved activities will be submitted to Super Admin for publishing approval. Existing published content stays unchanged until approved.<?php endif; ?> Nothing is automatically published by this import.</div>
                    <p class="small text-muted">Only approved rows are included. Pending and excluded activities will not be saved. Approved removals will archive their existing events.</p>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <span id="ciFooterSummary" class="small text-muted me-auto"></span>
                <button class="btn btn-outline-secondary d-none" type="button" id="ciBack">Back</button>
                <button class="btn btn-outline-secondary" type="button" id="calendarImportCancel" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-gnc-gold d-none" type="button" id="ciContinue">Continue</button>
                <button class="btn btn-gnc-gold d-none" type="button" id="calendarImportConfirm">Confirm Import</button>
            </div>
        </div>
    </div>
</div>
<script src="/assets/js/calendar-import.js" defer></script>
<link rel="stylesheet" href="/assets/css/calendar-import.css">
