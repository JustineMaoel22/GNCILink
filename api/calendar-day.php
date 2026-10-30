<?php
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../admin/admin-functions.php';
require_once __DIR__.'/../components/calendar-filters.php';
require_once __DIR__.'/../includes/event-display.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
if(($_SERVER['REQUEST_METHOD']??'')!=='GET'){http_response_code(405);header('Allow: GET');echo '{"error":"Use GET to view calendar details."}';exit;}
$date=$_GET['date']??'';$category=$_GET['category']??null;
if(!is_string($date)||!preg_match('/^(19\d{2}|20[0-4]\d|2050)-(\d{2})-(\d{2})$/D',$date,$m)||!checkdate((int)$m[2],(int)$m[3],(int)$m[1])
    ||($category!==null&&(!is_string($category)||strlen($category)>100))){http_response_code(400);echo '{"error":"Choose a valid calendar date."}';exit;}
try {
    echo json_encode(['date'=>$date,'events'=>publicEventsForDate(getDB(),$date,publicCalendarFilters($_GET),$category)],JSON_THROW_ON_ERROR|JSON_INVALID_UTF8_SUBSTITUTE);
}catch(Throwable $error){error_log('Calendar day details: '.get_class($error).' '.$error->getCode());http_response_code(503);echo '{"error":"Event details are temporarily unavailable. Please try again."}';}
