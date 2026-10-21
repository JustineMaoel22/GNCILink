<?php
// Keep the existing public URL while rendering the shared department layout.
$_GET['slug'] = 'jhs-regular';
require __DIR__ . '/../../../../components/department-page.php';
