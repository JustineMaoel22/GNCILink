<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/calendar-import.php';
function calendarCheck(bool $ok,string $message): void { if(!$ok)throw new RuntimeException($message); }
function calendarReject(callable $work,string $message): void { try{$work();}catch(InvalidArgumentException $e){return;}throw new RuntimeException($message); }
$cats=[['category_id'=>1,'category_name'=>'Holiday'],['category_id'=>2,'category_name'=>'School Event'],['category_id'=>3,'category_name'=>'Emergency']];
$departmentFixtures=[['dept_id'=>2,'dept_name'=>'College of Business Administration','slug'=>'cba'],['dept_id'=>3,'dept_name'=>'College of Computing','slug'=>'computing'],['dept_id'=>4,'dept_name'=>'College of Criminology','slug'=>'criminology']];
$activity=static fn(string $title,string $description='')=>['title'=>$title,'description'=>$description,'warnings'=>[]];
$detected=calendarImportDetectDepartments([$activity('CBA Days'),$activity('College of Business Administration celebration'),$activity('General assembly','Hosted by CBA'),$activity('CBA and College of Computing activity'),$activity('CC Days'),$activity('School celebration'),$activity('XCBAX celebration')],$departmentFixtures);
calendarCheck($detected[0]['dept_id']===2&&$detected[0]['program']==='ALL','CBA Days targets department only');
calendarCheck($detected[1]['dept_id']===2&&$detected[2]['dept_id']===2,'Full names and description identify departments');
calendarCheck($detected[3]['dept_id']===null&&count($detected[3]['warnings'])===1,'Multiple departments require review');
calendarCheck($detected[4]['dept_id']===null&&count($detected[4]['warnings'])===1,'Shared acronyms cannot silently choose a department');
calendarCheck($detected[5]['dept_id']===null&&$detected[6]['dept_id']===null,'General events and partial-word matches remain unassigned');
$cbaDates=calendarParseDates('Nov 1-3',2026);
calendarCheck($cbaDates[0]==='2026-11-01'&&$cbaDates[1]==='2026-11-03','Department-wide multi-day date range');
$corrected=calendarImportCompare([$detected[0]+['start_date'=>'2026-11-01','end_date'=>'2026-11-03','event_id'=>0,'action'=>'create','category_id'=>2]], [['event_id'=>20,'title'=>'CBA Days','start_date'=>'2026-11-01','dept_id'=>3,'program'=>'ALL','category_id'=>2]], []);
calendarCheck($corrected[0]['dept_id']===3,'Revised imports preserve manual department corrections over detection');
calendarCheck(calendarParseDates('Dec. 21–Jan. 3',2026)===['2026-12-21','2027-01-03',[]],'Cross-year range');
calendarCheck(calendarParseDates('Feb. 9–13',2026)[1]==='2026-02-13','Single month range');
calendarCheck(calendarParseDates('Feb 9-13, 2027',2026)[0]==='2027-02-09','Explicit trailing year');
calendarCheck(calendarParseDates('2026-01-02 - 2026-01-04',2026)[1]==='2026-01-04','ISO range');
calendarCheck(calendarParseDates('9-13',2026,2)[0]==='2026-02-09','Month heading context');
calendarCheck(calendarParseDates('15 January 2026',2027)[0]==='2026-01-15','Day-first month name');
calendarCheck(calendarParseDates('02/03/2026',2026)[0]==='','Ambiguous numeric date is flagged');
calendarCheck(!calendarValidDate('2026-02-30')&&calendarValidDate('2028-02-29'),'Real dates only');
$xml='<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:tbl>';
foreach([['Date','Activity','Description','Time'],['Dec. 21–Jan. 3','Christmas Break','No classes',''],['Jan. 15',"Foundation Day\nOrientation",'School community','9:00 am-10:30 am'],['Feb. 30','Examinations','Invalid date',''],['TBA','Mystery activity','Review me','evening']] as $cells){
    $xml.='<w:tr>';foreach($cells as $cell){$xml.='<w:tc>';foreach(explode("\n",$cell) as $line)$xml.='<w:p><w:r><w:t>'.htmlspecialchars($line,ENT_XML1).'</w:t></w:r></w:p>';$xml.='</w:tc>';}$xml.='</w:tr>';
}
$xml.='</w:tbl></w:body></w:document>';
$rows=calendarExtractXml($xml,2026,$cats);
calendarCheck(count($rows)===5,'Multiple activities on same date');
calendarCheck($rows[0]['title']==='Foundation Day'&&$rows[0]['start_time']==='09:00'&&$rows[0]['end_time']==='10:30','Chronological sorting and times');
calendarCheck($rows[0]['category_id']===2&&$rows[3]['category_id']===1&&$rows[4]['category_id']===null,'Existing categories and uncertain classification');
calendarCheck(count($rows[2]['warnings'])>0&&count($rows[4]['warnings'])>=2,'Bad dates, times and categories flagged');
calendarReject(static fn()=>calendarExtractXml('<!DOCTYPE x [<!ENTITY x SYSTEM "file:///etc/passwd">]><x>&x;</x>',2026,$cats),'XXE must be rejected');
calendarReject(static fn()=>calendarExtractXml('<invalid',2026,$cats),'Malformed XML rejected');
$edit=$rows[0];$edit['include']=true;$edit['reviewed']=true;
$valid=calendarImportValidate([$edit],[$rows[0]],[],$cats);
foreach(['Location','Venue','Place'] as $heading) {
    $venueXml='<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:tbl>';
    foreach([['Date','Activity',$heading],['Jan. 15','School celebration','Main Gym']] as $cells){$venueXml.='<w:tr>';foreach($cells as $cell)$venueXml.='<w:tc><w:p><w:r><w:t>'.$cell.'</w:t></w:r></w:p></w:tc>';$venueXml.='</w:tr>';}
    $venueRows=calendarExtractXml($venueXml.'</w:tbl></w:body></w:document>',2026,$cats);
    calendarCheck($venueRows[0]['location']==='Main Gym'&&$venueRows[0]['description']==='','Location aliases extracted without duplicating into description');
}
$bad=$edit;$bad['location']=str_repeat('x',501);calendarReject(static fn()=>calendarImportValidate([$bad],[$rows[0]],[],$cats),'Overlong locations rejected');
$locationExisting=[['event_id'=>20,'title'=>'Foundation Day','start_date'=>'2026-01-15 09:00:00','category_id'=>1,'location'=>'Corrected Hall']];
$locationRevision=calendarImportCompare([$rows[0]],$locationExisting,$locationExisting);calendarCheck($locationRevision[0]['location']==='Corrected Hall','Revisions without location preserve existing venue');
calendarCheck($valid[0]['start_date']==='2026-01-15 09:00:00','Canonical date/time');
$bad=$edit;$bad['start_date']='2026-02-30';calendarReject(static fn()=>calendarImportValidate([$bad],[$rows[0]],[],$cats),'Invalid dates rejected at confirmation');
$bad=$edit;$bad['category_id']=999;calendarReject(static fn()=>calendarImportValidate([$bad],[$rows[0]],[],$cats),'Forged category rejected');
$bad=$edit;$bad['category_id']=null;calendarReject(static fn()=>calendarImportValidate([$bad],[$rows[0]],[],$cats),'Uncategorized cannot be imported');
$bad=$edit;$bad['event_id']=99;calendarReject(static fn()=>calendarImportValidate([$bad],[$rows[0]],[],$cats),'Cannot overwrite a manual event');
$bad=$edit;$bad['reviewed']=false;calendarReject(static fn()=>calendarImportValidate([$bad],[$rows[0]],[],$cats),'Review required');
calendarReject(static fn()=>calendarImportValidate([$edit,$edit],[$rows[0],$rows[0]],[],$cats),'Duplicate rows rejected');
$existing=[['event_id'=>20,'title'=>'Foundation Day','start_date'=>'2026-01-15 09:00:00','category_id'=>1]];
$compared=calendarImportCompare([$rows[0]],$existing,$existing);
calendarCheck($compared[0]['event_id']===20&&$compared[0]['category_id']===1,'Revisions preserve manually corrected category');
$removed=calendarImportCompare([],$existing,$existing);calendarCheck($removed[0]['action']==='archive','Possible removals flagged');
$large='<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:tbl>';
for($i=0;$i<500;$i++)$large.='<w:tr><w:tc><w:p><w:r><w:t>Jan. 15</w:t></w:r></w:p></w:tc><w:tc><w:p><w:r><w:t>School activity '.$i.'</w:t></w:r></w:p></w:tc></w:tr>';
calendarCheck(count(calendarExtractXml($large.'</w:tbl></w:body></w:document>',2026,$cats))===500,'Hundreds of activities supported');
if(class_exists('ZipArchive')&&class_exists('finfo')){
    $path=tempnam(sys_get_temp_dir(),'gnc-docx-test-');
    try{
        $zip=new ZipArchive();$zip->open($path,ZipArchive::OVERWRITE);$zip->addFromString('[Content_Types].xml','<Types/>');$zip->addFromString('word/document.xml',$xml);$zip->close();
        calendarCheck(calendarDocxXml($path)===$xml,'DOCX ZIP reader');
        $zip->open($path);$zip->addFromString('word/vbaProject.bin','bad');$zip->close();
        calendarReject(static fn()=>calendarDocxXml($path),'Macros rejected');
    }finally{unlink($path);}
}else echo "SKIP: ZIP upload checks require ZIP and Fileinfo.\n";
echo "PASS: date ranges, sorting, multiple activities, time extraction, categorization, review validation, duplicates, revisions, removals and unsafe XML.\n";
