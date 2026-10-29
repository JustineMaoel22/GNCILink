<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__.'/../includes/visitor-feedback.php';
function feedbackCheck(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
function feedbackReject(callable $work, string $message): void { try { $work(); } catch (InvalidArgumentException | RuntimeException $e) { return; } throw new LogicException($message); }
$secret = str_repeat('test-only-',8);
$identity = bin2hex(random_bytes(32));
$cookie = $identity.'.'.hash_hmac('sha256','identity:'.$identity,$secret);
feedbackCheck(gncFeedbackIdentity($cookie,$secret)===$identity,'Signed browser identity is accepted');
foreach ([null,'', $identity.'.'.str_repeat('0',64),$cookie.'x'] as $invalid) feedbackCheck(gncFeedbackIdentity($invalid,$secret)===null,'Forged identity rejected');
feedbackCheck(gncFeedbackIdentity($cookie,'different-secret')===null,'Wrong secret rejects identity');
feedbackCheck(!hash_equals(gncFeedbackCsrf($identity,$secret),gncFeedbackCsrf(bin2hex(random_bytes(32)),$secret)),'CSRF is browser-bound');
$answers=['101'=>1,'102'=>2,'103'=>3,'104'=>4,'105'=>5,'106'=>1];
feedbackCheck(gncFeedbackAnswers($answers)===$answers,'Six valid ratings accepted');
foreach ([null,[],array_slice($answers,0,5,true),$answers+['107'=>2],array_replace($answers,['101'=>0]),array_replace($answers,['101'=>6]),array_replace($answers,['101'=>'5']),array_replace($answers,['101'=>true]),array_replace($answers,['101'=>[]])] as $invalid) feedbackReject(fn()=>gncFeedbackAnswers($invalid),'Incomplete or malformed ratings accepted');
$visitor=hash('sha256',$identity);
$questions=[];foreach(array_keys($answers) as $id)$questions[]=['id'=>(string)$id,'title'=>'Question '.$id,'section'=>'Effectiveness'];
$unsubmitted=['ok'=>true,'visitor'=>$visitor,'submitted'=>false,'questions'=>$questions];
feedbackCheck(gncFeedbackResult($unsubmitted,$visitor)['submitted']===false,'Unsubmitted status stays unsubmitted');
feedbackCheck(gncFeedbackResult(['ok'=>true,'visitor'=>$visitor,'submitted'=>true,'receipt'=>'test-receipt'],$visitor)===['ok'=>true,'submitted'=>true],'Only a matching saved receipt confirms submission');
foreach ([['ok'=>true,'visitor'=>$visitor,'submitted'=>true],['ok'=>true,'visitor'=>'wrong-browser','submitted'=>true,'receipt'=>'test'],['ok'=>false],['ok'=>true,'visitor'=>$visitor,'submitted'=>'true','receipt'=>'test'],array_replace($unsubmitted,['questions'=>[]]),array_replace($unsubmitted,['questions'=>array_fill(0,6,$questions[0])])] as $invalid) feedbackReject(fn()=>gncFeedbackResult($invalid,$visitor),'Invalid receipt/schema accepted');
feedbackCheck(gncFeedbackResult(['ok'=>true,'visitor'=>$visitor,'submitted'=>false,'pending'=>true],$visitor)['pending']===true,'Pending never becomes success');
echo "PASS: signed identity, browser-bound CSRF, six required ratings, invalid input, receipt binding, schema validation, pending state and forged confirmation rejection.\n";
