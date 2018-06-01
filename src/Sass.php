<?php

namespace Grandpa;

use Leafo\ScssPhp\Compiler;

class Sass
{
    private $compiledSass = '';

    private $sassCompiler;

    public function __construct()
    {
        $this->sassCompiler = new Compiler();
    }

    public function getSassCompiler()
    {
        return $this->sassCompiler;
    }

    public function compile($src)
    {
        $this->compiledSass = $this->sassCompiler->compile(file_get_contents($src));
        return $this;
    }

    public function saveTo($destination)
    {
        file_put_contents($destination, $this->compiledSass);
    }
}