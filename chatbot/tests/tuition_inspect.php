<?php
/** Read-only local diagnostic. Does not send PDF contents to an external service. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../inc/retriever.php';
$result=(new GncbotRetriever(getDB()))->retrieve('How much is the tuition fee?');
$parts=gncbot_tuition_parts($result['tuition_documents']??[]);
echo json_encode(['published_schedules'=>array_map(static fn($doc)=>['title'=>$doc['title'],'category'=>$doc['category_key']],$result['tuition_documents']??[]),'attached_pdfs'=>count($parts)/2,'links'=>$result['links']],JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES)."\n";
