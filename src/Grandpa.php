<?php

namespace Grandpa;


class Grandpa
{
    private $sass;

    public function css()
    {
        return $this;
    }

    public function compile()
    {
        return $this;
    }

    public function sass()
    {
        $this->sass = new Sass();
        return $this->sass;
    }

    public function less()
    {
        return $this;
    }

    public function javascript()
    {
        $js = new Javascript();
        return $js;
    }

    public function move()
    {
        return $this;
    }

    public function copy()
    {
        return $this;
    }

    public function deploy()
    {
        return $this;
    }

    public function test()
    {
        return $this;
    }

    public function clean()
    {
        return $this;
    }

    public function run()
    {
        return $this;
    }

    public function say()
    {
        return $this;
    }

    public function git()
    {
        $git = new Git();
        return $git;
    }
}

//$grandpa = new Grandpa();
//
//$grandpa->css()->minify();
//$grandpa->css()->concat();
//
//$grandpa->sass()->compile();
//$grandpa->sass()->concat();
//
//$grandpa->javascript()->minify();
//$grandpa->javascript()->uglify();
//$grandpa->javascript()->concat();
//
//$grandpa->move();
//$grandpa->copy();
//$grandpa->deploy();
//$grandpa->test();
//$grandpa->clean();
//
//// git operations
//$grandpa->git()->push();
//$grandpa->git()->pull();
//
//// print a message for user
//$grandpa->say();
//
//// run the chane
//$grandpa->run();