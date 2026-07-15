<?php
require_once __DIR__ . '/config/config.php';

$pageId = $_ENV['FB_PAGE_ID'];
$token  = $_ENV['FB_ACCESS_TOKEN'];

$url = "https://graph.facebook.com/v19.0/{$pageId}/posts?fields=id,message,created_time,permalink_url,full_picture&limit=6&access_token={$token}";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$res = curl_exec($ch);

echo "<pre>";
echo "CURL ERROR: " . curl_error($ch) . "\n\n";
var_dump(json_decode($res, true));
echo "</pre>";