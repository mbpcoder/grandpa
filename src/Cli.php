<?php

declare(strict_types=1);

namespace Grandpa;

class Cli
{
    public function run(array $argv): void
    {
        $first = $argv[1] ?? null;

        if ($first === null) {
            fwrite(STDERR, "Usage: grandpa <task>|schedule:run|init\n");
            fwrite(STDERR, "       grandpa <file.php> [task]\n");
            exit(1);
        }

        $cwd = (string) getcwd();

        if ($first === 'init') {
            (new Init())->run($cwd, $argv);

            return;
        }

        $taskFile = $this->isTaskFileArgument($first, $cwd)
            ? $this->resolvePath($first, $cwd)
            : $this->resolveTaskFile($cwd);

        $command = $this->isTaskFileArgument($first, $cwd) ? ($argv[2] ?? null) : $first;

        if ($taskFile === null) {
            fwrite(STDERR, "No runner.php or deploy.php found in {$cwd}\n");
            exit(1);
        }

        Env::load($cwd . '/.env');

        require $taskFile;

        try {
            if ($command === null) {
                foreach (Grandpa::instance()->getTaskNames() as $name) {
                    echo $name . PHP_EOL;
                }

                return;
            }

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

    private function isTaskFileArgument(string $argument, string $cwd): bool
    {
        return str_ends_with($argument, '.php') && file_exists($this->resolvePath($argument, $cwd));
    }

    private function resolvePath(string $path, string $cwd): string
    {
        return str_starts_with($path, '/') ? $path : $cwd . '/' . $path;
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
