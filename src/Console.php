<?php

declare(strict_types=1);

namespace Grandpa;

class Console
{
    private const COLOR_RESET = "\033[0m";
    private const COLOR_YELLOW = "\033[33m";
    private const COLOR_RED = "\033[31m";

    public function say(string $message): void
    {
        echo $message . PHP_EOL;
    }

    public function info(string $message): void
    {
        echo $message . PHP_EOL;
    }

    public function warning(string $message): void
    {
        echo self::COLOR_YELLOW . $message . self::COLOR_RESET . PHP_EOL;
    }

    public function error(string $message): void
    {
        fwrite(STDERR, self::COLOR_RED . $message . self::COLOR_RESET . PHP_EOL);
    }
}
