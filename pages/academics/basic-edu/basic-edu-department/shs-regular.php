<?php
// Keep the existing public URL while rendering the shared department layout.
$_GET['slug'] = 'shs-regular';
require __DIR__ . '/../../../../components/department-page.php';
