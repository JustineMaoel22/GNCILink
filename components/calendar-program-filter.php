<?php
$calendarFilterUrl = $calendarFilterUrl ?? static fn(array $filter) => '?' . http_build_query(array_merge($calendarFilterBaseQuery ?? [], $filter)) . '#events';
// Basic Education is listed by department; only higher education lists programs.
$calendarVisiblePrograms = array_values(array_filter($homePrograms, static function (array $program) use ($calendarDepartments): bool {
    $department = $calendarDepartments[$program['dept_slug'] ?? ''] ?? null;
    return $department !== null && ($department['department_type'] ?? '') !== 'basic-education';
}));
?>
<aside class="gnc-program-widget" id="calendarProgramWidget" data-filter-section="<?= htmlspecialchars($calendarFilterSection ?? 'events', ENT_QUOTES, 'UTF-8') ?>" aria-label="Department and program filter" hidden>
    <button type="button" class="gnc-program-widget-toggle" id="calendarProgramToggle" aria-expanded="false" aria-controls="calendarProgramPanel"><i class="bi bi-mortarboard-fill" aria-hidden="true"></i><span>Departments &amp; Programs</span><i class="bi bi-chevron-down" aria-hidden="true"></i></button>
    <div class="gnc-upcoming-card gnc-program-widget-panel" id="calendarProgramPanel" role="region" aria-labelledby="calendarProgramTitle" hidden>
        <div class="gnc-upcoming-header d-flex align-items-center justify-content-between">
            <span id="calendarProgramTitle"><i class="bi bi-funnel-fill" aria-hidden="true"></i> <?= htmlspecialchars($calendarFilterTitle ?? 'Calendar Categories') ?></span>
            <div class="d-flex align-items-center gap-3">
            <?php if (!empty($calendarDepartments)): ?>
                <span class="gnc-programs-count"><?= count($calendarDepartments) + count($calendarVisiblePrograms) ?></span>
            <?php endif; ?>
            <button type="button" class="gnc-program-widget-close" aria-label="Close department filter"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
            </div>
        </div>
        <div class="gnc-programs-list">
            <a href="<?= htmlspecialchars($calendarFilterUrl([])) ?>" class="gnc-calendar-filter-reset text-decoration-none<?= $calendarFilterLabel===null?' gnc-calendar-filter-all-active':'' ?>"><i class="bi bi-calendar3" aria-hidden="true"></i><span>All departments and programs</span></a>
            <h3 class="gnc-calendar-filter-heading">Departments</h3>
            <?php foreach ($calendarDepartments as $departmentKey=>$calendarDepartment): ?>
            <a href="<?= htmlspecialchars($calendarFilterUrl(['department'=>$departmentKey])) ?>" class="gnc-departments-list-item text-decoration-none<?= $selectedDepartment===$departmentKey?' gnc-calendar-department-active':'' ?>" data-calendar-department="<?= htmlspecialchars($departmentKey,ENT_QUOTES,'UTF-8') ?>"><i class="bi bi-buildings" aria-hidden="true"></i><span class="gnc-department-filter-name"><?= htmlspecialchars($calendarDepartment['dept_name']) ?></span></a>
            <?php endforeach; ?>
            <?php if (empty($calendarDepartments)): ?>
                <p class="text-muted px-3 py-4 mb-0">No departments available at this time.</p>
            <?php endif; ?>
            <h3 class="gnc-calendar-filter-heading">College &amp; Graduate School Programs</h3>
            <?php if (empty($calendarVisiblePrograms)): ?>
                <p class="text-muted px-3 py-4 mb-0">No programs available at this time.</p>
            <?php else: foreach ($calendarVisiblePrograms as $prog):
                $code = $prog['program_code'];
                $full = $programFullNames[$code] ?? null;
                $isActive = ($selectedProgram === $code);
                $progUrl = $calendarFilterUrl($isActive ? [] : ['program' => $code]);
            ?>
                <a href="<?= htmlspecialchars($progUrl) ?>" class="gnc-programs-list-item text-decoration-none<?= $isActive ? ' gnc-programs-list-item-active' : '' ?>">
                    <span class="gnc-programs-code"><?= htmlspecialchars($code) ?></span>
                    <?php if ($full): ?>
                        <span class="gnc-programs-list-fullname"><?= htmlspecialchars($full) ?></span>
                    <?php endif; ?>
                </a>
            <?php endforeach; endif; ?>
        </div>
    </div>
</aside>
