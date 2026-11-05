<?php
if(PHP_SAPI!=='cli')exit;
require __DIR__.'/../config/config.php';require __DIR__.'/../admin/announcement-view.php';
try{echo count(announcementPinnedItems(getDB()));}catch(Throwable $e){echo $e->getMessage();}
