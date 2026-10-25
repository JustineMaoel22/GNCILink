<?php
require_once __DIR__.'/../../config/config.php';require_once __DIR__.'/../admin-functions.php';initSession();requireLogin();header('Location: '.rbacHome());exit;
