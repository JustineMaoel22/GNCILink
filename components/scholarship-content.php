<?php
require_once __DIR__ . '/../admin/admin-functions.php';

function scholarshipDefaultCards(): array {
    $incomingGrade7 = [
        ['badge' => 'Rank 1', 'label' => 'Full Scholarship',    'pct' => 100, 'grade' => 'Grade 7', 'program' => 'Regular Program',    'type' => 'regular'],
        ['badge' => 'Rank 2', 'label' => 'Half Scholarship',    'pct' => 50,  'grade' => 'Grade 7', 'program' => 'Regular Program',    'type' => 'regular'],
        ['badge' => 'Rank 1', 'label' => 'Half Scholarship',    'pct' => 50,  'grade' => 'Grade 7', 'program' => 'Montessori Program', 'type' => 'montessori'],
        ['badge' => 'Rank 2', 'label' => 'Partial Scholarship', 'pct' => 25,  'grade' => 'Grade 7', 'program' => 'Montessori Program', 'type' => 'montessori'],
    ];
    $oldStudents = [
        ['badge' => '1st Honor', 'label' => 'Half Scholarship',    'pct' => 50, 'grade' => '', 'program' => 'Montessori Program', 'type' => 'montessori'],
        ['badge' => '2nd Honor', 'label' => 'Partial Scholarship', 'pct' => 25, 'grade' => '', 'program' => 'Montessori Program', 'type' => 'montessori'],
    ];
    $collegeIncoming = [
        ['badge' => 'Rank 1', 'label' => 'Full Scholarship', 'pct' => 100, 'grade' => 'First Year', 'program' => 'College Program', 'type' => 'regular'],
        ['badge' => 'Rank 2', 'label' => 'Half Scholarship', 'pct' => 50,  'grade' => 'First Year', 'program' => 'College Program', 'type' => 'regular'],
    ];
    $collegeOld = [
        ['badge' => "President's Lister", 'label' => 'Half Scholarship',    'pct' => 50, 'grade' => '', 'program' => 'College Program', 'type' => 'navy'],
        ['badge' => "Dean's Lister",      'label' => 'Partial Scholarship', 'pct' => 25, 'grade' => '', 'program' => 'College Program', 'type' => 'navy'],
    ];
    return compact('incomingGrade7','oldStudents','collegeIncoming','collegeOld');
}
function scholarshipDefaultRequirements(): array {
    $collegeRequirements = [
        'To be a regular student.',
        'Letter of Application addressed to GNCI President signed by the Dean.',
        'Grades from Last Semester. All course subject grades must be 1.8 and above.',
        'Submit all the requirements at Business Office Window 3.',
    ];
    return $collegeRequirements;
}
function scholarshipFieldDefaults(): array {
    $fields = [];
    $fields['copy_1'] = ['value'=>'Scholarship', 'label'=>'Scholarship', 'max'=>255, 'type'=>'text'];
    $fields['copy_2'] = ['value'=>'GNCI welcomes applicants whose academic credentials, character, and potential demonstrate their readiness to benefit from the school’s quality education and enriching intellectual, social, and spiritual environment.', 'label'=>'GNCI welcomes applicants whose academic credentials, character, and potential demonstrate their read', 'max'=>4000, 'type'=>'text'];
    $fields['copy_3'] = ['value'=>'GNC Scholarships', 'label'=>'GNC Scholarships', 'max'=>255, 'type'=>'text'];
    $fields['copy_4'] = ['value'=>'Government Scholarships', 'label'=>'Government Scholarships', 'max'=>255, 'type'=>'text'];
    $fields['copy_5'] = ['value'=>'The school is committed to providing quality education and offering various scholarship programs to support deserving and qualified students.', 'label'=>'The school is committed to providing quality education and offering various scholarship programs to ', 'max'=>4000, 'type'=>'text'];
    $fields['copy_6'] = ['value'=>'Academic Scholarship', 'label'=>'Academic Scholarship', 'max'=>255, 'type'=>'text'];
    $fields['copy_7'] = ['value'=>'Academic Scholarship', 'label'=>'Academic Scholarship', 'max'=>255, 'type'=>'text'];
    $fields['copy_8'] = ['value'=>'Athletic Scholarship', 'label'=>'Athletic Scholarship', 'max'=>255, 'type'=>'text'];
    $fields['copy_9'] = ['value'=>'Government scholarship information will be posted here.', 'label'=>'Government scholarship information will be posted here.', 'max'=>4000, 'type'=>'text'];
    $fields['copy_10'] = ['value'=>'Academic Scholarship for Basic Education Students', 'label'=>'Academic Scholarship for Basic Education Students', 'max'=>255, 'type'=>'text'];
    $fields['copy_11'] = ['value'=>'Incoming Grade 7 Students', 'label'=>'Incoming Grade 7 Students', 'max'=>255, 'type'=>'text'];
    $fields['copy_12'] = ['value'=>'Old Students (Advanced CASA - Grade 6 & Grade 8 - Grade 10)', 'label'=>'Old Students (Advanced CASA - Grade 6 & Grade 8 - Grade 10)', 'max'=>255, 'type'=>'text'];
    $fields['copy_13'] = ['value'=>'Academic Scholarship for College Students', 'label'=>'Academic Scholarship for College Students', 'max'=>255, 'type'=>'text'];
    $fields['copy_14'] = ['value'=>'Incoming Freshmen Students', 'label'=>'Incoming Freshmen Students', 'max'=>255, 'type'=>'text'];
    $fields['copy_15'] = ['value'=>'Old Students', 'label'=>'Old Students', 'max'=>255, 'type'=>'text'];
    $fields['copy_16'] = ['value'=>'Requirements for Application', 'label'=>'Requirements for Application', 'max'=>255, 'type'=>'text'];
    $fields['copy_17'] = ['value'=>'Students are required to submit the following documents to apply for academic scholarship.', 'label'=>'Students are required to submit the following documents to apply for academic scholarship.', 'max'=>4000, 'type'=>'text'];
    $fields['copy_18'] = ['value'=>'(Basic Education Department)', 'label'=>'(Basic Education Department)', 'max'=>255, 'type'=>'text'];
    $fields['copy_19'] = ['value'=>'(College Department)', 'label'=>'(College Department)', 'max'=>255, 'type'=>'text'];
    $copyLabels = ['Page title','Page introduction','School scholarships tab','Government scholarships tab','School scholarships introduction','Basic Education card title','College card title','Athletics card title','Government scholarship information','Basic Education dialog title','Incoming Grade 7 heading','Continuing Basic Education heading','College dialog title','Incoming college students heading','Continuing college students heading','Application requirements heading','Application requirements introduction','Basic Education card subtitle','College card subtitle'];
    foreach ($copyLabels as $i => $label) $fields['copy_' . ($i + 1)]['label'] = $label;
    $groupLabels = ['incomingGrade7'=>'Incoming Grade 7','oldStudents'=>'Continuing Basic Education','collegeIncoming'=>'Incoming college students','collegeOld'=>'Continuing college students'];
    $fieldLabels = ['badge'=>'Award or rank','label'=>'Scholarship name','pct'=>'Discount percentage','grade'=>'Year or grade level','program'=>'Program label'];
    foreach (scholarshipDefaultCards() as $group => $cards) foreach ($cards as $i => $card) {
        foreach (['badge','label','pct','grade','program'] as $key) {
            $fields[$group . '_' . $i . '_' . $key] = ['value'=>(string)$card[$key], 'label'=>$groupLabels[$group] . ' ' . ($i + 1) . ': ' . $fieldLabels[$key], 'max'=>$key === 'pct' ? 3 : 255, 'type'=>$key === 'pct' ? 'percent' : 'text'];
        }
    }
    foreach (scholarshipDefaultRequirements() as $i => $value) $fields['requirement_' . $i] = ['value'=>$value, 'label'=>'Application requirement ' . ($i+1), 'max'=>4000, 'type'=>'text'];
    return $fields;
}
function scholarshipValidateTexts(array $changes): array {
    $fields = scholarshipFieldDefaults();
    if (count($changes) > count($fields)) throw new InvalidArgumentException('Too many scholarship changes.');
    foreach ($changes as $key => &$value) {
        if (!isset($fields[$key]) || !is_string($value)) throw new InvalidArgumentException('Unknown scholarship field.');
        $value = trim(strip_tags($value));
        $length = function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        if ($length > $fields[$key]['max']) throw new InvalidArgumentException($fields[$key]['label'] . ' is too long.');
        if ($fields[$key]['type'] === 'percent' && (!preg_match('/^(?:100|[0-9]{1,2})$/D', $value))) throw new InvalidArgumentException('Enter a scholarship percentage from 0 to 100.');
    }
    unset($value);
    return $changes;
}
function scholarshipStoredValues(string $prefix, ?PDO $db = null): array {
    try {
        $stmt = ($db ?? getDB())->prepare('SELECT field_key,content FROM tuition_fee_content WHERE field_key LIKE ?');
        $stmt->execute([$prefix . '%']);
        return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (PDOException $error) { if ($error->getCode() !== '42S02') throw $error; return []; }
}
function scholarshipFields(?PDO $db = null): array {
    $fields = scholarshipFieldDefaults();
    foreach (scholarshipStoredValues('scholarship_text_', $db) as $key => $value) {
        $key = substr($key, strlen('scholarship_text_'));
        if (isset($fields[$key])) $fields[$key]['value'] = $value;
    }
    return $fields;
}
function scholarshipCards(array $fields): array {
    $groups = scholarshipDefaultCards();
    foreach ($groups as $group => &$cards) foreach ($cards as $i => &$card) {
        foreach (['badge','label','pct','grade','program'] as $key) $card[$key] = $fields[$group . '_' . $i . '_' . $key]['value'];
    }
    unset($cards, $card);
    return $groups;
}
function scholarshipImageDefaults(): array {
    return [
        'photo_1' => ['label'=>'Students gathered in a classroom for a school program', 'url'=>'/assets/images/acad_scholar_basic_edu.png', 'default'=>'/assets/images/acad_scholar_basic_edu.png'],
        'photo_2' => ['label'=>'Group of smiling college students', 'url'=>'/assets/images/acad_scholar_college.png', 'default'=>'/assets/images/acad_scholar_college.png'],
        'photo_3' => ['label'=>'Student athletes performing in green team uniforms', 'url'=>'/assets/images/athletic_scholar.png', 'default'=>'/assets/images/athletic_scholar.png'],
        'photo_4' => ['label'=>'Requirements illustration', 'url'=>'/assets/images/scholarship/requirements-illustration.png', 'default'=>'/assets/images/scholarship/requirements-illustration.png'],
    ];
}
function scholarshipImages(?PDO $db = null): array {
    $images = scholarshipImageDefaults();
    foreach (scholarshipStoredValues('scholarship_image_', $db) as $key => $value) {
        $key = substr($key, strlen('scholarship_image_'));
        if (isset($images[$key]) && preg_match('#^/uploads/scholarships/[a-f0-9]{32}\.(png|jpg|webp)$#D', $value)) $images[$key]['url'] = $value;
    }
    return $images;
}

function scholarshipValidateEntries(array $entries): array {
    if (!array_is_list($entries) || count($entries) > 100) throw new InvalidArgumentException('Use up to 100 scholarship entries.');
    $seen = [];
    foreach ($entries as &$entry) {
        if (!is_array($entry) || array_diff(array_keys($entry), ['id','category','title','description','url','pdf'])) throw new InvalidArgumentException('Invalid scholarship entry.');
        if (isset($entry['pdf']) && (!is_string($entry['pdf']) || ($entry['pdf'] !== '' && !preg_match('#^/uploads/scholarships/[a-f0-9]{32}\.pdf$#D', $entry['pdf'])))) throw new InvalidArgumentException('Invalid scholarship PDF.');
        foreach (['id','category','title','description','url'] as $key) if (!isset($entry[$key]) || !is_string($entry[$key])) throw new InvalidArgumentException('Invalid scholarship details.');
        if (!preg_match('/^[a-f0-9]{16}$/D', $entry['id']) || isset($seen[$entry['id']])) throw new InvalidArgumentException('Invalid or duplicate scholarship ID.');
        $seen[$entry['id']] = true;
        if (!in_array($entry['category'], ['gnc','government'], true)) throw new InvalidArgumentException('Choose a scholarship category.');
        foreach (['title'=>255,'description'=>6000] as $key => $max) {
            $entry[$key] = trim(strip_tags($entry[$key]));
            $length = function_exists('mb_strlen') ? mb_strlen($entry[$key]) : strlen($entry[$key]);
            if ($length > $max || ($key === 'title' && $entry[$key] === '')) throw new InvalidArgumentException('Enter a scholarship title and keep the details within the allowed length.');
        }
        $entry['url'] = trim($entry['url']);
        if (strlen($entry['url']) > 2048 || ($entry['url'] !== '' && (
            preg_match('/[\s\x00-\x1f\x7f<>"\\\\]/', $entry['url'])
            || !(str_starts_with($entry['url'], '/') && !str_starts_with($entry['url'], '//')
                || filter_var($entry['url'], FILTER_VALIDATE_URL) && in_array(strtolower((string)parse_url($entry['url'], PHP_URL_SCHEME)), ['http','https'], true))
        ))) throw new InvalidArgumentException('Enter an HTTP or HTTPS link, or a local path starting with /.');
    }
    unset($entry);
    if (strlen(json_encode($entries, JSON_THROW_ON_ERROR)) > 60000) throw new InvalidArgumentException('Scholarship details are too large to save. Shorten the descriptions and try again.');
    return $entries;
}

function scholarshipEntries(?PDO $db = null): array {
    $values = scholarshipStoredValues('scholarship_entries', $db);
    if (!isset($values['scholarship_entries'])) return [];
    return scholarshipValidateEntries(json_decode($values['scholarship_entries'], true, 16, JSON_THROW_ON_ERROR));
}
