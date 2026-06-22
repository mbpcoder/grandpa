<?php

declare(strict_types=1);

use Grandpa\Env;
use Grandpa\Git;
use Grandpa\Grandpa;
use Grandpa\Ssh;
use Grandpa\Storage;
use Grandpa\Task;

if (!function_exists('task')) {
    function task(string $name, Closure $callback): Task
    {
        return Grandpa::instance()->task($name, $callback);
    }
}

if (!function_exists('git')) {
    function git(): Git
    {
        return Grandpa::instance()->git();
    }
}

if (!function_exists('ftp')) {
    function ftp(): Storage
    {
        return Grandpa::instance()->ftp();
    }
}

if (!function_exists('ssh')) {
    function ssh(): Ssh
    {
        return Grandpa::instance()->ssh();
    }
}

if (!function_exists('say')) {
    function say(string $message): void
    {
        Grandpa::instance()->say($message);
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}
