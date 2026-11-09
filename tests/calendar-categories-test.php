<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../includes/calendar-docx.php';
function categoryFixture(array $names): array {
    return array_map(static fn($name, $index)=>['category_id'=>$index+1,'category_name'=>$name], $names, array_keys($names));
}
$categories = categoryFixture(['Academic','School Event','Holiday','Suspension']);
$cases = [
    ['Internship deployment', '', 1],
    ['OJT deployment', '', 1],
    ['Work immersion', '', 1],
    ['Clinical rotation', '', 1],
    ['Mid-term examinations', '', 1],
    ['Summative test', '', 1],
    ['Final examinations', '', 1],
    ['Safety seminar', 'Learn about typhoon preparedness and earthquake response.', 1],
    ['Workshop on examination design', '', 1],
    ['Emergency preparedness seminar', '', 1],
    ['Deployment', 'Students begin their supervised internship at partner companies.', 1],
    ['Foundation Day', '', 2],
    ['Student assembly', '', 2],
    ['Intramurals', '', 2],
    ['Career fair', '', 2],
    ['Christmas party', '', 2],
    ['Christmas Day', '', 3],
    ['Special non-working holiday', '', 3],
    ['No classes due to typhoon', '', 4],
    ['Classes suspended', '', 4],
    ['Walang pasok', '', 4],
    ['School program day', '', null],
    ['Emergency equipment inventory', '', null],
    ['Classes are not suspended', '', null],
    ['Medical examination', '', 2],
    ['Vaccination', '', 2],
    ['Deployment', '', null],
    ['Assessment and evaluation', '', null],
    ['Seminar and final examination', '', 1],
    ['Class suspension seminar', '', null],
    ['Final examinations', 'Classes suspended due to flooding.', null],
    ['Updates', '', null],
];
$checks = 0;
foreach ($cases as [$title, $description, $expected]) {
    foreach ([$categories, array_reverse($categories)] as $catalog) {
        $actual = calendarCategorize($title, $description, $catalog);
        if ($actual['category_id'] !== $expected) throw new RuntimeException($title.': '.json_encode($actual));
        if (!$actual['reason'] || ($expected !== null && $actual['confidence'] !== 'high')) throw new RuntimeException('Missing confidence/explanation.');
        $checks++;
    }
}
$extra = [
    [calendarCategorize('Seminar on examinations', '', categoryFixture(['Examinations','Seminars','School Event'])), 2],
    [calendarCategorize('Internship orientation', '', categoryFixture(['Holiday'])), null],
    [calendarCategorize('School program day', '', $categories, '  HOLIDAY '), 3],
    [calendarCategorize('Christmas Day', '', $categories, 'School'), null],
    [calendarCategorize('Christmas Day', '', $categories, 'Unknown'), null],
    [calendarCategorize('Research Forum', '', categoryFixture(['Research Forum'])), 1],
    [calendarCategorize('Research Forum', '', []), null],
    [calendarCategorize('Safety seminar', '', categoryFixture(['School Event'])), 1],
    [calendarCategorize('Safety seminar', '', categoryFixture(['School Event','School Activities'])), 2],
    [calendarCategorize('Classes suspended', '', categoryFixture(['Suspension','Emergency'])), 1],
    [calendarCategorize('Christmas Day', '', categoryFixture(['Holiday','HOLIDAY'])), null],
];
foreach ($extra as [$actual, $expected]) {
    if ($actual['category_id'] !== $expected) throw new RuntimeException(json_encode($actual));
    $checks++;
}
// The actual Events dropdown has several broad categories at the same time.
// They must not cause every academic activity/celebration to become ambiguous.
$eventCategories = categoryFixture(['Achievements','Alumni','Athletics and Sports','Campus Events','Community Extension Activity','Holiday','School Activities','School Event','Student Organization','Suspension']);
foreach ([
    ['Internship deployment',7], ['Work immersion',7], ['Final examinations',7],
    ['Safety seminar',7], ['Enrollment',7], ['Student orientation',7],
    ['Foundation Day',8], ['Christmas party',8], ['Commencement',8],
    ['Intramurals',3], ['Community outreach',5], ['Christmas Day',6],
    ['Classes suspended',10], ['Student council election',9],
    ['Alumni homecoming',2], ['Academic excellence awards',1],
    ['Unclear activity',null],
    ['Counseling Service: Walk in or Referral (All Students)',7],
    ['Booth Fair for Grade 7 and Transferees',8],
    ['Socio-emotional Well-being of Learners',7],
    ['Internship Orientation',7],
    ['Filing of Candidacy for SSLG Officers',9],
    ['Student Organization Officers Training and Teambuilding Activity',9],
    ['Campaign Period and Miting De Avance',9],
    ['Election Proper of SSLG Officers',9],
    ['Voting during Homeroom',9],
    ['Thanksgiving Mass',8],
    ['First Friday Mass / President’s Day',8],
    ['Recollection and Baccalaureate Mass',8],
    ['Father Banzali Day – Mass',8],
    ['Earthquake Drill – Basic Education Group',7],
    ['Fire drill',7],
    ['Online Students Needs Assessment Inventory re: Mental Health',7],
    ['Online Teachers’ Evaluation',7],
    ['Career Guidance Service: Career Talk',7],
    ['Checking of Permit',7],
    ['Submission of Third Tri Grading Sheets',7],
    ['End-of-Term Block',7],
    ['Second Semester Begins',7],
    ['End of 2nd Trimester',7],
    ['Summer Class Ends',7],
    ['Final Defense – BSIT',7],
    ['Defense Week for BSHM and BSTM',7],
    ['Deliberation of Graduating Students',7],
    ['Recognition Program',8],
    ['Trimestral Final Assessment Examination for Candidates for Completion and Graduation',7],
    ['Posting of List of Qualified Candidates for Completion and Graduation',7],
    ['Announcement of Academic Excellence Awardees',1],
    ['Nutrition Month Culminating Activity',8],
    ['International Women’s Month',8],
    ['World Teachers’ Day',8],
    ['STAMP Days',8],
    ['Education Days',8],
    ['Engineering Days',8],
    ['Tourism Day',8],
    ['MICE Event – BSHM/TM',8],
    ['Wellness Break for Teachers',7],
    ['Wellness Break for Learners (Guided Asynchronous Learning Experience)',7],
    ['Vision – Mission Awareness Celebration (Classroom Based)',7],
    ['Festivals of Culture (Door Design Activities) – Grades 4–6',7],
    ['Design your own kite – Grade 1',7],
    ['Draw and color things that your body needs – Grade 1',7],
    ['My Food Plate (paper plate drawing and coloring)',7],
    ['Big Book Recipe Collection',7],
    ['Trash to Treasure',7],
    ['Reading Literacy Day',7],
    ['Vocabulary Olympics',7],
    ['Math-Sci Olympics',7],
    ['Short Vlog – Grade 9',7],
    ['Grade 9: Picture Analysis',7],
    ['Grade 10: Interactive Modeling: Using Simulation',7],
    ['Grade 10: Creative Industry Portfolio',7],
    ['Stick it, Mean it (commitment/promise wall) – Grades 4–6',7],
    ['BSBA Tour (3rd Year Students)',7],
    ['Hospitality Management Tour',7],
    ['Pictorial 4th Year Education Students',7],
    ['Game Ka Na Ba? Contemporary Issues Quiz Show',7],
    ['Trick or Treat – Casa and Grade School Montessori',8],
    // Unrelated common words and cross-category conflicts still require review.
    ['Emergency equipment inventory – Grade 9',null],
    ['Weather forecast for all students',null],
    ['Software design staff meeting',null],
    ['Employee Applicant per request of concerned school official',null],
    ['Mass storage maintenance',null],
    ['Calculate atomic mass',null],
    ['Student Organization Officers Training',9],
    ['Class suspension seminar',null],
    ['Holiday and examination',null],
] as [$title,$expected]) {
    foreach ([$eventCategories,array_reverse($eventCategories)] as $catalog) {
        $actual=calendarCategorize($title,'',$catalog);
        if($actual['category_id']!==$expected)throw new RuntimeException($title.': '.json_encode($actual));
        $checks++;
    }
}
// Exercise real DOCX extraction through finalization, not only classifier calls.
$xml = '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:tbl>';
foreach ([['Date','Activity','Description'],['November 10, 2026','Deployment','Supervised work immersion'],['November 11, 2026','School program day','']] as $cells) {
    $xml .= '<w:tr>';
    foreach ($cells as $cell) $xml .= '<w:tc><w:p><w:r><w:t>'.htmlspecialchars($cell, ENT_XML1).'</w:t></w:r></w:p></w:tc>';
    $xml .= '</w:tr>';
}
$rows = calendarExtractXml($xml.'</w:tbl></w:body></w:document>', 2026, $categories);
if (count($rows)!==2 || $rows[0]['category_id']!==1 || $rows[0]['category_confidence']!=='high' || $rows[1]['category_id']!==null || !in_array('Category needs review.', $rows[1]['warnings'], true) || !in_array('category_id', $rows[1]['missing_fields'], true)) {
    throw new RuntimeException('Extraction must retain classification metadata and block uncertain rows.');
}
echo 'Passed '.($checks+1)." category checks.\n";
