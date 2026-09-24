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
 * SCHEMA NOTES (from your actual gnc_admin database)
 * - `announcements` has a single `program` varchar column (default
 *   'ALL'). Relevance = program matches CURRENT_PROGRAM, or program
 *   is 'ALL'. `category_id` on this table is a topic tag (Holiday,
 *   School Event, Emergency, Reminder) and is NOT used for program
 *   filtering — it has nothing to do with which program an
 *   announcement belongs to.
 * - `events` NOW has a `program` column (added via the migration
 *   below), following the exact same convention as
 *   `announcements.program`: default 'ALL' means "every program",
 *   otherwise it must match CURRENT_PROGRAM to be relevant.
 *
 *   Migration (run once against your DB if you haven't already):
 *     ALTER TABLE events ADD COLUMN program VARCHAR(10) NOT NULL DEFAULT 'ALL';
 *
 *   Existing rows will default to 'ALL' (i.e. shown on every program
 *   page) until you edit them in the admin to assign a specific
 *   program.
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
 * Recent Announcements — queries the real `announcements` table using
 * the exact same filter logic as news-and-announcement.php:
 * program = CURRENT_PROGRAM, OR program = 'ALL' (items meant for every
 * program page). Confirmed directly against that file's filter
 * function, not guessed. `category_id` on this table is a topic tag
 * (Holiday, School Event, Emergency, Reminder) — unrelated to program.
 */
function gnc_get_global_announcements(string $program, int $limit): array
{
    $pdo = gnc_bulletin_get_verified_pdo();

    $sql = "SELECT announcement_id, title, content, slug, COALESCE(published_at, created_at) AS item_date
            FROM announcements
            WHERE status = 'published'
            AND (program = :program OR program = 'ALL')
            ORDER BY COALESCE(published_at, created_at) DESC
            LIMIT :limit";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':program', $program, PDO::PARAM_STR);
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
 * Upcoming Events — now filtered by program using the same
 * "matches CURRENT_PROGRAM, OR program = 'ALL'" convention as
 * announcements. Requires the `events.program` column added by the
 * migration documented at the top of this file. If that column
 * doesn't exist yet, this query will throw — run the migration first.
 */
function gnc_get_global_events(string $program, int $limit): array
{
    $pdo = gnc_bulletin_get_verified_pdo();

    $sql = "SELECT event_id, title, location, start_date, end_date
            FROM events
            WHERE status = 'published'
              AND start_date >= NOW()
              AND (program = :program OR program = 'ALL')
            ORDER BY start_date ASC
            LIMIT :limit";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':program', $program, PDO::PARAM_STR);
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
    // - each item's own detail page is announcement.php?slug=...
    $announcementsUrl = '/pages/news-and-announcement.php?type=announcement&program=' . urlencode($program);

    // The homepage's Calendar of Events section (#events) already filters
    // by evt_program (see index.php / normalizeProgramCategory()), so we
    // link straight there instead of a separate events page — it
    // auto-filters to this program's events on load.
    $eventsUrl = '/index.php?evt_program=' . urlencode($program) . '#events';

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
                        <div class="bulletin-panel-header">
                            <span class="bulletin-panel-title text-uppercase">Recent Announcements</span>
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
                                       href="/pages/announcement.php?slug=<?= urlencode($item['slug']) ?>">
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
                        <div class="bulletin-panel-header">
                            <span class="bulletin-panel-title text-uppercase bulletin-panel-title--gold">Upcoming Events</span>
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