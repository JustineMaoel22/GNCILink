<?php
if (PHP_SAPI!=='cli') {http_response_code(404);exit;}
$_SERVER['REQUEST_URI']='/tests/feedback-fixture-page';
include __DIR__.'/../components/visitor-feedback.php';
