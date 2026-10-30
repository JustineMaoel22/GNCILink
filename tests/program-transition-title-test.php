<?php
/** Check every published program's actual HTTP markup; no writes or uploads. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../components/program-page-public.php';
$programs=getDB()->query("SELECT p.slug FROM programs p LEFT JOIN departments d ON d.dept_id=p.dept_id WHERE p.is_published=1 AND (d.department_type IS NULL OR d.department_type<>'basic-education')")->fetchAll(PDO::FETCH_COLUMN);
if(!$programs)throw new RuntimeException('Published programs required.');
foreach($programs as $slug){
    $curl=curl_init('http://127.0.0.1/pages/academics/college/program/'.rawurlencode($slug));
    curl_setopt_array($curl,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>20]);$html=curl_exec($curl);$status=curl_getinfo($curl,CURLINFO_RESPONSE_CODE);curl_close($curl);
    if($status!==200||!is_string($html))throw new RuntimeException('Program did not render: '.$slug);
    $dom=new DOMDocument();$previous=libxml_use_internal_errors(true);$dom->loadHTML($html);libxml_clear_errors();libxml_use_internal_errors($previous);$xp=new DOMXPath($dom);
    $overlay=$dom->getElementById('gncPageEntrance');$heading=$xp->query('//h1[contains(concat(" ",normalize-space(@class)," ")," template-title ")]')->item(0);
    if(!$overlay||!$heading||$overlay->getAttribute('data-opening-title')!==trim($heading->textContent))throw new RuntimeException('Transition heading differs from page heading: '.$slug);
}
echo 'PASS: transition title matches the initial visible heading for all '.count($programs)." published programs. No data changed.\n";
