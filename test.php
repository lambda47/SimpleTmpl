<?php
require('template.php');

$template = new Template();
$content = file_get_contents('test.phtml');
var_dump($template->parse($content));
