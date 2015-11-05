<?php
require('Template.php');

$template = new Template(array(
    'left_delimiter' => '<!--{',
    'right_delimiter' => '}-->',
    'template_dir' => 'view',
    'cache_dir' => 'cache'
));
$content = file_get_contents('test.phtml');
highlight_string($template->parse($content));
