<?php
/** Read-only hashes of affected feature outputs; no content or personal data is printed. */
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
if(DB_HOST!=='db')throw new RuntimeException('Use the local Docker database.');
require_once __DIR__.'/../admin/admin-functions.php';
require_once __DIR__.'/../includes/community-activities.php';
require_once __DIR__.'/../chatbot/inc/retriever.php';
$db=getDB();$features=[
    'administration'=>fetchAdministrationSections(true),
    'departments'=>getDepartments(),
    'programs'=>getPrograms(),
    'president'=>getHomepagePresident(),
    'community_activities'=>communityActivityContent($db),
];
foreach(['records office','school programs','community activities'] as $question){
    $answer=(new GncbotRetriever($db))->retrieve($question);
    // The prompt header contains the current minute, which is not stored content.
    $answer['context']=preg_replace('/^TODAY:.*(?:\n|$)/m','',$answer['context']);
    $features['chatbot:'.$question]=$answer;
}
$result=[];foreach($features as $name=>$value)$result[$name]=hash('sha256',json_encode($value,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
echo json_encode($result,JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT)."\n";
