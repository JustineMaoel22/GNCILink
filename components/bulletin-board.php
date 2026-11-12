<?php
/**
 * =====================================================================
 * BULLETIN BOARD COMPONENT
 * =====================================================================
 * A read-only, auto-filtered preview of the platform's existing global
 * Announcements/Events system, scoped to whichever program page it is
 * included on.
 *
 * IT CONTAINS NO PROGRAM NAME ANYWHERE. The program is resolved at
 * runtime from CURRENT_PROGRAM, which each program page must define
 * before including this file. That is page configuration (every page
 * already has to say what program it is, the same way it has a page
 * title) — it is not the Bulletin Board hardcoding a filter.
 *
 * -------------------------------------------------------------------
 * USAGE — put this at the top of a program page, e.g. bsit.php:
 *
 *     <?php
 *     define('CURRENT_PROGRAM', 'BSIT');
 *     require_once __DIR__ . '/../../../../components/bulletin-board.php';
 *     ?>
 *     ... rest of the page markup ...
 *     <?php render_bulletin_board(); ?>
 *
 * That is the ONLY per-page code required. Add a new program page next
 * year and the Bulletin Board works immediately — nothing here changes.
 *
 * -------------------------------------------------------------------
 * DATABASE CONNECTION
 * Uses your project's existing getDB(): PDO singleton (defined in
 * config/config.php). If that file isn't already loaded by the page,
 * this component requires it itself — see the require_once below.
 *
 * -------------------------------------------------------------------
 * SCHEMA NOTES
 * - Announcement audiences live in `announcement_programs` and
 *   `announcement_departments`, with legacy `program`/`dept_id` fields
 *   used only when the corresponding audience list is empty. A legacy
 *   program value of 'ALL' can also represent multiple selected programs;
 *   it does not override those selections. Both audience dimensions must
 *   match. `category_id` is a topic tag, unrelated to audience filtering.
 * - Events follow the same audience precedence through `event_programs`
 *   and `event_departments`, retaining legacy fields and audience groups.
 * =====================================================================
 */

if (!defined('CURRENT_PROGRAM') || CURRENT_PROGRAM === '') {
    throw new Exception('Bulletin Board: CURRENT_PROGRAM must be defined before including bulletin-board.php.');
}

/**
 * Pull in getDB() from the project's real config file, in case the page
 * that included this component hasn't already loaded it. Adjust this
 * path if your config file lives somewhere other than /config/config.php
 * relative to the project root — this only needs to be correct once.
 */
if (!function_exists('getDB')) {
    require_once __DIR__ . '/../config/config.php';
}

/**
 * Diagnostic guard: if getDB() exists but doesn't return a real PDO
 * (e.g. a different getDB() elsewhere in the codebase shadows the one
 * shown to me, and behaves differently on this page), fail with a
 * message that names the exact file it came from — instead of a bare
 * "prepare() on null" further down.
 */
function gnc_bulletin_get_verified_pdo(): PDO
{
    if (!function_exists('getDB')) {
        throw new Exception('Bulletin Board: getDB() is not defined. Check the require_once path in bulletin-board.php.');
    }

    $pdo = getDB();

    if (!($pdo instanceof PDO)) {
        $ref = new ReflectionFunction('getDB');
        $definedIn = $ref->getFileName() . ':' . $ref->getStartLine();
        $type = is_object($pdo) ? get_class($pdo) : gettype($pdo);
        throw new Exception(
            "Bulletin Board: getDB() returned {$type} instead of a PDO instance. " .
            "The getDB() actually running is defined in {$definedIn} — " .
            "if that's not the config/config.php file with the DB_HOST/DB_NAME setup, " .
            "there's a second getDB() somewhere else in the codebase being loaded first " .
            "on this page. Find that file and either remove the duplicate or make sure " .
            "eng-lang.php loads the correct config.php before render_bulletin_board() runs."
        );
    }

    return $pdo;
}

