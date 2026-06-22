<?php

declare(strict_types=1);

namespace Grandpa;

class Task implements ITask
{
    private string|null $cronExpression = null;
    private int $retries = 1;
    private int $retryDelayMs = 0;
    private int $repeatTimes = 1;
    private int $repeatIntervalMs = 0;
    private int|null $maxParallel = null;

    public function __construct(
        private readonly string $name,
        private readonly \Closure $callback,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Retry the task up to $times attempts, waiting $delayMs between them.
     */
    public function retry(int $times, int $delayMs = 0): static
    {
        $this->retries = max(1, $times);
        $this->retryDelayMs = max(0, $delayMs);

        return $this;
    }

    /**
     * Run the task $times times in total, waiting $intervalMs between each run.
     * Unlike retry(), every run happens regardless of the previous run's outcome.
     */
    public function repeat(int $times, int $intervalMs = 0): static
    {
        $this->repeatTimes = max(1, $times);
        $this->repeatIntervalMs = max(0, $intervalMs);

        return $this;
    }

    /**
     * Run repeat() runs concurrently, in separate processes, instead of one after
     * another. $maxConcurrent caps how many run at once; 0 means run them all at
     * once. Only takes effect when combined with repeat() for more than one run.
     */
    public function asParallel(int $maxConcurrent = 0): static
    {
        $this->maxParallel = max(0, $maxConcurrent);

        return $this;
    }

    public function run(): void
    {
        if ($this->repeatTimes <= 1) {
            $this->runAttempts();

            return;
        }

        if ($this->maxParallel !== null) {
            $this->runParallel();

            return;
        }

        $failures = 0;

        for ($run = 1; $run <= $this->repeatTimes; $run++) {
            try {
                $this->runAttempts();
            } catch (\RuntimeException) {
                $failures++;
            }

            if ($run < $this->repeatTimes && $this->repeatIntervalMs > 0) {
                usleep($this->repeatIntervalMs * 1000);
            }
        }

        if ($failures > 0) {
            throw new \RuntimeException("Task \"{$this->name}\" failed {$failures} of {$this->repeatTimes} run(s).");
        }
    }

    /**
     * Run exactly one retry-attempt-set of this task. Used by the child process
     * spawned per repeat when running with asParallel().
     */
    public function runSingleAttempt(): void
    {
        $this->runAttempts();
    }

    private function runParallel(): void
    {
        if ($this->repeatIntervalMs > 0) {
            Grandpa::instance()->console()->warning(
                "Task \"{$this->name}\" ignores its repeat interval while running in parallel.",
            );
        }

        $command = Grandpa::instance()->buildSingleRunCommand($this->name);
        $concurrency = $this->maxParallel > 0 ? min($this->maxParallel, $this->repeatTimes) : $this->repeatTimes;

        $pending = $this->repeatTimes;
        $running = [];
        $failures = 0;

        while ($pending > 0 || $running !== []) {
            while ($pending > 0 && count($running) < $concurrency) {
                $process = proc_open($command, [0 => ['pipe', 'r'], 1 => STDOUT, 2 => STDERR], $pipes);

                if ($process === false) {
                    $failures++;
                    $pending--;

                    continue;
                }

                fclose($pipes[0]);
                $running[] = $process;
                $pending--;
            }

            foreach ($running as $index => $process) {
                $status = proc_get_status($process);

                if ($status['running']) {
                    continue;
                }

                if ($status['exitcode'] !== 0) {
                    $failures++;
                }

                proc_close($process);
                unset($running[$index]);
            }

            if ($running !== []) {
                usleep(20_000);
            }
        }

        if ($failures > 0) {
            throw new \RuntimeException("Task \"{$this->name}\" failed {$failures} of {$this->repeatTimes} parallel run(s).");
        }
    }

    private function runAttempts(): void
    {
        for ($attempt = 1; $attempt <= $this->retries; $attempt++) {
            $status = ($this->callback)();

            if ($status === null || $status === TaskStatus::Success) {
                return;
            }

            if ($attempt < $this->retries && $this->retryDelayMs > 0) {
                usleep($this->retryDelayMs * 1000);
            }
        }

        throw new \RuntimeException("Task \"{$this->name}\" failed after {$this->retries} attempt(s).");
    }

    public function cron(string $expression): static
    {
        $this->cronExpression = $expression;

        return $this;
    }

    public function hasSchedule(): bool
    {
        return $this->cronExpression !== null;
    }

    public function isDue(\DateTimeInterface|null $time = null): bool
    {
        if ($this->cronExpression === null) {
            return false;
        }

        return (new CronExpression($this->cronExpression))->isDue($time ?? new \DateTimeImmutable());
    }

    public function everyMinute(): static
    {
        return $this->cron('* * * * *');
    }

    public function everyTwoMinutes(): static
    {
        return $this->cron('*/2 * * * *');
    }

    public function everyFiveMinutes(): static
    {
        return $this->cron('*/5 * * * *');
    }

    public function everyTenMinutes(): static
    {
        return $this->cron('*/10 * * * *');
    }

    public function everyFifteenMinutes(): static
    {
        return $this->cron('*/15 * * * *');
    }

    public function everyThirtyMinutes(): static
    {
        return $this->cron('*/30 * * * *');
    }

    public function hourly(): static
    {
        return $this->cron('0 * * * *');
    }

    public function hourlyAt(int $minute): static
    {
        return $this->cron("{$minute} * * * *");
    }

    public function daily(): static
    {
        return $this->cron('0 0 * * *');
    }

    public function dailyAt(string $time): static
    {
        [$hour, $minute] = array_pad(explode(':', $time), 2, '0');

        return $this->cron("{$minute} {$hour} * * *");
    }

    public function weekly(): static
    {
        return $this->cron('0 0 * * 0');
    }

    public function weeklyOn(int $dayOfWeek, string $time = '0:0'): static
    {
        [$hour, $minute] = array_pad(explode(':', $time), 2, '0');

        return $this->cron("{$minute} {$hour} * * {$dayOfWeek}");
    }

    public function monthly(): static
    {
        return $this->cron('0 0 1 * *');
    }

    public function monthlyOn(int $dayOfMonth = 1, string $time = '0:0'): static
    {
        [$hour, $minute] = array_pad(explode(':', $time), 2, '0');

        return $this->cron("{$minute} {$hour} {$dayOfMonth} * *");
    }

    public function yearly(): static
    {
        return $this->cron('0 0 1 1 *');
    }
}
