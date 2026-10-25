<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../includes/content-editor-drafts.php';
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('PRAGMA foreign_keys=ON');
$db->exec('CREATE TABLE content_editor_drafts(draft_id INTEGER PRIMARY KEY AUTOINCREMENT,user_id INTEGER,page_key TEXT,revision INTEGER,updated_at TEXT DEFAULT CURRENT_TIMESTAMP,UNIQUE(user_id,page_key))');
$db->exec('CREATE TABLE content_editor_draft_chunks(draft_id INTEGER,chunk_no INTEGER,payload BLOB,PRIMARY KEY(draft_id,chunk_no),FOREIGN KEY(draft_id) REFERENCES content_editor_drafts(draft_id) ON DELETE CASCADE)');
function checkDraft(bool $condition,string $label): void {if(!$condition)throw new RuntimeException($label);}
$data=['version'=>1,'state'=>['text'=>'Incomplete private draft','media'=>str_repeat('media',180000)],'preview'=>['fields'=>[]]];
$json=json_encode($data);
checkDraft(editorDraftLoad($db,1,'home:/')===null,'No draft initially');
checkDraft(editorDraftSave($db,1,'home:/',0,$json)===1,'First revision');
checkDraft(editorDraftLoad($db,1,'home:/')['snapshot']===$data,'Chunked state round trip');
checkDraft((int)$db->query('SELECT COUNT(*) FROM content_editor_draft_chunks')->fetchColumn()>1,'Payload split into packet-safe chunks');
checkDraft(editorDraftLoad($db,2,'home:/')===null,'Another user cannot read draft');
checkDraft(editorDraftLoad($db,1,'home:/other')===null,'Another page has independent draft');
try {editorDraftSave($db,1,'home:/',0,$json);throw new LogicException('Conflict accepted');}catch(RuntimeException $e){checkDraft($e->getCode()===409,'Reject stale writes');}
checkDraft(editorDraftLoad($db,1,'home:/')['revision']===1,'Conflict preserves old revision');
checkDraft(editorDraftSave($db,1,'home:/',1,$json)===2,'Update existing draft');
editorDraftDelete($db,2,'home:/',2);checkDraft(editorDraftLoad($db,1,'home:/')!==null,'Another user cannot delete draft');
editorDraftDelete($db,1,'home:/',1);checkDraft(editorDraftLoad($db,1,'home:/')!==null,'Stale deletion preserves newer draft');
editorDraftDelete($db,1,'home:/',2);checkDraft(editorDraftLoad($db,1,'home:/')===null,'Discard draft');
checkDraft((int)$db->query('SELECT COUNT(*) FROM content_editor_draft_chunks')->fetchColumn()===0,'Discard removes media chunks');
try {editorDraftSave($db,1,'home:/',0,'{"version":2}');throw new LogicException('Malformed draft accepted');}catch(InvalidArgumentException $e){}
echo "PASS: private chunked drafts, media round trip, page/user isolation, optimistic concurrency, discard and malformed data.\n";
