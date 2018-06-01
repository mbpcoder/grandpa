<?php

namespace Grandpa;

use Leafo\ScssPhp\Compiler;

class Sass
{
	private $compiledSass = '';

    public function compile($src)
    {
        $scss = new Compiler();
        $this->compiledSass = $scss->compile(file_get_contents($src));        
        return $this;
    }

    public function saveTo($destination)
    {
    	file_put_contents($destination, $this->compiledSass);
    }

}