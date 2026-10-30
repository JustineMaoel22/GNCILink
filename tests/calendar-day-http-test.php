<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
function dayHttp(string $query,string $method='GET'): array {
    $curl=curl_init('http://127.0.0.1/api/calendar-day.php?'.$query);
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15,CURLOPT_CUSTOMREQUEST=>$method]);
    $raw=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
    if(!is_string($raw))throw new RuntimeException('Calendar API unavailable');
    return [$status,json_decode($raw,true,512,JSON_THROW_ON_ERROR)];
}
foreach(['date=2026-02-30','date[]=2026-11-01','date=2026-11-01&category[]=Holiday','date=2051-01-01','date=invalid'] as $query){
    [$status,$result]=dayHttp($query);if($status!==400||empty($result['error']))throw new RuntimeException('Invalid calendar request accepted');
}
[$status]=dayHttp('date=2026-11-01','POST');if($status!==405)throw new RuntimeException('Wrong method accepted');
[$status,$result]=dayHttp('date=2026-11-01');
if($status!==200||$result['date']!=='2026-11-01'||!is_array($result['events']))throw new RuntimeException('Public details API failed');
foreach($result['events'] as $event)if(array_keys($event)!==['id','title','start_date','end_date','category','color','location','description'])throw new RuntimeException('Unexpected public fields');
echo "PASS: public day-details API, JSON responses, invalid dates/arrays, method validation and whitelisted fields. No data changed.\n";
