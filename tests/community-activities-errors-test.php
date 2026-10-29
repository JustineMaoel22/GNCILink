<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require_once __DIR__.'/../includes/community-activities.php';
class CommunityPublishTestException extends PDOException {
    public function __construct(string $state){parent::__construct('private database details');$this->code=$state;}
}

$cases=[
    [new Error('Call to undefined function array_is_list()'),'PHP 8.1'],
    [new Error('Call to undefined function mb_strlen()'),'mbstring'],
    [new Error('Class "finfo" not found'),'fileinfo'],
    [new CommunityPublishTestException('42S02'),'table is missing'],
    [new CommunityPublishTestException('42S22'),'outdated table structure'],
    [new RuntimeException('Install the community activities migration.'),'record is missing'],
    [new RuntimeException('Image storage is unavailable.'),'write access'],
    [new RuntimeException('Could not store the image.'),'write access'],
    [new RuntimeException('private database details'),'unexpected error'],
    [new Error('private database details'),'unexpected error'],
];
$permission=new PDOException('private database details');
$permission->errorInfo=['42000',1142,'private database details'];
$cases[]=[$permission,'lacks permission'];
foreach($cases as [$error,$expected]){
    $message=communityActivityPublishError($error);
    if(!str_contains($message,$expected)||str_contains($message,'private database details'))throw new RuntimeException('Incorrect or unsafe publishing error message.');
}
echo "PASS: hosting error messages identify known failures without exposing exception details.\n";
