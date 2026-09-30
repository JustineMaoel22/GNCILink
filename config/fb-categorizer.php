<?php
/**
 * Facebook post auto-categorizer  (place in /config/fb-categorizer.php)
 *
 * Guesses a category for a Facebook post from its text using weighted
 * keywords (English + Filipino). Returns a category NAME that exists in
 * the `categories` table (Emergency, Holiday, Reminder, School Event),
 * or null for "None".
 *
 * Keyword syntax: a plain word/phrase must match as a whole word
 * ("eid" will not match "provide"). A trailing * means "starts with"
 * ("celebrat*" matches celebrate, celebrating, celebration).
 */

if (!function_exists('gncNormalizeForSearch')) {
    /** Turns styled "fancy font" Unicode letters into plain lowercase text. */
    function gncNormalizeForSearch(string $text): string {
        if (class_exists('Normalizer')) {
            $n = Normalizer::normalize($text, Normalizer::FORM_KC);
            if ($n !== false) $text = $n;
        }
        $text = preg_replace_callback('/[\x{1D400}-\x{1D7FF}]/u', function ($m) {
            $cp = mb_ord($m[0], 'UTF-8');
            if ($cp >= 0x1D7CE) return (string)(($cp - 0x1D7CE) % 10);
            if ($cp <= 0x1D6A3) {
                $i = ($cp - 0x1D400) % 52;
                return $i < 26 ? chr(65 + $i) : chr(97 + $i - 26);
            }
            return $m[0];
        }, $text) ?? $text;
        return mb_strtolower($text, 'UTF-8');
    }
}

/** keyword => weight. Tune these against your real posts. */
function gncCategoryKeywords(): array {
    return [
        'Emergency' => [
            'emergency' => 5, 'urgent' => 3, 'typhoon' => 5, 'bagyo' => 5,
            'signal no' => 5, 'class suspension' => 6, 'suspen*' => 4,
            'walang pasok' => 6, 'no office work' => 5, 'online class*' => 4,
            'flood*' => 4, 'baha' => 3, 'earthquake' => 5, 'lindol' => 5,
            'evacuat*' => 5, 'power interruption' => 4, 'ashfall' => 5, 'lahar' => 5,
        ],
        'Holiday' => [
            'holiday*' => 6, 'non-working' => 6, 'non working' => 6,
            'national heroes day' => 6, 'independence day' => 6, 'bonifacio' => 5,
            'rizal day' => 5, 'christmas' => 4, 'new year' => 4, 'eid' => 5,
            'good friday' => 5, 'maundy' => 5, 'ninoy aquino' => 5,
            'all saints' => 5, 'all souls' => 5, 'labor day' => 5,
        ],
        'Reminder' => [
            'reminder*' => 6, 'paalala' => 6, 'deadline*' => 5, 'last day' => 5,
            'huling araw' => 5, 'enrol*' => 4, 'apply na' => 4, 'payment*' => 3,
            'exam' => 3, 'exams' => 3, 'examination*' => 3, 'requirement*' => 3,
            'submit' => 3, 'schedule' => 2, 'fitting' => 3, 'releasing' => 3,
            'please bring' => 3, 'dont forget' => 4, "don't forget" => 4,
            'save niyo' => 3, 'kindly' => 2,
            'policy' => 3, 'policies' => 3, 'uniform' => 3, 'guidelines' => 3, 'tomorrow' => 2,
        ],
        'School Event' => [
            'event*' => 4, 'foundation' => 5, 'intramural*' => 6, 'graduat*' => 6,
            'orientation' => 5, 'seminar*' => 5, 'convocation' => 6, 'recognition' => 4,
            'competition*' => 5, 'contest*' => 4, 'buwan ng wika' => 6,
            'acquaintance' => 6, 'prom' => 5, 'sports fest' => 6, 'festival*' => 4,
            'workshop*' => 4, 'congratulations' => 3, 'congrats' => 3, 'celebrat*' => 4,
            'home games' => 6, 'tournament*' => 5, 'game day' => 5, 'snaps' => 3,
            'thank you' => 2, 'winners' => 4, 'champion*' => 4, 'opening ceremony' => 6,
        ],
    ];
}

/** True if $keyword appears in the (already normalized) $text. */
function gncKeywordMatches(string $text, string $keyword): bool {
    $prefix = substr($keyword, -1) === '*';
    $word   = $prefix ? substr($keyword, 0, -1) : $keyword;
    $re     = '/(?<![\p{L}\p{N}])' . preg_quote($word, '/') . ($prefix ? '' : '(?![\p{L}\p{N}])') . '/u';
    return preg_match($re, $text) === 1;
}

/**
 * @return string|null  category name, or null when nothing scores (= "None")
 */
function gncClassifyFbPost(string $message, int $minScore = 4): ?string {
    $text = gncNormalizeForSearch($message);
    if (trim($text) === '') return null;

    $best = null;
    $bestScore = 0;
    // Order matters on a tie: Emergency > Holiday > Reminder > School Event.
    foreach (gncCategoryKeywords() as $category => $words) {
        $score = 0;
        foreach ($words as $word => $weight) {
            if (gncKeywordMatches($text, (string)$word)) $score += $weight;
        }
        if ($score > $bestScore) {
            $best = $category;
            $bestScore = $score;
        }
    }
    return $bestScore >= $minScore ? $best : null;
}

/** Map of lowercase category name => category_id, loaded once per run. */
function gncLoadCategoryMap(PDO $db): array {
    $map = [];
    foreach ($db->query("SELECT category_id, category_name FROM categories")->fetchAll() as $r) {
        $map[mb_strtolower($r['category_name'], 'UTF-8')] = (int)$r['category_id'];
    }
    return $map;
}

/** Classify a message and return the matching category_id (or null). */
function gncCategoryIdForMessage(string $message, array $categoryMap): ?int {
    $name = gncClassifyFbPost($message);
    return $name !== null ? ($categoryMap[mb_strtolower($name, 'UTF-8')] ?? null) : null;
}