// ---- Config -----------------------------------------------------------
const BULLETIN_MAX_ITEMS = 5; // upper bound requested ("3–5 most recent")
const BULLETIN_MIN_ITEMS = 3; // lower bound, informational — never padded

/**
 * Recent announcements scoped to the saved program and department
 * audiences. Explicit audience lists take precedence over legacy fields.
 */
function gnc_get_global_announcements(string $program, int $limit): array
{
    $pdo = gnc_bulletin_get_verified_pdo();

    $programDepartments = array_column($pdo->query('SELECT program_code,dept_id FROM programs')->fetchAll(PDO::FETCH_ASSOC), 'dept_id', 'program_code');
    $department = $programDepartments[$program] ?? null;
    $scope = $department === null ? [] : array_keys(array_filter($programDepartments, static fn($id) => (int)$id === (int)$department));
    $scopeParameters = [];
    foreach ($scope as $index => $code) $scopeParameters[':scope_' . $index] = $code;
    $scopeSql = $scopeParameters ? implode(',', array_keys($scopeParameters)) : 'NULL';

    $sql = "SELECT announcements.announcement_id, announcements.title, announcements.content, announcements.slug,
                   COALESCE(announcements.published_at, announcements.created_at) AS item_date
            FROM announcements
            LEFT JOIN (
                SELECT announcement_id, COUNT(*) AS target_count, MAX(COALESCE(dept_id = :department, 0)) AS matches_department
                FROM announcement_departments GROUP BY announcement_id
            ) audience_department ON audience_department.announcement_id = announcements.announcement_id
            LEFT JOIN (
                SELECT announcement_id, MAX(program_code = :prog_check) AS matches_program,
                    MAX(COALESCE(program_code IN ($scopeSql), 0)) AS has_scoped_programs
                FROM announcement_programs GROUP BY announcement_id
            ) audience_program ON audience_program.announcement_id = announcements.announcement_id
            WHERE announcements.status = 'published'
            AND COALESCE(
                audience_program.matches_program OR
                    (COALESCE(audience_department.target_count, 0) > 0 AND audience_program.has_scoped_programs = 0),
                announcements.program = :program OR announcements.program = 'ALL'
            )
            AND COALESCE(
                audience_department.matches_department,
                announcements.dept_id IS NULL OR announcements.dept_id = :legacy_department
            )
            ORDER BY COALESCE(announcements.published_at, announcements.created_at) DESC
            LIMIT :limit";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':program', $program, PDO::PARAM_STR);
    $stmt->bindValue(':prog_check', $program, PDO::PARAM_STR);
    $stmt->bindValue(':department', $department, $department === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    $stmt->bindValue(':legacy_department', $department, $department === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
    foreach ($scopeParameters as $parameter => $code) $stmt->bindValue($parameter, $code, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build a plain-text excerpt from the HTML `content` field.
    foreach ($rows as &$row) {
        $plain = trim(strip_tags($row['content'] ?? ''));
        $row['excerpt'] = mb_strlen($plain) > 140 ? mb_substr($plain, 0, 140) . '…' : $plain;
    }
    unset($row);

    return $rows;
}

/**
 * Upcoming and ongoing published events matching both saved audience dimensions.
 * Explicit lists override legacy ALL values, which may represent multiple choices.
 */
function gnc_get_global_events(string $program, int $limit): array
{
    $pdo = gnc_bulletin_get_verified_pdo();
    require_once __DIR__ . '/../includes/event-audience.php';
    $departmentScope = eventDepartmentsReady() ? " AND COALESCE(
        (SELECT MAX(COALESCE(ed.dept_id=bulletin_program.dept_id,0)) FROM event_departments ed WHERE ed.event_id=events.event_id),
        events.dept_id IS NULL OR events.dept_id=bulletin_program.dept_id
    )" : '';
    $groupScope=eventAudienceGroupsReady()?" AND (audience_group='all' OR EXISTS (SELECT 1 FROM departments gd WHERE gd.dept_id=bulletin_program.dept_id AND gd.department_type=events.audience_group AND (events.audience_group<>'college' OR (COALESCE(gd.slug,'')<>'graduate-school' AND gd.dept_name<>'Graduate School'))))":'';

    $sql = "SELECT event_id, title, location, start_date, end_date
            FROM events
            LEFT JOIN programs bulletin_program ON bulletin_program.program_code=:department_program
            WHERE status = 'published'
              AND (COALESCE(end_date, start_date) >= NOW()
                   OR (TIME(start_date)='00:00:00' AND TIME(COALESCE(end_date,start_date))='00:00:00'
                       AND DATE(COALESCE(end_date,start_date))>=CURRENT_DATE()))
              AND COALESCE(
                  (SELECT MAX(ep.program_code=:prog_evt_check) FROM event_programs ep WHERE ep.event_id=events.event_id),
                  events.program=:program OR events.program='ALL'
              ) {$departmentScope} {$groupScope}
            ORDER BY start_date ASC
            LIMIT :limit";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':program', $program, PDO::PARAM_STR);
    $stmt->bindValue(':prog_evt_check', $program, PDO::PARAM_STR);
    $stmt->bindValue(':department_program', $program, PDO::PARAM_STR);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Formats a DB date into the "Month D, Y" style used in the design.
 */
function bulletin_format_date(?string $rawDate): string
{
    if (!$rawDate) {
        return '';
    }
    $ts = strtotime($rawDate);
    return $ts ? date('F j, Y', $ts) : '';
}

/**
 * Formats an event date into the two-line badge shown in the design
 * (e.g. "JUN" / "12").
 */
function bulletin_format_event_badge(?string $rawDate): array
{
    if (!$rawDate) {
        return ['month' => '', 'day' => ''];
    }
    $ts = strtotime($rawDate);
    if (!$ts) {
        return ['month' => '', 'day' => ''];
    }
    return [
        'month' => strtoupper(date('M', $ts)),
        'day'   => date('j', $ts),
    ];
}

/**
 * Formats a start/end time range for an event card.
 */
function bulletin_format_time_range(?string $start, ?string $end): string
{
    if (!$start) {
        return '';
    }
    $startTs = strtotime($start);
    $startStr = $startTs ? date('g:i A', $startTs) : '';
    if (!$end) {
        return $startStr;
    }
    $endTs = strtotime($end);
    $endStr = $endTs ? date('g:i A', $endTs) : '';
    return $endStr ? "{$startStr} - {$endStr}" : $startStr;
}

/**
 * Renders the Bulletin Board section. Call this from the program page
 * wherever the board should appear on the page.
 */
function render_bulletin_board(): void
{
    $program = CURRENT_PROGRAM;

    $announcements = gnc_get_global_announcements($program, BULLETIN_MAX_ITEMS);
    $events        = gnc_get_global_events($program, BULLETIN_MAX_ITEMS);

    // Real routes, confirmed from news-and-announcement.php:
    // - the combined feed page takes ?type=announcement&program=CODE
    // - each item's detail page uses the shared announcement article route.
    $announcementsUrl = '/pages/news-and-announcement.php?type=announcement&program=' . urlencode($program);

    // Use the calendar's current query parameter so the program remains selected.
    $eventsUrl = '/index.php?program=' . urlencode($program) . '#events';

    $eventBadgeColors = ['badge-green', 'badge-gold', 'badge-maroon'];
    ?>

    <section class="bulletin-board py-5" id="bulletin-board" data-program="<?= htmlspecialchars($program, ENT_QUOTES) ?>">
        <div class="container">
            <h2 class="bulletin-heading">Bulletin Board</h2>
            <p class="bulletin-subtext">
                The latest announcements and events for this program, pulled automatically
                from the school-wide bulletin.
            </p>

            <div class="row g-4 mt-2">
                <!-- Recent Announcements -->
                <div class="col-lg-6">
                    <div class="bulletin-panel">
                        <div class="bulletin-panel-header bulletin-panel-header--solid">
                            <i class="bi bi-megaphone-fill bulletin-panel-icon"></i>
                            <span class="bulletin-panel-title">Recent Announcements</span>
                            <?php if (!empty($announcements)): ?>
                                <span class="bulletin-panel-count"><?= count($announcements) ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if (empty($announcements)): ?>
                            <div class="bulletin-empty">
                                <i class="bi bi-megaphone"></i>
                                <p>No recent announcements.</p>
                            </div>
                        <?php else: ?>
                            <div class="bulletin-list">
                                <?php foreach ($announcements as $item): ?>
                                    <a class="bulletin-item"
                                       href="/announcement-article.php?slug=<?= urlencode($item['slug']) ?>">
                                        <div class="bulletin-item-icon">
                                            <i class="bi bi-megaphone-fill"></i>
                                        </div>
                                        <div class="bulletin-item-body">
                                            <h6 class="bulletin-item-title"><?= htmlspecialchars($item['title']) ?></h6>
                                            <?php if (!empty($item['excerpt'])): ?>
                                                <p class="bulletin-item-excerpt"><?= htmlspecialchars($item['excerpt']) ?></p>
                                            <?php endif; ?>
                                            <span class="bulletin-item-date">
                                                <i class="bi bi-calendar3"></i>
                                                <?= htmlspecialchars(bulletin_format_date($item['item_date'])) ?>
                                            </span>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="bulletin-panel-footer">
                            <a href="<?= $announcementsUrl ?>" class="bulletin-view-all bulletin-view-all--green">
                                View All Announcements <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Upcoming Events -->
                <div class="col-lg-6">
                    <div class="bulletin-panel">
                        <div class="bulletin-panel-header bulletin-panel-header--solid">
                            <i class="bi bi-calendar-event-fill bulletin-panel-icon"></i>
                            <span class="bulletin-panel-title">Upcoming Events</span>
                            <?php if (!empty($events)): ?>
                                <span class="bulletin-panel-count"><?= count($events) ?></span>
                            <?php endif; ?>
                        </div>

                        <?php if (empty($events)): ?>
                            <div class="bulletin-empty">
                                <i class="bi bi-calendar-event"></i>
                                <p>No upcoming events.</p>
                            </div>
                        <?php else: ?>
                            <div class="bulletin-list">
                                <?php foreach ($events as $i => $event):
                                    $badge = bulletin_format_event_badge($event['start_date']);
                                    $colorClass = $eventBadgeColors[$i % count($eventBadgeColors)];
                                    $timeRange = bulletin_format_time_range($event['start_date'] ?? null, $event['end_date'] ?? null);
                                    ?>
                                    <a class="bulletin-item bulletin-event-item" href="<?= $eventsUrl ?>">
                                        <div class="bulletin-event-badge <?= $colorClass ?>">
                                            <span class="bulletin-event-month"><?= htmlspecialchars($badge['month']) ?></span>
                                            <span class="bulletin-event-day"><?= htmlspecialchars((string) $badge['day']) ?></span>
                                        </div>
                                        <div class="bulletin-item-body">
                                            <h6 class="bulletin-item-title bulletin-item-title--gold"><?= htmlspecialchars($event['title']) ?></h6>
                                            <?php if ($timeRange): ?>
                                                <span class="bulletin-item-meta">
                                                    <i class="bi bi-clock"></i> <?= htmlspecialchars($timeRange) ?>
                                                </span>
                                            <?php endif; ?>
                                            <?php if (!empty($event['location'])): ?>
                                                <span class="bulletin-item-meta">
                                                    <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($event['location']) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <div class="bulletin-panel-footer">
                            <a href="<?= $eventsUrl ?>" class="bulletin-view-all bulletin-view-all--gold">
                                View All Events <i class="bi bi-arrow-right"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php
}
