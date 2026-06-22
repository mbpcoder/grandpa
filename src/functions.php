<?php

declare(strict_types=1);

use Grandpa\Env;
use Grandpa\Git;
use Grandpa\Grandpa;
use Grandpa\Http;
use Grandpa\Ssh;
use Grandpa\Storage;
use Grandpa\Task;
use Grandpa\Telegram;

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

if (!function_exists('http')) {
    function http(): Http
    {
        return Grandpa::instance()->http();
    }
}

if (!function_exists('run')) {
    function run(string $command): void
    {
        passthru($command);
    }
}

if (!function_exists('say')) {
    function say(string $message): void
    {
        Grandpa::instance()->say($message);
    }
}

if (!function_exists('telegram')) {
    function telegram(): Telegram
    {
        return Grandpa::instance()->telegram();
    }
}

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('subDirectories')) {
    /**
     * One level of subdirectories of a local path, as absolute paths.
     *
     * @return list<string>
     */
    function subDirectories(string $path): array
    {
        $entries = array_diff(scandir($path) ?: [], ['.', '..']);

        $directories = array_filter(
            $entries,
            static fn (string $entry): bool => is_dir($path . '/' . $entry),
        );

        return array_values(array_map(
            static fn (string $entry): string => rtrim($path, '/') . '/' . $entry,
            $directories,
        ));
    }
}
