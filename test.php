<?php
require('template.php');

$template = new Template();
$content = file_get_contents('test.phtml');
echo '<pre>';
echo htmlspecialchars($template->parse($content));
echo '</pre>';
