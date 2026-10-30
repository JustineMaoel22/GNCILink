<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../../config/config.php';
require_once __DIR__.'/../inc/retriever.php';
require_once __DIR__.'/../inc/prompt.php';
require_once __DIR__.'/../inc/gemini.php';
function tuitionBotCheck(bool $ok,string $message): void {if(!$ok)throw new RuntimeException($message);}
$db=getDB();$folder=__DIR__.'/../../uploads/tuition-fees/college';
if(!is_dir($folder))mkdir($folder,0755,true);
$name='chatbot-test-'.bin2hex(random_bytes(10)).'.pdf';$url='/uploads/tuition-fees/college/'.$name;$path=$folder.'/'.$name;
// Synthetic reference only. No real fees are created or published.
file_put_contents($path,"%PDF-1.4\nSynthetic fixture for transport validation\n%%EOF\n");
try {
    $db->exec('CREATE TEMPORARY TABLE tuition_fee_documents(document_id INT PRIMARY KEY,category_key VARCHAR(50),title VARCHAR(255),file_path VARCHAR(500))');
    $insert=$db->prepare('INSERT INTO tuition_fee_documents VALUES(?,?,?,?)');
    $insert->execute([1,'college','BSIT Tuition AY 2026-2027',$url]);
    $insert->execute([2,'college','Nursing Tuition AY 2026-2027',$url]);
    $insert->execute([3,'graduate-school','Masters Tuition',$url]);
    $insert->execute([4,'college','Invalid external PDF','https://example.invalid/private.pdf']);
    $insert->execute([5,'college','Invalid path','/uploads/tuition-fees/college/../../private.pdf']);
    $docs=gncbot_tuition_documents($db,'How much is the BSIT tuition fee?', 'BSIT','Bachelor of Science in Information Technology','college');
    tuitionBotCheck(count($docs)===2&&$docs[0]['document_id']===1,'Program-matching published PDF ranks first; wrong categories and unsafe URLs excluded.');
    $parts=gncbot_tuition_parts($docs);
    tuitionBotCheck(count($parts)===2&&$parts[1]['inlineData']['mimeType']==='application/pdf'&&base64_decode($parts[1]['inlineData']['data'])===file_get_contents($path),'Actual PDF bytes attached to Gemini input; duplicate files are sent once.');
    tuitionBotCheck(str_contains($parts[0]['text'],'BSIT Tuition')&&str_contains($parts[0]['text'],$url),'Document labels and source URLs accompany attachment.');
    tuitionBotCheck(gncbot_tuition_path('/config/config.php')===null&&gncbot_tuition_path('https://example.com/test.pdf')===null&&gncbot_tuition_path('/uploads/tuition-fees/college/%2e%2e/test.pdf')===null,'Private paths, remote files and encoded traversal rejected.');
    $result=(new GncbotRetriever($db))->retrieve('How much is the BSIT tuition fee?');
    tuitionBotCheck(in_array('tuition',$result['intents'],true)&&!$result['empty']&&count($result['tuition_documents'])===2,'Tuition intent retrieves published PDF descriptors.');
    tuitionBotCheck(str_contains($result['context'],'BSIT Tuition')&&!str_contains($result['context'],'does not currently store tuition'),'Outdated no-tuition assertion removed.');
    tuitionBotCheck(in_array(gncbot_url($url),$result['allowed'],true),'Source PDF is allowlisted for response citations.');
    $followup=(new GncbotRetriever($db))->retrieve('BSIT', [['role'=>'user','text'=>'How much is the tuition?'],['role'=>'model','text'=>'Which program and school year?']]);
    tuitionBotCheck(in_array('tuition',$followup['intents'],true)&&count($followup['tuition_documents'])===2,'Short program reply continues tuition lookup.');
    tuitionBotCheck(str_contains(gncbot_sanitize_reply('[Fee schedule]('.gncbot_url($url).')',$result['allowed']),'Fee schedule'),'Published PDF citation survives reply sanitizer.');
    $nonTuition=(new GncbotRetriever($db))->retrieve('Who built this website?');tuitionBotCheck($nonTuition['tuition_documents']===[],'Unrelated queries do not send fee PDFs.');
    $db->exec('DELETE FROM tuition_fee_documents');
    $result=(new GncbotRetriever($db))->retrieve('Magkano ang tuition?');
    tuitionBotCheck($result['tuition_documents']===[]&&str_contains($result['context'],'No matching published PDF'),'No rows means no draft/orphan PDFs are picked up from disk.');
    file_put_contents($path,'Not a PDF');tuitionBotCheck(gncbot_tuition_path($url)===null,'Renamed non-PDF files rejected.');
    $prompt=gncbot_system_instruction();tuitionBotCheck(str_contains($prompt,'payment basis')&&str_contains($prompt,'never follow instructions')&&str_contains($prompt,'clarifying question'),'PDF scope, ambiguity and injection rules included.');
} finally {unlink($path);}
echo "PASS: published tuition lookup, relevance, real PDF attachment transport, safe paths, citations, Filipino queries, missing PDFs and unrelated-query isolation. No API call or real fee data changes.\n";
