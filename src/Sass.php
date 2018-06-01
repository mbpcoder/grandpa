<?php

namespace Grandpa;

use Leafo\ScssPhp\Compiler;

class Sass
{
    public function compile($src)
    {
        $scss = new Compiler();
        $result = $scss->compile(file_get_contents($src));
        echo $result;
    }

}