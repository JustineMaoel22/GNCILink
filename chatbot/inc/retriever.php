<?php
/**
 * GNCILink AI Assistant - retrieval layer (the "R" in RAG).
 *
 * Given a visitor question, decide what the question is about, run a few small
 * PREPARED queries against the existing CMS tables, and return a compact,
 * labelled text block for Gemini. Only public content is ever read:
 *   announcements/news/events  -> status = 'published'
 *   page_sections              -> status = 'published' on an 'active' page
 *   users / csrf_tokens / activity_logs / visitors tables are never touched.
 *
 * NOTE: getDB() uses native prepares, so no named placeholder is reused and
 * LIMITs are inlined as (int) casts.
 */
declare(strict_types=1);

require_once __DIR__ . '/knowledge.php';

final class GncbotRetriever
{
    private const MAX_CONTEXT_CHARS = 7000;

    private PDO $db;
    /** @var array<string,string> code => full name */
    private array $programNames;
    /** @var array<string,array> code => [dept_id, pdf_path] */
    private array $programRows = [];
    /** @var array<int,array> dept_id => department row */
    private array $departments = [];
    private array $pages;
    private array $inst;

    /** @var string[] */
    private array $blocks = [];
    /** @var array<string,array> url => [label,url] suggested link chips */
    private array $links = [];
    /** @var array<string,true> every URL that may legitimately appear in a reply */
    private array $allowed = [];
    public array $intents = [];
    public int $dbErrors = 0;
    public bool $offTopic = false;

    /** Words that make a question plausibly about the school even when no retriever matched. */
    private const SCHOOL_TERMS = '/\b(gnc|gncian\w*|guagua|school|college|campus|student\w*|teacher\w*|faculty|class\w*|semester|term|uniform|dress ?code|library|canteen|grades?|id|policy|policies|rules?|handbook|clearance|graduat\w+|diploma|transcript|enroll\w*|tuition|admission\w*|scholar\w*|program\w*|course\w*|dean|office|registrar|announce\w*|events?|news|vision|mission|alumni|principal|president|nstp|pampanga|home games|intramurals?)\b/u';
    public ?string $scopeProgram = null;
    private array $ctx;

    public function __construct(PDO $db, array $pageContext = [])
    {
        $this->db           = $db;
        $this->ctx          = $pageContext;
        $this->programNames = gncbot_program_names();
        $this->pages        = gncbot_page_urls();
        $this->inst         = gncbot_institution();
        $this->loadReferenceData();
        foreach ($this->pages as $p) {
            if (is_array($p)) { $this->allowed[$p['url']] = true; }
        }
    }

    // ---------------------------------------------------------------- public

