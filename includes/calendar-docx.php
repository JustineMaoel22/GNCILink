<?php
/** Bounded, read-only DOCX reader using PHP's libzip and libxml libraries. */
function calendarDocxXml(string $path): string {
    if (!class_exists('ZipArchive') || !class_exists('DOMDocument') || !class_exists('finfo')) {
        throw new InvalidArgumentException('Calendar import requires PHP ZIP, DOM/XML and Fileinfo. Ask your hosting administrator to enable them.');
    }
    if (!is_file($path) || filesize($path) > 5 * 1024 * 1024 || filesize($path) < 4) throw new InvalidArgumentException('Upload a DOCX file up to 5 MB.');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($path);
    if (!in_array($mime, ['application/zip', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/octet-stream'], true)) throw new InvalidArgumentException('This file is not a Word DOCX document.');
    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::RDONLY) !== true) throw new InvalidArgumentException('The Word document could not be opened.');
    try {
        if ($zip->numFiles > 2000 || $zip->locateName('[Content_Types].xml') === false) throw new InvalidArgumentException('Invalid or overly complex DOCX package.');
        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $entry = $zip->statIndex($i);
            $total += $entry['size'];
            if ($total > 40 * 1024 * 1024 || preg_match('~(?:vbaProject|embeddings/|activeX/)~i', $entry['name'])) throw new InvalidArgumentException('Embedded programs or oversized DOCX contents are not supported.');
        }
        $stat = $zip->statName('word/document.xml');
        if (!$stat || $stat['size'] > 8 * 1024 * 1024) throw new InvalidArgumentException('The document text is missing or too large.');
        $xml = $zip->getFromName('word/document.xml', 8 * 1024 * 1024 + 1);
        if (!is_string($xml) || $xml === '' || preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) throw new InvalidArgumentException('Unsafe or empty document XML.');
        return $xml;
    } finally { $zip->close(); }
}

function calendarValidDate(string $date): bool {
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) return false;
    return (int)$m[1] >= 1900 && (int)$m[1] <= 2050 && checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
}

/** Deliberately rejects ambiguous numeric dates rather than guessing their order. */
function calendarParseDates(string $text, int $year, ?int $contextMonth = null): array {
    $text = trim(str_replace(['–', '—', ' to '], '-', $text));
    $text = preg_replace('/\b(Monday|Tuesday|Wednesday|Thursday|Friday|Saturday|Sunday)\b,?\s*/i', '', $text);
    if (preg_match('/^(\d{4}-\d{2}-\d{2})(?:\s*(?:-|through)\s*(\d{4}-\d{2}-\d{2}))?$/i', $text, $m)) {
        return [$m[1], $m[2] ?? '', []];
    }
    $months = 'Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:t(?:ember)?)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?';
    $names = ['jan'=>1,'feb'=>2,'mar'=>3,'apr'=>4,'may'=>5,'jun'=>6,'jul'=>7,'aug'=>8,'sep'=>9,'oct'=>10,'nov'=>11,'dec'=>12];
    if (preg_match('/^(\d{1,2})\s+('.$months.')\.?(?:,?\s+(\d{4}))?$/i',$text,$dayFirst)) {
        $text=$dayFirst[2].' '.$dayFirst[1].(!empty($dayFirst[3])?' '.$dayFirst[3]:'');
    }
    if (preg_match('/^('.$months.')\.?\s+(\d{1,2})(?:st|nd|rd|th)?(?:,?\s+(\d{4}))?(?:\s*-\s*(?:('.$months.')\.?\s+)?(\d{1,2})(?:st|nd|rd|th)?(?:,?\s+(\d{4}))?)?$/i', $text, $m)) {
        $month = $names[strtolower(substr($m[1], 0, 3))];
        $startYear = !empty($m[3]) ? (int)$m[3] : (!empty($m[6]) && empty($m[4]) ? (int)$m[6] : $year);
        $start = sprintf('%04d-%02d-%02d', $startYear, $month, $m[2]);
        $end = '';
        if (!empty($m[5])) {
            $endMonth = !empty($m[4]) ? $names[strtolower(substr($m[4],0,3))] : $month;
            $endYear = !empty($m[6]) ? (int)$m[6] : $startYear + ($endMonth < $month ? 1 : 0);
            $end = sprintf('%04d-%02d-%02d', $endYear, $endMonth, $m[5]);
        }
        return [$start, $end, []];
    }
    if ($contextMonth && preg_match('/^(\d{1,2})(?:\s*-\s*(\d{1,2}))?$/', $text, $m)) {
        return [sprintf('%04d-%02d-%02d',$year,$contextMonth,$m[1]), isset($m[2]) ? sprintf('%04d-%02d-%02d',$year,$contextMonth,$m[2]) : '', []];
    }
    return ['', '', ['Date is missing or unclear. Enter an exact date.']];
}

