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
            $description = trim($_POST['description'] ?? '');
            $location    = trim($_POST['location'] ?? '');
            $startDate   = trim($_POST['start_date'] ?? '');
            $endDate     = trim($_POST['end_date'] ?? '') ?: null;
            $categoryId  = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
            $status      = in_array($_POST['status'] ?? '', ['draft','pending','published','archived'])
                            ? $_POST['status'] : 'pending';

            if ($title === '' || $startDate === '') {
                echo json_encode(['success' => false, 'message' => 'Title and start date are required.']);
                exit;
            }

            // basic datetime sanity check
            if (!strtotime($startDate) || ($endDate && !strtotime($endDate))) {
                echo json_encode(['success' => false, 'message' => 'Invalid date format.']);
                exit;
            }

            if ($action === 'create') {
                $stmt = $db->prepare("
                    INSERT INTO events (category_id, user_id, title, description, location, start_date, end_date, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$categoryId, $userId, $title, $description, $location, $startDate, $endDate, $status]);
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
                    SET category_id = ?, title = ?, description = ?, location = ?,
                        start_date = ?, end_date = ?, status = ?
                    WHERE event_id = ?
                ");
                $stmt->execute([$categoryId, $title, $description, $location, $startDate, $endDate, $status, $eventId]);

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
                SELECT event_id, category_id, title, description, location, start_date, end_date, status
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
        SELECT e.event_id, e.title, e.description, e.location, e.start_date, e.end_date,
               e.status, e.category_id, c.category_name, c.category_color
        FROM events e
        LEFT JOIN categories c ON e.category_id = c.category_id
        WHERE e.start_date BETWEEN ? AND ?
        ORDER BY e.start_date ASC
    ");
    $stmt->execute([$rangeStart, $rangeEnd]);
    $monthEvents = $stmt->fetchAll();
} catch (Exception $e) {
    error_log('Get month events error: ' . $e->getMessage());
    $monthEvents = [];
}

// Group events by day number for quick lookup in the calendar grid
$eventsByDay = [];
foreach ($monthEvents as $ev) {
    $day = (int)date('j', strtotime($ev['start_date']));
    $eventsByDay[$day][] = $ev;
}