    /**
     * @param string $message  current visitor message (already sanitised)
     * @param array  $history  prior turns: [['role'=>'user'|'model','text'=>...], ...]
     * @return array{context:string,links:array,allowed:array,intents:array,empty:bool}
     */
    public function retrieve(string $message, array $history = []): array
    {
        $q = $this->norm($message);

        // ---- resolve program scope: explicit > follow-up history > current page
        $explicit = $this->detectProgram($q);
        $program  = $explicit;
        $inferredFrom = null;
        if ($program === null && $this->looksLikeFollowUp($q)) {
            foreach (array_reverse($history) as $turn) {
                $p = $this->detectProgram($this->norm((string)($turn['text'] ?? '')));
                if ($p !== null) { $program = $p; $inferredFrom = 'earlier in this conversation'; break; }
            }
        }
        if ($program === null && !$this->wantsAllScope($q)) {
            $pc = (string)($this->ctx['program'] ?? '');
            if ($pc !== '' && isset($this->programNames[$pc])) {
                $program = $pc; $inferredFrom = 'the page the visitor is viewing';
            }
        }
        $this->scopeProgram = $program;

        // ---- department scope
        $dept = $this->detectDepartment($q);
        if ($dept === null && $program !== null) {
            $d = $this->programRows[$program]['dept_id'] ?? null;
            if ($d !== null && isset($this->departments[(int)$d])) { $dept = (int)$d; }
        }
        if ($dept === null && $explicit === null && !empty($this->ctx['dept'])) {
            foreach ($this->departments as $id => $d) {
                if (strcasecmp((string)$d['slug'], (string)$this->ctx['dept']) === 0) { $dept = (int)$id; }
            }
        }

        $i = $this->detectIntents($q, $program !== null);
        $this->intents = $i;

        // Off-topic guard: nothing school-related at all and no conversation to follow up on.
        $this->offTopic = !$i && $program === null && $dept === null && !$history
            && !preg_match(self::SCHOOL_TERMS, $q);

        // "College of Computing" etc. that is not a GNCILink department: tell the model explicitly.
        $unknownCollege = null;
        if ($dept === null && preg_match('/\bcollege of ([a-z][a-z ,&]{2,50}?)(?= (?:offer|have|has|programs?|courses?|dean|department)\b|[?.!,]|$)/u', $q, $m)) {
            $unknownCollege = trim($m[1]);
        }

        // ---- header notes
        $notes = ['TODAY: ' . date('l, F j, Y, g:i A') . '.'];
        if ($program !== null) {
            $notes[] = 'SCOPE: program ' . gncbot_program_display_code($program) . ' (' . ($this->programNames[$program] ?? gncbot_program_display_code($program)) . ')'
                . ($inferredFrom ? ' - inferred from ' . $inferredFrom . ', not stated in the question.' : ' - stated by the visitor.');
        }
        if (!empty($this->ctx['title']) || !empty($this->ctx['url'])) {
            $notes[] = 'VISITOR IS CURRENTLY VIEWING: ' . ($this->ctx['title'] ?? '') . ' (' . ($this->ctx['url'] ?? '') . ').';
        }

        if ($unknownCollege !== null) {
            $notes[] = 'NOTE: The visitor mentioned "College of ' . $unknownCollege . '", which does NOT match any college department in GNCILink. Say you could not find it; do not assume it exists.';
        }

        // ---- run the retrievers in priority order
        if ($program !== null)              { $this->programDetail($program); }
        if (isset($i['programs']) && $program === null) { $this->programList($dept); }
        if (isset($i['departments']))       { $this->departmentBlock($dept); }
        if (isset($i['announcements']))     { $this->announcements($program, $dept, $inferredFrom !== null && $explicit === null); }
        if (isset($i['events']))            { $this->events($program, $inferredFrom !== null && $explicit === null, $q); }
        if (isset($i['news']))              { $this->news(); }
        if (isset($i['vision_mission']))    { $this->pageSections(['vision-mission'], 'Vision and Mission'); }
        if (isset($i['about']))             { $this->pageSections(['about-us', 'history'], 'About GNC / History'); }
        if (isset($i['people']))            { $this->people($q); }
        if (isset($i['offices']) || isset($i['contact']) || isset($i['gaps'])) { $this->offices($q, isset($i['contact']) && !isset($i['offices'])); }
        if (isset($i['location']))          { $this->location(); }
        if (isset($i['basic_ed']))          { $this->link('basic_education'); }
        if (isset($i['grad']))              { $this->link('graduate_school'); }
        if (isset($i['gaps']))              { $notes[] = 'NOTE: GNCILink does not currently store tuition/fee amounts, admission requirements, enrollment dates, class schedules, curriculum/subject lists or scholarship details. If asked, say so and refer to the offices listed (if any).'; }

        // ---- nothing matched? cheap keyword search across public content
        if (!$this->blocks) { $this->keywordSearch($q); }

        $empty = !$this->blocks;
        $ctxText = implode("\n", $notes) . "\n\n" . ($empty
            ? "(No matching GNCILink information was found for this question.)\n"
            : $this->joinBlocks());

        return [
            'context' => $ctxText,
            'links'   => array_slice(array_values($this->links), 0, 3),
            'allowed' => array_keys($this->allowed),
            'intents' => array_keys($i),
            'empty'   => $empty,
            'offTopic' => $this->offTopic,
        ];
    }

    // ------------------------------------------------------ reference loading

