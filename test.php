<?php
require('SimpleTmpl.php');

$SimpleTmpl->setConfig(array(
    'left_delimiter' => '<!--{',
    'right_delimiter' => '}-->',
    'template_dir' => './view/',
    'cache_dir' => './cache/'
));
highlight_string($SimpleTmpl->display('test'));
