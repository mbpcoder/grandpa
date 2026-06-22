<?php

declare(strict_types=1);

namespace Grandpa;

class Cli
{
    public function run(array $argv): void
    {
        $entryScript = $this->resolveEntryScript($argv[0] ?? '');

        [$positional, $options] = $this->parseArguments(array_slice($argv, 1));

        $first = $positional[0] ?? null;

        if ($first === null && !isset($options['file'])) {
            fwrite(STDERR, "Usage: grandpa <task>|schedule:run|init [--force|-f] [--dir=<path>]\n");
            fwrite(STDERR, "       grandpa <file.php> [task] [--force|-f] [--dir=<path>]\n");
            fwrite(STDERR, "       grandpa --file=<file.php> [task] [--force|-f] [--dir=<path>]\n");
            exit(1);
        }

        if (isset($options['dir'])) {
            $dir = $this->resolvePath((string) $options['dir'], (string) getcwd());

            if (!is_dir($dir) || !chdir($dir)) {
                fwrite(STDERR, "Directory not found: {$dir}\n");
                exit(1);
            }
        }

        $cwd = (string) getcwd();

        if ($first === 'init') {
            (new Init())->run($cwd, $argv);

            return;
        }

        $force = isset($options['force']);

        if (isset($options['file'])) {
            $taskFile = $this->resolvePath($options['file'], $cwd);
            $command = $first;
        } elseif ($first !== null && $this->isTaskFileArgument($first, $cwd)) {
            $taskFile = $this->resolvePath($first, $cwd);
            $command = $positional[1] ?? null;
        } else {
            $taskFile = $this->resolveTaskFile($cwd);
            $command = $first;
        }

        if ($taskFile === null || !file_exists($taskFile)) {
            fwrite(STDERR, "No runner.php or deploy.php found in {$cwd}\n");
            exit(1);
        }

        Env::load($cwd . '/.env');
        Grandpa::instance()->setExecutionContext($entryScript, $taskFile);

        require $taskFile;

        try {
            if (isset($options['single_run']) && $command !== null) {
                $this->runSingleAttempt($command);

                return;
            }

            if ($command === null) {
                Grandpa::instance()->runEligibleTasks();

                return;
            }

            if ($command === 'schedule:run') {
                Grandpa::instance()->runDueTasks();

                return;
            }

            $this->runNamedTask($command, $force);
        } catch (\Throwable $exception) {
            fwrite(STDERR, $exception->getMessage() . PHP_EOL);
            exit(1);
        }
    }

    private function runNamedTask(string $name, bool $force): void
    {
        $task = Grandpa::instance()->getTask($name);

        if ($task === null) {
            throw new \RuntimeException("Task \"{$name}\" is not defined.");
        }

        if (!$force && $task->hasSchedule() && !$task->isDue()) {
            echo "Schedule for task \"{$name}\" hasn't been met yet. Use --force/-f to run it anyway." . PHP_EOL;

            return;
        }

        $task->run();
    }

    /**
     * Internal entry point used by Task::asParallel()'s child processes:
     * run a single attempt of one task, bypassing retry/repeat/parallel wrapping.
     */
    private function runSingleAttempt(string $name): void
    {
        $task = Grandpa::instance()->getTask($name);

        if ($task === null) {
            throw new \RuntimeException("Task \"{$name}\" is not defined.");
        }

        $task->runSingleAttempt();
    }

    private function resolveEntryScript(string $script): string
    {
        $resolved = realpath($script);

        return $resolved !== false ? $resolved : $this->resolvePath($script, (string) getcwd());
    }

    /**
     * @param list<string> $arguments
     * @return array{0: list<string>, 1: array<string, string|true>}
     */
    private function parseArguments(array $arguments): array
    {
        $positional = [];
        $options = [];

        foreach ($arguments as $argument) {
            if ($argument === '--force' || $argument === '-f') {
                $options['force'] = true;
            } elseif ($argument === '--grandpa-single-run') {
                $options['single_run'] = true;
            } elseif (str_starts_with($argument, '--file=')) {
                $options['file'] = substr($argument, strlen('--file='));
            } elseif (str_starts_with($argument, '--dir=')) {
                $options['dir'] = substr($argument, strlen('--dir='));
            } elseif (str_starts_with($argument, '-d=')) {
                $options['dir'] = substr($argument, strlen('-d='));
            } else {
                $positional[] = $argument;
            }
        }

        return [$positional, $options];
    }

    private function isTaskFileArgument(string $argument, string $cwd): bool
    {
        return str_ends_with($argument, '.php') && file_exists($this->resolvePath($argument, $cwd));
    }

    private function resolvePath(string $path, string $cwd): string
    {
        return $this->isAbsolutePath($path) ? $path : $cwd . '/' . $path;
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, '/') || (bool) preg_match('#^[A-Za-z]:[/\\\\]#', $path);
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