    private function loadReferenceData(): void
    {
        try {
            $st = $this->db->query(
                "SELECT p.program_code, p.dept_id, m.file_path AS pdf_path
                   FROM programs p LEFT JOIN media_library m ON m.media_id = p.pdf_media_id
               ORDER BY p.program_code"
            );
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $this->programRows[$r['program_code']] = ['dept_id' => $r['dept_id'], 'pdf_path' => $r['pdf_path']];
            }
            $st = $this->db->query(
                "SELECT dept_id, dept_name, slug, dean_name, dean_title, dean_phone, dean_email, dean_office, dean_hours
                   FROM departments ORDER BY dept_id"
            );
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $this->departments[(int)$r['dept_id']] = $r; }
        } catch (Throwable $e) {
            $this->dbErrors++;
            error_log('[gncbot] reference load failed: ' . $e->getMessage());
        }
        // Only programs that really exist in the CMS are valid scopes.
        if ($this->programRows) {
            $this->programNames = array_intersect_key($this->programNames, $this->programRows)
                + array_fill_keys(array_diff(array_keys($this->programRows), array_keys($this->programNames)), '');
            foreach ($this->programNames as $c => $n) { if ($n === '' || $n === $c) { $this->programNames[$c] = gncbot_program_display_code($c); } }
        }
    }

    // ------------------------------------------------------------- detection

    private function norm(string $s): string
    {
        $s = mb_strtolower($s, 'UTF-8');
        $s = preg_replace('/[\x{2019}\x{2018}]/u', "'", $s) ?? $s;          // curly apostrophes
        $s = preg_replace("/'s\\b/u", '', $s) ?? $s;                       // possessives: registrar's -> registrar
        $s = preg_replace('/[^\p{L}\p{N}\-\' ]+/u', ' ', $s) ?? $s;
        return trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
    }

    private function looksLikeFollowUp(string $q): bool
    {
        return mb_strlen($q) < 60 && (bool)preg_match('/\b(it|its|that|this|those|them|there|more|also|again|same|what about|how about|and the|ito|nito|niyan)\b/u', $q)
            || str_word_count($q) <= 4;
    }

    private function wantsAllScope(string $q): bool
    {
        return (bool)preg_match('/\b(all programs|all (the )?(events|announcements)|school[- ]?wide|campus[- ]?wide|whole school|overall|in general|any program|other programs|every program)\b/u', $q);
    }

    private function detectProgram(string $q): ?string
    {
        if ($q === '' || !$this->programNames) { return null; }

        // Remove department names first so "College of Nursing" is not read as the BSN program.
        $stripped = $q;
        foreach ($this->departments as $d) {
            $stripped = str_ireplace(mb_strtolower((string)$d['dept_name']), ' ', $stripped);
        }

        $best = null; $bestPos = PHP_INT_MAX;
        $consider = function (string $code, int $pos) use (&$best, &$bestPos) {
            if ($pos < $bestPos) { $best = $code; $bestPos = $pos; }
        };

        foreach ($this->programNames as $code => $name) {
            $lc = mb_strtolower($code);
            if (preg_match('/(?<![a-z0-9])' . preg_quote($lc, '/') . '(?![a-z0-9])/u', $q, $m, PREG_OFFSET_CAPTURE)) {
                $consider($code, (int)$m[0][1]);
            }
            // alias: code without the major suffix ("bsba" for BSBA-FM) when unambiguous
            if (str_contains($lc, '-')) {
                $alias = explode('-', $lc)[0];
                $dupes = array_filter(array_keys($this->programNames), fn($c) => explode('-', mb_strtolower($c))[0] === $alias);
                if (count($dupes) === 1 && preg_match('/(?<![a-z0-9])' . preg_quote($alias, '/') . '(?![a-z0-9])/u', $q, $m, PREG_OFFSET_CAPTURE)) {
                    $consider($code, (int)$m[0][1]);
                }
            }
            // full-name tails ("information technology", "civil engineering", ...)
            $tail = mb_strtolower(preg_replace('/^bachelor of (science|arts) in /i', '', $name) ?? $name);
            $tail = trim(preg_replace('/ major in .*/', '', $tail) ?? $tail);
            if (mb_strlen($tail) >= 7) {
                $pos = mb_strpos($stripped, $tail);
                if ($pos !== false) { $consider($code, (int)$pos); }
            }
        }
        return $best;
    }

    private function detectDepartment(string $q): ?int
    {
        foreach ($this->departments as $id => $d) {
            $name = mb_strtolower((string)$d['dept_name']);
            $slug = mb_strtolower((string)$d['slug']);
            if (str_contains($q, $name)) { return (int)$id; }
            if ($slug !== '' && preg_match('/(?<![a-z0-9])' . preg_quote($slug, '/') . '(?![a-z0-9])/u', $q)) { return (int)$id; }
            // "college of nursing" style shorthand
            $short = preg_replace('/^college of /', '', $name);
            if ($short !== $name && mb_strlen($short) > 6 && preg_match('/\bcollege of ' . preg_quote($short, '/') . '\b/u', $q)) { return (int)$id; }
        }
        return null;
    }

    /** @return array<string,true> */
    private function detectIntents(string $q, bool $hasProgram): array
    {
        $rules = [
            'announcements'  => '/\b(announce\w*|advisor\w*|notice|suspen\w+|cancel\w*|bulletin|update\w*|memo|pinned)\b/u',
            'events'         => '/\b(events?|calendar|activit\w+|upcoming|happening|holidays?|celebrat\w+|intramurals?|foundation day)\b/u',
            'news'           => '/\b(news|articles?|press)\b/u',
            'programs'       => '/\b(programs?|courses?|degrees?|majors?|offer\w*|bachelor\w*|undergrad\w*)\b/u',
            'departments'    => '/\b(departments?|colleges?|deans?|faculty)\b/u',
            'vision_mission' => '/\b(vision|mission|core values?|philosophy|objectives?|goals?|mga layunin)\b/u',
            'about'          => '/\b(about gnc|about the school|history|founded|established|who is gnc|what is gnc|about us)\b/u',
            'contact'        => '/\b(contact|phone|telephone|number|e-?mail|call|reach)\b/u',
            'location'       => '/\b(where|located|location|address|directions?|map|navigat\w+|how to get|saan)\b/u',
            'offices'        => '/\b(offices?|registrar|librar\w+|clinic|guidance|business office|cashier|student affairs|osa|security|mis|nstp|printing|purchasing|sports|technician|stockroom|student services?|hours|open|office hours)\b/u',
            'people'         => '/\b(president|chairman|chairwoman|trustees?|principal|administrators?|administration|board|officers?|who is|head of|directress|director|librarian|treasurer)\b/u',
            'basic_ed'       => '/\b(basic education|elementary|junior high|senior high|grade school|high school|kindergarten|shs|jhs|montessori|grade \d+)\b/u',
            'grad'           => '/\b(graduate school|master\w*|doctor\w*|phd|thesis)\b/u',
            'gaps'           => '/\b(tuition|fees?|cost|how much|payments?|scholarships?|admission\w*|requirements?|enroll\w*|enrol\w*|apply|application|schedules?|curriculum|subjects?|deadline|entrance|exam\w*|price|magkano)\b/u',
        ];
        $out = [];
        foreach ($rules as $k => $re) {
            if (preg_match($re, $q)) { $out[$k] = true; }
        }
        // "where is the registrar" is an office question, not a campus-location one
        if (isset($out['offices']) && isset($out['location']) && !preg_match('/\b(gnc|school|college|campus)\b/u', $q)) { unset($out['location']); }
        if (isset($out['location']) && isset($out['offices'])) { /* keep both */ }
        // a program scope with no other topic -> program overview + its announcements/events
        if ($hasProgram && !array_intersect_key($out, array_flip(['announcements', 'events', 'news', 'gaps', 'contact', 'offices', 'people']))) {
            $out['announcements'] = true; $out['events'] = true;
        }
        // "latest/recent" alone -> announcements
        if (!$out && preg_match('/\b(latest|recent|newest)\b/u', $q)) { $out['announcements'] = true; }
        // "programs" with a program scope is covered by programDetail
        if ($hasProgram) { unset($out['programs']); }
        return $out;
    }

    // ------------------------------------------------------------- retrievers

    private function programList(?int $dept): void
    {
        $by = [];
        foreach ($this->programRows as $code => $r) {
            $d = (int)($r['dept_id'] ?? 0);
            if ($dept !== null && $d !== $dept) { continue; }
            $by[$d][] = $code;
        }
        if (!$by) { return; }
        $lines = [];
        foreach ($by as $d => $codes) {
            $dn = $this->departments[$d]['dept_name'] ?? 'Unassigned';
            $lines[] = $dn . ':';
            foreach ($codes as $c) { $lines[] = '  - ' . gncbot_program_display_code($c) . ' - ' . $this->programNames[$c]; }
        }
        $this->addBlock('College programs offered (' . count($this->programRows) . ' programs in total on GNCILink' . ($dept !== null ? '; filtered to one department' : '') . ')', implode("\n", $lines));
        $this->link('college_departments');
    }

    private function programDetail(string $code): void
    {
        $r = $this->programRows[$code] ?? null;
        if (!$r) { return; }
        $displayCode = gncbot_program_display_code($code);
        $name = $this->programNames[$code] ?? $displayCode;
        $lines = ["Program code: $displayCode", "Full name: $name"];
        $d = $this->departments[(int)($r['dept_id'] ?? 0)] ?? null;
        if ($d) {
            $lines[] = 'Department: ' . $d['dept_name'];
            if (!empty($d['dean_name'])) { $lines[] = 'Dean: ' . $d['dean_name'] . (!empty($d['dean_title']) ? ' (' . $d['dean_title'] . ')' : ''); }
            $contact = array_filter([
                !empty($d['dean_phone']) ? 'phone ' . $d['dean_phone'] : null,
                !empty($d['dean_email']) ? 'email ' . $d['dean_email'] : null,
                !empty($d['dean_office']) ? 'office ' . $d['dean_office'] : null,
                !empty($d['dean_hours']) ? 'hours ' . $this->oneLine($d['dean_hours']) : null,
            ]);
            if ($contact) { $lines[] = 'Dean\'s office contact: ' . implode('; ', $contact); }
        }
        if (!empty($r['pdf_path']) && !str_contains((string)$r['pdf_path'], '..')) {
            $pdf = ($r['pdf_path'][0] === '/') ? gncbot_url((string)$r['pdf_path']) : (string)$r['pdf_path'];
            $lines[] = 'Program PDF available: ' . $pdf;
            $this->allowed[$pdf] = true;
            $this->addLink('Program PDF (' . $displayCode . ')', $pdf);
        }
        $lines[] = 'Program events page: ' . gncbot_program_events_url($code);
        $this->allowed[gncbot_program_events_url($code)] = true;
        $lines[] = '(GNCILink stores no description, curriculum, tuition or admission details for this program.)';
        $this->addBlock("Program: $displayCode", implode("\n", $lines));
        $this->link('college_departments');
    }

    private function departmentBlock(?int $dept): void
    {
        $lines = [];
        foreach ($this->departments as $id => $d) {
            if ($dept !== null && (int)$id !== $dept) { continue; }
            $progs = [];
            foreach ($this->programRows as $c => $r) { if ((int)$r['dept_id'] === (int)$id) { $progs[] = gncbot_program_display_code($c); } }
            $line = '- ' . $d['dept_name'] . ($d['slug'] ? ' (' . strtoupper($d['slug']) . ')' : '')
                  . ($d['dean_name'] ? '; Dean: ' . $d['dean_name'] : '')
                  . ($progs ? '; programs: ' . implode(', ', $progs) : '');
            if ($dept !== null) {
                $c = array_filter([$d['dean_phone'] ?? '', $d['dean_email'] ?? '', $d['dean_office'] ?? '', $this->oneLine((string)($d['dean_hours'] ?? ''))]);
                if ($c) { $line .= '; contact: ' . implode(' | ', $c); }
            }
            $lines[] = $line;
        }
        if ($lines) { $this->addBlock('College departments', implode("\n", $lines)); $this->link('college_departments'); }
    }

    private function announcements(?string $program, ?int $dept, bool $inferred): void
    {
        $limit = 5; $rows = []; $label = 'Latest published announcements';
        try {
            $sql = "SELECT a.title, a.slug, a.content, a.program, a.is_pinned, c.category_name,
                           COALESCE(a.published_at, a.created_at) AS posted
                      FROM announcements a LEFT JOIN categories c ON c.category_id = a.category_id
                     WHERE a.status = 'published'";
            $args = [];
            if ($program !== null) { $sql .= ' AND a.program = ?'; $args[] = $program; }
            elseif ($dept !== null) { $sql .= ' AND a.dept_id = ?'; $args[] = $dept; }
            $sql .= ' ORDER BY posted DESC LIMIT ' . $limit;
            $st = $this->db->prepare($sql); $st->execute($args);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            if ($program !== null) { $label .= " tagged for $program"; }
            // inferred-from-page scope with nothing found: fall back to school-wide, clearly labelled
            if (!$rows && $program !== null && $inferred) {
                $st = $this->db->prepare("SELECT a.title, a.slug, a.content, a.program, a.is_pinned, c.category_name,
                        COALESCE(a.published_at, a.created_at) AS posted
                      FROM announcements a LEFT JOIN categories c ON c.category_id = a.category_id
                     WHERE a.status = 'published' ORDER BY posted DESC LIMIT $limit");
                $st->execute();
                $rows = $st->fetchAll(PDO::FETCH_ASSOC);
                $label = "No announcements are tagged for $program; these are the latest school-wide/other announcements";
            }
        } catch (Throwable $e) { $this->dbFail($e); }

        if ($rows) {
            $out = [];
            foreach ($rows as $n => $r) {
                $url = gncbot_announcement_url((string)$r['slug']);
                $this->allowed[$url] = true;
                $out[] = ($n + 1) . '. "' . $this->clean((string)$r['title'], 120) . '"'
                    . ($r['category_name'] ? ' [' . $r['category_name'] . ']' : '')
                    . ' - program tag: ' . $r['program'] . ' - posted ' . $this->fmtDate((string)$r['posted'])
                    . ($r['is_pinned'] ? ' (pinned)' : '') . ' - URL: ' . $url
                    . "\n   Summary: " . $this->clean((string)$r['content'], 220);
                if ($n === 0) { $this->addLink('Read: ' . $this->clean((string)$r['title'], 40), $url); }
            }
            $this->addBlock($label . ' (website announcements)', implode("\n", $out));
        } elseif ($program !== null) {
            $this->addBlock("Announcements tagged for $program", "(none published)");
        }

        // Official Facebook posts are shown as announcements on the homepage; no program tag exists for them.
        if ($program === null) { $this->facebookPosts(5); }
        $this->link('news_announcements');
    }

    private function facebookPosts(int $limit): void
    {
        try {
            $st = $this->db->query(
                "SELECT f.message, f.permalink_url, f.created_time, f.is_pinned, c.category_name
                   FROM fb_posts_cache f LEFT JOIN categories c ON c.category_id = f.category_id
               ORDER BY f.created_time DESC LIMIT " . (int)$limit
            );
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $n => $r) {
                $url = (string)$r['permalink_url'];
                if ($url !== '' && preg_match('#^https://(www\.)?facebook\.com/#', $url)) { $this->allowed[$url] = true; } else { $url = ''; }
                $out[] = ($n + 1) . '. ' . ($r['category_name'] ? '[' . $r['category_name'] . '] ' : '')
                    . 'posted ' . $this->fmtDate((string)$r['created_time']) . ($r['is_pinned'] ? ' (pinned)' : '') . ($url ? ' - URL: ' . $url : '')
                    . "\n   Text: " . $this->clean((string)$r['message'], 320);
            }
            if ($out) { $this->addBlock('Latest posts from the official GNC Facebook page (shown as announcements on GNCILink)', implode("\n", $out)); }
        } catch (Throwable $e) { $this->dbFail($e); }
    }

    private function events(?string $program, bool $inferred, string $q): void
    {
        $today = date('Y-m-d 00:00:00');
        $base = "SELECT e.title, e.location, e.start_date, e.end_date, e.program, c.category_name
                   FROM events e LEFT JOIN categories c ON c.category_id = e.category_id
                  WHERE e.status = 'published'";
        $up = []; $past = [];
        try {
            $filter = $program !== null ? ' AND e.program = ?' : '';
            $args   = $program !== null ? [$today, $program] : [$today];
            // upcoming OR still running (multi-day) - the homepage calendar uses the same overlap idea
            $st = $this->db->prepare($base . " AND COALESCE(e.end_date, e.start_date) >= ?" . ($program !== null ? ' AND e.program = ?' : '') . ' ORDER BY e.start_date ASC LIMIT 6');
            $st->execute($args); $up = $st->fetchAll(PDO::FETCH_ASSOC);

            if (!$up || preg_match('/\b(latest|recent|past|previous|last)\b/u', $q)) {
                $st = $this->db->prepare($base . " AND COALESCE(e.end_date, e.start_date) < ?" . ($program !== null ? ' AND e.program = ?' : '') . ' ORDER BY e.start_date DESC LIMIT 3');
                $st->execute($args); $past = $st->fetchAll(PDO::FETCH_ASSOC);
            }
            // page-inferred program with nothing at all: show school-wide instead, labelled
            if ($program !== null && $inferred && !$up && !$past) {
                $st = $this->db->prepare($base . " AND COALESCE(e.end_date, e.start_date) >= ? ORDER BY e.start_date ASC LIMIT 6");
                $st->execute([$today]); $up = $st->fetchAll(PDO::FETCH_ASSOC);
                $note = "No events are tagged for $program; showing school-wide/other upcoming events";
            }
        } catch (Throwable $e) { $this->dbFail($e); return; }

        $fmt = function (array $r): string {
            $s = '"' . $this->clean((string)$r['title'], 100) . '"'
               . ($r['category_name'] ? ' [' . $r['category_name'] . ']' : '')
               . ' - ' . $this->fmtRange((string)$r['start_date'], $r['end_date'] ?: null)
               . ($r['location'] ? ' - at ' . $this->clean((string)$r['location'], 80) : '')
               . ' - program tag: ' . $r['program'];
            return $s;
        };
        $scope = $program !== null ? " tagged for $program" : '';
        if ($up) {
            $this->addBlock(($note ?? "Upcoming/ongoing published events$scope"), implode("\n", array_map(fn($r, $n) => ($n + 1) . '. ' . $fmt($r), $up, array_keys($up))));
        } else {
            $this->addBlock("Upcoming events$scope", '(none - there are no upcoming published events)');
        }
        if ($past) {
            $this->addBlock("Most recent PAST events$scope (already happened)", implode("\n", array_map(fn($r, $n) => ($n + 1) . '. ' . $fmt($r), $past, array_keys($past))));
        }
        if ($program !== null) { $u = gncbot_program_events_url($program); $this->allowed[$u] = true; $this->addLink($program . ' events', $u); }
        else { $this->link('events'); }
    }

    private function news(): void
    {
        try {
            $st = $this->db->query(
                "SELECT n.title, n.slug, n.content, n.author, COALESCE(n.published_at, n.created_at) AS posted
                   FROM news n WHERE n.status = 'published' ORDER BY posted DESC LIMIT 3"
            );
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $n => $r) {
                $url = gncbot_news_url((string)$r['slug']); $this->allowed[$url] = true;
                $out[] = ($n + 1) . '. "' . $this->clean((string)$r['title'], 120) . '" - posted ' . $this->fmtDate((string)$r['posted'])
                       . ' - URL: ' . $url . "\n   Summary: " . $this->clean((string)$r['content'], 200);
            }
            if ($out) { $this->addBlock('Latest published news articles', implode("\n", $out)); $this->link('news_announcements'); }
        } catch (Throwable $e) { $this->dbFail($e); }
    }

    private function pageSections(array $slugs, string $title): void
    {
        try {
            $in = implode(',', array_fill(0, count($slugs), '?'));
            $st = $this->db->prepare(
                "SELECT s.title, s.subtitle, s.content, p.slug
                   FROM page_sections s JOIN pages p ON p.page_id = s.page_id
                  WHERE p.status = 'active' AND s.status = 'published' AND p.slug IN ($in)
               ORDER BY p.display_order, s.display_order"
            );
            $st->execute($slugs);
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $txt = $this->clean((string)$r['content'], 900);
                if ($txt !== '') { $out[] = ($r['title'] ?: 'Section') . ': ' . $txt; }
            }
            if ($out) { $this->addBlock($title . ' (official GNCILink page content)', implode("\n", $out)); }
        } catch (Throwable $e) { $this->dbFail($e); }
        $key = str_contains($slugs[0], 'vision') ? 'vision_mission' : 'about';
        $this->link($key);
    }

    private function people(string $q): void
    {
        try {
            $want = [];
            if (preg_match('/\b(trustees?|chairman|chairwoman)\b/u', $q)) { $want[] = 'board-of-trustees'; }
            if (preg_match('/\b(officers?|treasurer|secretary)\b/u', $q))  { $want[] = 'corporate-officers'; }
            if (preg_match('/\b(deans?|principal|president|administrat\w+|head|librarian)\b/u', $q) || !$want) { $want[] = 'board-of-administration'; }
            $in = implode(',', array_fill(0, count($want), '?'));
            $st = $this->db->prepare(
                "SELECT s.title AS section, p.name, p.role
                   FROM admin_people p JOIN admin_sections s ON s.section_key = p.section_key
                  WHERE p.section_key IN ($in) ORDER BY s.display_order, p.display_order"
            );
            $st->execute($want);
            $by = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $by[$r['section']][] = $r['name'] . ' - ' . $this->clean((string)$r['role'], 110); }
            $out = [];
            foreach ($by as $sec => $list) { $out[] = $sec . ":\n  - " . implode("\n  - ", $list); }
            if ($out) { $this->addBlock('School leadership (Administration page)', implode("\n", $out)); }
        } catch (Throwable $e) { $this->dbFail($e); }
        $this->link('administration');
    }

    private function offices(string $q, bool $contactOnly): void
    {
        $syn = ['cashier' => 'business office', 'payment' => 'payment', 'fee' => 'payment', 'fees' => 'payment', 'tuition' => 'payment',
                'scholarship' => 'scholarship', 'osa' => 'student affairs', 'nurse' => 'clinic', 'doctor' => 'clinic', 'medical' => 'clinic',
                'health' => 'clinic', 'counselor' => 'guidance', 'counseling' => 'guidance', 'enroll' => 'enrollment', 'enrolment' => 'enrollment',
                'admission' => 'admission', 'requirements' => 'records', 'schedule' => 'records', 'uniform' => 'uniform', 'id' => 'id printing',
                'parking' => 'parking', 'guard' => 'security', 'computer' => 'computer', 'books' => 'book', 'transcript' => 'records', 'tor' => 'records'];
        $stop = array_flip(['what','where','when','which','who','how','the','and','for','are','you','can','does','have','has','with','from','tell','about','please','there','their','gnc','school','college','open','hours','contact','phone','number','email','office','offices','services','service','student','location','located','is','of','in','to','a','an','me','my','do','i']);
        $tokens = [];
        foreach (explode(' ', $q) as $t) {
            if ($t === '' || isset($stop[$t])) { continue; }
            $tokens[] = $syn[$t] ?? $t;
        }
        try {
            $rows = $this->db->query(
                "SELECT office_name, head_name, head_role, phone, email, location, hours, services
                   FROM admin_offices ORDER BY display_order"
            )->fetchAll(PDO::FETCH_ASSOC);
            $legacy = $this->db->query("SELECT office_name, email, contact_no AS phone, location, office_hours AS hours, description AS services FROM offices")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($legacy as $l) { $rows[] = $l + ['head_name' => '', 'head_role' => '']; }
        } catch (Throwable $e) { $this->dbFail($e); return; }
        if (!$rows) { return; }

        $scored = [];
        foreach ($rows as $r) {
            $name = mb_strtolower((string)$r['office_name']);
            $hay  = mb_strtolower($r['office_name'] . ' ' . ($r['head_role'] ?? '') . ' ' . ($r['services'] ?? ''));
            $s = 0;
            foreach ($tokens as $t) {
                if (mb_strlen($t) < 3 && $t !== 'id') { continue; }
                if (str_contains($name, $t)) { $s += 3; } elseif (str_contains($hay, $t)) { $s += 1; }
            }
            if ($s > 0) { $scored[] = [$s, $r]; }
        }
        usort($scored, fn($a, $b) => $b[0] <=> $a[0]);
        $top = array_slice($scored, 0, 3);

        if ($top && $top[0][0] >= 1) {
            $out = [];
            foreach ($top as [$s, $r]) {
                if ($s < $top[0][0] - 1 && count($out) >= 1) { continue; }
                $svc = array_filter(array_map('trim', preg_split('/\R|\\\\r\\\\n/u', (string)$r['services']) ?: []));
                $out[] = '- ' . $r['office_name']
                    . ($r['head_name'] ? ' | Head: ' . $r['head_name'] . ($r['head_role'] ? ' (' . $this->clean((string)$r['head_role'], 80) . ')' : '') : '')
                    . ($r['phone'] ? ' | Phone: ' . $r['phone'] : '')
                    . ($r['email'] ? ' | Email: ' . $r['email'] : '')
                    . ($r['location'] ? ' | Location on campus: ' . $r['location'] : '')
                    . ($r['hours'] ? ' | Hours: ' . $this->oneLine((string)$r['hours']) : '')
                    . ($svc ? ' | Services: ' . implode('; ', array_slice($svc, 0, 8)) : '');
            }
            $this->addBlock('Matching GNC offices (Student Services / Administration)', implode("\n", $out));
        } elseif (!$contactOnly || !$tokens) {
            // Generic "what offices / services exist" question: compact directory
            $out = array_map(fn($r) => '- ' . $r['office_name'] . ($r['location'] ? ' (' . $r['location'] . ')' : ''), $rows);
            $this->addBlock('Directory of GNC offices (ask about a specific office for its phone, email, hours and services)', implode("\n", $out));
        }
        $this->link('student_services');
        $this->link('contact');
    }

    private function location(): void
    {
        $i = $this->inst;
        $this->addBlock('Campus location and general contact',
            'Institution: ' . $i['name'] . "\nAddress: " . $i['location'] . "\nGeneral office hours: " . $i['hours'] .
            "\nGeneral phone: " . $i['phone'] . "\nGeneral email: " . $i['email'] .
            "\n(The interactive Campus Navigator on GNCILink shows buildings and offices on campus.)");
        $this->link('campus_navigator');
        $this->link('contact');
        $this->link('facebook');
    }

    /** Last resort: LIKE search over public text so odd questions can still hit real content. */
    private function keywordSearch(string $q): void
    {
        $stop = array_flip(['what','where','when','which','who','how','the','and','for','are','you','can','does','have','has','with','from','tell','about','please','there','their','gnc','guagua','national','colleges','school','is','of','in','to','a','an','me','my','do','i','any','information','info','know']);
        $kw = [];
        foreach (explode(' ', $q) as $t) {
            if (mb_strlen($t) >= 4 && !isset($stop[$t])) { $kw[$t] = true; }
        }
        $kw = array_slice(array_keys($kw), 0, 3);
        if (!$kw) { return; }

        $like = fn(string $col) => '(' . implode(' OR ', array_fill(0, count($kw), "$col LIKE ?")) . ')';
        $args = array_map(fn($k) => '%' . addcslashes($k, '%_\\') . '%', $kw);
        try {
            $st = $this->db->prepare("SELECT title, slug, content, COALESCE(published_at, created_at) AS posted FROM announcements
                WHERE status='published' AND (" . $like('title') . ' OR ' . $like('content') . ') ORDER BY posted DESC LIMIT 3');
            $st->execute(array_merge($args, $args));
            $out = [];
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $url = gncbot_announcement_url((string)$r['slug']); $this->allowed[$url] = true;
                $out[] = '- Announcement "' . $this->clean((string)$r['title'], 100) . '" (' . $this->fmtDate((string)$r['posted']) . ') URL: ' . $url . ' - ' . $this->clean((string)$r['content'], 180);
            }
            $st = $this->db->prepare("SELECT title, slug, content, COALESCE(published_at, created_at) AS posted FROM news
                WHERE status='published' AND (" . $like('title') . ' OR ' . $like('content') . ') ORDER BY posted DESC LIMIT 2');
            $st->execute(array_merge($args, $args));
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $url = gncbot_news_url((string)$r['slug']); $this->allowed[$url] = true;
                $out[] = '- News "' . $this->clean((string)$r['title'], 100) . '" (' . $this->fmtDate((string)$r['posted']) . ') URL: ' . $url . ' - ' . $this->clean((string)$r['content'], 180);
            }
            $st = $this->db->prepare('SELECT message, permalink_url, created_time FROM fb_posts_cache WHERE ' . $like('message') . ' ORDER BY created_time DESC LIMIT 2');
            $st->execute($args);
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $url = (string)$r['permalink_url'];
                if (preg_match('#^https://(www\.)?facebook\.com/#', $url)) { $this->allowed[$url] = true; } else { $url = ''; }
                $out[] = '- Facebook post (' . $this->fmtDate((string)$r['created_time']) . ')' . ($url ? ' URL: ' . $url : '') . ' - ' . $this->clean((string)$r['message'], 200);
            }
            $st = $this->db->prepare('SELECT question, answer FROM faqs WHERE ' . $like('question') . ' OR ' . $like('answer') . ' ORDER BY display_order LIMIT 2');
            $st->execute(array_merge($args, $args));
            foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
                $out[] = '- FAQ: Q: ' . $this->clean((string)$r['question'], 120) . ' A: ' . $this->clean((string)$r['answer'], 260);
            }
            if ($out) { $this->addBlock('Possibly relevant published GNCILink content (keyword match: ' . implode(', ', $kw) . ')', implode("\n", $out)); }
        } catch (Throwable $e) { $this->dbFail($e); }
    }

    // ---------------------------------------------------------------- helpers

    private function link(string $key): void
    {
        $p = $this->pages[$key] ?? null;
        if (is_array($p)) { $this->addLink($p['label'], $p['url']); }
    }

    private function addLink(string $label, string $url): void
    {
        if (!isset($this->links[$url])) { $this->links[$url] = ['label' => $label, 'url' => $url]; }
        $this->allowed[$url] = true;
    }

    private function addBlock(string $title, string $body): void
    {
        $this->blocks[] = "### $title\n$body\n";
    }

    private function joinBlocks(): string
    {
        $out = ''; $len = 0;
        foreach ($this->blocks as $b) {
            if ($len + strlen($b) > self::MAX_CONTEXT_CHARS) {
                $room = self::MAX_CONTEXT_CHARS - $len;
                if ($room > 400) { $out .= mb_strcut($b, 0, $room) . "\n[truncated]\n"; }
                break;
            }
            $out .= $b . "\n"; $len += strlen($b) + 1;
        }
        return $out;
    }

    private function dbFail(Throwable $e): void
    {
        $this->dbErrors++;
        error_log('[gncbot] query failed: ' . $e->getMessage());
    }

    /** Strip HTML/entities/control chars and angle brackets (prompt-delimiter safety), then trim. */
    private function clean(string $s, int $max): string
    {
        $s = html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $s = preg_replace('/[\x00-\x1F\x7F<>]+/u', ' ', $s) ?? $s;
        $s = trim(preg_replace('/\s+/u', ' ', $s) ?? $s);
        return mb_strlen($s) > $max ? rtrim(mb_substr($s, 0, $max)) . '...' : $s;
    }

    private function oneLine(string $s): string
    {
        return trim(preg_replace('/\s*(\R|\\\\r\\\\n)\s*/u', '; ', $s) ?? $s);
    }

    private function fmtDate(string $dt): string
    {
        $t = strtotime($dt);
        return $t ? date('M j, Y', $t) : $dt;
    }

    private function fmtRange(string $start, ?string $end): string
    {
        $s = strtotime($start);
        if (!$s) { return 'date not set'; }
        $hasTime = fn(int $t) => date('H:i:s', $t) !== '00:00:00';
        $out = date('D, M j, Y', $s) . ($hasTime($s) ? ', ' . date('g:i A', $s) : '');
        if ($end && ($e = strtotime($end)) && $e !== $s) {
            $out .= date('Y-m-d', $e) === date('Y-m-d', $s)
                ? ' to ' . date('g:i A', $e)
                : ' to ' . date('D, M j, Y', $e) . ($hasTime($e) ? ', ' . date('g:i A', $e) : '');
        }
        return $out;
    }
}
