<?php

declare(strict_types=1);

namespace Grandpa;

class Task implements ITask
{
    private string|null $cronExpression = null;
    private string|null $lastDueKey = null;

    public function __construct(
        private readonly string $name,
        private readonly \Closure $callback,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function run(): void
    {
        ($this->callback)();
    }

    /**
     * Accepts a standard 5-field cron expression (minute resolution), or a
     * 6-field expression with a leading seconds field for sub-minute
     * scheduling, e.g. cron('*\/10 * * * * *') to run every 10 seconds.
     */
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

    /**
     * Like isDue(), but only returns true once per matching second (for
     * 6-field expressions) or minute (for 5-field expressions), so a loop
     * ticking faster than the schedule's resolution doesn't re-trigger the
     * task repeatedly while it remains due.
     */
    public function isDueOnce(\DateTimeInterface|null $time = null): bool
    {
        if ($this->cronExpression === null) {
            return false;
        }

        $time ??= new \DateTimeImmutable();
        $hasSecondsField = count(preg_split('/\s+/', trim($this->cronExpression))) === 6;
        $key = $time->format($hasSecondsField ? 'Y-m-d H:i:s' : 'Y-m-d H:i');

        if ($key === $this->lastDueKey || !$this->isDue($time)) {
            return false;
        }

        $this->lastDueKey = $key;

        return true;
    }

    public function everySecond(): static
    {
        return $this->cron('* * * * * *');
    }

    public function everySeconds(int $seconds): static
    {
        return $this->cron("*/{$seconds} * * * * *");
    }

    public function everyFiveSeconds(): static
    {
        return $this->everySeconds(5);
    }

    public function everyTenSeconds(): static
    {
        return $this->everySeconds(10);
    }

    public function everyFifteenSeconds(): static
    {
        return $this->everySeconds(15);
    }

    public function everyThirtySeconds(): static
    {
        return $this->everySeconds(30);
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
