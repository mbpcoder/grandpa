<?php

declare(strict_types=1);

namespace Grandpa;

class Cli
{
    public function run(array $argv): void
    {
        $command = $argv[1] ?? null;

        if ($command === null) {
            fwrite(STDERR, "Usage: grandpa <task>|schedule:run\n");
            exit(1);
        }

        $cwd = (string) getcwd();
        Env::load($cwd . '/.env');

        $taskFile = $this->resolveTaskFile($cwd);

        if ($taskFile === null) {
            fwrite(STDERR, "No runner.php or deploy.php found in {$cwd}\n");
            exit(1);
        }

        require $taskFile;

        try {
            if ($command === 'schedule:run') {
                Grandpa::instance()->runDueTasks();

                return;
            }

            Grandpa::instance()->runTask($command);
        } catch (\Throwable $exception) {
            fwrite(STDERR, $exception->getMessage() . PHP_EOL);
            exit(1);
        }
    }

    private function resolveTaskFile(string $cwd): string|null
    {
        foreach (['runner.php', 'deploy.php'] as $file) {
            $path = $cwd . '/' . $file;

            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }
}