// All events list (most recent first) for the management table below
try {
    $allEvents = $db->query("
        SELECT e.event_id, e.title, e.start_date, e.end_date, e.status, e.category_id,
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

try {
    $categories = $db->query("SELECT category_id, category_name, category_color FROM categories ORDER BY category_name ASC")->fetchAll();
} catch (Exception $e) {
    $categories = [];
}

$evtCsrfToken = evt_generateCsrfToken($db, $currentUser['user_id'] ?? 0);

include __DIR__ . '/../components/header-admin.php';
?>

<div class="page-header">
    <div>
        <h1>Events &amp; Calendar</h1>
        <p>Click any date to add an event. Manage existing events in the list below.</p>
    </div>
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
                <button type="button" class="btn btn-sm btn-success ms-auto" id="addEventBtn">
                    <i class="bi bi-plus-lg"></i> Add Event
                </button>
            </div>

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
                            <div class="evt-cal-pill"
                                 style="background-color: <?= evt_categoryColor($ev['category_color'] ?? null, $ev['category_name'] ?? null) ?>;"
                                 data-event-id="<?= $ev['event_id'] ?>"
                                 title="<?= htmlspecialchars($ev['title'] . ' — ' . ($ev['category_name'] ?? 'Uncategorized')) ?>">
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

    <!-- Quick legend / status key -->
    <div class="col-lg-5">
        <div class="data-card h-100">
            <div class="data-card-header">
                <span class="data-card-title"><i class="bi bi-info-circle me-1"></i> This Month</span>
            </div>
            <div style="padding:1rem 1.25rem">
                <p class="text-muted mb-3" style="font-size:.85rem">
                    <?= count($monthEvents) ?> event<?= count($monthEvents) === 1 ? '' : 's' ?> scheduled in <?= htmlspecialchars($monthLabel) ?>.
                </p>
                <div class="d-flex flex-column gap-2">
                    <div class="fw-semibold text-muted" style="font-size:.78rem;text-transform:uppercase;letter-spacing:.5px;">Category</div>
                    <div><span class="evt-cat-dot" style="background:#EABA3B;"></span> Holiday</div>
                    <div><span class="evt-cat-dot" style="background:#1F5E2C;"></span> School Event</div>
                    <div><span class="evt-cat-dot" style="background:#B3312D;"></span> Emergency <span class="text-muted">(e.g. class suspension)</span></div>

                    <div class="fw-semibold text-muted mt-2" style="font-size:.78rem;text-transform:uppercase;letter-spacing:.5px;">Status</div>
                    <div><span class="status-badge draft">Draft</span> — not visible publicly</div>
                    <div><span class="status-badge pending">Pending</span> — awaiting approval</div>
                    <div><span class="status-badge published">Published</span> — live on the public site</div>
                    <div><span class="status-badge archived">Archived</span> — hidden from public view</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Events list / management table -->
<div class="row g-3 mt-1">
    <div class="col-12">
        <div class="data-card">
            <div class="data-card-header">
                <span class="data-card-title"><i class="bi bi-list-ul me-1"></i> All Events</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Author</th>
                            <th style="width:110px">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="eventsTableBody">
                        <?php if (empty($allEvents)): ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No events yet. Click "Add Event" or a calendar date to create one.</td></tr>
                        <?php else: foreach ($allEvents as $ev): ?>
                        <tr data-event-id="<?= $ev['event_id'] ?>">
                            <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                                <?= htmlspecialchars($ev['title']) ?>
                            </td>
                            <td>
                                <?php if (!empty($ev['category_name'])): ?>
                                    <span class="evt-cat-badge" style="background-color: <?= evt_categoryColor($ev['category_color'] ?? null, $ev['category_name'] ?? null) ?>;">
                                        <?= htmlspecialchars($ev['category_name']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:.8rem"><?= date('M d, Y', strtotime($ev['start_date'])) ?></td>
                            <td><span class="status-badge <?= $ev['status'] ?>"><?= ucfirst($ev['status']) ?></span></td>
                            <td style="font-size:.8rem"><?= htmlspecialchars(trim($ev['author']) ?: '—') ?></td>
                            <td>
                                <button type="button" class="btn btn-sm btn-outline-secondary btn-action evt-edit-btn" data-event-id="<?= $ev['event_id'] ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger btn-action evt-delete-btn" data-event-id="<?= $ev['event_id'] ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add / Edit Event Modal -->
<div class="modal fade" id="eventModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
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
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="title" id="eventTitle" required maxlength="500">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea class="form-control" name="description" id="eventDescription" rows="3"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <input type="text" class="form-control" name="location" id="eventLocation" maxlength="500">
                    </div>

                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Start Date <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" name="start_date" id="eventStartDate" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">End Date</label>
                            <input type="datetime-local" class="form-control" name="end_date" id="eventEndDate">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Category</label>
                            <div class="d-flex align-items-center gap-2">
                                <span id="categoryColorDot" class="evt-cat-dot" style="width:14px;height:14px;background:#6c757d;flex:0 0 auto;"></span>
                                <select class="form-select" name="category_id" id="eventCategory">
                                    <option value="">— None —</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= $cat['category_id'] ?>" data-color="<?= evt_categoryColor($cat['category_color'] ?? null, $cat['category_name'] ?? null) ?>"><?= htmlspecialchars($cat['category_name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="eventStatus">
                                <option value="draft">Draft</option>
                                <option value="pending" selected>Pending</option>
                                <option value="published">Published</option>
                                <option value="archived">Archived</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success" id="eventSubmitBtn">Save Event</button>
                </div>
            </form>
        </div>
    </div>
</div>

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

    function resetForm() {
        form.reset();
        document.getElementById('eventId').value = '';
        document.getElementById('eventFormAction').value = 'create';
        document.getElementById('eventStatus').value = 'pending';
        alertBox.classList.add('d-none');
        alertBox.textContent = '';
        modalLabel.textContent = 'Add Event';
        updateCategoryDot();
    }

    // Open modal for a brand-new event
    document.getElementById('addEventBtn').addEventListener('click', function () {
        resetForm();
        eventModal.show();
    });

    // Click a calendar day -> prefill start date and open modal
    document.querySelectorAll('.evt-cal-cell[data-date]').forEach(function (cell) {
        cell.addEventListener('click', function (e) {
            // if they clicked an existing event pill, edit that event instead
            if (e.target.closest('.evt-cal-pill')) return;
            resetForm();
            document.getElementById('eventStartDate').value = cell.dataset.date + 'T09:00';
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

    // Click delete button in the table
    document.querySelectorAll('.evt-delete-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            if (!confirm('Delete this event? This cannot be undone.')) return;
            const fd = new FormData();
            fd.append('ajax_action', 'delete');
            fd.append('event_id', btn.dataset.eventId);
            fd.append('csrf_token', document.getElementById('csrfToken').value);

            fetch(window.location.pathname + window.location.search, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Could not delete event.');
                    }
                })
                .catch(() => alert('A network error occurred.'));
        });
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
                    alert(data.message || 'Could not load event.');
                    return;
                }
                const ev = data.event;
                resetForm();
                modalLabel.textContent = 'Edit Event';
                document.getElementById('eventFormAction').value = 'update';
                document.getElementById('eventId').value = ev.event_id;
                document.getElementById('eventTitle').value = ev.title || '';
                document.getElementById('eventDescription').value = ev.description || '';
                document.getElementById('eventLocation').value = ev.location || '';
                document.getElementById('eventStartDate').value = ev.start_date ? ev.start_date.replace(' ', 'T').slice(0,16) : '';
                document.getElementById('eventEndDate').value = ev.end_date ? ev.end_date.replace(' ', 'T').slice(0,16) : '';
                document.getElementById('eventCategory').value = ev.category_id || '';
                document.getElementById('eventStatus').value = ev.status || 'pending';
                updateCategoryDot();
                eventModal.show();
            })
            .catch(() => alert('A network error occurred.'));
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        submitBtn.disabled = true;
        submitBtn.textContent = 'Saving…';
        alertBox.classList.add('d-none');

        const fd = new FormData(form);
        fetch(window.location.pathname + window.location.search, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alertBox.textContent = data.message || 'Something went wrong.';
                    alertBox.classList.remove('d-none');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Save Event';
                }
            })
            .catch(() => {
                alertBox.textContent = 'A network error occurred.';
                alertBox.classList.remove('d-none');
                submitBtn.disabled = false;
                submitBtn.textContent = 'Save Event';
            });
    });
});
</script>

<?php include __DIR__ . '/../components/footer-admin.php'; ?>