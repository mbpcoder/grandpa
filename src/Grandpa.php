<?php

declare(strict_types=1);

namespace Grandpa;

class Grandpa
{
    private static self|null $instance = null;

    /** @var array<string, Task> */
    private array $tasks = [];

    private string|null $entryScript = null;
    private string|null $taskFile = null;

    private Git|null $git = null;
    private StorageManager|null $storage = null;
    private Ssh|null $ssh = null;
    private Http|null $http = null;
    private Console|null $console = null;

    private $sass;

    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function task(string $name, \Closure $callback): Task
    {
        return $this->tasks[$name] = new Task($name, $callback);
    }

    public function runTask(string $name): void
    {
        if (!isset($this->tasks[$name])) {
            throw new \RuntimeException("Task \"{$name}\" is not defined.");
        }

        $this->tasks[$name]->run();
    }

    /** @return list<string> */
    public function getTaskNames(): array
    {
        return array_keys($this->tasks);
    }

    public function getTask(string $name): Task|null
    {
        return $this->tasks[$name] ?? null;
    }

    public function setExecutionContext(string $entryScript, string $taskFile): void
    {
        $this->entryScript = $entryScript;
        $this->taskFile = $taskFile;
    }

    /**
     * Command to re-invoke this same CLI, as a single attempt of one task, in a
     * separate process. Used by Task::asParallel() to run repeats concurrently.
     *
     * @return list<string>
     */
    public function buildSingleRunCommand(string $taskName): array
    {
        if ($this->entryScript === null || $this->taskFile === null) {
            throw new \RuntimeException('Execution context is not available for parallel task runs.');
        }

        return [PHP_BINARY, $this->entryScript, '--file=' . $this->taskFile, $taskName, '--force', '--grandpa-single-run'];
    }

    public function runDueTasks(\DateTimeInterface|null $time = null): void
    {
        foreach ($this->tasks as $task) {
            if ($task->isDue($time)) {
                $this->say("Running scheduled task \"{$task->getName()}\"");
                $task->run();
            }
        }
    }

    public function runEligibleTasks(\DateTimeInterface|null $time = null): void
    {
        foreach ($this->tasks as $task) {
            if (!$task->hasSchedule() || $task->isDue($time)) {
                $task->run();
            }
        }
    }

    public function storage(): StorageManager
    {
        return $this->storage ??= new StorageManager();
    }

    public function ssh(): Ssh
    {
        return $this->ssh ??= Ssh::fromEnv();
    }

    public function http(): Http
    {
        return $this->http ??= new Http();
    }

    public function telegram(): Telegram
    {
        return Telegram::fromEnv();
    }

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

    public function test()
    {
        return $this;
    }

    public function clean()
    {
        return $this;
    }

    public function say(string|null $message = null): Console|null
    {
        if ($message === null) {
            return $this->console();
        }

        $this->console()->say($message);

        return null;
    }

    public function console(): Console
    {
        return $this->console ??= new Console();
    }

    public function git(): Git
    {
        return $this->git ??= new Git();
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
//$grandpa->test();
//$grandpa->clean();
//
//// deploy via the task system
//task('deploy', function () {
//    $revision = storage()->ftp()->get('.revision');
//    storage()->ftp()->upload(git()->changedFiles($revision));
//    storage()->ftp()->delete(git()->deletedFiles($revision));
//    storage()->ftp()->put('.revision', git()->currentHead());
//    say('Deployed');
//});