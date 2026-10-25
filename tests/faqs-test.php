<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/faqs.php';
function faqCheck(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function faqReject(callable $work):void{try{$work();}catch(InvalidArgumentException $error){return;}throw new RuntimeException('Invalid FAQ accepted');}
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE faq_page(page_id INTEGER PRIMARY KEY,content TEXT,updated_at TEXT);CREATE TABLE faqs(faq_id INTEGER PRIMARY KEY AUTOINCREMENT,category_id INTEGER,question TEXT NOT NULL,answer TEXT NOT NULL,display_order INTEGER,created_by INTEGER,created_at TEXT)');
$texts=['title'=>'FAQ fixture','intro'=>'Introduction','empty_title'=>'Empty','empty_description'=>'Empty description'];
$db->prepare('INSERT INTO faq_page VALUES(1,?,NULL)')->execute([json_encode($texts)]);
$initial=faqPageContent($db);faqCheck($initial['items']===[],'No hardcoded questions');
$input=$initial;
foreach(['a','b'] as $id)$input['items'][]=['id'=>'faq-new-'.$id,'question'=>'Authored question '.$id.'?','answer'=>"Authored answer $id\nSecond line."];
$saved=faqPublish($db,$input,faqPageRevision($initial),123);
faqCheck(count($saved['items'])===2&&$db->query('SELECT COUNT(*) FROM faqs')->fetchColumn()==2,'Published new questions appear in the shared table');
$db->exec('UPDATE faqs SET category_id=42 WHERE faq_id=1');
$edit=$saved;$edit['items']=array_reverse($edit['items']);$edit['items'][0]['answer']='Updated answer <script>example</script>';
$updated=faqPublish($db,$edit,faqPageRevision($saved),456);
faqCheck($updated['items'][0]['id']===$saved['items'][1]['id']&&$updated['items'][0]['answer']==='Updated answer <script>example</script>','Edit/reorder persist with stable IDs');
faqCheck((int)$db->query('SELECT category_id FROM faqs WHERE faq_id=1')->fetchColumn()===42,'Existing category metadata preserved');
faqCheck((int)$db->query('SELECT created_by FROM faqs WHERE faq_id=1')->fetchColumn()===123,'Original author preserved');
faqReject(fn()=>faqPublish($db,$updated,faqPageRevision($initial),123));
$bad=$updated;$bad['items'][1]['id']=$bad['items'][0]['id'];faqReject(fn()=>faqPublish($db,$bad,faqPageRevision($updated),123));
$bad=$updated;$bad['items'][0]['id']='faq-99999';faqReject(fn()=>faqValidate($bad,$updated));
$bad=$updated;$bad['items'][0]['question']='';faqReject(fn()=>faqPublish($db,$bad,faqPageRevision($updated),123));
faqCheck(faqPageContent($db)===$updated,'Failed publication rolls back atomically');
$_POST['allow_incomplete']='1';$incomplete=faqPublish($db,$bad,faqPageRevision($updated),123);faqCheck($incomplete['items'][0]['question']==='','Publish Anyway persists incomplete content');
$bad=$incomplete;$bad['items'][0]['answer']=str_repeat('x',20001);faqReject(fn()=>faqValidate($bad,$incomplete));
$bad=$incomplete;$bad['items'][0]['answer']=[];faqReject(fn()=>faqValidate($bad,$incomplete));unset($_POST['allow_incomplete']);
$edit=$incomplete;$edit['items']=[$edit['items'][1]];$deleted=faqPublish($db,$edit,faqPageRevision($incomplete),123);faqCheck(count($deleted['items'])===1,'Delete persists');
$edit=$deleted;$edit['items']=[];$empty=faqPublish($db,$edit,faqPageRevision($deleted),123);faqCheck($empty['items']===[],'Delete last FAQ produces empty state');
echo "PASS: isolated FAQ create/edit/reorder/delete, preserved existing metadata, stale revisions, atomic rollback, invalid IDs/types/lengths, Publish Anyway and empty collection.\n";
