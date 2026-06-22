<?php

declare(strict_types=1);

namespace Grandpa;

class Task implements ITask
{
    private string|null $cronExpression = null;

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
