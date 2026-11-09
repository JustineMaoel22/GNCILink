<?php
/** Local purpose recognition; confidence labels are rule strength, not probabilities. */
function calendarCategoryText(string $text): string {
    $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = function_exists('mb_strtolower') ? mb_strtolower($text, 'UTF-8') : strtolower($text);
    return trim((string)preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text));
}

function calendarCategorize(string $title, string $description, array $categories, string $explicitCategory = ''): array {
    $result = static fn($id, $confidence, $reason) => ['category_id'=>$id, 'confidence'=>$confidence, 'reason'=>$reason];
    $catalog = [];
    foreach ($categories as $category) $catalog[calendarCategoryText($category['category_name'])][] = (int)$category['category_id'];
    $explicit = calendarCategoryText($explicitCategory);
    if ($explicit !== '') {
        $ids = $catalog[$explicit] ?? [];
        if (count($ids) === 1) return $result($ids[0], 'high', 'Matches the category explicitly supplied in the document.');
        return $result(null, 'low', 'The document category does not uniquely match an available category.');
    }

    // Each purpose has specific category aliases, broad fallbacks and bounded phrases.
    // Generic words (day, school, program, assessment, emergency) are not evidence.
    $rules = [
        'class suspension' => [['Suspension','Class Suspension'], ['Emergency'], 'class(?:es)? (?:are |is )?(?:suspended|cancelled|canceled)|suspension of (?:class(?:es)?|work)|class suspension|no classes|walang pasok'],
        'emergency closure' => [['Emergency'], ['Suspension'], '(?:school|campus) (?:closure|closed)|emergency (?:closure|evacuation)|evacuation order'],
        'holiday or break' => [['Holiday','Holidays'], [], '(?:regular|public|national|special non working) holiday|holidays?|christmas (?:break|day|eve)|new year(?: s)? day|holy week|maundy thursday|good friday|black saturday|independence day|labor day|all saints(?: day)?|all souls(?: day)?|bonifacio day|rizal day|ninoy aquino day|national heroes day|eid(?:ul)?(?: fitr| adha)?|chinese new year|semestral break|day of valor|araw ng kagitingan|people power anniversary|pampanga day|town fiesta'],
        'examination' => [['Examination','Examinations','Exam','Exams'], ['School Activities','Academic','Academic Activities','School Event','School Events','Campus Events'], 'exams?|examinations?|prelim(?:inary)? examinations?|midterm(?:s| examinations?)?|finals|summative tests?|achievement tests?|entrance tests?|national achievement test|nat(?= (?:grade|test|examination))|ncae'],
        'internship or practicum' => [['Internship','Internships','Practicum','On the Job Training'], ['School Activities','Academic','Academic Activities','School Event','School Events','Campus Events'], 'internships?|practicum|on the job training|ojt|work immersion|clinical (?:placement|rotation|duty)|student teaching|practice teaching'],
        'seminar or training' => [['Seminar','Seminars','Workshop','Workshops','Training','Seminars and Workshops'], ['School Activities','Academic','Academic Activities','School Event','School Events','Campus Events'], 'seminars?|webinars?|workshops?|training(?: sessions?)?|faculty development|professional development|conferences?|symposi(?:um|a)|research congress'],
        'academic schedule' => [['School Activities'], ['Academic','Academic Activities','School Event','School Events','Campus Events'], 'enrol(?:l)?ment|registration (?:period|of students)|(?:start|end|first day|last day|resumption|opening) of classes|classes (?:begin|resume|end)|submission of grades|encoding of grades|report cards?|thesis defense|dissertation defense|academic deliberation|posting of list of qualified candidates|wellness break|guided asynchronous learning|instructional block|end of term block|checking of permits?|submission of (?:\w+ ){0,5}grading sheets|deliberation(?: of (?:grades|honors?|students|graduating|graduation))?|summer class(?:es)?|(?:start|end) of (?:\w+ ){0,3}(?:term|semester|trimester)|(?:semester|trimester|term) (?:begins|ends)|(?:final|oral|research|thesis|dissertation) defense|defense week|colloquium'],
        'graduation or recognition' => [['Graduation','Recognition'], ['School Event','School Events','Campus Events','School Activities','Academic','Academic Activities'], 'graduation|commencement|recognition(?: day| ceremony| program)?|moving up|completion ceremony|capping|pinning|hard hatting|labcoating'],
        'sports activity' => [['Athletics and Sports','Sports','Athletics'], ['School Event','School Events','School Activities'], 'intramurals?|sports ?fest|athletic meet|zonal meet|interzonal|pasdam|basketball tournament|volleyball tournament'],
        'school celebration' => [['School Event','School Events'], ['Campus Events','School Activities'], 'foundation (?:day|anniversary)|founding anniversary|school anniversary|celebrations?|festivals?|christmas party|acquaintance party|year end party|trick or treat|promenade|gnc night|parade|pageant|school fair|booth fair|mice event|(?:world|national) teachers(?: s)? (?:day|month)|united nations(?: s)? day|valentine(?: s)? day|international women(?: s)? month|international nurses day|student(?:s)? nurses day|president(?: s)? day|(?:engineering|accountancy|nursing|education|tourism|cba|cased|stamp) days?|(?:senior|junior) high school (?:regular |montessori )?days|buwan ng wika|national language month|nutrition month|montessori month|culminating activit(?:y|ies)|(?:death|birth) anniversary'],
        'student activity' => [['Student Activities','Student Activity'], ['School Activities','School Event','School Events','Campus Events'], 'student (?:assembly|orientation|election|activities|activity)|general assembly|orientation|induction ceremony|quiz (?:bee|show)|competitions?|contests?|retreat|pta meeting|faculty meeting|exhibit(?:ion)?|career fair|job fair'],
        'community service' => [['Community Extension Activity','Community Extension','Outreach'], ['School Event','School Events','School Activities'], 'outreach|blood donation|gift giving|community service|community extension|tree planting|clean up drive'],
        'student organization activity' => [['Student Organization'], ['School Activities','School Event','Campus Events'], 'student council|student organization|student organizations|club (?:meeting|election|induction|activities)|organization (?:meeting|election|induction)|sslg|ssg|filing of candidacy|campaign period|election (?:proper|of)|voting (?:during|for)|miting de avance'],
        'alumni activity' => [['Alumni'], ['School Event','Campus Events'], 'alumni (?:homecoming|reunion|gathering|meeting|activities)|alumni association'],
        'achievement' => [['Achievements'], [], '(?:award|medal|competition) winners?|awarding of (?:honors|awards)|academic excellence award(?:s|ees)|outstanding (?:student|alumni|faculty) awards'],
        'guidance and student welfare' => [['School Activities'], ['School Event','School Events','Campus Events'], 'counsel(?:l)?ing (?:service|session|center)|career (?:guidance|talk)|guidance service|testing service|socio emotional well being|social emotional (?:learning|well being)|students? needs assessment|teachers(?: s)? evaluation|vision mission awareness|guidance and library week'],
        'safety drill' => [['School Activities'], ['School Event','School Events','Campus Events'], '(?:earthquake|fire|evacuation|disaster|safety) drill'],
        'religious observance' => [['School Event','School Events'], ['Campus Events','School Activities'], '(?:thanksgiving|first friday|holy|baccalaureate) mass|feast of (?:our lady|the immaculate)|rosary|recollection|religious service|(?:father|saint) (?:\w+ ){1,4}day mass|^mass$'],
        'educational visit' => [['School Activities'], ['School Event','Campus Events'], 'educational (?:tour|trip)|field trip|(?:bsba|bshm|bstm) tour|(?:hospitality|tourism) management tour|(?:student|students|graduation) pictorial|pictorial (?:\w+ ){0,5}students'],
        'learning activity' => [['School Activities'], ['School Event','School Events','Campus Events'], 'balagtasan|harana sa klase|readers theater|dulang pagbasa|tinig ng kuwento|reading literacy|drop everything and read|story circle|big book recipe collection|book (?:cover|marking|blurb)|vocabulary olympics|math sci olympics|vision tree|commitment(?: promise)? wall|growing with our vision|katutubong kasuotan|dress like a saint|dress up as your favorite book character|scavenger hunt|math (?:crossword|trail|amazing race)|great math trail|slogan per section|save the planet coloring|pagkulay sa larawan|color my heart|seed planting|trash to treasure|food plate|healthy living calendar|\b(?:grade|grades|montessori|learners?|students?)\b.*\b(?:coloring|drawing|draw and color|painting|design|making|craft|collage|multimedia|portfolio|simulation|modeling|picture analysis|mapping|mix and match|infomercial|vlog|recipe collection)\b|\b(?:coloring|drawing|draw and color|painting|design|making|craft|collage|multimedia|portfolio|simulation|picture analysis|infomercial|vlog|recipe collection)\b.*\b(?:grade|grades|montessori|learners?|students?)\b'],
        'health service' => [['Health','Health Services','Medical'], ['School Event','School Events','School Activities'], '(?:physical|medical|dental) examinations?|health screening|vaccination|immunization'],
    ];
    $title = calendarCategoryText($title);
    $description = calendarCategoryText($description);
    // A seminar's subject is not itself a closure, examination or holiday.
    $purposeText = static function(string $text): string {
        $text = (string)preg_replace('/\bclass(?:es)? (?:are |is )?not (?:suspended|cancelled|canceled)\b/u', '', $text);
        return (string)preg_replace('/\b(seminars?|webinars?|workshops?|training sessions?) (?:on|about|regarding|in)\b.*$/u', '$1', $text);
    };
    $scores = [];
    foreach ($rules as $purpose => [$aliases, $fallbacks, $pattern]) {
        $regex = '/\b(?:'.$pattern.')\b/u';
        $purposeTitle = $purposeText($title);
        $purposeDescription = $purposeText($description);
        if ($purpose === 'examination') {
            $purposeTitle = (string)preg_replace('/\b(?:physical|medical|dental) examinations?\b/u', '', $purposeTitle);
            $purposeDescription = (string)preg_replace('/\b(?:physical|medical|dental) examinations?\b/u', '', $purposeDescription);
        }
        if ($purpose === 'graduation or recognition') {
            $purposeTitle = (string)preg_replace('/\b(?:candidates? for|for candidates? for) (?:completion and )?graduation\b/u', '', $purposeTitle);
            $purposeDescription = (string)preg_replace('/\b(?:candidates? for|for candidates? for) (?:completion and )?graduation\b/u', '', $purposeDescription);
        }
        $score = (preg_match($regex, $purposeTitle) ? 6 : 0) + (preg_match($regex, $purposeDescription) ? 4 : 0);
        if ($score) $scores[$purpose] = $score;
    }
    // Resolve specific activities within a broader theme without suppressing
    // independent closure/holiday notices or unrelated competing categories.
    if (isset($scores['student organization activity'], $scores['seminar or training'])
        && preg_match('/\b(?:student organization|student council|sslg|ssg) officers? training\b/u', $title)) {
        unset($scores['seminar or training']);
    }
    if (isset($scores['school celebration'], $scores['learning activity'])
        && preg_match('/\b(?:door design|classroom based)\b/u', $title)) {
        unset($scores['school celebration']);
    }
    if (isset($scores['school celebration'], $scores['guidance and student welfare'])
        && str_contains($title, 'classroom based')) {
        unset($scores['school celebration']);
    }
    // Explicitly named custom categories also work without adding database records.
    foreach ($catalog as $name => $ids) {
        if (count($ids) !== 1 || $name === '') continue;
        if ($title === $name || (str_contains($name, ' ') && str_contains(' '.$title.' ', ' '.$name.' '))) {
            // Known broad categories are destinations, not evidence of purpose.
            $known = false;
            foreach ($rules as [$aliases, $fallbacks]) foreach (array_merge($aliases, $fallbacks) as $alias) {
                if (calendarCategoryText($alias) === $name) $known = true;
            }
            if (!$known) {
                $purpose = 'configured category: '.$name;
                $rules[$purpose] = [[$name], [], ''];
                $scores[$purpose] = 6;
            }
        }
    }
    if (!$scores) return $result(null, 'low', 'No clear event purpose matches the configured categories.');
    arsort($scores, SORT_NUMERIC);
    $purposes = array_keys($scores);
    $purpose = $purposes[0];
    $best = $scores[$purpose];
    $resolve = static function(string $purpose) use ($rules, $catalog): array {
        [$aliases, $fallbacks] = $rules[$purpose];
        $tiers = array_merge([$aliases], array_map(static fn($name)=>[$name], $fallbacks));
        foreach ($tiers as $names) {
            $ids = [];
            foreach ($names as $name) $ids = array_merge($ids, $catalog[calendarCategoryText($name)] ?? []);
            $ids = array_values(array_unique($ids));
            if (count($ids)>1) return [null, 'Several available categories fit '.$purpose.'. Choose the most appropriate category.'];
            if (count($ids)===1) return [$ids[0], 'Recognized purpose: '.$purpose.'.'];
        }
        return [null, 'Recognized '.$purpose.', but no suitable configured category is available.'];
    };
    $contenders = array_values(array_filter($purposes, static fn($p)=>$best-$scores[$p]<3));
    if (count($contenders)>1) {
        $ids = [];
        foreach ($contenders as $contender) $ids[] = $resolve($contender)[0];
        // Orientation and internship are compatible when both map to School
        // Activities. Keep conflicts between different categories for review.
        if (!in_array(null,$ids,true) && count(array_unique($ids))===1) {
            return $result($ids[0], 'high', 'Related purposes agree on this category: '.implode(', ', $contenders).'.');
        }
        return $result(null, 'medium', 'Competing purposes: '.implode(' and ', $contenders).'. Choose the primary purpose.');
    }
    [$id,$reason] = $resolve($purpose);
    return $result($id, $id===null?'low':'high', $reason);
}
