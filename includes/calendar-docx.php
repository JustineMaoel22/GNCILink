<?php
/** Bounded, read-only DOCX reader using PHP's libzip (or pure-PHP zlib fallback) and libxml libraries. */
function calendarDocxXml(string $path): string {
    if (!class_exists('DOMDocument')) {
        throw new InvalidArgumentException('Calendar import requires PHP DOM/XML. Ask your hosting administrator to enable it.');
    }
    if (!is_file($path) || filesize($path) > 5 * 1024 * 1024 || filesize($path) < 4) throw new InvalidArgumentException('Upload a DOCX file up to 5 MB.');
    // Without Fileinfo, fall back to the ZIP signature; archive validation below still applies.
    $mime = class_exists('finfo') ? (new finfo(FILEINFO_MIME_TYPE))->file($path) : ((string)@file_get_contents($path, false, null, 0, 4) === "PK\x03\x04" ? 'application/zip' : '');
    if (!in_array($mime, ['application/zip', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/octet-stream'], true)) throw new InvalidArgumentException('This file is not a Word DOCX document.');
    if (class_exists('ZipArchive')) {
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
    return calendarDocxXmlFallback($path);
}

/** Pure-PHP bounded ZIP reader for environments where ext-zip is not enabled in php.ini. */
function calendarDocxXmlFallback(string $path): string {
    $data = @file_get_contents($path);
    if (!is_string($data) || strlen($data) < 22 || substr($data, 0, 4) !== "PK\x03\x04") {
        throw new InvalidArgumentException('The Word document could not be opened.');
    }
    $len = strlen($data);
    $eocdPos = strrpos($data, "PK\x05\x06", max(0, $len - 65557));
    if ($eocdPos === false || $eocdPos + 22 > $len) {
        throw new InvalidArgumentException('The Word document could not be opened.');
    }
    $eocd = unpack('vdisk/vcdDisk/vdiskEntries/vtotalEntries/VcdSize/VcdOffset', substr($data, $eocdPos + 4, 16));
    $numFiles = (int)($eocd['totalEntries'] ?? 0);
    $cdOffset = (int)($eocd['cdOffset'] ?? 0);
    $cdSize = (int)($eocd['cdSize'] ?? 0);
    if ($numFiles < 1 || $numFiles > 2000 || $cdOffset < 0 || $cdOffset + $cdSize > $len) {
        throw new InvalidArgumentException('Invalid or overly complex DOCX package.');
    }
    $pos = $cdOffset;
    $end = $cdOffset + $cdSize;
    $hasContentTypes = false;
    $docEntry = null;
    $total = 0;
    for ($i = 0; $i < $numFiles && $pos + 46 <= $end; $i++) {
        if (substr($data, $pos, 4) !== "PK\x01\x02") {
            throw new InvalidArgumentException('Invalid or overly complex DOCX package.');
        }
        $hdr = unpack('vverMade/vverNeed/vflags/vmethod/vmodTime/vmodDate/Vcrc/VcompSize/VuncompSize/vnameLen/vextraLen/vcommentLen/vdiskStart/vintAttr/VextAttr/VlocalOffset', substr($data, $pos + 4, 42));
        $nameLen = (int)$hdr['nameLen'];
        $extraLen = (int)$hdr['extraLen'];
        $commentLen = (int)$hdr['commentLen'];
        if ($pos + 46 + $nameLen + $extraLen + $commentLen > $end) {
            throw new InvalidArgumentException('Invalid or overly complex DOCX package.');
        }
        $name = substr($data, $pos + 46, $nameLen);
        $pos += 46 + $nameLen + $extraLen + $commentLen;
        $uncompSize = (int)$hdr['uncompSize'];
        $total += $uncompSize;
        if ($total > 40 * 1024 * 1024 || preg_match('~(?:vbaProject|embeddings/|activeX/)~i', $name)) {
            throw new InvalidArgumentException('Embedded programs or oversized DOCX contents are not supported.');
        }
        if ($name === '[Content_Types].xml') $hasContentTypes = true;
        if ($name === 'word/document.xml') $docEntry = $hdr;
    }
    if (!$hasContentTypes) throw new InvalidArgumentException('Invalid or overly complex DOCX package.');
    if (!$docEntry || (int)$docEntry['uncompSize'] > 8 * 1024 * 1024) {
        throw new InvalidArgumentException('The document text is missing or too large.');
    }
    $localOffset = (int)$docEntry['localOffset'];
    if ($localOffset < 0 || $localOffset + 30 > $len || substr($data, $localOffset, 4) !== "PK\x03\x04") {
        throw new InvalidArgumentException('The document text is missing or too large.');
    }
    $localHdr = unpack('vnameLen/vextraLen', substr($data, $localOffset + 26, 4));
    $dataStart = $localOffset + 30 + (int)$localHdr['nameLen'] + (int)$localHdr['extraLen'];
    $compSize = (int)$docEntry['compSize'];
    if ($dataStart < 0 || $compSize < 0 || $dataStart + $compSize > $len) {
        throw new InvalidArgumentException('The document text is missing or too large.');
    }
    $raw = substr($data, $dataStart, $compSize);
    $method = (int)$docEntry['method'];
    if ($method === 0) {
        $xml = $raw;
    } elseif ($method === 8 && function_exists('gzinflate')) {
        $xml = @gzinflate($raw, 8 * 1024 * 1024 + 1);
    } else {
        throw new InvalidArgumentException('Unsupported DOCX compression method.');
    }
    if (!is_string($xml) || $xml === '' || strlen($xml) > 8 * 1024 * 1024 || preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
        throw new InvalidArgumentException('Unsafe or empty document XML.');
    }
    return $xml;
}

function calendarTrimPunctuation(string $text, bool $trimOpenParen = false): string {
    $pattern = $trimOpenParen
        ? '/^[\s,\-–—:;()]+|[\s,\-–—:;()]+$/u'
        : '/^[\s,\-–—:;]+|[\s,\-–—:;]+$/u';
    $res = preg_replace($pattern, '', $text);
    return is_string($res) ? $res : trim($text, " \t\n\r\0\x0B,-:;()");
}

function calendarValidDate(string $date): bool {
    if (!preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $date, $m)) return false;
    return (int)$m[1] >= 1900 && (int)$m[1] <= 2050 && checkdate((int)$m[2], (int)$m[3], (int)$m[1]);
}

function calendarMonthRegex(): string {
    return 'Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:t(?:ember)?)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?';
}

function calendarMonthMap(): array {
    return ['jan'=>1,'feb'=>2,'mar'=>3,'apr'=>4,'may'=>5,'jun'=>6,'jul'=>7,'aug'=>8,'sep'=>9,'oct'=>10,'nov'=>11,'dec'=>12];
}

function calendarCleanDateInput(string $text): string {
    $text = trim($text);
    // Deduplicate multi-line or repeated date strings in the same cell (e.g., "August 07, 2026 Friday \n August 07, 2026 Friday")
    $lines = array_values(array_unique(array_filter(array_map('trim', preg_split('/\r?\n+/u', $text)), static fn($l) => $l !== '')));
    if (count($lines) > 1) {
        $text = implode(' ', $lines);
    }
    $text = (string)(preg_replace('/^(?:event\s+date|inclusive\s+dates?|start\s+date|dates?|when|on)\s*[:\-–—]?\s*/iu', '', $text) ?? $text);
    $dow = '(?:Mon(?:day)?|Tue(?:s(?:day)?)?|Wed(?:nesday)?|Thu(?:r(?:s(?:day)?)?)?|Fri(?:day)?|Sat(?:urday)?|Sun(?:day)?)';
    $text = (string)(preg_replace('/\(\s*' . $dow . '(?:\s*[-–—,&]\s*' . $dow . ')*\s*\)/iu', ' ', $text) ?? $text);
    $text = (string)(preg_replace('/\(\s*(?:whole\s+day|all\s+day|half\s+day|morning|afternoon|am|pm|tentative|tba|tbd)\s*\)/iu', ' ', $text) ?? $text);
    $text = (string)(preg_replace('/\b' . $dow . '\b\.?(?:\s*(?:[-–—,&]|\band\b)\s*\b' . $dow . '\b\.?)*,?\s*/iu', ' ', $text) ?? $text);
    $text = (string)(preg_replace('/(\d{1,2})\s*(?:&|\band\b)\s*(\d{1,2})\b/iu', '$1-$2', $text) ?? $text);
    $text = (string)(preg_replace('/\s+(?:to|through|until|up\s+to)\s+/iu', '-', $text) ?? $text);
    $text = str_replace(['–', '—'], '-', $text);
    $text = (string)(preg_replace('/\s+/', ' ', trim($text, " \t\n\r\0\x0B,.;:")) ?? $text);
    // Deduplicate identical date phrase repeated twice on the same line: "August 07, 2026 August 07, 2026"
    if (preg_match('/^(.{6,40}?)\s+\1$/iu', $text, $dup)) {
        $text = trim($dup[1]);
    }
    return $text;
}

/** Deliberately rejects ambiguous numeric dates rather than guessing their order. */
function calendarParseDates(string $text, int $year, ?int $contextMonth = null): array {
    $rawTrimmed = trim($text);
    $hasTentativeOr = (bool)preg_match('/\b\d{1,2}\s+or\s+\d{1,2}\b/iu', $rawTrimmed);
    $text = calendarCleanDateInput($text);
    if ($text === '') {
        return ['', '', ['Date is missing or unclear. Enter an exact date.']];
    }
    if (preg_match('/^(?:to\s+be\s+announced|to\s+be\s+determined|tba|tbd)$/iu', $text)) {
        return ['', '', ['Date is marked as To Be Announced (TBA). Enter an exact date.']];
    }
    if (preg_match('/^all\s+year\s+round$/iu', $text)) {
        $mNum = $contextMonth ?: 1;
        $start = sprintf('%04d-%02d-01', $year, $mNum);
        $lastDay = (int)date('t', mktime(0, 0, 0, $mNum, 1, $year));
        $end = sprintf('%04d-%02d-%02d', $year, $mNum, $lastDay);
        return [$start, $end, ['Marked as ALL YEAR ROUND. Verify exact start and end dates.']];
    }
    if (preg_match('/^(\d{4})-(\d{2})-(\d{2})(?:\s*-\s*(\d{4})-(\d{2})-(\d{2}))?$/', $text, $m)) {
        $start = "{$m[1]}-{$m[2]}-{$m[3]}";
        $end = !empty($m[4]) ? "{$m[4]}-{$m[5]}-{$m[6]}" : '';
        return [$start, $end, []];
    }
    $months = calendarMonthRegex();
    $names = calendarMonthMap();
    // Day-first format: "15 November 2026", "15-17 November 2026", "15 Nov - 17 Nov 2026"
    if (preg_match('/^(\d{1,2})(?:st|nd|rd|th)?(?:\s*-\s*(\d{1,2})(?:st|nd|rd|th)?)?\s+(' . $months . ')\.?(?:,?\s+(\d{4}))?$/i', $text, $df)) {
        $month = $names[strtolower(substr($df[3], 0, 3))];
        $startYear = !empty($df[4]) ? (int)$df[4] : $year;
        $start = sprintf('%04d-%02d-%02d', $startYear, $month, (int)$df[1]);
        $end = !empty($df[2]) ? sprintf('%04d-%02d-%02d', $startYear, $month, (int)$df[2]) : '';
        return [$start, $end, []];
    }
    if (preg_match('/^(\d{1,2})(?:st|nd|rd|th)?\s+(' . $months . ')\.?(?:,?\s+(\d{4}))?\s*-\s*(\d{1,2})(?:st|nd|rd|th)?\s+(' . $months . ')\.?(?:,?\s+(\d{4}))?$/i', $text, $df2)) {
        $m1 = $names[strtolower(substr($df2[2], 0, 3))];
        $m2 = $names[strtolower(substr($df2[5], 0, 3))];
        $y1 = !empty($df2[3]) ? (int)$df2[3] : (!empty($df2[6]) && $m2 >= $m1 ? (int)$df2[6] : $year);
        $y2 = !empty($df2[6]) ? (int)$df2[6] : $y1 + ($m2 < $m1 ? 1 : 0);
        return [sprintf('%04d-%02d-%02d', $y1, $m1, (int)$df2[1]), sprintf('%04d-%02d-%02d', $y2, $m2, (int)$df2[4]), []];
    }
    // Month-first format: "November 15, 2026", "Nov 15-17, 2026", "Nov 28, 2026 - Dec 2, 2026"
    if (preg_match('/^(' . $months . ')\.?\s+(\d{1,2})(?:st|nd|rd|th)?(?:,?\s+(\d{4}))?(?:\s*-\s*(?:(' . $months . ')\.?\s+)?(\d{1,2})(?:st|nd|rd|th)?(?:,?\s+(\d{4}))?)?$/i', $text, $m)) {
        $month = $names[strtolower(substr($m[1], 0, 3))];
        $startYear = !empty($m[3]) ? (int)$m[3] : (!empty($m[6]) && empty($m[4]) ? (int)$m[6] : $year);
        $dateWarnings = [];
        if ($contextMonth !== null && $month === $contextMonth && $startYear === $year - 1) {
            $startYear = $year;
        }
        $start = sprintf('%04d-%02d-%02d', $startYear, $month, (int)$m[2]);
        $end = '';
        if (!empty($m[5])) {
            $endMonth = !empty($m[4]) ? $names[strtolower(substr($m[4], 0, 3))] : $month;
            $endYear = !empty($m[6]) ? (int)$m[6] : $startYear + ($endMonth < $month ? 1 : 0);
            if ($contextMonth !== null && $endMonth === $contextMonth && $endYear === $year - 1) {
                $endYear = $year;
            }
            $end = sprintf('%04d-%02d-%02d', $endYear, $endMonth, (int)$m[5]);
        }
        return [$start, $end, $dateWarnings];
    }
    // Multi-day list or series in the same month: "December 3, 4 and 7, 2026", "March 19, 22-23, 2027", "November 7 - 14 - 21 - 28, 2026", "May 17 or 18, 2027"
    if (preg_match('/^(' . $months . ')\.?\s+(\d{1,2}(?:\s*(?:,|-|\bor\b)\s*\d{1,2})+)(?:,?\s+(\d{4}))?$/i', $text, $ml)) {
        $month = $names[strtolower(substr($ml[1], 0, 3))];
        $startYear = !empty($ml[3]) ? (int)$ml[3] : $year;
        if (preg_match_all('/\d{1,2}/', $ml[2], $dayNums) && count($dayNums[0]) >= 2) {
            $firstDay = (int)$dayNums[0][0];
            $lastDay = (int)$dayNums[0][count($dayNums[0]) - 1];
            $start = sprintf('%04d-%02d-%02d', $startYear, $month, $firstDay);
            $end = $lastDay !== $firstDay ? sprintf('%04d-%02d-%02d', $startYear, $month, $lastDay) : '';
            $w = $hasTentativeOr ? ['Tentative date (' . trim(preg_replace('/\s+/', ' ', $rawTrimmed)) . '). Verify exact date.'] : [];
            return [$start, $end, $w];
        }
    }
    // Month range without specific days: "September - November 2026", "October - November", "January - February 2027", "March - April"
    if (preg_match('/^(' . $months . ')\.?(?:\s+(\d{4}))?\s*-\s*(' . $months . ')\.?(?:,?\s+(\d{4}))?$/i', $text, $mr)) {
        $m1 = $names[strtolower(substr($mr[1], 0, 3))];
        $m2 = $names[strtolower(substr($mr[3], 0, 3))];
        $y1 = !empty($mr[2]) ? (int)$mr[2] : (!empty($mr[4]) && $m2 >= $m1 ? (int)$mr[4] : $year);
        $y2 = !empty($mr[4]) ? (int)$mr[4] : $y1 + ($m2 < $m1 ? 1 : 0);
        $start = sprintf('%04d-%02d-01', $y1, $m1);
        $lastDay = (int)date('t', mktime(0, 0, 0, $m2, 1, $y2));
        $end = sprintf('%04d-%02d-%02d', $y2, $m2, $lastDay);
        return [$start, $end, ['Multi-month schedule (' . $text . '). Verify exact start and end dates.']];
    }
    // Single Month + Year in a date cell: "October 2026", "March 2027"
    if (preg_match('/^(' . $months . ')\.?\s+(\d{4})$/i', $text, $my)) {
        $m1 = $names[strtolower(substr($my[1], 0, 3))];
        $y1 = (int)$my[2];
        $start = sprintf('%04d-%02d-01', $y1, $m1);
        $lastDay = (int)date('t', mktime(0, 0, 0, $m1, 1, $y1));
        $end = sprintf('%04d-%02d-%02d', $y1, $m1, $lastDay);
        return [$start, $end, ['Month-wide schedule (' . $text . '). Verify exact start and end dates.']];
    }
    if ($contextMonth && preg_match('/^(\d{1,2})(?:st|nd|rd|th)?(?:\s*-\s*(\d{1,2})(?:st|nd|rd|th)?)?$/i', $text, $m)) {
        $d1 = (int)$m[1];
        $d2 = isset($m[2]) && $m[2] !== '' ? (int)$m[2] : 0;
        if ($d1 >= 1 && $d1 <= 31 && ($d2 === 0 || ($d2 >= 1 && $d2 <= 31))) {
            return [
                sprintf('%04d-%02d-%02d', $year, $contextMonth, $d1),
                $d2 ? sprintf('%04d-%02d-%02d', $year, $contextMonth, $d2) : '',
                []
            ];
        }
    }
    return ['', '', ['Date is missing or unclear. Enter an exact date.']];
}

function calendarNormalizeTimeString(string $text): string {
    $text = trim($text);
    $text = preg_replace('/^(?:event\s+time|time\s+schedule|schedule\s+time|time|hours|at|@)\s*[:\-–—]?\s*/iu', '', $text);
    $text = trim($text, " \t\n\r\0\x0B().");
    $text = preg_replace('/\ba\.?\s*m\.?(?=\b|\s|$)/iu', 'AM', $text);
    $text = preg_replace('/\bp\.?\s*m\.?(?=\b|\s|$)/iu', 'PM', $text);
    $text = preg_replace('/\b12(?::00)?\s*(?:nn|noon)\b/iu', '12:00 PM', $text);
    $text = preg_replace('/\b12(?::00)?\s*(?:mn|midnight)\b/iu', '12:00 AM', $text);
    $text = preg_replace('/\b(\d{1,2}:\d{2})\s*(?:nn|noon)\b/iu', '$1 PM', $text);
    $text = preg_replace('/\s*(?:-|–|—|\bto\b|\buntil\b|\btill\b)?\s*\b(?:onwards?|until\s+finished|til\s+finished)\b\.?$/iu', '', $text);
    return trim($text);
}

function calendarParseTimeRange(string $rawTime): array {
    // Support multi-session times separated by "/" or ";" (e.g. "8:00 AM – 12:00 NN / 1:00 PM – 5:00 PM" or "8:00 AM – 11:00 AM (Induction) / 1:00 PM (Leadership Workshop)")
    $rawSessions = preg_split('/\s*[\/;]\s*/u', trim($rawTime));
    if (count($rawSessions) > 1) {
        $sessionStarts = [];
        $sessionEnds = [];
        $allValid = true;
        foreach ($rawSessions as $sess) {
            $sessClean = trim((string)(preg_replace('/\([^)]*\)/u', '', $sess) ?? $sess));
            if ($sessClean === '') continue;
            [$st, $et, $w, $matched] = calendarParseTimeRange($sessClean);
            if (!$matched || !empty($w)) {
                $allValid = false;
                break;
            }
            if ($st !== '') $sessionStarts[] = $st;
            if ($et !== '') $sessionEnds[] = $et;
            elseif ($st !== '' && count($sessionStarts) > 1) $sessionEnds[] = $st;
        }
        if ($allValid && !empty($sessionStarts)) {
            $firstStart = $sessionStarts[0];
            $lastEnd = !empty($sessionEnds) ? $sessionEnds[count($sessionEnds) - 1] : '';
            return [$firstStart, $lastEnd, [], true];
        }
    }

    $strippedNotes = (string)(preg_replace('/\([^)]*\)/u', ' ', $rawTime) ?? $rawTime);
    $clean = calendarNormalizeTimeString($strippedNotes);
    if ($clean === '') return ['', '', [], false];
    if (preg_match('/^(?:whole\s+day|all\s+day|full\s+day|throughout\s+the\s+day)$/iu', $clean)) {
        return ['', '', [], true];
    }
    if (preg_match('/^(?:tba|tbd|to\s+be\s+announced|to\s+be\s+determined|tentative)$/iu', $clean)) {
        return ['', '', ['Time is TBA or unclear. Please verify the schedule time.'], true];
    }
    $warnings = [];
    $parts = preg_split('/\s*(?:–|—|-|\bto\b|\buntil\b|\btill\b)\s*/iu', $clean);
    if (count($parts) > 2) {
        $warnings[] = 'Several time ranges were found. Review the start and end times.';
    }
    if (count($parts) > 1 && !preg_match('/\b(?:am|pm)\b/i', $parts[0]) && preg_match('/\b(?:am|pm)\b/i', $parts[1])) {
        $warnings[] = 'Time range has an implied AM/PM. Review both times.';
    }
    $startTime = '';
    $endTime = '';
    $matchedAny = false;
    foreach ($parts as $i => $value) {
        $value = trim($value, " \t\n\r\0\x0B()");
        if (preg_match('/^(\d{1,2})(?::(\d{2}))?\s*(am|pm)?$/i', $value, $t)) {
            $h = (int)$t[1];
            $min = isset($t[2]) && $t[2] !== '' ? (int)$t[2] : 0;
            $amp = strtolower($t[3] ?? '');
            // Require either ":MM" or "AM/PM" so plain numbers like "2026" aren't treated as times
            if ((!isset($t[2]) || $t[2] === '') && $amp === '') {
                $warnings[] = 'Time is unclear. Please correct it.';
                continue;
            }
            if ($min > 59 || ($amp !== '' ? ($h < 1 || $h > 12) : $h > 23)) {
                $warnings[] = 'Time is unclear. Please correct it.';
                continue;
            }
            if ($amp !== '') {
                $h = ($h % 12) + ($amp === 'pm' ? 12 : 0);
            }
            $formatted = sprintf('%02d:%02d', $h, $min);
            if ($i === 0) $startTime = $formatted;
            elseif ($i === 1) $endTime = $formatted;
            $matchedAny = true;
        } else {
            $warnings[] = 'Time is unclear. Please correct it.';
        }
    }
    return [$startTime, $endTime, array_values(array_unique($warnings)), $matchedAny];
}

function calendarLooksLikeStandaloneTime(string $text): bool {
    $rawSessions = preg_split('/\s*[\/;]\s*/u', trim($text));
    if (count($rawSessions) > 1) {
        $validCount = 0;
        foreach ($rawSessions as $sess) {
            $sessClean = trim((string)(preg_replace('/\([^)]*\)/u', '', $sess) ?? $sess));
            if ($sessClean === '') continue;
            if (!calendarLooksLikeStandaloneTime($sessClean)) return false;
            $validCount++;
        }
        return $validCount >= 2;
    }
    $stripped = (string)(preg_replace('/\([^)]*\)/u', ' ', $text) ?? $text);
    $clean = calendarNormalizeTimeString($stripped);
    if ($clean === '') return false;
    if (preg_match('/^(?:whole\s+day|all\s+day|full\s+day|throughout\s+the\s+day|morning\s+session|afternoon\s+session)$/iu', $clean)) {
        return true;
    }
    $clock = '(?:\d{1,2}:\d{2}\s*(?:am|pm)?|\d{1,2}\s*(?:am|pm))';
    return (bool)preg_match('/^' . $clock . '(?:\s*(?:-|–|—|\bto\b|\buntil\b)\s*' . $clock . ')?$/iu', $clean);
}

function calendarLooksLikeStandaloneDate(string $text, int $year, ?int $contextMonth = null, bool $allowBareDay = false): bool {
    $clean = calendarCleanDateInput($text);
    if ($clean === '') return false;
    if (!$allowBareDay && preg_match('/^\d{1,2}(?:\s*-\s*\d{1,2})?$/', $clean)) {
        return false;
    }
    [$start, $end, $warnings] = calendarParseDates($text, $year, $allowBareDay ? $contextMonth : null);
    if ($warnings || $start === '' || !calendarValidDate($start)) return false;
    if ($end !== '' && !calendarValidDate($end)) return false;
    return true;
}

/** Recognize any date cell expression in a 2-column Date | Activity table, including ALL YEAR ROUND, To Be Announced, and month ranges. */
function calendarIsDateCellText(string $text, int $year, ?int $contextMonth = null): bool {
    $clean = calendarCleanDateInput($text);
    if ($clean === '') return false;
    if (preg_match('/^(?:all\s+year\s+round|to\s+be\s+announced|to\s+be\s+determined|tba|tbd)$/iu', $clean)) {
        return true;
    }
    [$start, $end] = calendarParseDates($text, $year, $contextMonth);
    return $start !== '' && calendarValidDate($start) && ($end === '' || calendarValidDate($end));
}

function calendarHasActivityKeywords(string $text): bool {
    return (bool)preg_match('/\b(day|days|celebration|orientation|exam(?:ination)?s?|prelim(?:inary)?|midterm|finals?|summative|assessment|test|testing|meeting|assembly|seminar|workshop|training|teambuilding|contest|competition|festival|program|ceremony|graduation|commencement|completion|moving-?up|recognition|enrol(?:l)?ment|registration|intramurals|sports\s*fest|zonal|interzonal|pasdam|holiday|break|suspension|suspended|classes|mass|retreat|recollection|blessing|dedication|outreach|gift\s+giving|parade|pageant|party|night|fair|exhibit|forum|conference|congress|symposium|drill|election|voting|campaign|miting\s+de\s+avance|filing|candidacy|posting|checking\s+of\s+permit|deliberation|opening|closing|kick-?off|launching|submission|release|releasing|distribution|culminating|acquaintance|week|month|anniversary|fiesta|convocation|induction|investiture|turn-?over|turnover|general\s+assembly|capping|pinning|labcoating|hard\s+hatting|white\s+coat|hooding|baccalaureate|colloquium|defense|tour|promenade|rosary|trick\s+or\s+treat|vaccination|blood\s+donation|evaluation|inventory|counseling|quiz\s+bee|quiz\s+show|olympiad|olympics|balagtasan|oath\s*taking|oathtaking|hackathon|showcase|practicum|internship|deployment|pictorial|practice|practices|brigada\s+eskwela)\b/iu', $text);
}

function calendarLooksLikeStandaloneLocation(string $text): bool {
    $clean = trim((string)(preg_replace('/^[•●○▪▫▸▹►▻\-–—*]+\s*/u', '', $text) ?? $text));
    if ($clean === '' || strlen($clean) > 140) return false;
    if (calendarLooksLikeStandaloneTime($clean) || calendarLooksLikeStandaloneDate($clean, (int)date('Y'))) return false;
    if (preg_match('/^(?:venue|location|place|where)\s*[:\-–—]\s*.+$/iu', $clean)) return true;
    if (preg_match('/^(?:classroom[\s\-]*based(?:\s+activity)?|guidance\s+(?:and\s+)?testing\s+cent(?:er|re)|testing\s+cent(?:er|re))$/iu', $clean)) return true;
    if (preg_match('/^(?:at|@)\s+(?:the\s+)?[A-Z0-9].+$/u', $clean) && !calendarHasActivityKeywords($clean)) return true;
    if (calendarHasActivityKeywords($clean)) return false;
    // Recognize standalone GNC & general campus / venue / room / hall / online location lines (including compound "Venue A and Venue B")
    $singleVenue = '(?:(?:gnc(?:i)?|guagua\s+national\s+colleges?|guagua\s+town|school|college|main|montessori|basic\s+ed(?:ucation)?|high\s+school|elementary|campus|new|old|respective|guidance\s+testing)\s+)*(?:tanjangco\s+hall(?:\s*\(gnci?\s+gym\))?|gozo\s+hall|bulwagan\s+alumni|lh\s*\d+|beta\s*\d+|alpha\s*\d+|ph\s+laboratory|computer\s+laborator(?:y|ies)|testing\s+center|covered\s+courts?|gymnasium|gym|auditorium|open\s+court|basketball\s+court|volleyball\s+court|quadrangle|quad|function\s+hall(?:\s*[-–—]\s*outside)?|social\s+hall|multi-?purpose\s+hall|hall|avr|audio[\s\-]*visual\s+room|library|learning\s+resource\s+center|(?:computer|science|nursing|speech|chem(?:istry)?|physics|biology|hle|tle|kitchen|mock\s+hotel)?\s*lab(?:orator(?:y|ies))?|chapel|church|cathedral|stage|grounds|campus\s+grounds|field|oval|clinic|guidance\s+office|registrar\'?s?\s+office|avr\s*\d*|room\s+[a-z0-9\-]+|rm\.?\s*[a-z0-9\-]+|bldg\.?\s*[a-z0-9\-]+|building\s+[a-z0-9\-]+|conference\s+room|board\s*room|classrooms?|offices?|lobby|main\s+lobby|canteen|cafeteria|amphitheater|plaza|town\s+plaza|zoom(?:\s+meeting)?|google\s+meet|ms\s+teams|microsoft\s+teams|online(?:\s+via\s+.+)?|virtual(?:\s+via\s+.+)?|facebook\s+live|fb\s+live|tba|tbd|to\s+be\s+announced)(?:\s*\([^)]+\))?';
    return (bool)preg_match('/^' . $singleVenue . '(?:\s+and\s+' . $singleVenue . ')*$/iu', $clean);
}

/**
 * Parse lines that combine a Venue, a Time, and/or care-of/department notes using "/", "\", or "–"
 * e.g.:
 * - "Tanjangco Hall (GNCI GYM) / 8:00 AM – 12:00 NN / 1:00 PM – 5:00 PM"
 * - "Tanjangco Hall (GNCI GYM) – 8:00 AM – 12:00 NN"
 * - "GNCI Covered Courts / 9:00 AM"
 * - "GNCI Covered Courts \ 9:00 AM"
 * - "8:00 AM – 12:00 NN / 2:00 PM – 6:00 PM / Function Hall – Outside"
 * - "GNCI Computer Laboratories / 8:00 AM – 6:00 PM c/o OSA"
 * - "Online / 7:00 PM c/o OSA"
 * - "(Place/Venue/Time – TBA) – OSA and Marketing"
 * - "c/o Guidance and OSA / GNCI Auditorium"
 * - "Junior High School Regular – Classroom-Based Activity"
 * - "Junior Advance Casa and Advance Casa Montessori – Classroom-Based Activity"
 */
function calendarParseVenueAndTimeLine(string $text): ?array {
    $clean = trim((string)(preg_replace('/^[•●○▪▫▸▹►▻\-–—*]+\s*/u', '', $text) ?? $text));
    if ($clean === '') return null;

    // 1. "(Place/Venue/Time – TBA) – ..."
    if (preg_match('/^\(\s*place\/venue\/time\s*[-–—:]\s*(tba|tbd)\s*\)(?:\s*[-–—]\s*(.+))?$/iu', $clean, $m)) {
        return ['location' => 'TBA', 'time' => 'TBA', 'department' => '', 'notes' => trim($m[2] ?? '')];
    }

    // 2. "Classroom-Based Activity" or "<Department> – Classroom-Based Activity"
    if (preg_match('/^(?:(.+?)\s*[-–—]\s*)?classroom[\s\-]*based(?:\s+activity)?$/iu', $clean, $m)) {
        $prefix = trim($m[1] ?? '');
        if ($prefix === '') {
            return ['location' => 'Classroom-Based', 'time' => '', 'department' => '', 'notes' => ''];
        }
        // Only treat "<Prefix> – Classroom-Based Activity" as a supporting line when <Prefix> is a department/grade level (not an event title like "National Language Month")
        if (preg_match('/^(?:junior\s+advance\s+casa|advance\s+casa|casa|grade(?:s)?\s+\d|junior\s+high\s+school|senior\s+high\s+school|high\s+school\s+montessori|shs|jhs|college|basic\s+education)/iu', $prefix)) {
            return ['location' => 'Classroom-Based', 'time' => '', 'department' => $prefix, 'notes' => ''];
        }
    }

    // 3. Standalone time
    if (calendarLooksLikeStandaloneTime($clean)) {
        return ['location' => '', 'time' => $clean, 'department' => '', 'notes' => ''];
    }

    // 4. Standalone location
    if (calendarLooksLikeStandaloneLocation($clean)) {
        $loc = (string)(preg_replace('/^(?:venue|location|place|where|at|@)\s*[:\-–—]?\s*/iu', '', $clean) ?? $clean);
        return ['location' => trim($loc), 'time' => '', 'department' => '', 'notes' => ''];
    }

    // 5. Extract trailing "c/o ..." note before splitting on "/" or "\"
    $coNote = '';
    $working = $clean;
    if (preg_match('/^(.*?)\s+(c\/o\s+.+)$/iu', $working, $coMatch)) {
        $working = trim($coMatch[1]);
        $coNote = trim($coMatch[2]);
    }

    // 6. Split by "/" or "\" (e.g., "Tanjangco Hall (GNCI GYM) / 8:00 AM – 12:00 NN / 1:00 PM – 5:00 PM" or "8:00 AM – 12:00 NN / 2:00 PM – 6:00 PM / Function Hall – Outside")
    if (str_contains($working, '/') || str_contains($working, '\\')) {
        $segments = array_values(array_filter(array_map('trim', preg_split('/\s*[\/\\\\]\s*/u', $working)), static fn($s) => $s !== ''));
        if (count($segments) >= 2) {
            $locParts = [];
            $timeParts = [];
            $noteParts = [];
            $allRecognized = true;
            foreach ($segments as $seg) {
                $segNoParens = trim((string)(preg_replace('/\([^)]*\)/u', '', $seg) ?? $seg));
                if (calendarLooksLikeStandaloneTime($seg) || ($segNoParens !== '' && calendarLooksLikeStandaloneTime($segNoParens))) {
                    $timeParts[] = $seg;
                } elseif (calendarLooksLikeStandaloneLocation($seg)) {
                    $locParts[] = $seg;
                } elseif (preg_match('/^c\/o\s+.+$/iu', $seg)) {
                    $noteParts[] = $seg;
                } else {
                    $allRecognized = false;
                    break;
                }
            }
            if ($allRecognized && (!empty($locParts) || !empty($timeParts))) {
                if ($coNote !== '') $noteParts[] = $coNote;
                if (count($timeParts) > 1 || (count($timeParts) === 1 && preg_match('/\([A-Za-z]/', $timeParts[0]))) {
                    $noteParts[] = implode(' / ', $timeParts);
                }
                return [
                    'location' => implode(', ', $locParts),
                    'time' => implode(' / ', $timeParts),
                    'department' => '',
                    'notes' => implode('; ', $noteParts)
                ];
            }
        }
    }

    // 7. Split by dash "–" or "-" when Left is a Venue and Right is a Time (e.g. "Tanjangco Hall (GNCI GYM) – 8:00 AM – 12:00 NN" or "GNCI Covered Courts – 8:00 AM")
    if (preg_match('/^(.+?)\s*[-–—]\s*(\d{1,2}(?::\d{2})?\s*(?:a\.?m\.?|p\.?m\.?|nn|noon|mn).*)$/iu', $working, $vtMatch)) {
        $candLoc = trim($vtMatch[1]);
        $candTime = trim($vtMatch[2]);
        if (calendarLooksLikeStandaloneLocation($candLoc) && calendarLooksLikeStandaloneTime($candTime)) {
            return [
                'location' => $candLoc,
                'time' => $candTime,
                'department' => '',
                'notes' => $coNote
            ];
        }
    }

    return null;
}

function calendarLooksLikeSupportingDetail(string $text): bool {
    $clean = trim((string)(preg_replace('/^[•●○▪▫▸▹►▻\-–—*]+\s*/u', '', $text) ?? $text));
    if ($clean === '') return false;
    // Standalone holiday classification or tentative/enrollment parentheticals
    if (preg_match('/^(?:holiday|regular\s+holiday|special\s+non-?working\s+holiday|special\s+holiday|\(tentative\)|tentative|\(enrollment\s+until\s+[^)]+\)|4th\s+year\s+students\s+only|1st\s+year\s*[-–—]\s*3rd\s+year)$/iu', $clean)) {
        return true;
    }
    // Care-of / forwarding link / organizer office lines
    if (preg_match('/^(?:c\/o\s+.+|link\s+will\s+be\s+forwarded\s+to\s+.+|office\s+of\s+(?:the\s+)?student\s+affairs|osa\s+and\s+marketing|osa\s+director\s+and\s+student\s+volunteers|guidance\s+and\s+counseling\s+center)$/iu', $clean)) {
        return true;
    }
    // Audience / level / class suspension descriptors
    if (preg_match('/^(?:all\s+levels|all\s+departments|all\s+programs|all\s+students(?:\s+and\s+faculty)?|all\s+college(?:\s+students|\s+departments)?|all\s+basic\s+ed(?:ucation)?|college\s+department|basic\s+education(?:\s+department|\s+group)?|senior\s+high\s+school(?:\s+regular|\s+department)?|junior\s+high\s+school(?:\s+regular|\s+department)?|junior\s+and\s+senior\s+high\s+school\s+regular\s+and\s+montessori(?:\s+students)?|high\s+school\s+montessori|casa\s+and\s+grade\s+school\s+montessori|elementary(?:\s+department)?|graduate\s+school(?:\s+department)?|montessori(?:\s+department)?|no\s+classes(?:\s+in\s+all\s+levels)?|classes\s+suspended(?:\s+in\s+all\s+levels)?)$/iu', $clean)) {
        return true;
    }
    // Standalone department or degree program names without activity keywords (e.g., "College of Nursing", "Bachelor of Science in Accountancy", "BSN", "CBA")
    if (!calendarHasActivityKeywords($clean)) {
        if (preg_match('/^(?:college\s+of\s+[a-z\s,&]+|[a-z\s,&]+\s+department|cased|cba|coe|camp|coa|con|conursing|ccs|coc|jhs|shs|gs)$/iu', $clean)) {
            return true;
        }
        if (preg_match('/^(?:bachelor\s+of\s+(?:science|arts|elementary\s+education|secondary\s+education)(?:\s+in\s+[a-z\s,&()-]+)?|master\s+(?:of\s+arts\s+)?in\s+[a-z\s,&()-]+|doctor\s+of\s+philosophy(?:\s+in\s+[a-z\s,&()-]+|\s*\([a-z\s,&-]+\))?|bs\s+in\s+[a-z\s,&-]+|ab\s+in\s+[a-z\s,&-]+|beed|bsed|bael|bsce|bsais|bsa|bsba(?:\s*-\s*fm|\s+fm)?|bscs|bsit|bshm|bshrm|bstm|bsmls|bsmt|bsph|bspharm|bsn|bscrim|gs-(?:phd|maed|mba|mpa)|maed|mba|mpa|phd)(?:\s+(?:students?|majors?))?$/iu', $clean)) {
            return true;
        }
    }
    // Prose sentence / instruction / note
    if (preg_match('/^(?:note|remarks?|details?|description|theme|attire|participants?|target\s+audience|organized\s+by|hosted\s+by|in\s+coordination\s+with|guest\s+speaker|for\s+all\s+|open\s+to\s+|required\s+for\s+|all\s+students\s+|students\s+are\s+|faculty\s+and\s+|please\s+|this\s+is\s+|an?\s+annual\s+|school-?wide\s+)/iu', $clean)) {
        return true;
    }
    if (preg_match('/[.!]$/u', $clean) && substr_count($clean, ' ') >= 4 && !calendarHasActivityKeywords($clean)) {
        return true;
    }
    if (strlen($clean) > 85 && preg_match('/[.!]$/u', $clean) && substr_count($clean, ' ') >= 7) {
        return true;
    }
    return false;
}

/**
 * Parse lines with explicit labels such as:
 * "Event Title: College Foundation Day", "Date: November 15, 2026",
 * "Time: 8:00 AM – 5:00 PM", "Location: School Gymnasium", etc.
 */
function calendarParseLabeledLine(string $line): ?array {
    $clean = trim($line);
    $clean = preg_replace('/^(?:[•●○▪▫▸▹►▻\-–—*]+|\(?\d{1,2}[\.\)])\s+/u', '', $clean);
    if (!preg_match('/^([A-Za-z][A-Za-z0-9\s\/()_-]{1,30}?)\s*([:\-–—])\s*(.*)$/u', $clean, $m)) {
        return null;
    }
    $rawLabel = strtolower(trim(preg_replace('/\s+/u', ' ', $m[1])));
    $sep = $m[2];
    $value = trim($m[3]);

    $labelMap = [
        'event title' => 'title', 'event name' => 'title', 'activity title' => 'title', 'activity name' => 'title',
        'name of event' => 'title', 'name of activity' => 'title', 'title of event' => 'title', 'title of activity' => 'title',
        'event/activity' => 'title', 'activity/event' => 'title', 'event' => 'title', 'activity' => 'title',
        'title' => 'title', 'what' => 'title', 'occasion' => 'title', 'subject' => 'title',

        'event date' => 'date', 'date of event' => 'date', 'activity date' => 'date', 'schedule date' => 'date',
        'inclusive date' => 'date', 'inclusive dates' => 'date', 'start date' => 'date', 'date(s)' => 'date',
        'dates' => 'date', 'date' => 'date', 'when' => 'date', 'day/date' => 'date', 'date/day' => 'date',

        'end date' => 'end_date', 'ends on' => 'end_date',

        'event time' => 'time', 'time of event' => 'time', 'activity time' => 'time', 'time schedule' => 'time',
        'schedule time' => 'time', 'start time' => 'time', 'time(s)' => 'time', 'times' => 'time',
        'time' => 'time', 'hours' => 'time', 'schedule' => 'time',

        'end time' => 'end_time',

        'event location' => 'location', 'event venue' => 'location', 'activity venue' => 'location',
        'activity location' => 'location', 'venue/location' => 'location', 'location/venue' => 'location',
        'location' => 'location', 'venue' => 'location', 'place' => 'location', 'where' => 'location',
        'room' => 'location', 'site' => 'location',

        'event description' => 'description', 'activity description' => 'description', 'event details' => 'description',
        'activity details' => 'description', 'description' => 'description', 'details' => 'description',
        'remarks' => 'description', 'remark' => 'description', 'notes' => 'description', 'note' => 'description',
        'summary' => 'description', 'about' => 'description', 'agenda' => 'description', 'overview' => 'description',
        'objective' => 'description', 'objectives' => 'description', 'theme' => 'description',
        'participants' => 'description', 'target participants' => 'description', 'persons involved' => 'description',
        'person involved' => 'description', 'who' => 'description', 'attire' => 'description',
        'organizer' => 'description', 'host' => 'description', 'in-charge' => 'description',

        'event category' => 'category', 'category' => 'category', 'event type' => 'category',
        'type of event' => 'category', 'classification' => 'category', 'activity type' => 'category',

        'department' => 'department', 'departments' => 'department', 'dept' => 'department', 'dept.' => 'department',
        'event department' => 'department', 'target department' => 'department', 'college' => 'department',
        'college/department' => 'department', 'department/college' => 'department', 'office/department' => 'department',
        'academic department' => 'department', 'level' => 'department', 'educational level' => 'department',
        'audience' => 'department', 'target audience' => 'department', 'for' => 'department',

        'program' => 'program', 'programs' => 'program', 'event program' => 'program', 'target program' => 'program',
        'academic program' => 'program', 'degree program' => 'program', 'degree' => 'program',
        'course' => 'program', 'courses' => 'program', 'program/course' => 'program', 'course/program' => 'program',
        'strand' => 'program', 'track' => 'program', 'major' => 'program', 'specialization' => 'program',
    ];

    if (!isset($labelMap[$rawLabel])) {
        return null;
    }
    // For dash separators ("-"), require an unambiguous multi-word or explicit field label
    // so titles like "Event - Orientation" or "Activity - Workshop" only match when clearly labeled,
    // while "Room - 101" or "Event Title - College Foundation Day" still work.
    if ($sep !== ':' && in_array($rawLabel, ['event', 'activity', 'room', 'site', 'subject', 'college', 'program', 'course', 'level', 'for', 'track', 'major'], true)) {
        return null;
    }

    return ['field' => $labelMap[$rawLabel], 'value' => $value, 'label' => $rawLabel];
}

/**
 * Extract inline time and location from a line and detect Date+Title or Title+Date combinations.
 */
function calendarParseUnlabeledLine(string $line, int $year, ?int $contextMonth = null, bool $allowBareDay = false): array {
    $clean = trim(preg_replace('/^[•●○▪▫▸▹►▻\-–—*]+\s*/u', '', $line));
    if ($clean === '') {
        return ['kind' => 'empty'];
    }
    // Tab or pipe separated segments on a single line
    if (str_contains($clean, "\t") || preg_match('/\s+\|\s+/u', $clean)) {
        $parts = array_values(array_filter(array_map('trim', preg_split('/\t+|\s+\|\s+/u', $clean)), static fn($p) => $p !== ''));
        if (count($parts) === 2) {
            $labeled = calendarParseLabeledLine($parts[0] . ': ' . $parts[1]);
            if ($labeled !== null) {
                return ['kind' => 'labeled', 'field' => $labeled['field'], 'value' => $labeled['value']];
            }
        }
        if (count($parts) >= 2) {
            $date = ''; $time = ''; $location = ''; $title = ''; $desc = [];
            foreach ($parts as $part) {
                if ($date === '' && calendarLooksLikeStandaloneDate($part, $year, $contextMonth, $allowBareDay)) {
                    $date = $part;
                } elseif ($time === '' && calendarLooksLikeStandaloneTime($part)) {
                    $time = $part;
                } elseif ($location === '' && calendarLooksLikeStandaloneLocation($part)) {
                    $location = preg_replace('/^(?:venue|location|place|where|at|@)\s*[:\-–—]?\s*/iu', '', $part);
                } elseif ($title === '') {
                    $title = $part;
                } else {
                    $desc[] = $part;
                }
            }
            if ($title !== '' || $date !== '') {
                return ['kind' => 'event', 'title' => $title, 'date' => $date, 'time' => $time, 'location' => $location, 'description' => implode('; ', $desc)];
            }
        }
    }

    if (calendarLooksLikeStandaloneTime($clean)) {
        return ['kind' => 'time', 'time' => $clean];
    }
    if (calendarLooksLikeStandaloneDate($clean, $year, $contextMonth, $allowBareDay)) {
        return ['kind' => 'date', 'date' => $clean];
    }
    $venueTime = calendarParseVenueAndTimeLine($clean);
    if ($venueTime !== null) {
        return [
            'kind' => 'venue_time',
            'location' => $venueTime['location'] ?? '',
            'time' => $venueTime['time'] ?? '',
            'department' => $venueTime['department'] ?? '',
            'notes' => $venueTime['notes'] ?? '',
        ];
    }
    if (calendarLooksLikeStandaloneLocation($clean)) {
        $loc = preg_replace('/^(?:venue|location|place|where|at|@)\s*[:\-–—]?\s*/iu', '', $clean);
        return ['kind' => 'location', 'location' => trim($loc)];
    }
    if (calendarLooksLikeSupportingDetail($clean)) {
        return ['kind' => 'description', 'description' => $clean];
    }

    $working = $clean;
    $extractedTime = '';
    $extractedLocation = '';
    $extractedDate = '';

    // 1. Extract trailing inline location like "at School Gymnasium", "@ School Gymnasium", "Venue: School Gymnasium", or "/ GNCI Auditorium"
    if (preg_match('/^(.*?)[\s,;]*(?:\b(?:venue|location|place)\s*:\s*|(?:(?<=\s)|^)(?:at|@)\s+|\s+[\/\\\\]\s+)([^,;()\/\\\\]+)$/iu', $working, $locMatch)) {
        $candidateLoc = trim($locMatch[2]);
        if (calendarLooksLikeStandaloneLocation($candidateLoc) || calendarLooksLikeStandaloneLocation('at ' . $candidateLoc)) {
            $extractedLocation = $candidateLoc;
            $working = calendarTrimPunctuation($locMatch[1]);
        }
    }

    // 2. Extract inline time range at the end or in parentheses: "(8:00 AM - 5:00 PM)", "/ 8:00 AM - 12:00 NN", "7:00 AM - Onwards", or ", 8:00 AM - 5:00 PM"
    $clock = '(?:\d{1,2}:\d{2}\s*(?:a\.?m\.?|p\.?m\.?|nn|noon|mn)?|\d{1,2}\s*(?:a\.?m\.?|p\.?m\.?|nn|noon|mn))';
    $singleTime = $clock . '(?:\s*(?:-|–|—|\bto\b|\buntil\b)?\s*(?:' . $clock . '|\bonwards?\b))?';
    $timePattern = '(' . $singleTime . '(?:\s*[\/\\\\]\s*' . $singleTime . ')*)';
    if (preg_match('/^(.*?)(?:\s+at\s+|\s*[,;–—\-\/\\\\]\s*|\s*\()' . $timePattern . '\)?\s*$/iu', $working, $tm)) {
        if (calendarLooksLikeStandaloneTime($tm[2])) {
            $extractedTime = trim($tm[2]);
            $working = calendarTrimPunctuation($tm[1], true);
        }
    }

    // Re-check trailing location in case format was "Title at Location (Time)" or "Title – Location / Time"
    if ($extractedLocation === '' && preg_match('/^(.*?)[\s,;]*(?:\b(?:venue|location|place)\s*:\s*|(?:(?<=\s)|^)(?:at|@)\s+)([^,;()]+)$/iu', $working, $locMatch)) {
        $candidateLoc = trim($locMatch[2]);
        if (calendarLooksLikeStandaloneLocation($candidateLoc) || calendarLooksLikeStandaloneLocation('at ' . $candidateLoc)) {
            $extractedLocation = $candidateLoc;
            $working = calendarTrimPunctuation($locMatch[1]);
        }
    } elseif ($extractedLocation === '' && $extractedTime !== '' && preg_match('/^(.*?)\s*[-–—]\s*([^,;()]+)$/u', $working, $locMatch)) {
        $candidateLoc = trim($locMatch[2]);
        if (calendarLooksLikeStandaloneLocation($candidateLoc)) {
            $extractedLocation = $candidateLoc;
            $working = calendarTrimPunctuation($locMatch[1]);
        }
    }

    // Check if $working is now a standalone date (e.g. "November 15, 2026 (8:00 AM - 5:00 PM)")
    if (calendarLooksLikeStandaloneDate($working, $year, $contextMonth, $allowBareDay)) {
        return ['kind' => 'date', 'date' => $working, 'time' => $extractedTime, 'location' => $extractedLocation];
    }

    // 3. Check for "Date - Title" or "Title - Date" or "Title (Date)" or "Title on Date"
    $months = calendarMonthRegex();
    $dow = '(?:Mon(?:day)?|Tue(?:s(?:day)?)?|Wed(?:nesday)?|Thu(?:r(?:s(?:day)?)?)?|Fri(?:day)?|Sat(?:urday)?|Sun(?:day)?)';
    $dateExpr = '(?:(?:' . $dow . '\.?,?\s*)?(?:(?:' . $months . ')\.?\s+\d{1,2}(?:st|nd|rd|th)?(?:,?\s+\d{4})?(?:\s*(?:-|–|—|\bto\b|&)\s*(?:(?:' . $months . ')\.?\s+)?\d{1,2}(?:st|nd|rd|th)?(?:,?\s+\d{4})?)?|\d{1,2}(?:st|nd|rd|th)?(?:\s*(?:-|–|—|\bto\b)\s*\d{1,2}(?:st|nd|rd|th)?)?\s+(?:' . $months . ')\.?(?:,?\s+\d{4})?|\d{4}-\d{2}-\d{2}(?:\s*(?:-|–|—|\bto\b)\s*\d{4}-\d{2}-\d{2})?))';

    // Date on the left: "November 15, 2026 – College Foundation Day"
    if (preg_match('/^(' . $dateExpr . ')\s*(?:[-–—:|]|\s+-\s+)\s*(.+)$/iu', $working, $dm)) {
        if (calendarLooksLikeStandaloneDate($dm[1], $year, $contextMonth, true)) {
            $extractedDate = trim($dm[1]);
            $working = trim($dm[2]);
        }
    }
    // Context-month day on the left: "15-17 - Midterm Examinations" (never split clock times like "7:00" or hyphenated words like "1-minute")
    elseif ($contextMonth && $allowBareDay && preg_match('/^(\d{1,2}(?:\s*[-–—]\s*\d{1,2})?)(?!:\d{2}|\s*[-–—]?\s*(?:minute|min|hour|hr|day|week|month|year|point|step|way|part|tier|level|stage|phase|fold|piece|page|item)\b)\s*(?:\s+[-–—|]\s+|\s*:\s+)\s*(.+)$/iu', $working, $dm)) {
        if (calendarLooksLikeStandaloneDate($dm[1], $year, $contextMonth, true)) {
            $extractedDate = trim($dm[1]);
            $working = trim($dm[2]);
        }
    }
    // Date in parentheses on the right: "College Foundation Day (November 15, 2026)"
    elseif (preg_match('/^(.+?)\s*\((' . $dateExpr . ')\)\s*$/iu', $working, $dm)) {
        if (calendarLooksLikeStandaloneDate($dm[2], $year, $contextMonth, true)) {
            $working = trim($dm[1]);
            $extractedDate = trim($dm[2]);
        }
    }
    // Date on the right: "College Foundation Day – November 15, 2026" or "College Foundation Day on November 15, 2026"
    elseif (preg_match('/^(.+?)\s*(?:[-–—:|]|\bon\b|,)\s*(' . $dateExpr . ')$/iu', $working, $dm)) {
        if (calendarLooksLikeStandaloneDate($dm[2], $year, $contextMonth, true)) {
            $working = trim($dm[1]);
            $extractedDate = trim($dm[2]);
        }
    }

    // Re-check if $working still has a trailing time or location after stripping date
    if ($extractedTime === '' && preg_match('/^(.*?)(?:\s+at\s+|\s*[,;–—-]\s*|\s*\()' . $timePattern . '\)?\s*$/iu', $working, $tm)) {
        if (calendarLooksLikeStandaloneTime($tm[2])) {
            $extractedTime = trim($tm[2]);
            $working = calendarTrimPunctuation($tm[1], true);
        }
    }
    if ($extractedLocation === '' && preg_match('/^(.*?)\s+(?:[-–—])\s+([^,;()]+)$/u', $working, $locMatch)) {
        if (calendarLooksLikeStandaloneLocation($locMatch[2])) {
            $extractedLocation = trim($locMatch[2]);
            $working = trim($locMatch[1]);
        }
    }

    return [
        'kind' => 'event',
        'title' => calendarTrimPunctuation($working),
        'date' => $extractedDate,
        'time' => $extractedTime,
        'location' => $extractedLocation,
        'description' => ''
    ];
}

function calendarClassify(string $text, array $categories, string $explicitCategory = ''): ?int {
    if ($explicitCategory !== '') {
        foreach ($categories as $cat) {
            if (strcasecmp($cat['category_name'], trim($explicitCategory)) === 0 || stripos($cat['category_name'], trim($explicitCategory)) !== false) {
                return (int)$cat['category_id'];
            }
        }
    }
    $combined = trim($explicitCategory . ' ' . $text);
    $rules = [
        'Suspension' => '/\b(suspension|suspended|emergency|typhoon|evacuation|earthquake|flood|inclement weather)\b/i',
        'Emergency'  => '/\b(suspension|suspended|emergency|typhoon|evacuation|earthquake|flood|inclement weather)\b/i',
        'Holiday'    => '/\b(holiday|christmas break|christmas day|christmas eve|new year|holy week|maundy thursday|good friday|black saturday|easter|independence day|labor day|all saints|all souls|bonifacio day|rizal day|ninoy aquino day|national heroes day|eid|eidul|chinese new year|feast of|special non-working|regular holiday|semestral break|day of valor|araw ng kagitingan|people power|abad santos|pinatubo|pampanga day|town fiesta)\b/i',
        'Academic'   => '/\b(exam(?:ination)?s?|prelim(?:inary)?|midterm|mid\s+term|final exam|summative\s+test|assessment|enrol(?:l)?ment|registration|classes|start of classes|end of classes|first day of classes|last day of classes|resumption of classes|summer class|grading|submission of .*grading|encoding of grades|report\s+cards?|deliberation|end-of-term|instructional\s+block|semester|trimester|academic|thesis|defense|colloquium|commencement|graduation|recognition day|completion|checking of permit|internship|deployment|evaluation)\b/i',
        'School Event' => '/\b(foundation|founding\s+anniversary|anniversary|intramurals|sports\s*fest|zonal\s+meet|interzonal|pasdam|olympics|graduation|commencement|recognition|orientation|assembly|celebration|awareness|program|ceremony|pinning|cap(?:ping)?|hard\s+hatting|labcoating|send-off|seminar|workshop|training|teambuilding|congress|conference|pta\s+meeting|faculty\s+meeting|miting\s+de\s+avance|campaign|candidacy|election|voting|elected|induction|retreat|recollection|mass|rosary|blessing|pageant|parade|fair|booth|exhibit|contest|competition|quiz|challenge|scavenger\s+hunt|amazing\s+race|balagtasan|harana|culminating|acquaintance|promenade|gnc\s+night|party|trick\s+or\s+treat|gift\s+giving|outreach|blood\s+donation|vaccination|counseling|testing\s+service|needs\s+assessment|career|job\s+fair|tour|mice\s+event|pictorial|nat\b|ncae\b|festivals?|month|week|day|days|color(?:ing)?|pagkulay|draw(?:ing)?|painting|design|slogan|poster|bilao|recipe|kite|seed\s+planting|trash\s+to\s+treasure|tinig\s+ng\s+kuwento|vision\s+tree|growing\s+with\s+our\s+vision|commitment\s+wall|stick\s+it|dress\s+like|dress\s+up|book\s+marking|book\s+cover|big\s+book|story\s+circle|literacy|vocabulary|heart\s+craft|bracelet\s+making|collage|multimedia|portfolio|simulation|mapping|mix\s+and\s+match|picture\s+analysis|infomercial|vlog|school|exam(?:ination)?s?|enrol(?:l)?ment|registration|classes|grading|semester)\b/i',
    ];
    foreach ($rules as $name => $pattern) {
        if (!preg_match($pattern, $combined)) continue;
        foreach ($categories as $cat) {
            if (strcasecmp($cat['category_name'], $name) === 0) return (int)$cat['category_id'];
        }
    }
    return null;
}

function calendarIsDocumentNoise(string $text): bool {
    if (str_contains($text, "\t")) {
        $tabParts = array_values(array_filter(array_map('trim', explode("\t", $text)), static fn($p) => $p !== ''));
        if (count($tabParts) >= 2) {
            $allNoise = true;
            foreach ($tabParts as $tp) {
                if (!calendarIsDocumentNoise($tp)) {
                    $allNoise = false;
                    break;
                }
            }
            if ($allNoise) return true;
        }
    }
    $clean = trim(preg_replace('/\s+/u', ' ', $text));
    if ($clean === '') return true;
    if (preg_match('/^(?:calendar\s+of\s+activities|school\s+calendar|academic\s+calendar|institutional\s+calendar(?:\s+of\s+activities)?|activity\s+calendar|events?\s+calendar|schedule\s+of\s+activities|list\s+of\s+events|list\s+of\s+activities)(?:\s*[-–—:]?\s*(?:s\.?y\.?|a\.?y\.?|school\s+year|academic\s+year)?\s*\d{4}(?:\s*[-–—]\s*\d{4})?)?$/iu', $clean)) {
        return true;
    }
    if (preg_match('/^(?:(?:s\.?y\.?|a\.?y\.?|school\s+year|academic\s+year)\s*\d{4}(?:\s*[-–—]\s*\d{4})?|(?:1st|2nd|first|second)\s+semester(?:\s*,\s*(?:s\.?y\.?|a\.?y\.?)?\s*\d{4}(?:\s*[-–—]\s*\d{4})?)?|guagua\s+national\s+colleges(?:\s*,\s*inc\.?)?)$/iu', $clean)) {
        return true;
    }
    if (preg_match('/^(?:prepared\s+by|noted\s+by|approved\s+by|approved|reviewed\s+by|recommending\s+approval|certified\s+correct|truly\s+yours|respectfully\s+yours|sincerely\s+yours|for\s+your\s+approval|note|notes)\s*[:.,]?$/iu', $clean)) {
        return true;
    }
    // Cover letter / header / footer / signatory lines
    if (preg_match('/^(?:guagua\s*,\s*pampanga|tel\.?\s*no\.?\b.*|instruction\s+cluster\s+office|dear\s+(?:ma[\'’]?am|sir|madam)\s*[:.,]?|herewith\s+is\s+the\b.*|filename\s*:.*)$/iu', $clean)) {
        return true;
    }
    // Signatory job titles
    if (preg_match('/^(?:president|vice\s+president\b.*|chief\s+[a-z\s]+\s+officer|head\s*,\s*.+|dean\s*,\s*.+|principal\s*,\s*.+|oic\s*,\s*.+|director\s*,\s*.+)$/iu', $clean)) {
        return true;
    }
    // Signatory names with credentials or honorifics (e.g., "GERALDINE G. LIM, MBA", "ALLEN JAN S. NULUD, Ph.D.", "ENGR. GENEROSO YTALIA, RCE", "MR. JOHNNY C. GARCIA")
    $cred = '(?:MBA|Ph\.?D\.?|Ed\.?D\.?|MAEd|MAN|MS\s*Crim\.?|M\.?A\.?|M\.?S\.?|MMEP|ENP|LPT|CPA|RN|RM|RCE|RMT|RPh|RPm|RGC|MD|JD|DBA)';
    if (!calendarHasActivityKeywords($clean) && (
        preg_match('/^(?:mr\.|ms\.|mrs\.|dr\.|engr\.|atty\.|rev\.|fr\.|sr\.)\s+[A-Z][A-Za-z.\s,\-\']+$/iu', $clean)
        || preg_match('/^[A-Z][A-Z.\s\-\']+,\s*' . $cred . '(?:[\s,]+' . $cred . ')*$/u', $clean)
    )) {
        return true;
    }
    return false;
}

function calendarMatchMonthHeading(string $text): ?array {
    $clean = trim(preg_replace('/\s+/u', ' ', $text));
    $monthNames = [
        'january'=>1,'jan'=>1,'february'=>2,'feb'=>2,'march'=>3,'mar'=>3,'april'=>4,'apr'=>4,
        'may'=>5,'june'=>6,'jun'=>6,'july'=>7,'jul'=>7,'august'=>8,'aug'=>8,
        'september'=>9,'sept'=>9,'sep'=>9,'october'=>10,'oct'=>10,'november'=>11,'nov'=>11,'december'=>12,'dec'=>12
    ];
    if (preg_match('/^(?:month\s+of\s+)?(' . implode('|', array_keys($monthNames)) . ')\.?(?:\s+(\d{4}))?(?:\s+(?:activities|events|calendar|schedule))?$/iu', $clean, $m)) {
        return [
            'month' => $monthNames[strtolower($m[1])],
            'year' => !empty($m[2]) ? (int)$m[2] : null
        ];
    }
    return null;
}

function calendarEmptyDraft(?int $month = null, ? int $year = null): array {
    return [
        'title' => '',
        'date' => '',
        'end_date' => '',
        'time' => '',
        'end_time' => '',
        'location' => '',
        'location_provided' => false,
        'description' => '',
        'category' => '',
        'department' => '',
        'program' => '',
        'month' => $month,
        'year' => $year,
        'sources' => [],
        'bullet' => false
    ];
}

function calendarHasDraftContent(array $draft): bool {
    return $draft['title'] !== '' || $draft['date'] !== '' || $draft['time'] !== '' || $draft['location'] !== '' || $draft['description'] !== '' || $draft['department'] !== '' || $draft['program'] !== '';
}

function calendarApplyFieldToDraft(array &$draft, string $field, string $value, int $year, ?int $month): void {
    $value = trim($value);
    if ($value === '') return;

    if ($field === 'title') {
        // Check if value accidentally has embedded time/location/date only if not separate
        $draft['title'] = $draft['title'] === '' ? $value : $draft['title'] . ' — ' . $value;
        return;
    }
    if ($field === 'date') {
        // Check if the date value also contains an embedded time (e.g. "November 15, 2026, 8:00 AM - 5:00 PM")
        $clock = '(?:\d{1,2}:\d{2}\s*(?:a\.?m\.?|p\.?m\.?|nn|noon|mn)?|\d{1,2}\s*(?:a\.?m\.?|p\.?m\.?|nn|noon|mn))';
        $timePattern = '(' . $clock . '(?:\s*(?:-|–|—|\bto\b|\buntil\b)\s*' . $clock . ')?)';
        if ($draft['time'] === '' && preg_match('/^(.*?)(?:\s+at\s+|\s*[,;|]\s*|\s*\()' . $timePattern . '\)?\s*$/iu', $value, $tm)) {
            if (calendarLooksLikeStandaloneTime($tm[2]) && calendarLooksLikeStandaloneDate($tm[1], $year, $month, true)) {
                $draft['date'] = trim($tm[1]);
                $draft['time'] = trim($tm[2]);
                return;
            }
        }
        $draft['date'] = $value;
        return;
    }
    if ($field === 'end_date') {
        $draft['end_date'] = $value;
        return;
    }
    if ($field === 'time') {
        $draft['time'] = $draft['time'] === '' ? $value : $draft['time'] . '; ' . $value;
        return;
    }
    if ($field === 'end_time') {
        $draft['end_time'] = $value;
        return;
    }
    if ($field === 'location') {
        $draft['location'] = $draft['location'] === '' ? $value : $draft['location'] . ', ' . $value;
        $draft['location_provided'] = true;
        return;
    }
    if ($field === 'description') {
        $draft['description'] = $draft['description'] === '' ? $value : $draft['description'] . "\n" . $value;
        return;
    }
    if ($field === 'category') {
        $draft['category'] = $value;
        return;
    }
    if ($field === 'department') {
        $draft['department'] = $draft['department'] === '' ? $value : $draft['department'] . ', ' . $value;
        return;
    }
    if ($field === 'program') {
        $draft['program'] = $draft['program'] === '' ? $value : $draft['program'] . ', ' . $value;
    }
}

function calendarFinalizeDraft(array $draft, int $defaultYear, ?int $defaultMonth, array $categories, array &$rows): void {
    if (!calendarHasDraftContent($draft)) return;
    $year = $draft['year'] ?? $defaultYear;
    $month = $draft['month'] ?? $defaultMonth;

    $title = trim($draft['title']);
    $dateRaw = trim($draft['date']);
    $endDateRaw = trim($draft['end_date']);
    $timeRaw = trim($draft['time']);
    $endTimeRaw = trim($draft['end_time']);
    $location = trim($draft['location']);
    $description = trim($draft['description']);
    $source = trim(implode("\n", array_unique(array_filter($draft['sources']))));

    // Guard against dates, times, or locations being misclassified as the title
    if ($title !== '') {
        if ($dateRaw === '' && calendarLooksLikeStandaloneDate($title, $year, $month, false)) {
            $dateRaw = $title;
            $title = '';
        } elseif ($timeRaw === '' && calendarLooksLikeStandaloneTime($title)) {
            $timeRaw = $title;
            $title = '';
        } elseif ($location === '' && calendarLooksLikeStandaloneLocation($title)) {
            $location = preg_replace('/^(?:venue|location|place|where|at|@)\s*[:\-–—]?\s*/iu', '', $title);
            $draft['location_provided'] = true;
            $title = '';
        }
    }

    // If this block has no title and only a stray detail, try attaching it to the previous event if appropriate
    if ($title === '' && !empty($rows)) {
        $lastIdx = count($rows) - 1;
        $attached = false;
        if ($dateRaw === '' && $timeRaw !== '' && $rows[$lastIdx]['start_time'] === '' && $location === '' && $description === '') {
            [$st, $et, $tw] = calendarParseTimeRange($timeRaw);
            $rows[$lastIdx]['start_time'] = $st;
            $rows[$lastIdx]['end_time'] = $et;
            $rows[$lastIdx]['warnings'] = array_values(array_unique(array_merge($rows[$lastIdx]['warnings'], $tw)));
            $rows[$lastIdx]['source'] = trim($rows[$lastIdx]['source'] . "\n" . $source);
            return;
        }
        if ($dateRaw === '' && $timeRaw === '' && $location !== '' && $rows[$lastIdx]['location'] === '' && $description === '') {
            $rows[$lastIdx]['location'] = $location;
            $rows[$lastIdx]['location_provided'] = true;
            $rows[$lastIdx]['source'] = trim($rows[$lastIdx]['source'] . "\n" . $source);
            return;
        }
        if ($dateRaw === '' && $timeRaw === '' && $location === '' && $description !== '') {
            $rows[$lastIdx]['description'] = trim($rows[$lastIdx]['description'] . ($rows[$lastIdx]['description'] !== '' ? "\n" : '') . $description);
            $rows[$lastIdx]['source'] = trim($rows[$lastIdx]['source'] . "\n" . $source);
            return;
        }
    }

    // Do not create a false standalone event from a single orphan date/time/location without any other event details
    $detailCount = ($dateRaw !== '' ? 1 : 0) + ($timeRaw !== '' ? 1 : 0) + ($location !== '' ? 1 : 0) + ($description !== '' ? 1 : 0);
    if ($title === '' && $detailCount <= 1) {
        return;
    }

    // Extract inline time from date or title if time wasn't provided separately
    if ($timeRaw === '') {
        $clock = '(?:\d{1,2}(?::\d{2})?\s*(?:a\.?m\.?|p\.?m\.?|nn|noon|mn)|\d{1,2}:\d{2})';
        foreach (['dateRaw', 'title'] as $fieldVar) {
            if (preg_match('/(?:\s+at\s+|\s*[,;]\s*|\s*\()((?:' . $clock . ')(?:\s*(?:-|–|—|\bto\b)?\s*(?:' . $clock . '|\bonwards?\b))?)\)?$/iu', $$fieldVar, $clockMatch)) {
                if (calendarLooksLikeStandaloneTime($clockMatch[1])) {
                    $timeRaw = $clockMatch[1];
                    $$fieldVar = calendarTrimPunctuation(substr($$fieldVar, 0, -strlen($clockMatch[0])), true);
                    break;
                }
            }
        }
    }

    [$start, $end, $warnings] = calendarParseDates($dateRaw, $year, $month);
    if ($endDateRaw !== '') {
        [$parsedEnd, , $endWarnings] = calendarParseDates($endDateRaw, $year, $month);
        if ($parsedEnd !== '') {
            $end = $parsedEnd;
        } else {
            $warnings = array_merge($warnings, $endWarnings);
        }
    }

    $startTime = '';
    $endTime = '';
    if ($timeRaw !== '') {
        [$startTime, $endTime, $timeWarnings] = calendarParseTimeRange($timeRaw);
        $warnings = array_merge($warnings, $timeWarnings);
    }
    if ($endTimeRaw !== '') {
        [$parsedEndTime, , $endTimeWarnings] = calendarParseTimeRange($endTimeRaw);
        if ($parsedEndTime !== '') {
            $endTime = $parsedEndTime;
        } else {
            $warnings = array_merge($warnings, $endTimeWarnings);
        }
    }

    $missingFields = [];
    if ($title === '') {
        $warnings[] = 'Event title is missing or unclear. Enter an event title.';
        $missingFields[] = 'title';
    }
    if ($start === '' || !calendarValidDate($start) || ($end !== '' && (!calendarValidDate($end) || $end < $start))) {
        if ($start !== '' && (!calendarValidDate($start) || ($end !== '' && (!calendarValidDate($end) || $end < $start)))) {
            $warnings[] = 'Invalid date range. Please correct it.';
        }
        $missingFields[] = 'start_date';
    }
    foreach ($warnings as $w) {
        if (stripos($w, 'time') !== false && !in_array('start_time', $missingFields, true)) {
            $missingFields[] = 'start_time';
        }
    }
    if ($location !== '' && preg_match('/^(?:tba|tbd|to\s+be\s+announced|to\s+be\s+determined|\?+)$/iu', $location)) {
        $warnings[] = 'Location is marked as TBA or unclear. Verify the venue.';
        $missingFields[] = 'location';
    }

    $cat = calendarClassify($title . ' ' . $description, $categories, $draft['category']);
    if (!$cat) {
        $warnings[] = 'Category needs review.';
        $missingFields[] = 'category_id';
    }

    $rows[] = [
        'title' => $title,
        'start_date' => $start,
        'end_date' => $end,
        'start_time' => $startTime,
        'end_time' => $endTime,
        'description' => $description,
        'location' => $location,
        'location_provided' => (bool)$draft['location_provided'],
        'category_id' => $cat,
        'department_hint' => trim($draft['department'] ?? ''),
        'program_hint' => trim($draft['program'] ?? ''),
        'warnings' => array_values(array_unique($warnings)),
        'missing_fields' => array_values(array_unique($missingFields)),
        'source' => $source,
        'event_id' => 0,
        'action' => 'create'
    ];
    if (count($rows) > 1500) {
        throw new InvalidArgumentException('Import at most 1,500 activities at a time.');
    }
}

/**
 * Context-aware grouping of sequential lines (paragraphs, list items, or cell lines) into events.
 */
function calendarProcessLineStream(array $items, int &$year, ?int &$month, array $categories, array &$rows, array $seed = []): void {
    $current = null;
    $sawBlank = false;
    $pendingLabel = null;
    $inDateCell = ($seed['date'] ?? '') !== '';
    $streamStartRowCount = count($rows);

    $initDraft = static function () use (&$year, &$month, $seed): array {
        $d = calendarEmptyDraft($month, $year);
        if (!empty($seed['date'])) $d['date'] = $seed['date'];
        if (!empty($seed['end_date'])) $d['end_date'] = $seed['end_date'];
        if (!empty($seed['time'])) $d['time'] = $seed['time'];
        if (!empty($seed['location'])) {
            $d['location'] = $seed['location'];
            $d['location_provided'] = true;
        } elseif (!empty($seed['location_provided'])) {
            $d['location_provided'] = true;
        }
        if (!empty($seed['description'])) $d['description'] = $seed['description'];
        if (!empty($seed['category'])) $d['category'] = $seed['category'];
        if (!empty($seed['department'])) $d['department'] = $seed['department'];
        if (!empty($seed['program'])) $d['program'] = $seed['program'];
        if (!empty($seed['source'])) $d['sources'][] = $seed['source'];
        return $d;
    };

    $flush = static function () use (&$current, &$year, &$month, $categories, &$rows, &$sawBlank): void {
        if ($current !== null && calendarHasDraftContent($current)) {
            calendarFinalizeDraft($current, $year, $month, $categories, $rows);
        }
        $current = null;
        $sawBlank = false;
    };

    $count = count($items);
    for ($idx = 0; $idx < $count; $idx++) {
        $item = $items[$idx];
        $type = $item['type'] ?? 'line';

        if ($type === 'blank') {
            $pendingLabel = null;
            if ($current !== null && calendarHasDraftContent($current)) {
                $sawBlank = true;
            }
            continue;
        }

        $text = trim($item['text'] ?? '');
        if ($text === '') continue;

        $monthHeading = calendarMatchMonthHeading($text);
        if ($monthHeading !== null) {
            $flush();
            $nextMonth = $monthHeading['month'];
            if ($monthHeading['year'] !== null) {
                $year = $monthHeading['year'];
            } elseif ($month !== null && $nextMonth < $month) {
                $year++;
            }
            $month = $nextMonth;
            continue;
        }

        if (calendarIsDocumentNoise($text)) {
            $flush();
            continue;
        }

        // Check for explicit field labels ("Event Title: ...", "Date: ...", "Time: ...", "Location: ...")
        $labeled = calendarParseLabeledLine($text);
        if ($labeled !== null) {
            $field = $labeled['field'];
            $val = $labeled['value'];
            if ($val === '') {
                if ($field === 'title' && $current !== null && $current['title'] !== '') {
                    $flush();
                }
                $pendingLabel = $field;
                continue;
            }
            $pendingLabel = null;
            if ($field === 'title' && $current !== null && $current['title'] !== '') {
                $flush();
            } elseif ($field === 'date' && $current !== null && !$inDateCell && ($current['date'] !== '' || ($sawBlank && ($current['time'] !== '' || $current['location'] !== '')))) {
                $flush();
            } elseif ($field === 'time' && $current !== null && $current['time'] !== '' && $sawBlank && ($current['title'] !== '' || $current['date'] !== '')) {
                $flush();
            } elseif ($field === 'location' && $current !== null && $current['location'] !== '' && $sawBlank && ($current['title'] !== '' || $current['date'] !== '')) {
                $flush();
            }
            if ($current === null) $current = $initDraft();
            calendarApplyFieldToDraft($current, $field, $val, $year, $month);
            $current['sources'][] = $text;
            $sawBlank = false;
            continue;
        }

        if ($pendingLabel !== null) {
            if ($pendingLabel === 'title' && $current !== null && $current['title'] !== '') {
                $flush();
            } elseif ($pendingLabel === 'date' && $current !== null && $current['date'] !== '' && !$inDateCell) {
                $flush();
            }
            if ($current === null) $current = $initDraft();
            calendarApplyFieldToDraft($current, $pendingLabel, $text, $year, $month);
            $current['sources'][] = $text;
            $pendingLabel = null;
            $sawBlank = false;
            continue;
        }

        $parsed = calendarParseUnlabeledLine($text, $year, $month, !$inDateCell && $month !== null);
        $kind = $parsed['kind'] ?? 'empty';

        if ($kind === 'labeled') {
            $field = $parsed['field'];
            if ($field === 'title' && $current !== null && $current['title'] !== '') $flush();
            elseif ($field === 'date' && $current !== null && $current['date'] !== '' && !$inDateCell) $flush();
            if ($current === null) $current = $initDraft();
            calendarApplyFieldToDraft($current, $field, $parsed['value'], $year, $month);
            $current['sources'][] = $text;
            $sawBlank = false;
            continue;
        }

        if ($kind === 'date') {
            if ($current !== null && $current['date'] === '') {
                calendarApplyFieldToDraft($current, 'date', $parsed['date'], $year, $month);
                if (!empty($parsed['time'])) calendarApplyFieldToDraft($current, 'time', $parsed['time'], $year, $month);
                if (!empty($parsed['location'])) calendarApplyFieldToDraft($current, 'location', $parsed['location'], $year, $month);
                $current['sources'][] = $text;
            } else {
                $flush();
                $current = $initDraft();
                calendarApplyFieldToDraft($current, 'date', $parsed['date'], $year, $month);
                if (!empty($parsed['time'])) calendarApplyFieldToDraft($current, 'time', $parsed['time'], $year, $month);
                if (!empty($parsed['location'])) calendarApplyFieldToDraft($current, 'location', $parsed['location'], $year, $month);
                $current['sources'][] = $text;
            }
            $sawBlank = false;
            continue;
        }

        if ($kind === 'venue_time') {
            $vtLoc = trim($parsed['location'] ?? '');
            $vtTime = trim($parsed['time'] ?? '');
            $vtDept = trim($parsed['department'] ?? '');
            $vtNotes = trim($parsed['notes'] ?? '');

            if ($current !== null) {
                if ($vtLoc !== '') calendarApplyFieldToDraft($current, 'location', $vtLoc, $year, $month);
                if ($vtTime !== '' && $current['time'] === '') calendarApplyFieldToDraft($current, 'time', $vtTime, $year, $month);
                if ($vtDept !== '') calendarApplyFieldToDraft($current, 'department', $vtDept, $year, $month);
                if ($vtNotes !== '') calendarApplyFieldToDraft($current, 'description', $vtNotes, $year, $month);
                $current['sources'][] = $text;
            } elseif (!empty($rows)) {
                $last = count($rows) - 1;
                if ($vtLoc !== '' && $rows[$last]['location'] === '') {
                    $rows[$last]['location'] = $vtLoc;
                    $rows[$last]['location_provided'] = true;
                }
                if ($vtTime !== '' && $rows[$last]['start_time'] === '') {
                    [$st, $et, $tw] = calendarParseTimeRange($vtTime);
                    $rows[$last]['start_time'] = $st;
                    $rows[$last]['end_time'] = $et;
                    $rows[$last]['warnings'] = array_values(array_unique(array_merge($rows[$last]['warnings'], $tw)));
                }
                if ($vtDept !== '' && ($rows[$last]['department_hint'] ?? '') === '') {
                    $rows[$last]['department_hint'] = $vtDept;
                }
                if ($vtNotes !== '') {
                    $rows[$last]['description'] = trim($rows[$last]['description'] . ($rows[$last]['description'] !== '' ? "\n" : '') . $vtNotes);
                }
                $rows[$last]['source'] = trim($rows[$last]['source'] . "\n" . $text);
            } else {
                $current = $initDraft();
                if ($vtLoc !== '') calendarApplyFieldToDraft($current, 'location', $vtLoc, $year, $month);
                if ($vtTime !== '') calendarApplyFieldToDraft($current, 'time', $vtTime, $year, $month);
                if ($vtDept !== '') calendarApplyFieldToDraft($current, 'department', $vtDept, $year, $month);
                if ($vtNotes !== '') calendarApplyFieldToDraft($current, 'description', $vtNotes, $year, $month);
                $current['sources'][] = $text;
            }

            // When a shared classroom-based line appears at the bottom of a date cell after multiple activities, backfill consecutive preceding sibling events in the same cell that don't yet have a location
            if ($inDateCell && stripos($vtLoc, 'Classroom') !== false) {
                $targetIdx = ($current !== null) ? count($rows) - 1 : count($rows) - 2;
                for ($sibIdx = $targetIdx; $sibIdx >= $streamStartRowCount; $sibIdx--) {
                    if ($rows[$sibIdx]['location'] !== '') {
                        break;
                    }
                    $rows[$sibIdx]['location'] = $vtLoc;
                    $rows[$sibIdx]['location_provided'] = true;
                    if ($vtDept !== '' && ($rows[$sibIdx]['department_hint'] ?? '') === '') {
                        $rows[$sibIdx]['department_hint'] = $vtDept;
                    }
                }
            }
            $sawBlank = false;
            continue;
        }

        if ($kind === 'time') {
            if ($current !== null && $current['time'] === '') {
                calendarApplyFieldToDraft($current, 'time', $parsed['time'], $year, $month);
                $current['sources'][] = $text;
            } elseif ($current === null && !empty($rows) && $rows[count($rows) - 1]['start_time'] === '') {
                [$st, $et, $tw] = calendarParseTimeRange($parsed['time']);
                $rows[count($rows) - 1]['start_time'] = $st;
                $rows[count($rows) - 1]['end_time'] = $et;
                $rows[count($rows) - 1]['warnings'] = array_values(array_unique(array_merge($rows[count($rows) - 1]['warnings'], $tw)));
                $rows[count($rows) - 1]['source'] = trim($rows[count($rows) - 1]['source'] . "\n" . $text);
            } else {
                if ($current === null) $current = $initDraft();
                calendarApplyFieldToDraft($current, 'time', $parsed['time'], $year, $month);
                $current['sources'][] = $text;
            }
            $sawBlank = false;
            continue;
        }

        if ($kind === 'location') {
            if ($current !== null && $current['location'] === '') {
                calendarApplyFieldToDraft($current, 'location', $parsed['location'], $year, $month);
                $current['sources'][] = $text;
            } elseif ($current === null && !empty($rows) && $rows[count($rows) - 1]['location'] === '') {
                $rows[count($rows) - 1]['location'] = $parsed['location'];
                $rows[count($rows) - 1]['location_provided'] = true;
                $rows[count($rows) - 1]['source'] = trim($rows[count($rows) - 1]['source'] . "\n" . $text);
            } else {
                if ($current === null) $current = $initDraft();
                calendarApplyFieldToDraft($current, 'location', $parsed['location'], $year, $month);
                $current['sources'][] = $text;
            }
            $sawBlank = false;
            continue;
        }

        if ($kind === 'description') {
            if ($current !== null) {
                calendarApplyFieldToDraft($current, 'description', $parsed['description'], $year, $month);
                $current['sources'][] = $text;
            } elseif (!empty($rows)) {
                $last = count($rows) - 1;
                $rows[$last]['description'] = trim($rows[$last]['description'] . ($rows[$last]['description'] !== '' ? "\n" : '') . $parsed['description']);
                $rows[$last]['source'] = trim($rows[$last]['source'] . "\n" . $text);
            } else {
                $current = $initDraft();
                calendarApplyFieldToDraft($current, 'description', $parsed['description'], $year, $month);
                $current['sources'][] = $text;
            }
            $sawBlank = false;
            continue;
        }

        // $kind === 'event'
        $hasInlineDate = ($parsed['date'] ?? '') !== '';
        if ($hasInlineDate) {
            if ($current !== null && ($current['title'] !== '' || ($current['date'] !== '' && !$inDateCell))) {
                $flush();
            }
            if ($current === null) $current = $initDraft();
            if ($parsed['title'] !== '') calendarApplyFieldToDraft($current, 'title', $parsed['title'], $year, $month);
            calendarApplyFieldToDraft($current, 'date', $parsed['date'], $year, $month);
            if ($parsed['time'] !== '') calendarApplyFieldToDraft($current, 'time', $parsed['time'], $year, $month);
            if ($parsed['location'] !== '') calendarApplyFieldToDraft($current, 'location', $parsed['location'], $year, $month);
            if ($parsed['description'] !== '') calendarApplyFieldToDraft($current, 'description', $parsed['description'], $year, $month);
            $current['sources'][] = $text;
            $current['bullet'] = !empty($item['bullet']);
            $sawBlank = false;
            continue;
        }

        // Event title line without an inline date
        if ($current === null) {
            $current = $initDraft();
            calendarApplyFieldToDraft($current, 'title', $parsed['title'], $year, $month);
            if ($parsed['time'] !== '') calendarApplyFieldToDraft($current, 'time', $parsed['time'], $year, $month);
            if ($parsed['location'] !== '') calendarApplyFieldToDraft($current, 'location', $parsed['location'], $year, $month);
            if ($parsed['description'] !== '') calendarApplyFieldToDraft($current, 'description', $parsed['description'], $year, $month);
            $current['sources'][] = $text;
            $current['bullet'] = !empty($item['bullet']);
            $sawBlank = false;
            continue;
        }

        if ($current['title'] === '') {
            calendarApplyFieldToDraft($current, 'title', $parsed['title'], $year, $month);
            if ($parsed['time'] !== '') calendarApplyFieldToDraft($current, 'time', $parsed['time'], $year, $month);
            if ($parsed['location'] !== '') calendarApplyFieldToDraft($current, 'location', $parsed['location'], $year, $month);
            if ($parsed['description'] !== '') calendarApplyFieldToDraft($current, 'description', $parsed['description'], $year, $month);
            $current['sources'][] = $text;
            $current['bullet'] = !empty($item['bullet']);
            $sawBlank = false;
            continue;
        }

        // $current already has a title. Determine whether this line starts a new event or is a description/detail of $current:
        $startsNewEvent = false;
        $isSubScheduleOfCurrent = $inDateCell
            && ($parsed['location'] !== '' && $parsed['time'] !== '')
            && (
                preg_match('/^(?:mass|holy\s+mass|eucharistic\s+celebration|pinning\s+ceremony|send[\s\-]*off\s+ceremony|part\s+[i12]|registration|program\s+proper)$/iu', $parsed['title'])
                || ($parsed['title'] !== '' && stripos($current['title'], $parsed['title']) !== false)
            );

        if ($isSubScheduleOfCurrent) {
            $startsNewEvent = false;
            calendarApplyFieldToDraft($current, 'description', $text, $year, $month);
            if ($parsed['time'] !== '' && $current['time'] === '') calendarApplyFieldToDraft($current, 'time', $parsed['time'], $year, $month);
            if ($parsed['location'] !== '') calendarApplyFieldToDraft($current, 'location', $parsed['location'], $year, $month);
            $current['sources'][] = $text;
            $sawBlank = false;
            continue;
        }

        if ($sawBlank && ($current['date'] !== '' || $inDateCell)) {
            $startsNewEvent = true;
        } elseif ($parsed['time'] !== '' && $current['time'] !== '') {
            $startsNewEvent = true;
        } elseif ($inDateCell) {
            // Inside a Date-seeded table cell, each distinct activity line is a separate event unless it is an un-dated bulleted sub-item under a non-bulleted heading
            if (empty($current['bullet']) && !empty($item['bullet']) && !calendarHasActivityKeywords($parsed['title'])) {
                $startsNewEvent = false;
            } else {
                $startsNewEvent = true;
            }
        } else {
            // Look ahead at the next non-blank line: if $current already has a date, and the next line is a Date for this new title, start a new event
            for ($nextIdx = $idx + 1; $nextIdx < $count; $nextIdx++) {
                if (($items[$nextIdx]['type'] ?? 'line') === 'blank') continue;
                $nextText = trim($items[$nextIdx]['text'] ?? '');
                if ($nextText === '') continue;
                $nextLabeled = calendarParseLabeledLine($nextText);
                if ($nextLabeled !== null && $nextLabeled['field'] === 'date' && $current['date'] !== '') {
                    $startsNewEvent = true;
                } elseif ($nextLabeled === null && $current['date'] !== '' && calendarLooksLikeStandaloneDate($nextText, $year, $month, false)) {
                    $startsNewEvent = true;
                }
                break;
            }
        }

        if ($startsNewEvent) {
            $flush();
            $current = $initDraft();
            calendarApplyFieldToDraft($current, 'title', $parsed['title'], $year, $month);
            if ($parsed['time'] !== '') calendarApplyFieldToDraft($current, 'time', $parsed['time'], $year, $month);
            if ($parsed['location'] !== '') calendarApplyFieldToDraft($current, 'location', $parsed['location'], $year, $month);
            if ($parsed['description'] !== '') calendarApplyFieldToDraft($current, 'description', $parsed['description'], $year, $month);
            $current['sources'][] = $text;
            $current['bullet'] = !empty($item['bullet']);
        } else {
            calendarApplyFieldToDraft($current, 'description', $parsed['title'], $year, $month);
            if ($parsed['time'] !== '' && $current['time'] === '') calendarApplyFieldToDraft($current, 'time', $parsed['time'], $year, $month);
            if ($parsed['location'] !== '' && $current['location'] === '') calendarApplyFieldToDraft($current, 'location', $parsed['location'], $year, $month);
            if ($parsed['description'] !== '') calendarApplyFieldToDraft($current, 'description', $parsed['description'], $year, $month);
            $current['sources'][] = $text;
        }
        $sawBlank = false;
    }

    $flush();
}

function calendarDetectTableColumns(array $cells): ?array {
    $header = [];
    foreach ($cells as $i => $cell) {
        $key = strtolower(trim(preg_replace('/\s+/u', ' ', $cell)));
        if (in_array($key, ['date', 'dates', 'day', 'day/date', 'date/day', 'inclusive date', 'inclusive dates', 'when', 'schedule date'], true)) $header['date'] = $i;
        elseif (in_array($key, ['event', 'events', 'event title', 'event name', 'activity', 'activities', 'activity title', 'activity name', 'event/activity', 'activity/event', 'title', 'particulars', 'occasion', 'program/activity', 'what'], true)) $header['title'] = $i;
        elseif (in_array($key, ['description', 'details', 'event details', 'remarks', 'remark', 'notes', 'note', 'participants', 'person involved', 'persons involved', 'target participants', 'in-charge'], true)) $header['description'] = $i;
        elseif (in_array($key, ['time', 'times', 'event time', 'time schedule', 'schedule', 'hours'], true)) $header['time'] = $i;
        elseif (in_array($key, ['location', 'venue', 'place', 'where', 'room', 'venue/location', 'location/venue'], true)) $header['location'] = $i;
        elseif (in_array($key, ['category', 'event category', 'type', 'event type', 'classification'], true)) $header['category'] = $i;
        elseif (in_array($key, ['department', 'departments', 'dept', 'dept.', 'college', 'college/department', 'department/college', 'office/department', 'level', 'audience', 'target audience', 'for'], true)) $header['department'] = $i;
        elseif (in_array($key, ['program', 'programs', 'academic program', 'degree program', 'course', 'courses', 'program/course', 'course/program', 'strand', 'major'], true)) $header['program'] = $i;
        elseif ($key === 'month') $header['month'] = $i;
    }
    if ((isset($header['date']) && isset($header['title'])) || (count($header) >= 2 && (isset($header['date']) || isset($header['title'])))) {
        return $header;
    }
    return null;
}

function calendarIsWeekdayHeaderRow(array $cells): bool {
    if (count($cells) < 5 || count($cells) > 7) return false;
    $dowMatches = 0;
    foreach ($cells as $cell) {
        if (preg_match('/^(?:sun(?:day)?|mon(?:day)?|tue(?:s(?:day)?)?|wed(?:nesday)?|thu(?:r(?:s(?:day)?)?)?|fri(?:day)?|sat(?:urday)?)\.?$/iu', trim($cell))) {
            $dowMatches++;
        }
    }
    return $dowMatches >= 5;
}

function calendarParseKeyValueCells(array $cells): ?array {
    $nonEmpty = array_values(array_filter($cells, static fn($c) => trim($c) !== ''));
    if (count($nonEmpty) < 2) return null;

    // Check if cells form (Label, Value) pairs: e.g., 2 cells [Label, Value] or 4 cells [Label1, Val1, Label2, Val2]
    if (count($nonEmpty) === 2 || count($nonEmpty) === 4) {
        $pairs = [];
        for ($i = 0; $i < count($nonEmpty); $i += 2) {
            $labelCandidate = trim($nonEmpty[$i], " \t\n\r\0\x0B:");
            $valCandidate = trim($nonEmpty[$i + 1]);
            $parsed = calendarParseLabeledLine($labelCandidate . ': ' . $valCandidate);
            if ($parsed === null) return null;
            // Make sure the value cell isn't just another column header keyword when both are headers
            if (calendarDetectTableColumns([$labelCandidate, $valCandidate]) !== null) return null;
            $pairs[] = $parsed;
        }
        return $pairs;
    }
    return null;
}

function calendarExtractXml(string $xml, int $year, array $categories): array {
    if (strlen($xml) > 8 * 1024 * 1024 || preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
        throw new InvalidArgumentException('Unsafe or oversized document XML.');
    }
    $doc = new DOMDocument();
    $previous = libxml_use_internal_errors(true);
    try { $ok = $doc->loadXML($xml, LIBXML_NONET | LIBXML_COMPACT); }
    finally { libxml_clear_errors(); libxml_use_internal_errors($previous); }
    if (!$ok) throw new InvalidArgumentException('The document contains invalid XML.');

    $xp = new DOMXPath($doc);
    $xp->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

    $paragraph = static function (DOMNode $node) use ($xp): array {
        $text = '';
        foreach ($xp->query('.//w:t|.//w:tab|.//w:br|.//w:cr', $node) as $part) {
            if ($part->localName === 't') $text .= $part->textContent;
            elseif ($part->localName === 'tab') $text .= "\t";
            else $text .= "\n";
        }
        $trimmed = trim($text);
        $hasBullet = $xp->query('./w:pPr/w:numPr', $node)->length > 0
            || (bool)preg_match('/^(?:[•●○▪▫▸▹►▻\-–—*]+|\(?\d{1,2}[\.\)])\s+/u', $trimmed);
        return ['text' => $trimmed, 'bullet' => $hasBullet];
    };

    $rows = [];
    $month = null;
    $paragraphStream = [];

    $flushParagraphStream = static function () use (&$paragraphStream, &$year, &$month, $categories, &$rows): void {
        if (!empty($paragraphStream)) {
            calendarProcessLineStream($paragraphStream, $year, $month, $categories, $rows);
            $paragraphStream = [];
        }
    };

    $monthNames = [
        'january'=>1,'jan'=>1,'february'=>2,'feb'=>2,'march'=>3,'mar'=>3,'april'=>4,'apr'=>4,
        'may'=>5,'june'=>6,'jun'=>6,'july'=>7,'jul'=>7,'august'=>8,'aug'=>8,
        'september'=>9,'sept'=>9,'sep'=>9,'october'=>10,'oct'=>10,'november'=>11,'nov'=>11,'december'=>12,'dec'=>12
    ];

    foreach ($xp->query('//w:body/*') as $bodyChild) {
        $nodes = ($bodyChild->localName === 'sdt')
            ? $xp->query('./w:sdtContent/*', $bodyChild)
            : [$bodyChild];

        foreach ($nodes as $node) {
            if ($node->localName === 'p') {
                $pInfo = $paragraph($node);
                if ($pInfo['text'] === '') {
                    $paragraphStream[] = ['type' => 'blank'];
                } else {
                    $lines = preg_split('/\n+/u', $pInfo['text']);
                    foreach ($lines as $lineIdx => $lineText) {
                        $lineText = trim($lineText);
                        if ($lineText === '') continue;
                        $paragraphStream[] = [
                            'type' => 'line',
                            'text' => $lineText,
                            'bullet' => $pInfo['bullet'] || (bool)preg_match('/^(?:[•●○▪▫▸▹►▻\-–—*]+|\(?\d{1,2}[\.\)])\s+/u', $lineText)
                        ];
                    }
                }
                continue;
            }

            if ($node->localName !== 'tbl') {
                continue;
            }

            $flushParagraphStream();

            $columns = [];
            $lastDate = '';
            $isCalendarGrid = false;
            $kvStream = [];

            $flushKvStream = static function () use (&$kvStream, &$year, &$month, $categories, &$rows): void {
                if (!empty($kvStream)) {
                    calendarProcessLineStream($kvStream, $year, $month, $categories, $rows);
                    $kvStream = [];
                }
            };

            foreach ($xp->query('./w:tr', $node) as $tr) {
                $cells = [];
                $cellParagraphs = [];
                foreach ($xp->query('./w:tc', $tr) as $tc) {
                    $pList = [];
                    $pItems = [];
                    foreach ($xp->query('./w:p', $tc) as $p) {
                        $pInfo = $paragraph($p);
                        if ($pInfo['text'] === '') {
                            $pItems[] = ['type' => 'blank'];
                        } else {
                            $pList[] = $pInfo['text'];
                            foreach (preg_split('/\n+/u', $pInfo['text']) as $subLine) {
                                $subLine = trim($subLine);
                                if ($subLine === '') continue;
                                $pItems[] = [
                                    'type' => 'line',
                                    'text' => $subLine,
                                    'bullet' => $pInfo['bullet'] || (bool)preg_match('/^(?:[•●○▪▫▸▹►▻\-–—*]+|\(?\d{1,2}[\.\)])\s+/u', $subLine)
                                ];
                            }
                        }
                    }
                    $cells[] = trim(implode("\n", $pList));
                    $cellParagraphs[] = $pItems;
                }

                $nonEmptyCells = array_values(array_filter($cells, static fn($c) => $c !== ''));
                if (empty($nonEmptyCells)) continue;

                $whole = trim(implode(' ', $nonEmptyCells));
                $uniqueCells = array_values(array_unique($nonEmptyCells));

                // Check if row is a month heading or document title spanning the row
                if (count($uniqueCells) === 1) {
                    $singleText = $uniqueCells[0];
                    $mh = calendarMatchMonthHeading($singleText);
                    if ($mh !== null) {
                        $flushKvStream();
                        $nextMonth = $mh['month'];
                        if ($mh['year'] !== null) $year = $mh['year'];
                        elseif ($month !== null && $nextMonth < $month) $year++;
                        $month = $nextMonth;
                        $lastDate = '';
                        continue;
                    }
                    if (calendarIsDocumentNoise($singleText)) {
                        $flushKvStream();
                        continue;
                    }
                }

                // Check for 7-column weekday header (Sun Mon Tue Wed Thu Fri Sat)
                if (calendarIsWeekdayHeaderRow($cells)) {
                    $flushKvStream();
                    $isCalendarGrid = true;
                    continue;
                }

                // Check for standard table column headers (Date | Activity | Time | Location | Description)
                $detectedHeaders = calendarDetectTableColumns($cells);
                if ($detectedHeaders !== null) {
                    $flushKvStream();
                    $columns = $detectedHeaders;
                    $isCalendarGrid = false;
                    continue;
                }

                // Handle 7-column calendar grid cells ("15 \n College Foundation Day \n 8:00 AM - 5:00 PM")
                if ($isCalendarGrid && $month !== null) {
                    foreach ($cellParagraphs as $cItems) {
                        $lineItems = array_values(array_filter($cItems, static fn($it) => ($it['type'] ?? '') === 'line'));
                        if (empty($lineItems)) continue;
                        $firstLine = $lineItems[0]['text'];
                        if (preg_match('/^(\d{1,2})(?:\s+(.+))?$/u', $firstLine, $dayMatch)) {
                            $dayNum = (int)$dayMatch[1];
                            if ($dayNum >= 1 && $dayNum <= 31) {
                                $cellDate = sprintf('%04d-%02d-%02d', $year, $month, $dayNum);
                                $restItems = array_slice($cItems, 1);
                                if (!empty($dayMatch[2])) {
                                    array_unshift($restItems, ['type' => 'line', 'text' => trim($dayMatch[2]), 'bullet' => false]);
                                }
                                if (!empty($restItems)) {
                                    calendarProcessLineStream($restItems, $year, $month, $categories, $rows, ['date' => $cellDate]);
                                }
                            }
                        }
                    }
                    continue;
                }

                // Check for Key-Value row (e.g. "Event Title | College Foundation Day", "Date | November 15, 2026")
                if (empty($columns)) {
                    $kvPairs = calendarParseKeyValueCells($cells);
                    if ($kvPairs !== null) {
                        foreach ($kvPairs as $pair) {
                            $kvStream[] = ['type' => 'line', 'text' => ucfirst($pair['label']) . ': ' . $pair['value'], 'bullet' => false];
                        }
                        continue;
                    }
                }

                // Single-column table or single non-empty cell when no column headers exist
                if (count($cells) === 1 || (empty($columns) && count($nonEmptyCells) === 1 && $lastDate === '')) {
                    $singleIdx = array_key_first(array_filter($cells, static fn($c) => $c !== ''));
                    if ($singleIdx !== null) {
                        foreach ($cellParagraphs[$singleIdx] as $it) {
                            $kvStream[] = $it;
                        }
                        // If cell had multiple lines forming a block, add a blank separator hint after the cell
                        if (count($cellParagraphs[$singleIdx]) > 1) {
                            $kvStream[] = ['type' => 'blank'];
                        }
                    }
                    continue;
                }

                $flushKvStream();

                // Multi-column table row extraction
                $date = '';
                $title = '';
                $description = '';
                $time = '';
                $location = '';
                $categoryText = '';
                $departmentText = '';
                $programText = '';
                $titleCellIndex = null;

                if (!empty($columns)) {
                    if (isset($columns['month']) && isset($cells[$columns['month']])) {
                        $monthText = strtolower(trim($cells[$columns['month']]));
                        if (isset($monthNames[$monthText])) {
                            $month = $monthNames[$monthText];
                        }
                    }
                    $date = isset($columns['date']) ? ($cells[$columns['date']] ?? '') : '';
                    $titleCellIndex = $columns['title'] ?? 1;
                    $title = $cells[$titleCellIndex] ?? '';
                    $time = isset($columns['time']) ? ($cells[$columns['time']] ?? '') : '';
                    $location = isset($columns['location']) ? ($cells[$columns['location']] ?? '') : '';
                    $categoryText = isset($columns['category']) ? ($cells[$columns['category']] ?? '') : '';
                    $departmentText = isset($columns['department']) ? ($cells[$columns['department']] ?? '') : '';
                    $programText = isset($columns['program']) ? ($cells[$columns['program']] ?? '') : '';
                    if (isset($columns['description'])) {
                        $description = $cells[$columns['description']] ?? '';
                    } else {
                        $extra = array_diff_key($cells, array_flip(array_values($columns)));
                        $description = trim(implode("\n", array_filter($extra, static fn($v) => $v !== '')));
                    }
                } else {
                    // Unlabeled multi-column table: inspect cell contents contextually
                    $usedIndices = [];
                    foreach ($cells as $cIdx => $cVal) {
                        if ($cVal === '') continue;
                        $isDateCell = ($cIdx === 0)
                            ? calendarIsDateCellText($cVal, $year, $month)
                            : calendarLooksLikeStandaloneDate($cVal, $year, $month, true);
                        if ($date === '' && $isDateCell) {
                            $date = $cVal;
                            $usedIndices[$cIdx] = true;
                        } elseif ($time === '' && calendarLooksLikeStandaloneTime($cVal)) {
                            $time = $cVal;
                            $usedIndices[$cIdx] = true;
                        } elseif ($location === '' && count($cells) >= 3 && calendarLooksLikeStandaloneLocation($cVal)) {
                            $location = preg_replace('/^(?:venue|location|place|where|at|@)\s*[:\-–—]?\s*/iu', '', $cVal);
                            $usedIndices[$cIdx] = true;
                        }
                    }
                    foreach ($cells as $cIdx => $cVal) {
                        if ($cVal === '' || isset($usedIndices[$cIdx])) continue;
                        if ($titleCellIndex === null) {
                            $titleCellIndex = $cIdx;
                            $title = $cVal;
                            $usedIndices[$cIdx] = true;
                        } else {
                            $description = trim($description . ($description !== '' ? "\n" : '') . $cVal);
                        }
                    }
                }

                $dateWasEmpty = (trim($date) === '');
                if ($dateWasEmpty) {
                    $date = $lastDate;
                } else {
                    $lastDate = $date;
                }

                // Check if this row is a continuation row of the previous event (e.g., empty or repeated date cell, and title cell has only time, location, or description)
                if ($titleCellIndex !== null && !empty($rows) && ($dateWasEmpty || $date === $lastDate)) {
                    $singleLineTitle = trim($title);
                    if (!str_contains($singleLineTitle, "\n")) {
                        $labeledCont = calendarParseLabeledLine($singleLineTitle);
                        if ($labeledCont !== null && $labeledCont['field'] !== 'title') {
                            $lastIdx = count($rows) - 1;
                            if ($labeledCont['field'] === 'time' && $rows[$lastIdx]['start_time'] === '') {
                                [$st, $et, $tw] = calendarParseTimeRange($labeledCont['value']);
                                $rows[$lastIdx]['start_time'] = $st;
                                $rows[$lastIdx]['end_time'] = $et;
                                $rows[$lastIdx]['warnings'] = array_values(array_unique(array_merge($rows[$lastIdx]['warnings'], $tw)));
                                $rows[$lastIdx]['source'] = trim($rows[$lastIdx]['source'] . "\n" . $whole);
                                continue;
                            }
                            if ($labeledCont['field'] === 'location' && $rows[$lastIdx]['location'] === '') {
                                $rows[$lastIdx]['location'] = $labeledCont['value'];
                                $rows[$lastIdx]['location_provided'] = true;
                                $rows[$lastIdx]['source'] = trim($rows[$lastIdx]['source'] . "\n" . $whole);
                                continue;
                            }
                            if ($labeledCont['field'] === 'description') {
                                $rows[$lastIdx]['description'] = trim($rows[$lastIdx]['description'] . ($rows[$lastIdx]['description'] !== '' ? "\n" : '') . $labeledCont['value']);
                                $rows[$lastIdx]['source'] = trim($rows[$lastIdx]['source'] . "\n" . $whole);
                                continue;
                            }
                        } elseif ($dateWasEmpty) {
                            $lastIdx = count($rows) - 1;
                            if (calendarLooksLikeStandaloneTime($singleLineTitle)) {
                                if ($rows[$lastIdx]['start_time'] === '') {
                                    [$st, $et, $tw] = calendarParseTimeRange($singleLineTitle);
                                    $rows[$lastIdx]['start_time'] = $st;
                                    $rows[$lastIdx]['end_time'] = $et;
                                    $rows[$lastIdx]['warnings'] = array_values(array_unique(array_merge($rows[$lastIdx]['warnings'], $tw)));
                                }
                                $rows[$lastIdx]['source'] = trim($rows[$lastIdx]['source'] . "\n" . $whole);
                                continue;
                            }
                            if (calendarLooksLikeStandaloneLocation($singleLineTitle)) {
                                $locVal = trim(preg_replace('/^(?:venue|location|place|where|at|@)\s*[:\-–—]?\s*/iu', '', $singleLineTitle));
                                if ($rows[$lastIdx]['location'] === '') {
                                    $rows[$lastIdx]['location'] = $locVal;
                                } elseif (stripos($rows[$lastIdx]['location'], $locVal) === false) {
                                    $rows[$lastIdx]['location'] .= ', ' . $locVal;
                                }
                                $rows[$lastIdx]['location_provided'] = true;
                                $rows[$lastIdx]['source'] = trim($rows[$lastIdx]['source'] . "\n" . $whole);
                                continue;
                            }
                            if (calendarLooksLikeSupportingDetail($singleLineTitle)) {
                                $rows[$lastIdx]['description'] = trim($rows[$lastIdx]['description'] . ($rows[$lastIdx]['description'] !== '' ? "\n" : '') . $singleLineTitle);
                                $rows[$lastIdx]['source'] = trim($rows[$lastIdx]['source'] . "\n" . $whole);
                                continue;
                            }
                        }
                    }
                }

                // Parse the activity cell lines contextually with the row's date, time, location, and description seed
                $seed = [
                    'date' => $date,
                    'time' => $time,
                    'location' => $location,
                    'location_provided' => isset($columns['location']) || $location !== '',
                    'description' => $description,
                    'category' => $categoryText,
                    'department' => $departmentText,
                    'program' => $programText,
                    'source' => $whole
                ];

                $activityItems = ($titleCellIndex !== null && isset($cellParagraphs[$titleCellIndex]) && !empty($cellParagraphs[$titleCellIndex]))
                    ? $cellParagraphs[$titleCellIndex]
                    : ($title !== '' ? [['type' => 'line', 'text' => $title, 'bullet' => false]] : []);

                if (!empty($activityItems)) {
                    calendarProcessLineStream($activityItems, $year, $month, $categories, $rows, $seed);
                } elseif ($date !== '' && ($time !== '' || $location !== '' || $description !== '')) {
                    // Row has date and details but missing title: flag for manual review
                    $draft = calendarEmptyDraft($month, $year);
                    $draft['date'] = $date;
                    $draft['time'] = $time;
                    $draft['location'] = $location;
                    $draft['location_provided'] = $location !== '' || isset($columns['location']);
                    $draft['description'] = $description;
                    $draft['category'] = $categoryText;
                    $draft['sources'] = [$whole];
                    calendarFinalizeDraft($draft, $year, $month, $categories, $rows);
                }
            }

            $flushKvStream();
        }
    }

    $flushParagraphStream();

    if (!$rows) {
        throw new InvalidArgumentException('No activities were found. Upload a Word document containing event titles and dates in tables, lists, or paragraphs.');
    }
    usort($rows, static fn($a, $b) => strcmp($a['start_date'] ?: '9999', $b['start_date'] ?: '9999'));
    return $rows;
}
