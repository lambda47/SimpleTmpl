<?php
require('template.php');

$template = new Template();
$content = file_get_contents('test.phtml');
$template->parse($content);
