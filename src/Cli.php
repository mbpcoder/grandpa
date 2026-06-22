<?php

declare(strict_types=1);

namespace Grandpa;

class Cli
{
    public function run(array $argv): void
    {
        $taskName = $argv[1] ?? null;

        if ($taskName === null) {
            fwrite(STDERR, "Usage: grandpa <task>\n");
            exit(1);
        }

        $cwd = (string) getcwd();
        Env::load($cwd . '/.env');

        $deployFile = $cwd . '/deploy.php';

        if (!file_exists($deployFile)) {
            fwrite(STDERR, "No deploy.php found in {$cwd}\n");
            exit(1);
        }

        require $deployFile;

        try {
            Grandpa::instance()->runTask($taskName);
        } catch (\Throwable $exception) {
            fwrite(STDERR, $exception->getMessage() . PHP_EOL);
            exit(1);
        }
    }
}