function calendarClassify(string $text, array $categories): ?int {
    $rules = [
        'Emergency'=>'/\b(suspension|suspended|emergency|typhoon|evacuation)\b/i',
        'Holiday'=>'/\b(holiday|christmas break|christmas day|new year|holy week|independence day|labor day|all saints|semestral break)\b/i',
        'Academic'=>'/\b(exam(?:ination)?s?|enrol(?:l)?ment|registration|classes|grading|semester)\b/i',
        'School Event'=>'/\b(foundation|intramurals|graduation|recognition|orientation|assembly|celebration|school|exam(?:ination)?s?|enrol(?:l)?ment|registration|classes|grading|semester)\b/i',
    ];
    foreach ($rules as $name=>$pattern) {
        if (!preg_match($pattern,$text)) continue;
        foreach ($categories as $cat) if (strcasecmp($cat['category_name'],$name)===0) return (int)$cat['category_id'];
    }
    return null;
}

function calendarExtractXml(string $xml, int $year, array $categories): array {
    if (strlen($xml)>8*1024*1024 || preg_match('/<!DOCTYPE|<!ENTITY/i',$xml)) throw new InvalidArgumentException('Unsafe or oversized document XML.');
    $doc = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    try { $ok = $doc->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT); }
    finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    if (!$ok) throw new InvalidArgumentException('The document contains invalid XML.');
    $xp = new DOMXPath($doc);
    $xp->registerNamespace('w','http://schemas.openxmlformats.org/wordprocessingml/2006/main');
    $paragraph = static function ($node) use ($xp): string {
        $text = '';
        foreach ($xp->query('.//w:t|.//w:tab|.//w:br', $node) as $part) $text .= $part->localName === 't' ? $part->textContent : "\n";
        return trim($text);
    };
    $rows=[]; $month=null; $lastDate=''; $columns=[];
    $monthNames=['january'=>1,'february'=>2,'march'=>3,'april'=>4,'may'=>5,'june'=>6,'july'=>7,'august'=>8,'september'=>9,'october'=>10,'november'=>11,'december'=>12];
    foreach ($xp->query('//w:body/w:p | //w:body/w:tbl/w:tr') as $node) {
        $cells=[];
        if ($node->localName==='tr') {
            foreach ($xp->query('./w:tc',$node) as $cell) {
                $parts=[]; foreach ($xp->query('./w:p',$cell) as $p) $parts[]=$paragraph($p);
                $cells[]=trim(implode("\n",$parts));
            }
        } else { $cells=[$paragraph($node)]; }
        $whole=trim(implode(' ', $cells));
        if ($whole==='') continue;
        if (preg_match('/^('.implode('|',array_keys($monthNames)).')(?:\s+(\d{4}))?$/i',$whole,$m)) {
            $nextMonth=$monthNames[strtolower($m[1])];
            if(!empty($m[2]))$year=(int)$m[2]; elseif($month!==null&&$nextMonth<$month)$year++;
            $month=$nextMonth; $lastDate=''; continue;
        }
        if (preg_match('/(?:calendar of activities|school year|academic year)/i',$whole) && count($cells)===1) continue;
        $header=[];
        foreach ($cells as $i=>$cell) {
            $key=strtolower(trim($cell));
            if (in_array($key,['date','dates','day'],true))$header['date']=$i;
            if (in_array($key,['event','event title','activity','activities','title'],true))$header['title']=$i;
            if (in_array($key,['description','details','remarks'],true))$header['description']=$i;
            if (in_array($key,['time','schedule'],true))$header['time']=$i;
            if (in_array($key,['location','venue','place'],true))$header['location']=$i;
            if ($key==='month')$header['month']=$i;
        }
        if (isset($header['date']) && isset($header['title'])) { $columns=$header; continue; }
        $date='';$title='';$description='';$time='';$location='';
        if (count($cells)>=2) {
            $date=$cells[$columns['date']??0]??''; $title=$cells[$columns['title']??1]??'';
            if(isset($columns['month'])&&isset($cells[$columns['month']])) {
                $monthText=strtolower(trim($cells[$columns['month']]));
                if(isset($monthNames[$monthText]))$month=$monthNames[$monthText];
            }
            $description=isset($columns['description'])?($cells[$columns['description']]??''):implode("\n",$columns?array_diff_key($cells,array_flip(array_values($columns))):array_slice($cells,2));
            $time=isset($columns['time'])?($cells[$columns['time']]??''):'';
            $location=isset($columns['location'])?($cells[$columns['location']]??''):'';
            if($date==='')$date=$lastDate; else $lastDate=$date;
        } else {
            // Paragraph calendars use a tab, colon or spaced dash between date and activity.
            if (preg_match('/^(.+?)(?:\t|\n|\s+[|–—]\s+|:\s+|\s+-\s+)(.+)$/u',$whole,$m)) { $date=$m[1];$title=$m[2]; }
            else { $title=$whole; }
        }
        // Recognize explicit times in date/title cells as well as Time columns.
        if($time==='') {
            $clock='(?:\d{1,2}(?::\d{2})?\s*[ap]m|\d{1,2}:\d{2})';
            foreach(['date','title'] as $field) {
                if(preg_match('/(?:\s+at\s+|\s*\(?)(('.$clock.')(?:\s*(?:-|–|—|to)\s*'.$clock.')?)\)?$/i',${$field},$clockMatch)) {
                    $time=$clockMatch[1];${$field}=trim(substr(${$field},0,-strlen($clockMatch[0]))," ,-");break;
                }
            }
        }
        [$start,$end,$warnings]=calendarParseDates($date,$year,$month);
        $startTime='';$endTime='';
        if ($time!=='') {
            $times=preg_split('/\s*(?:–|—|-|\bto\b)\s*/u',$time);
            if(count($times)>2)$warnings[]='Several time ranges were found. Review the start and end times.';
            if(count($times)>1&&!preg_match('/[ap]m/i',$times[0])&&preg_match('/[ap]m/i',$times[1]))$warnings[]='Time range has an implied AM/PM. Review both times.';
            foreach ($times as $i=>$value) {
                if (preg_match('/^(\d{1,2})(?::(\d{2}))?\s*(am|pm)?$/i',trim($value),$t)) {
                    $h=(int)$t[1];$min=(int)($t[2]??0);$amp=strtolower($t[3]??'');
                    if($min>59||($amp!==''?($h<1||$h>12):$h>23)) { $warnings[]='Time is unclear. Please correct it.';continue; }
                    if($amp!=='')$h=$h%12+($amp==='pm'?12:0);
                    if($i===0)$startTime=sprintf('%02d:%02d',$h,$min);elseif($i===1)$endTime=sprintf('%02d:%02d',$h,$min);
                } else $warnings[]='Time is unclear. Please correct it.';
            }
        }
        foreach (preg_split('/\n+/', $title) as $activity) {
            $activity=trim(preg_replace('/^[•\-]\s*/u','',$activity)); if($activity==='')continue;
            $cat=calendarClassify($activity.' '.$description,$categories);
            $issues=$warnings;
            if(!$cat)$issues[]='Category needs review.';
            if($start!==''&&!calendarValidDate($start)||$end!==''&&!calendarValidDate($end)||$end!==''&&$end<$start)$issues[]='Invalid date range. Please correct it.';
            $rows[]=['title'=>$activity,'start_date'=>$start,'end_date'=>$end,'start_time'=>$startTime,'end_time'=>$endTime,'description'=>$description,'location'=>$location,'location_provided'=>isset($columns['location']),'category_id'=>$cat,'warnings'=>$issues,'source'=>$whole,'event_id'=>0,'action'=>'create'];
            if(count($rows)>1500)throw new InvalidArgumentException('Import at most 1,500 activities at a time.');
        }
    }
    if(!$rows)throw new InvalidArgumentException('No activities were found. Use a table with Date and Activity columns.');
    usort($rows,static fn($a,$b)=>strcmp($a['start_date']?:'9999',$b['start_date']?:'9999'));
    return $rows;
}
