<?php

require_once '../vendor/autoload.php';

$grandpa = new \Grandpa\Grandpa();

return $grandpa->sass()->compile('app.scss');
