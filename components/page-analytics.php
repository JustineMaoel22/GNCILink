<?php
/**
 * Page analytics helpers: which pages are visited most.
 *
 * Tables (created automatically on first use, or import page_analytics.sql):
 *   page_visits        one row per page: URL, title, total visit_count, last_visited
 *   page_visits_daily  views per page per day (for "today" and "by date")
 * Unique visitors come from the existing site_visitors table (visitor-counter.php).
 *
 * Nothing here stores IP addresses or personal data. Only page_url, page_title
 * and counts are kept per page.
 */

require_once __DIR__ . '/visitor-counter.php';

if (!function_exists('gncPageNow')) {

    function gncPageNow(): string {
        return (new DateTime('now', new DateTimeZone('Asia/Manila')))->format('Y-m-d H:i:s');
    }

    function gncPageEnsureTables(PDO $db): void {
        $db->exec("
            CREATE TABLE IF NOT EXISTS page_visits (
                page_id      INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                page_url     VARCHAR(255) NOT NULL,
                page_title   VARCHAR(255) NOT NULL DEFAULT '',
                visit_count  INT UNSIGNED NOT NULL DEFAULT 0,
                last_visited DATETIME     NOT NULL,
                created_at   DATETIME     NOT NULL,
                UNIQUE KEY uq_page_url (page_url),
                KEY idx_visit_count (visit_count)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $db->exec("
            CREATE TABLE IF NOT EXISTS page_visits_daily (
                page_id    INT UNSIGNED NOT NULL,
                visit_date DATE         NOT NULL,
                views      INT UNSIGNED NOT NULL DEFAULT 0,
                PRIMARY KEY (page_id, visit_date),
                KEY idx_visit_date (visit_date)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    /**
     * Turns a raw URL/path into a stable page identifier, or null if it should
     * not be tracked (admin, api, assets, files, malformed input).
     * Query strings are ignored except for entity identifiers. Home is excluded.
     */
    function gncPageNormalizePath(string $raw): ?string {
        $path = parse_url($raw, PHP_URL_PATH);
        if (!is_string($path) || $path === '') return null;

        $path = strtolower(trim($path));
        $path = preg_replace('#/+#', '/', $path);
        if ($path === '' || $path[0] !== '/' || strlen($path) > 255) return null;
        if (strpos($path, '..') !== false) return null;
        if (!preg_match('#^[a-z0-9/_\-.~%]+$#', $path)) return null;

        // /index.php and /folder/index.php -> / and /folder
        $path = preg_replace('#/index\.(php|html?)/?$#', '/', $path);
        if ($path !== '/') $path = rtrim($path, '/');
        if ($path === '') $path = '/';
        if ($path === '/' || $path === '/index') return null;

        // Only pages, not files (images, css, pdf...)
        $base = basename($path);
        if (strpos($base, '.') !== false && !preg_match('/\.(php|html?)$/', $base)) return null;

        foreach (['/admin', '/api', '/assets', '/config', '/components', '/uploads', '/vendor'] as $skip) {
            if ($path === $skip || strpos($path, $skip . '/') === 0) return null;
        }
        // Preserve entity identity for dynamic pages while dropping tracking and editor parameters.
        $query = [];
        parse_str((string)(parse_url($raw, PHP_URL_QUERY) ?? ''), $query);
        // Legacy department wrappers render the same database-managed department.
        $departmentAliases = ['cased'=>'cased', 'cba'=>'cba', 'coa'=>'coa', 'coe'=>'coe', 'comlsp'=>'camp', 'conursing'=>'conursing'];
        if (preg_match('~^/pages/academics/college/departments/([a-z0-9-]+)(?:\.php)?$~D', $path, $alias)
            && isset($departmentAliases[$alias[1]])) {
            $path = '/pages/academics/college/college-department.php';
            $query['slug'] = $departmentAliases[$alias[1]];
        }
        // Keep historical counts and entity identity for clean academic URLs.
        if (preg_match('~^/pages/academics/college/(program|college-department)/([a-z0-9-]+)$~D', $path, $programMatch)) {
            $query['slug'] = $programMatch[2];
            $path = '/pages/academics/college/' . $programMatch[1] . '.php';
        } elseif (in_array($path, ['/pages/academics/college/program', '/pages/academics/college/college-department'], true)) {
            $path .= '.php';
        }
        $keys = in_array($path, ['/pages/academics/college/program.php', '/pages/academics/college/college-department.php'], true)
            ? ['slug'] : (in_array($path, ['/news-article.php', '/announcement-article.php', '/event.php'], true) ? ['id', 'slug'] : []);
        $keep = [];
        foreach ($keys as $key) {
            if (!isset($query[$key]) || !is_string($query[$key])) continue;
            if ($key === 'id' && !preg_match('/^[1-9][0-9]{0,9}$/', $query[$key])) continue;
            if ($key === 'slug' && !preg_match('/^[a-zA-Z0-9_-]{1,180}$/', $query[$key])) continue;
            $keep[$key] = $query[$key];
        }
        // Static clean URLs and their PHP URLs share a single analytics identity.
        if (!$keys) $path = preg_replace('/\.php$/', '', $path);
        $result = $path . ($keep ? '?' . http_build_query($keep, '', '&', PHP_QUERY_RFC3986) : '');
        return strlen($result) <= 255 ? $result : null;
    }

    function gncPageCleanTitle(string $title, string $path): string {
        $title = trim(preg_replace('/\s+/', ' ', strip_tags($title)));
        if ($title === '') {
            if ($path === '/') return 'Home';
            $name = preg_replace('/\.(php|html?)$/', '', basename($path));
            $title = ucwords(str_replace(['-', '_'], ' ', $name));
        }
        if (function_exists('mb_substr')) {
            return mb_substr($title, 0, 255);
        }
        // No mbstring: cut by bytes, then drop any half-cut UTF-8 character at the end.
        $title = substr($title, 0, 255);
        return preg_replace('/[\x80-\xBF]*$/', '', preg_replace('/[\xC0-\xFF][\x80-\xBF]{0,2}$/', '', $title)) ?: $title;
    }

    /** Counts one view of a page (total + today). Returns false if not trackable. */
    function gncPageRecord(PDO $db, string $rawPath, string $rawTitle): bool {
        $path = gncPageNormalizePath($rawPath);
        if ($path === null) return false;
        $title = gncPageCleanTitle($rawTitle, $path);
        // Missing program pages must not become popular-page recommendations.
        if (strcasecmp($title, 'Program not found') === 0) return false;

        $attempt = function () use ($db, $path, $title): void {
            $now   = gncPageNow();
            $today = substr($now, 0, 10);

            $stmt = $db->prepare("
                INSERT INTO page_visits (page_url, page_title, visit_count, last_visited, created_at)
                VALUES (:url, :title, 1, :now, :created)
                ON DUPLICATE KEY UPDATE
                    visit_count  = visit_count + 1,
                    last_visited = :now2,
                    page_title   = :title2
            ");
            $stmt->execute([
                ':url' => $path, ':title' => $title, ':now' => $now, ':created' => $now,
                ':now2' => $now, ':title2' => $title,
            ]);

            $sel = $db->prepare("SELECT page_id FROM page_visits WHERE page_url = :url");
            $sel->execute([':url' => $path]);
            $pageId = (int) $sel->fetchColumn();

            $daily = $db->prepare("
                INSERT INTO page_visits_daily (page_id, visit_date, views)
                VALUES (:id, :d, 1)
                ON DUPLICATE KEY UPDATE views = views + 1
            ");
            $daily->execute([':id' => $pageId, ':d' => $today]);
        };

        try {
            $attempt();
        } catch (PDOException $e) {
            // Table missing on first use: create and retry once.
            gncPageEnsureTables($db);
            $attempt();
        }
        return true;
    }

    /**
     * Most visited pages, for the admin table AND the future public
     * "Popular Pages" section (returns no visitor information).
     *
     * @param string[] $excludeUrls e.g. ['/'] to leave the homepage out
     */
    function gncPopularPages(PDO $db, int $limit = 5, array $excludeUrls = []): array {
        $excludeUrls = array_map('gncPageNormalizePath', $excludeUrls);
        $rows = array_filter(gncPageTotals($db), function (array $row) use ($excludeUrls): bool {
            return !in_array($row['page_url'], $excludeUrls, true);
        });
        return array_slice(array_values($rows), 0, max(1, min(100, $limit)));
    }

    /** Combine historical URL aliases without changing or deleting stored counts. */
    function gncPageTotals(PDO $db): array {
        try {
            $rows = $db->query('SELECT page_url, page_title, visit_count, last_visited FROM page_visits')->fetchAll(PDO::FETCH_ASSOC);
            $pages = [];
            foreach ($rows as $row) {
                $path = gncPageNormalizePath($row['page_url']);
                if ($path === null || strcasecmp(trim($row['page_title']), 'Program not found') === 0) continue;
                $row['page_url'] = $path;
                $row['visit_count'] = (int) $row['visit_count'];
                if (isset($pages[$path])) {
                    $count = $pages[$path]['visit_count'] + $row['visit_count'];
                    if ($row['last_visited'] > $pages[$path]['last_visited']) $pages[$path] = $row;
                    $pages[$path]['visit_count'] = $count;
                } else {
                    $pages[$path] = $row;
                }
            }
            $pages = array_values($pages);
            usort($pages, function (array $a, array $b): int {
                return ($b['visit_count'] <=> $a['visit_count']) ?: strcmp($b['last_visited'], $a['last_visited']);
            });
            return $pages;
        } catch (Throwable $e) {
            return [];
        }
    }

    /** Admin dashboard cards: total views, unique visitors, today's views, top page. */
    function gncAnalyticsSummary(PDO $db): array {
        $out = ['total_views' => 0, 'unique_visitors' => 0, 'today_views' => 0, 'most_visited' => null];
        try {
            $top = gncPageTotals($db);
            $out['total_views'] = (int) array_sum(array_column($top, 'visit_count'));
            $out['today_views'] = (int) array_sum(gncViewsByDate($db, 1));
            $out['most_visited'] = $top[0] ?? null;
        } catch (Throwable $e) {}
        try {
            $out['unique_visitors'] = (int) $db->query("SELECT COUNT(DISTINCT visitor_hash) FROM site_visitors")->fetchColumn();
        } catch (Throwable $e) {}
        return $out;
    }

    /** Page views per day for the last $days days (zero-filled), oldest first. */
    function gncViewsByDate(PDO $db, int $days = 30): array {
        $days  = max(1, min(365, $days));
        $today = new DateTime(gncVisitorToday());
        $from  = (clone $today)->modify('-' . ($days - 1) . ' days');

        $rows = [];
        try {
            $stmt = $db->prepare("
                SELECT d.visit_date, p.page_url, SUM(d.views) AS views
                FROM page_visits_daily d
                INNER JOIN page_visits p ON p.page_id = d.page_id
                WHERE d.visit_date >= :from AND LOWER(TRIM(p.page_title)) <> 'program not found'
                GROUP BY d.visit_date, p.page_url
            ");
            $stmt->execute([':from' => $from->format('Y-m-d')]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
                if (gncPageNormalizePath($r['page_url']) === null) continue;
                $rows[$r['visit_date']] = ($rows[$r['visit_date']] ?? 0) + (int) $r['views'];
            }
        } catch (Throwable $e) {}

        $out = [];
        for ($d = clone $from; $d <= $today; $d->modify('+1 day')) {
            $key = $d->format('Y-m-d');
            $out[$key] = $rows[$key] ?? 0;
        }
        return $out;
    }

    /** "Today", "Yesterday" or "Oct 1, 2026" for the Last Visited column. */
    function gncFormatLastVisited(string $datetime): string {
        $day   = substr($datetime, 0, 10);
        $today = gncVisitorToday();
        if ($day === $today) return 'Today';
        if ($day === (new DateTime($today))->modify('-1 day')->format('Y-m-d')) return 'Yesterday';
        return (new DateTime($day))->format('M j, Y');
    }
}
