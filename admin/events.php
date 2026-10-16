<?php
/**
 * GNC Admin Panel – Events / Calendar
 * Lets admins view a month calendar, click a date to add an event,
 * and manage (edit/delete) existing events in a list below.
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../admin/admin-functions.php';

initSession();
requireLogin();

$currentUser = getCurrentUser();
$pageTitle   = 'Events & Calendar';

// A Super Admin approves content, so their events never wait for approval.
$isSuperAdmin       = (($_SESSION['user_role'] ?? '') === 'Super Admin');
$evtDefaultStatus   = $isSuperAdmin ? 'draft' : 'pending';

// ---------------------------------------------------------------
// CSRF helpers (self-contained, uses the csrf_tokens table)
// ---------------------------------------------------------------
function evt_generateCsrfToken(PDO $db, int $userId): string {
    $token  = bin2hex(random_bytes(32));
    $expiry = date('Y-m-d H:i:s', time() + 3600); // 1 hour
    $stmt = $db->prepare("INSERT INTO csrf_tokens (user_id, token, expires_at) VALUES (?, ?, ?)");
    $stmt->execute([$userId, $token, $expiry]);
    return $token;
}

function evt_verifyCsrfToken(PDO $db, int $userId, ?string $token): bool {
    if (empty($token)) return false;
    // Valid until expiry (not one-time-use) since the Add/Edit modal can fire
    // a "fetch_one" lookup followed by a "save" without a full page reload.
    $stmt = $db->prepare("SELECT token_id FROM csrf_tokens WHERE user_id = ? AND token = ? AND expires_at > NOW() LIMIT 1");
    $stmt->execute([$userId, $token]);
    return (bool)$stmt->fetch();
}

$db = getDB();

// ---------------------------------------------------------------
// Category colors
// Holiday = yellow, School Event = green, Emergency = red
// ---------------------------------------------------------------
function evt_categoryColor(?string $categoryColor, ?string $categoryName = null): string {
    if (!empty($categoryColor)) return $categoryColor;
    $name = strtolower(trim($categoryName ?? ''));
    if (strpos($name, 'holiday') !== false)   return '#EABA3B';
    if (strpos($name, 'emergency') !== false || strpos($name, 'suspension') !== false) return '#B3312D';
    if (strpos($name, 'school') !== false)    return '#1F5E2C';
    return '#6c757d'; // uncategorized fallback
}

// ---------------------------------------------------------------
// AJAX endpoint: create / update / delete events
// ---------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');

    $userId = $currentUser['user_id'] ?? 0;
    $action = $_POST['ajax_action'];

    if (!evt_verifyCsrfToken($db, $userId, $_POST['csrf_token'] ?? null)) {
        echo json_encode(['success' => false, 'message' => 'Invalid or expired security token. Please refresh the page and try again.']);
        exit;
    }

    try {
        if ($action === 'create' || $action === 'update') {
            $title       = trim($_POST['title'] ?? '');
            $location    = trim($_POST['location'] ?? '');
            // Date is required; time is optional. No time is stored as 00:00:00.
            $startD      = trim($_POST['start_date'] ?? '');
            $startT      = trim($_POST['start_time'] ?? '');
            $endD        = trim($_POST['end_date'] ?? '');
            $endT        = trim($_POST['end_time'] ?? '');
            $startDate   = $startD;
            $endDate     = null;
            $categoryId  = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
            $program     = normalizeProgramCategory($_POST['program'] ?? null);
            $status      = in_array($_POST['status'] ?? '', ['draft','pending','published','archived'])
                            ? $_POST['status'] : $evtDefaultStatus;
            // Super Admins can't send content to themselves for approval
            if ($isSuperAdmin && $status === 'pending') {
                $status = 'draft';
            }

            if ($title === '' || $startD === '') {
                echo json_encode(['success' => false, 'message' => 'Title and start date are required.']);
                exit;
            }

            // basic date/time sanity check
            $validDate = function ($d) { return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false; };
            $validTime = function ($t) { return $t === '' || strtotime('1970-01-01 ' . $t) !== false; };
            if (!$validDate($startD) || !$validTime($startT)
                || ($endD !== '' && !$validDate($endD)) || !$validTime($endT)) {
                echo json_encode(['success' => false, 'message' => 'Invalid date or time format.']);
                exit;
            }

            $startDate = $startD . ' ' . ($startT !== '' ? date('H:i:s', strtotime('1970-01-01 ' . $startT)) : '00:00:00');
            if ($endD !== '') {
                $endDate = $endD . ' ' . ($endT !== '' ? date('H:i:s', strtotime('1970-01-01 ' . $endT)) : '00:00:00');
            }

            if ($action === 'create') {
                $stmt = $db->prepare("
                    INSERT INTO events (category_id, program, user_id, title, location, start_date, end_date, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$categoryId, $program, $userId, $title, $location, $startDate, $endDate, $status]);
                $newId = $db->lastInsertId();

                if (function_exists('logActivity')) {
                    logActivity($userId, 'CREATE', "Created event: {$title}");
                }
                echo json_encode(['success' => true, 'message' => 'Event created.', 'event_id' => $newId]);
            } else {
                $eventId = (int)($_POST['event_id'] ?? 0);
                if (!$eventId) {
                    echo json_encode(['success' => false, 'message' => 'Missing event ID.']);
                    exit;
                }
                $stmt = $db->prepare("
                    UPDATE events
                    SET category_id = ?, program = ?, title = ?, location = ?,
                        start_date = ?, end_date = ?, status = ?
                    WHERE event_id = ?
                ");
                $stmt->execute([$categoryId, $program, $title, $location, $startDate, $endDate, $status, $eventId]);

                if (function_exists('logActivity')) {
                    logActivity($userId, 'UPDATE', "Updated event: {$title}");
                }
                echo json_encode(['success' => true, 'message' => 'Event updated.']);
            }
            exit;
        }

        if ($action === 'fetch_one') {
            $eventId = (int)($_POST['event_id'] ?? 0);
            if (!$eventId) {
                echo json_encode(['success' => false, 'message' => 'Missing event ID.']);
                exit;
            }
            $stmt = $db->prepare("
                SELECT event_id, category_id, program, title, location, start_date, end_date, status
                FROM events WHERE event_id = ?
            ");
            $stmt->execute([$eventId]);
            $ev = $stmt->fetch();
            if (!$ev) {
                echo json_encode(['success' => false, 'message' => 'Event not found.']);
                exit;
            }
            echo json_encode(['success' => true, 'event' => $ev]);
            exit;
        }

        if ($action === 'delete') {
            $eventId = (int)($_POST['event_id'] ?? 0);
            if (!$eventId) {
                echo json_encode(['success' => false, 'message' => 'Missing event ID.']);
                exit;
            }
            $stmt = $db->prepare("SELECT title FROM events WHERE event_id = ?");
            $stmt->execute([$eventId]);
            $ev = $stmt->fetch();

            $del = $db->prepare("DELETE FROM events WHERE event_id = ?");
            $del->execute([$eventId]);

            if ($ev && function_exists('logActivity')) {
                logActivity($userId, 'DELETE', "Deleted event: {$ev['title']}");
            }
            echo json_encode(['success' => true, 'message' => 'Event deleted.']);
            exit;
        }

        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
        exit;

    } catch (Exception $e) {
        error_log('Events AJAX error: ' . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again.']);
        exit;
    }
}

// ---------------------------------------------------------------
// GET: render the page
// ---------------------------------------------------------------
$month = isset($_GET['month']) ? max(1, min(12, (int)$_GET['month'])) : (int)date('n');
$year  = isset($_GET['year'])  ? (int)$_GET['year']                  : (int)date('Y');

$firstOfMonth = mktime(0, 0, 0, $month, 1, $year);
$daysInMonth  = (int)date('t', $firstOfMonth);
$startWeekday = (int)date('w', $firstOfMonth); // 0 = Sunday
$monthLabel   = date('F Y', $firstOfMonth);

$prevMonth = $month - 1; $prevYear = $year;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $month + 1; $nextYear = $year;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

// Fetch events for this month
try {
    $rangeStart = sprintf('%04d-%02d-01 00:00:00', $year, $month);
    $rangeEnd   = date('Y-m-d 23:59:59', mktime(0, 0, 0, $month, $daysInMonth, $year));

    $stmt = $db->prepare("
        SELECT e.event_id, e.title, e.location, e.start_date, e.end_date,
               e.status, e.category_id, e.program, c.category_name, c.category_color
        FROM events e
        LEFT JOIN categories c ON e.category_id = c.category_id
        WHERE e.start_date <= ?
          AND COALESCE(e.end_date, e.start_date) >= ?
        ORDER BY e.start_date ASC
    ");
    $stmt->execute([$rangeEnd, $rangeStart]);
    $monthEvents = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Get month events error: ' . $e->getMessage());
    $monthEvents = [];
}

// Group events by day number for quick lookup in the calendar grid.
// Multi-day events (start_date .. end_date) are placed on every day they
// span, not just their start day, so the end date is actually reflected
// on the calendar.
$eventsByDay = [];
foreach ($monthEvents as $ev) {
    $startTs = strtotime($ev['start_date']);
    $endTs   = !empty($ev['end_date']) ? strtotime($ev['end_date']) : $startTs;
    if ($endTs < $startTs) $endTs = $startTs; // guard against bad data

    $spanStart = max($startTs, $firstOfMonth);
    $spanEnd   = min($endTs, mktime(0, 0, 0, $month, $daysInMonth, $year));

    for ($ts = $spanStart; $ts <= $spanEnd; $ts = strtotime('+1 day', $ts)) {
        $day = (int)date('j', $ts);
        $eventsByDay[$day][] = $ev;
    }
}

// All events list (most recent first) for the management table below
try {
    $allEvents = $db->query("
        SELECT e.event_id, e.title, e.start_date, e.end_date, e.status, e.category_id, e.program,
               c.category_name, c.category_color,
               CONCAT(COALESCE(u.first_name,''),' ',COALESCE(u.last_name,'')) AS author
        FROM events e
        LEFT JOIN categories c ON e.category_id = c.category_id
        LEFT JOIN users u ON e.user_id = u.user_id
        ORDER BY e.start_date DESC
        LIMIT 50
    ")->fetchAll();
} catch (Exception $e) {
    error_log('Get all events error: ' . $e->getMessage());
    $allEvents = [];
}

// Only categories that make sense for an Event go in this dropdown.
// "Reminder" belongs to Announcements only, so it's excluded here —
// the category row itself isn't touched/deleted, just left out of this list.
try {
    $categories = $db->query("
        SELECT category_id, category_name, category_color
        FROM categories
        WHERE category_name NOT IN ('Reminder')
        ORDER BY category_name ASC
    ")->fetchAll();
} catch (Exception $e) {
    $categories = [];
}

$evtCsrfToken = evt_generateCsrfToken($db, $currentUser['user_id'] ?? 0);

// Plain-language status names for non-technical admins.
$evtStatusLabels = [
    'draft'     => 'Draft',
    'pending'   => 'Waiting for approval',
    'published' => 'Live on website',
    'archived'  => 'Hidden',
];
$evtStatusHelp = [
    'draft'     => 'Only admins can see it. Use this while you are still working on it.',
    'pending'   => 'Sent to an approver. It will go live once approved.',
    'published' => 'Visible to everyone on the public website.',
    'archived'  => 'Kept on record but hidden from the public website.',
];
$evtNow   = time();
$evtToday = strtotime('today');
$isCurrentMonth = ($month === (int)date('n') && $year === (int)date('Y'));

// Status counts for the filter chips
$evtCounts = ['all' => count($allEvents), 'draft' => 0, 'pending' => 0, 'published' => 0, 'archived' => 0];
foreach ($allEvents as $e) { if (isset($evtCounts[$e['status']])) $evtCounts[$e['status']]++; }

include __DIR__ . '/../components/header-admin.php';
?>

<div class="page-header">
    <div>
        <h1>Events &amp; Calendar</h1>
        <p>Plan school events, holidays and class suspensions. Click a date on the calendar to add something on that day.</p>
    </div>
    <button type="button" class="btn btn-success" id="addEventBtnTop">
        <i class="bi bi-plus-lg"></i> Add Event
    </button>
</div>

<div class="row g-3">
    <!-- Calendar -->
    <div class="col-lg-7">
        <div class="data-card h-100">
            <div class="data-card-header">
                <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="btn btn-sm btn-outline-secondary btn-action">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <span class="data-card-title mx-2"><?= htmlspecialchars($monthLabel) ?></span>
                <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="btn btn-sm btn-outline-secondary btn-action">
                    <i class="bi bi-chevron-right"></i>
                </a>
                <?php if (!$isCurrentMonth): ?>
                    <a href="?month=<?= date('n') ?>&year=<?= date('Y') ?>" class="btn btn-sm btn-outline-secondary ms-2">
                        <i class="bi bi-calendar-check"></i> Back to today
                    </a>
                <?php endif; ?>
                <button type="button" class="btn btn-sm btn-success ms-auto" id="addEventBtn">
                    <i class="bi bi-plus-lg"></i> Add Event
                </button>
            </div>
            <div class="evt-hint"><i class="bi bi-cursor"></i> Click a date to add an event &middot; click an event to edit it</div>

            <div class="evt-calendar-grid">
                <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dow): ?>
                    <div class="evt-cal-dow"><?= $dow ?></div>
                <?php endforeach; ?>

                <?php for ($i = 0; $i < $startWeekday; $i++): ?>
                    <div class="evt-cal-cell evt-cal-empty"></div>
                <?php endfor; ?>

                <?php for ($day = 1; $day <= $daysInMonth; $day++):
                    $isToday = ($day == (int)date('j') && $month == (int)date('n') && $year == (int)date('Y'));
                    $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
                    $dayEvents = $eventsByDay[$day] ?? [];
                ?>
                    <div class="evt-cal-cell<?= $isToday ? ' evt-cal-today' : '' ?>" data-date="<?= $dateStr ?>">
                        <span class="evt-cal-daynum"><?= $day ?></span>
                        <?php foreach (array_slice($dayEvents, 0, 2) as $ev): ?>
                            <div class="evt-cal-pill<?= ($ev['status'] ?? '') !== 'published' ? ' evt-pill-unpublished' : '' ?>"
                                 style="background-color: <?= evt_categoryColor($ev['category_color'] ?? null, $ev['category_name'] ?? null) ?>;"
                                 data-event-id="<?= $ev['event_id'] ?>"
                                 title="<?= htmlspecialchars($ev['title'] . ' — ' . ($ev['category_name'] ?? 'Uncategorized') . ' — ' . ($evtStatusLabels[$ev['status']] ?? $ev['status'])) ?>">
                                <?= htmlspecialchars(mb_strlen($ev['title']) > 16 ? mb_substr($ev['title'], 0, 16) . '…' : $ev['title']) ?>
                            </div>
                        <?php endforeach; ?>
                        <?php if (count($dayEvents) > 2): ?>
                            <div class="evt-cal-more">+<?= count($dayEvents) - 2 ?> more</div>
                        <?php endif; ?>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <!-- This month at a glance -->
    <div class="col-lg-5">
        <div class="data-card h-100">
            <div class="data-card-header">
                <span class="data-card-title"><i class="bi bi-calendar-event me-1"></i> Happening in <?= htmlspecialchars($monthLabel) ?></span>
            </div>
            <div class="evt-month-list">
                <?php if (empty($monthEvents)): ?>
                    <div class="evt-empty">
                        <i class="bi bi-calendar-plus"></i>
                        <p class="mb-2">Nothing scheduled in <?= htmlspecialchars($monthLabel) ?> yet.</p>
                        <button type="button" class="btn btn-sm btn-success js-add-event"><i class="bi bi-plus-lg"></i> Add an event</button>
                    </div>
                <?php else: foreach ($monthEvents as $ev):
                    $evStart = strtotime($ev['start_date']);
                    $evEnd   = !empty($ev['end_date']) ? strtotime($ev['end_date']) : $evStart;
                    $chipTs  = max($evStart, $firstOfMonth);
                    $multi   = date('Y-m-d', $evEnd) !== date('Y-m-d', $evStart);
                ?>
                    <div class="evt-month-item" data-event-id="<?= $ev['event_id'] ?>" role="button" tabindex="0">
                        <div class="evt-date-chip" style="background-color: <?= evt_categoryColor($ev['category_color'] ?? null, $ev['category_name'] ?? null) ?>;">
                            <span class="evt-date-chip-day"><?= date('j', $chipTs) ?></span>
                            <span class="evt-date-chip-dow"><?= date('D', $chipTs) ?></span>
                        </div>
                        <div class="evt-month-info">
                            <div class="evt-month-title"><?= htmlspecialchars($ev['title']) ?></div>
                            <div class="evt-month-meta">
                                <?= $multi ? htmlspecialchars(date('M j', $evStart) . ' – ' . date('M j', $evEnd)) : htmlspecialchars(date('l', $evStart)) ?>
                                <?php if (date('H:i:s', $evStart) !== '00:00:00'): ?> &middot; <?= date('g:i A', $evStart) ?><?php endif; ?>
                            </div>
                        </div>
                        <span class="status-badge <?= htmlspecialchars($ev['status']) ?>"><?= htmlspecialchars($evtStatusLabels[$ev['status']] ?? ucfirst($ev['status'])) ?></span>
                    </div>
                <?php endforeach; endif; ?>
            </div>

            <details class="evt-guide">
                <summary><i class="bi bi-question-circle me-1"></i> What do the colors and labels mean?</summary>
                <div class="evt-guide-body">
                    <div class="evt-guide-title">Event colors (category)</div>
                    <?php foreach ($categories as $cat): ?>
                        <div><span class="evt-cat-dot" style="background: <?= evt_categoryColor($cat['category_color'] ?? null, $cat['category_name'] ?? null) ?>;"></span> <?= htmlspecialchars($cat['category_name']) ?></div>
                    <?php endforeach; ?>
                    <div class="evt-guide-title mt-3">Who can see it (status)</div>
                    <?php foreach ($evtStatusLabels as $key => $label): ?>
                        <div class="mb-1"><span class="status-badge <?= $key ?>"><?= $label ?></span> <span class="text-muted"><?= htmlspecialchars($evtStatusHelp[$key]) ?></span></div>
                    <?php endforeach; ?>
                    <div class="text-muted mt-2" style="font-size:.8rem"><span class="evt-pill-sample"></span> Faded events with a dashed outline are not live on the website yet.</div>
                </div>
            </details>
        </div>
    </div>
</div>

<!-- Events list / management table -->
<div class="row g-3 mt-1">
    <div class="col-12">
        <div class="data-card">
            <div class="data-card-header flex-wrap gap-2">
                <span class="data-card-title"><i class="bi bi-list-ul me-1"></i> All Events</span>
                <div class="evt-search ms-auto">
                    <i class="bi bi-search"></i>
                    <input type="search" id="evtSearch" class="form-control form-control-sm" placeholder="Search by event name…" autocomplete="off">
                </div>
            </div>
            <div class="evt-filters">
                <button type="button" class="evt-chip active" data-filter="all">All <span><?= $evtCounts['all'] ?></span></button>
                <button type="button" class="evt-chip" data-filter="published">Live on website <span><?= $evtCounts['published'] ?></span></button>
                <button type="button" class="evt-chip" data-filter="pending">Waiting for approval <span><?= $evtCounts['pending'] ?></span></button>
                <button type="button" class="evt-chip" data-filter="draft">Drafts <span><?= $evtCounts['draft'] ?></span></button>
                <button type="button" class="evt-chip" data-filter="archived">Hidden <span><?= $evtCounts['archived'] ?></span></button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0 evt-table">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>When</th>
                            <th>Category</th>
                            <th>For</th>
                            <th>Status</th>
                            <th style="width:170px">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="eventsTableBody">
                        <?php if (empty($allEvents)): ?>
                        <tr><td colspan="6"><div class="evt-empty">
                            <i class="bi bi-calendar-plus"></i>
                            <p class="mb-2">You haven't added any events yet.</p>
                            <button type="button" class="btn btn-sm btn-success js-add-event"><i class="bi bi-plus-lg"></i> Add your first event</button>
                        </div></td></tr>
                        <?php else: foreach ($allEvents as $ev):
                            $evStart = strtotime($ev['start_date']);
                            $evEnd   = !empty($ev['end_date']) ? strtotime($ev['end_date']) : $evStart;
                            if ($evEnd < $evStart) $evEnd = $evStart;
                            $isPast    = $evEnd < $evtToday;
                            $isNow     = !$isPast && $evStart <= $evtNow && strtotime('tomorrow', $evEnd) > $evtNow && date('Y-m-d', $evStart) <= date('Y-m-d');
                            $daysAway  = (int)floor(($evStart - $evtToday) / 86400);
                            $startLabel = date('M j, Y', $evStart);
                            if (date('H:i:s', $evStart) !== '00:00:00') $startLabel .= ' · ' . date('g:i A', $evStart);
                            $multi = date('Y-m-d', $evEnd) !== date('Y-m-d', $evStart);
                            $authorName = trim($ev['author']);
                        ?>
                        <tr data-event-id="<?= $ev['event_id'] ?>"
                            data-status="<?= htmlspecialchars($ev['status']) ?>"
                            data-title="<?= htmlspecialchars($ev['title']) ?>"
                            class="evt-row<?= $isPast ? ' evt-past' : '' ?>">
                            <td class="evt-title-cell" data-label="Event">
                                <div class="evt-row-title"><?= htmlspecialchars($ev['title']) ?></div>
                                <?php if ($authorName !== ''): ?><div class="evt-row-sub">Added by <?= htmlspecialchars($authorName) ?></div><?php endif; ?>
                            </td>
                            <td data-label="When" style="font-size:.85rem">
                                <div><?= htmlspecialchars($startLabel) ?><?= $multi ? ' <span class="text-muted">to</span> ' . htmlspecialchars(date('M j, Y', $evEnd)) : '' ?></div>
                                <?php if ($isNow): ?>
                                    <span class="evt-when evt-when-now">Happening now</span>
                                <?php elseif ($isPast): ?>
                                    <span class="evt-when">Finished</span>
                                <?php elseif ($daysAway === 0): ?>
                                    <span class="evt-when evt-when-soon">Today</span>
                                <?php elseif ($daysAway === 1): ?>
                                    <span class="evt-when evt-when-soon">Tomorrow</span>
                                <?php elseif ($daysAway <= 30): ?>
                                    <span class="evt-when evt-when-soon">In <?= $daysAway ?> days</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Category">
                                <?php if (!empty($ev['category_name'])): ?>
                                    <span class="evt-cat-badge" style="background-color: <?= evt_categoryColor($ev['category_color'] ?? null, $ev['category_name'] ?? null) ?>;">
                                        <?= htmlspecialchars($ev['category_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="For" style="font-size:.82rem">
                                <?= ($ev['program'] ?? 'ALL') !== 'ALL' ? htmlspecialchars($ev['program']) : 'All programs' ?>
                            </td>
                            <td data-label="Status"><span class="status-badge <?= htmlspecialchars($ev['status']) ?>"><?= htmlspecialchars($evtStatusLabels[$ev['status']] ?? ucfirst($ev['status'])) ?></span></td>
                            <td class="evt-actions-cell">
                                <button type="button" class="btn btn-sm btn-outline-secondary evt-edit-btn" data-event-id="<?= $ev['event_id'] ?>">
                                    <i class="bi bi-pencil"></i> Edit
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger evt-delete-btn" data-event-id="<?= $ev['event_id'] ?>">
                                    <i class="bi bi-trash"></i> Delete
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <tr id="evtNoMatch" style="display:none"><td colspan="6"><div class="evt-empty"><i class="bi bi-search"></i><p class="mb-0">No events match your search.</p></div></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if (count($allEvents) >= 50): ?>
                <div class="evt-hint" style="border-top:1px solid rgba(128,128,128,.2)">Showing the 50 most recent events.</div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add / Edit Event Modal -->
<div class="modal fade" id="eventModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen-sm-down">
        <div class="modal-content">
            <form id="eventForm">
                <input type="hidden" name="ajax_action" id="eventFormAction" value="create">
                <input type="hidden" name="event_id" id="eventId" value="">
                <input type="hidden" name="csrf_token" id="csrfToken" value="<?= htmlspecialchars($evtCsrfToken) ?>">

                <div class="modal-header">
                    <h5 class="modal-title" id="eventModalLabel">Add Event</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="eventFormAlert" class="alert alert-danger d-none" role="alert"></div>

                    <div class="mb-3">
                        <label class="form-label" for="eventTitle">What is the event called? <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="title" id="eventTitle" required maxlength="500" placeholder="e.g. Foundation Day Celebration">
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="eventLocation">Where will it happen? <span class="text-muted fw-normal">(optional)</span></label>
                        <input type="text" class="form-control" name="location" id="eventLocation" maxlength="500" placeholder="e.g. School Gymnasium">
                    </div>

                    <div class="evt-section-label">When</div>
                    <div class="row">
                        <div class="col-12 col-sm-6 mb-2">
                            <label class="form-label" for="eventStartDate">Starts on <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="start_date" id="eventStartDate" required>
                        </div>
                        <div class="col-12 col-sm-6 mb-2">
                            <label class="form-label" for="eventEndDate">Ends on <span class="text-muted fw-normal">(optional)</span></label>
                            <input type="date" class="form-control" name="end_date" id="eventEndDate">
                        </div>
                    </div>
                    <div class="form-text mb-2">Leave "Ends on" empty if the event is only one day.</div>

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" id="eventAllDay" checked>
                        <label class="form-check-label" for="eventAllDay">No specific time (all day)</label>
                    </div>
                    <div class="row d-none" id="eventTimeRow">
                        <div class="col-12 col-sm-6 mb-3">
                            <label class="form-label" for="eventStartTime">Start time</label>
                            <input type="time" class="form-control" name="start_time" id="eventStartTime">
                        </div>
                        <div class="col-12 col-sm-6 mb-3">
                            <label class="form-label" for="eventEndTime">End time <span class="text-muted fw-normal">(optional)</span></label>
                            <input type="time" class="form-control" name="end_time" id="eventEndTime">
                        </div>
                    </div>

                    <div class="evt-section-label">Details</div>
                    <div class="row">
                        <div class="col-12 col-sm-6 mb-3">
                            <label class="form-label" for="eventCategory">Type of event</label>
                            <div class="d-flex align-items-center gap-2">
                                <span id="categoryColorDot" class="evt-cat-dot" style="width:14px;height:14px;background:#6c757d;flex:0 0 auto;"></span>
                                <select class="form-select" name="category_id" id="eventCategory">
                                    <option value="">— Not set —</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['category_id'] ?>" data-color="<?= evt_categoryColor($cat['category_color'] ?? null, $cat['category_name'] ?? null) ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-12 col-sm-6 mb-3">
                            <label class="form-label" for="eventProgram">Who is it for?</label>
                            <select class="form-select" name="program" id="eventProgram">
                                <?php foreach (getProgramCategories() as $code => $label):
                                    $optionText = $code === 'ALL' ? 'Everyone (all programs)' : "{$code} - {$label}";
                                ?>
                                    <option value="<?= htmlspecialchars($code) ?>"<?= $code === 'ALL' ? ' selected' : '' ?>><?= htmlspecialchars($optionText) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="evt-section-label">Visibility</div>
                    <select class="form-select" name="status" id="eventStatus">
                        <option value="draft"<?= $isSuperAdmin ? ' selected' : '' ?>>Draft — still working on it</option>
                        <?php if (!$isSuperAdmin): ?>
                        <option value="pending" selected>Waiting for approval</option>
                        <?php endif; ?>
                        <option value="published">Live on website</option>
                        <option value="archived">Hidden from website</option>
                    </select>
                    <div class="evt-status-help" id="eventStatusHelp"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="eventSubmitBtn"><i class="bi bi-check-lg"></i> Save Event</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteEventModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="max-width:420px">
        <div class="modal-content evt-del-content">
            <div class="modal-body text-center p-4">
                <div class="evt-del-icon"><i class="bi bi-trash3"></i></div>
                <h5 class="fw-bold mb-2">Delete this event?</h5>
                <p class="mb-1" style="font-size:.9rem">
                    <strong id="deleteEventTitle"></strong>
                </p>
                <p class="text-muted mb-3" style="font-size:.85rem">This action cannot be undone.</p>
                <div id="deleteEventError" class="alert alert-danger py-2 d-none" style="font-size:.85rem"></div>
                <div class="d-flex gap-2 justify-content-center">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger px-4" id="deleteEventConfirmBtn">
                        <i class="bi bi-trash3 me-1"></i> Delete
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.evt-del-icon {
    width: 60px; height: 60px; margin: 0 auto 14px;
    border-radius: 50%;
    background: rgba(220, 53, 69, .15);
    color: #dc3545;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.6rem;
}
</style>

<style>
.evt-calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 4px;
    padding: 1rem 1.25rem;
}
.evt-cal-dow {
    text-align: center;
    font-size: .72rem;
    font-weight: 700;
    color: #888;
    text-transform: uppercase;
    padding-bottom: 6px;
}
.evt-cal-cell {
    min-height: 80px;
    border: 1px solid #eee;
    border-radius: 6px;
    padding: 4px;
    cursor: pointer;
    transition: background-color .15s, border-color .15s;
    position: relative;
}
.evt-cal-cell:hover {
    background-color: #f6f9f7;
    border-color: #c9dcd0;
}
.evt-cal-empty {
    cursor: default;
    border: none;
}
.evt-cal-empty:hover { background: none; }
.evt-cal-today {
    background-color: #DDEBFD;
    border-color: #1877F2;
}
.evt-cal-daynum {
    font-size: .78rem;
    font-weight: 600;
    color: #444;
    display: block;
    margin-bottom: 2px;
}
.evt-cal-pill {
    font-size: .65rem;
    padding: 1px 5px;
    border-radius: 4px;
    margin-bottom: 2px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    color: #fff;
    background-color: #6c757d;
}
.evt-cal-pill.evt-status-draft     { background-color: #adb5bd; }
.evt-cal-pill.evt-status-pending   { background-color: #EABA3B; color:#094024; }
.evt-cal-pill.evt-status-published { background-color: #094024; }
.evt-cal-pill.evt-status-archived  { background-color: #c3cbc4; color:#555; }
.evt-cal-more {
    font-size: .62rem;
    color: #999;
}
.evt-cat-dot {
    display: inline-block;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    margin-right: 6px;
    vertical-align: middle;
}
.evt-cat-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: .72rem;
    font-weight: 600;
    color: #fff;
}

/* ---- UX additions ---- */
.evt-hint { font-size: .78rem; color: #8a8f8c; padding: 0 1.25rem .6rem; }
.evt-hint i { margin-right: 4px; }
.evt-cal-cell { border-color: rgba(128,128,128,.25); }
.evt-cal-cell:hover { background-color: rgba(128,128,128,.12); border-color: rgba(128,128,128,.4); }
.evt-cal-cell:not(.evt-cal-empty)::after {
    content: '+'; position: absolute; top: 2px; right: 6px;
    font-size: .9rem; font-weight: 700; color: #EABA3B; opacity: 0; transition: opacity .15s;
}
.evt-cal-cell:not(.evt-cal-empty):hover::after { opacity: 1; }
.evt-cal-today { background-color: rgba(234,186,59,.15); border-color: #EABA3B; }
.evt-cal-today .evt-cal-daynum { color: #EABA3B; }
.evt-cal-pill { cursor: pointer; font-size: .7rem; }
.evt-cal-pill:hover { filter: brightness(1.15); }
.evt-pill-unpublished { opacity: .6; outline: 1px dashed rgba(255,255,255,.7); outline-offset: -2px; }
.evt-pill-sample { display:inline-block; width:26px; height:10px; border-radius:3px; background:#6c757d; opacity:.6; outline:1px dashed #aaa; outline-offset:-2px; vertical-align:middle; margin-right:4px; }

.evt-month-list { padding: .5rem .75rem; max-height: 420px; overflow-y: auto; }
.evt-month-item {
    display: flex; align-items: center; gap: .75rem; padding: .55rem .5rem;
    border-radius: 8px; cursor: pointer; transition: background-color .15s;
}
.evt-month-item:hover, .evt-month-item:focus { background-color: rgba(128,128,128,.14); outline: none; }
.evt-date-chip {
    flex: 0 0 48px; height: 48px; border-radius: 10px; color: #fff;
    display: flex; flex-direction: column; align-items: center; justify-content: center; line-height: 1.1;
}
.evt-date-chip-day { font-size: 1.1rem; font-weight: 700; }
.evt-date-chip-dow { font-size: .62rem; text-transform: uppercase; letter-spacing: .5px; opacity: .9; }
.evt-month-info { flex: 1 1 auto; min-width: 0; }
.evt-month-title { font-weight: 600; font-size: .9rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.evt-month-meta { font-size: .78rem; color: #8a8f8c; }

.evt-guide { border-top: 1px solid rgba(128,128,128,.2); padding: .75rem 1.25rem; font-size: .85rem; }
.evt-guide summary { cursor: pointer; font-weight: 600; color: #8a8f8c; }
.evt-guide-body { padding-top: .75rem; }
.evt-guide-title { font-size: .72rem; text-transform: uppercase; letter-spacing: .5px; font-weight: 700; color: #8a8f8c; margin-bottom: .35rem; }

.evt-empty { text-align: center; padding: 2rem 1rem; color: #8a8f8c; }
.evt-empty i { font-size: 2rem; display: block; margin-bottom: .4rem; opacity: .7; }

.evt-search { position: relative; min-width: 240px; }
.evt-search i { position: absolute; left: 10px; top: 50%; transform: translateY(-50%); font-size: .8rem; opacity: .6; }
.evt-search input { padding-left: 30px; }
.evt-filters { display: flex; flex-wrap: wrap; gap: .5rem; padding: .75rem 1.25rem; }
.evt-chip {
    border: 1px solid rgba(128,128,128,.35); background: transparent; color: inherit;
    border-radius: 999px; padding: .3rem .8rem; font-size: .8rem; cursor: pointer; transition: all .15s;
}
.evt-chip span { opacity: .65; margin-left: 4px; }
.evt-chip:hover { background: rgba(128,128,128,.15); }
.evt-chip.active { background: #EABA3B; border-color: #EABA3B; color: #094024; font-weight: 600; }
.evt-chip.active span { opacity: .8; }

.evt-row { cursor: pointer; }
.evt-past .evt-row-title,
.evt-past .evt-when:not(.evt-when-now) { opacity: .75; }
.evt-table .evt-cat-badge { box-shadow: none; }
.evt-table .status-badge.published { background: #1F8A3B; color: #fff; }
.evt-table .status-badge.pending   { background: #F2B705; color: #3d2c00; }
.evt-table .status-badge.draft     { background: #6c757d; color: #fff; }
.evt-table .status-badge.archived  { background: #8a929a; color: #fff; }
.evt-row-sub { color: #8a8f8c; }
.evt-when { background: rgba(128,128,128,.22); color: inherit; }
.evt-title-cell { max-width: 280px; }
.evt-row-title { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.evt-row-sub { font-size: .75rem; color: #8a8f8c; }
.evt-when { display: inline-block; font-size: .7rem; padding: 1px 7px; border-radius: 999px; background: rgba(128,128,128,.2); margin-top: 2px; }
.evt-when-now  { background: #1F5E2C; color: #fff; }
.evt-when-soon { background: rgba(234,186,59,.25); color: #EABA3B; }

.evt-section-label {
    font-size: .72rem; text-transform: uppercase; letter-spacing: .6px; font-weight: 700;
    color: #8a8f8c; margin: 1.1rem 0 .5rem; padding-bottom: .3rem; border-bottom: 1px solid rgba(128,128,128,.2);
}
.modal-body > .evt-section-label:first-of-type { margin-top: 0; }
.evt-status-help { font-size: .8rem; color: #8a8f8c; margin-top: .4rem; }

.evt-toast {
    position: fixed; right: 20px; bottom: 24px; z-index: 2000;
    background: #1F5E2C; color: #fff; padding: .75rem 1.1rem; border-radius: 10px;
    box-shadow: 0 6px 20px rgba(0,0,0,.35); font-size: .9rem;
    display: flex; align-items: center; gap: .5rem;
    transform: translateY(20px); opacity: 0; transition: all .3s;
}
.evt-toast.show { transform: none; opacity: 1; }
.evt-toast.error { background: #B3312D; }

/* ================= Responsive ================= */
.page-header { flex-wrap: wrap; gap: .75rem; }
.evt-calendar-grid > * { min-width: 0; }
.data-card-header { flex-wrap: wrap; }

/* Tablets and below */
@media (max-width: 991.98px) {
    .evt-cal-cell { min-height: 72px; }
    .evt-month-list { max-height: none; }
}

/* Phones and small tablets: events table becomes a list of cards */
@media (max-width: 767.98px) {
    .evt-search { min-width: 0; width: 100%; margin-left: 0 !important; order: 3; }
    .evt-filters { flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none; }
    .evt-filters::-webkit-scrollbar { display: none; }
    .evt-chip { white-space: nowrap; flex: 0 0 auto; padding: .4rem .9rem; }

    .evt-table thead { display: none; }
    .evt-table, .evt-table tbody { display: block; width: 100%; }
    .evt-table tr.evt-row {
        display: block; padding: .85rem 1rem;
        border-bottom: 1px solid rgba(128,128,128,.25);
    }
    .evt-table tr.evt-row > td {
        display: flex; align-items: center; justify-content: space-between; gap: 1rem;
        padding: .3rem 0; border: 0; text-align: right; max-width: none;
    }
    .evt-table tr.evt-row > td::before {
        content: attr(data-label); flex: 0 0 auto;
        font-size: .7rem; font-weight: 700; text-transform: uppercase; letter-spacing: .5px; color: #8a8f8c;
        text-align: left;
    }
    .evt-table tr.evt-row > td.evt-title-cell { display: block; text-align: left; padding-top: 0; }
    .evt-table tr.evt-row > td.evt-title-cell::before { display: none; }
    .evt-row-title { white-space: normal; font-size: 1rem; }
    .evt-table tr.evt-row > td.evt-actions-cell { padding-top: .6rem; gap: .5rem; }
    .evt-table tr.evt-row > td.evt-actions-cell::before { display: none; }
    .evt-actions-cell .btn { flex: 1 1 0; padding: .5rem .75rem; font-size: .9rem; }
    .evt-table #evtNoMatch, .evt-table #evtNoMatch td, .evt-table tbody > tr:only-child, .evt-table tbody > tr:only-child > td { display: block; }
}

/* Phones */
@media (max-width: 575.98px) {
    .page-header > div { flex: 1 1 100%; }
    .page-header h1 { font-size: 1.4rem; }
    #addEventBtnTop { width: 100%; padding: .65rem 1rem; font-size: 1rem; }
    #addEventBtn { display: none; }               /* the big top button + tapping a date already cover this */

    .evt-calendar-grid { padding: .75rem .5rem; gap: 3px; }
    .evt-cal-dow { font-size: .62rem; }
    .evt-cal-cell { min-height: 54px; padding: 3px; border-radius: 5px; }
    .evt-cal-daynum { font-size: .75rem; }
    /* events show as small colored bars; full details are in the list below */
    .evt-cal-pill { font-size: 0; height: 6px; padding: 0; border-radius: 3px; margin-bottom: 3px; outline-offset: -1px; }
    .evt-cal-more { font-size: .6rem; }
    .evt-hint { padding: 0 .75rem .5rem; }
    .data-card-header { padding-left: .75rem; padding-right: .75rem; }

    .evt-month-list { padding: .5rem; }
    .evt-month-item { flex-wrap: wrap; }
    .evt-month-item .status-badge { margin-left: 60px; font-size: .62rem; }
    .evt-guide { padding: .75rem; }
    .evt-filters { padding: .6rem .75rem; }

    /* Add/Edit form fills the screen with a sticky Save bar */
    .modal-fullscreen-sm-down .modal-footer {
        position: sticky; bottom: 0; background: var(--bs-modal-bg, inherit); z-index: 2;
        flex-wrap: nowrap;
    }
    .modal-fullscreen-sm-down .modal-footer .btn { flex: 1 1 0; padding: .65rem; }
    .modal-body .form-control, .modal-body .form-select { font-size: 16px; } /* stops iOS zooming into inputs */

    #deleteEventModal .modal-dialog { margin: 1rem; }
    #deleteEventModal .btn { flex: 1 1 0; }

    .evt-toast { left: 12px; right: 12px; bottom: 16px; }
}

/* Touch devices: no hover, so don't show the hover "+" hint */
@media (hover: none) {
    .evt-cal-cell:not(.evt-cal-empty)::after { display: none; }
    .btn, .evt-chip, .evt-month-item { min-height: 40px; }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const eventModalEl = document.getElementById('eventModal');
    const eventModal    = new bootstrap.Modal(eventModalEl);
    const form           = document.getElementById('eventForm');
    const alertBox        = document.getElementById('eventFormAlert');
    const submitBtn         = document.getElementById('eventSubmitBtn');
    const modalLabel          = document.getElementById('eventModalLabel');
    const categorySelect       = document.getElementById('eventCategory');
    const categoryColorDot      = document.getElementById('categoryColorDot');

    function updateCategoryDot() {
        const opt = categorySelect.options[categorySelect.selectedIndex];
        categoryColorDot.style.background = (opt && opt.dataset.color) ? opt.dataset.color : '#6c757d';
    }
    categorySelect.addEventListener('change', updateCategoryDot);

    // ---- All-day toggle: hide the time inputs unless a time is needed ----
    const allDayCb = document.getElementById('eventAllDay');
    const timeRow  = document.getElementById('eventTimeRow');
    function syncAllDay() {
        const allDay = allDayCb.checked;
        timeRow.classList.toggle('d-none', allDay);
        if (allDay) {
            document.getElementById('eventStartTime').value = '';
            document.getElementById('eventEndTime').value = '';
        }
    }
    allDayCb.addEventListener('change', syncAllDay);

    // ---- Plain-language hint under the Status dropdown ----
    const statusHelpText = <?= json_encode($evtStatusHelp) ?>;
    const DEFAULT_STATUS = <?= json_encode($evtDefaultStatus) ?>;
    const statusSelect   = document.getElementById('eventStatus');
    const statusHelpEl   = document.getElementById('eventStatusHelp');
    function updateStatusHelp() { statusHelpEl.textContent = statusHelpText[statusSelect.value] || ''; }
    statusSelect.addEventListener('change', updateStatusHelp);

    // ---- End date can never be before the start date ----
    const startDateEl = document.getElementById('eventStartDate');
    const endDateEl   = document.getElementById('eventEndDate');
    startDateEl.addEventListener('change', function () {
        endDateEl.min = startDateEl.value || '';
        if (endDateEl.value && endDateEl.value < startDateEl.value) endDateEl.value = '';
    });

    // ---- Friendly success message that survives the page reload ----
    function showToast(msg, isError) {
        const t = document.createElement('div');
        t.className = 'evt-toast' + (isError ? ' error' : '');
        t.innerHTML = '<i class="bi bi-' + (isError ? 'exclamation-circle' : 'check-circle') + '"></i><span></span>';
        t.querySelector('span').textContent = msg;
        document.body.appendChild(t);
        requestAnimationFrame(function () { t.classList.add('show'); });
        setTimeout(function () { t.classList.remove('show'); setTimeout(function () { t.remove(); }, 400); }, 4000);
    }
    function reloadWithToast(msg) {
        try { sessionStorage.setItem('evtToast', msg); } catch (e) {}
        location.reload();
    }
    try {
        const pending = sessionStorage.getItem('evtToast');
        if (pending) { sessionStorage.removeItem('evtToast'); showToast(pending); }
    } catch (e) {}

    function resetForm() {
        form.reset();
        document.getElementById('eventId').value = '';
        document.getElementById('eventFormAction').value = 'create';
        document.getElementById('eventStatus').value = DEFAULT_STATUS;
        document.getElementById('eventProgram').value = 'ALL';
        alertBox.classList.add('d-none');
        alertBox.textContent = '';
        modalLabel.textContent = 'Add Event';
        document.getElementById('eventAllDay').checked = true;
        syncAllDay();
        updateStatusHelp();
        updateCategoryDot();
    }

    // Open modal for a brand-new event
    document.querySelectorAll('#addEventBtn, #addEventBtnTop, .js-add-event').forEach(function (b) {
        b.addEventListener('click', function () {
            resetForm();
            eventModal.show();
        });
    });

    // Click a calendar day -> prefill start date and open modal
    document.querySelectorAll('.evt-cal-cell[data-date]').forEach(function (cell) {
        cell.addEventListener('click', function (e) {
            // if they clicked an existing event pill, edit that event instead
            if (e.target.closest('.evt-cal-pill')) return;
            resetForm();
            document.getElementById('eventStartDate').value = cell.dataset.date;
            eventModal.show();
        });
    });

    // Click an event pill on the calendar -> edit
    document.querySelectorAll('.evt-cal-pill[data-event-id]').forEach(function (pill) {
        pill.addEventListener('click', function (e) {
            e.stopPropagation();
            loadEventForEdit(pill.dataset.eventId);
        });
    });

    // Click edit button in the table
    document.querySelectorAll('.evt-edit-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            loadEventForEdit(btn.dataset.eventId);
        });
    });

    // Click delete button in the table -> open styled confirmation modal
    const deleteModalEl   = document.getElementById('deleteEventModal');
    const deleteModal     = new bootstrap.Modal(deleteModalEl);
    const deleteTitleEl   = document.getElementById('deleteEventTitle');
    const deleteErrorEl   = document.getElementById('deleteEventError');
    const deleteConfirmBtn = document.getElementById('deleteEventConfirmBtn');
    let pendingDeleteId = null;

    document.querySelectorAll('.evt-delete-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            pendingDeleteId = btn.dataset.eventId;
            const row = btn.closest('tr');
            deleteTitleEl.textContent = row ? (row.dataset.title || '') : '';
            deleteErrorEl.classList.add('d-none');
            deleteConfirmBtn.disabled = false;
            deleteConfirmBtn.innerHTML = '<i class="bi bi-trash3 me-1"></i> Delete';
            deleteModal.show();
        });
    });

    deleteConfirmBtn.addEventListener('click', function () {
        if (!pendingDeleteId) return;
        deleteConfirmBtn.disabled = true;
        deleteConfirmBtn.textContent = 'Deleting…';

        const fd = new FormData();
        fd.append('ajax_action', 'delete');
        fd.append('event_id', pendingDeleteId);
        fd.append('csrf_token', document.getElementById('csrfToken').value);

        const fail = (msg) => {
            deleteErrorEl.textContent = msg;
            deleteErrorEl.classList.remove('d-none');
            deleteConfirmBtn.disabled = false;
            deleteConfirmBtn.innerHTML = '<i class="bi bi-trash3 me-1"></i> Delete';
        };

        fetch(window.location.pathname + window.location.search, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    reloadWithToast('Event deleted.');
                } else {
                    fail(data.message || 'Could not delete event.');
                }
            })
            .catch(() => fail('A network error occurred.'));
    });

    function loadEventForEdit(eventId) {
        // We already have the data rendered server-side in the table; pull it from there
        const row = document.querySelector('tr[data-event-id="' + eventId + '"]');
        // Simplest reliable approach: fetch fresh details via a lightweight GET isn't wired,
        // so instead we re-fetch the page data through a tiny inline AJAX using POST "read" pattern
        // is unnecessary here -- we fall back to asking the server for the single event's fields.
        const fd = new FormData();
        fd.append('ajax_action', 'fetch_one');
        fd.append('event_id', eventId);
        fd.append('csrf_token', document.getElementById('csrfToken').value);

        fetch(window.location.pathname + window.location.search, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (!data.success) {
                    showToast(data.message || 'Could not load event.', true);
                    return;
                }
                const ev = data.event;
                resetForm();
                modalLabel.textContent = 'Edit Event';
                document.getElementById('eventFormAction').value = 'update';
                document.getElementById('eventId').value = ev.event_id;
                document.getElementById('eventTitle').value = ev.title || '';
                document.getElementById('eventLocation').value = ev.location || '';
                // 00:00 means "no time set" -> leave the time inputs blank
                const splitDT = (v) => {
                    if (!v) return ['', ''];
                    const t = v.slice(11, 16);
                    return [v.slice(0, 10), t === '00:00' ? '' : t];
                };
                const [sd, st] = splitDT(ev.start_date);
                const [ed, et] = splitDT(ev.end_date);
                document.getElementById('eventStartDate').value = sd;
                document.getElementById('eventStartTime').value = st;
                document.getElementById('eventEndDate').value = ed;
                document.getElementById('eventEndTime').value = et;
                document.getElementById('eventCategory').value = ev.category_id || '';
                document.getElementById('eventProgram').value = ev.program || 'ALL';
                // If this role has no such option (e.g. an old "pending" event opened by a
                // Super Admin), the select would go blank, so fall back to the default.
                const statusEl = document.getElementById('eventStatus');
                statusEl.value = ev.status || DEFAULT_STATUS;
                if (statusEl.value === '') statusEl.value = DEFAULT_STATUS;
                allDayCb.checked = !(st || et);
                syncAllDay();
                endDateEl.min = sd || '';
                updateStatusHelp();
                updateCategoryDot();
                eventModal.show();
            })
            .catch(() => showToast('A network error occurred. Please try again.', true));
    }

    // Month-list items and table rows are clickable too
    document.querySelectorAll('.evt-month-item').forEach(function (item) {
        item.addEventListener('click', function () { loadEventForEdit(item.dataset.eventId); });
        item.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); loadEventForEdit(item.dataset.eventId); }
        });
    });
    document.querySelectorAll('tr.evt-row').forEach(function (row) {
        row.addEventListener('click', function (e) {
            if (e.target.closest('button')) return;
            loadEventForEdit(row.dataset.eventId);
        });
    });

    // ---- Search + status filter for the events list ----
    const searchEl = document.getElementById('evtSearch');
    const chips    = document.querySelectorAll('.evt-chip');
    const noMatch  = document.getElementById('evtNoMatch');
    let activeFilter = 'all';
    function applyFilters() {
        const q = (searchEl.value || '').trim().toLowerCase();
        let visible = 0;
        document.querySelectorAll('tr.evt-row').forEach(function (row) {
            const okStatus = activeFilter === 'all' || row.dataset.status === activeFilter;
            const okText   = !q || (row.dataset.title || '').toLowerCase().indexOf(q) !== -1;
            const show = okStatus && okText;
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        if (noMatch) noMatch.style.display = visible ? 'none' : '';
    }
    searchEl.addEventListener('input', applyFilters);
    chips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            chips.forEach(function (c) { c.classList.remove('active'); });
            chip.classList.add('active');
            activeFilter = chip.dataset.filter;
            applyFilters();
        });
    });

    // Put the cursor in the title box when the form opens
    eventModalEl.addEventListener('shown.bs.modal', function () {
        document.getElementById('eventTitle').focus();
    });

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (endDateEl.value && endDateEl.value < startDateEl.value) {
            alertBox.textContent = 'The end date cannot be earlier than the start date.';
            alertBox.classList.remove('d-none');
            return;
        }
        if (!allDayCb.checked && !document.getElementById('eventStartTime').value) {
            alertBox.textContent = 'Please enter a start time, or switch on "No specific time (all day)".';
            alertBox.classList.remove('d-none');
            return;
        }
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving…';
        alertBox.classList.add('d-none');

        const fd = new FormData(form);
        fetch(window.location.pathname + window.location.search, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    reloadWithToast(document.getElementById('eventFormAction').value === 'update' ? 'Event saved.' : 'Event added to the calendar.');
                } else {
                    alertBox.textContent = data.message || 'Something went wrong.';
                    alertBox.classList.remove('d-none');
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="bi bi-check-lg"></i> Save Event';
                }
            })
            .catch(() => {
                alertBox.textContent = 'A network error occurred.';
                alertBox.classList.remove('d-none');
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="bi bi-check-lg"></i> Save Event';
            });
    });
});
</script>

<?php include __DIR__ . '/../components/footer-admin.php'; ?>