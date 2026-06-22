<?php

declare(strict_types=1);

namespace Grandpa;

class Task implements ITask
{
    private string|null $cronExpression = null;
    private string|null $lastDueKey = null;

    private string|null $watchPath = null;
    /** @var list<string> */
    private array $watchExtensions = [];
    private string|null $watchSignature = null;
    private bool $watchInitialized = false;

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

    /**
     * Like isDue(), but only returns true once per matching minute, so a
     * long-running watch loop ticking faster than once a minute doesn't
     * re-trigger the task repeatedly while it remains due.
     */
    public function isDueOnce(\DateTimeInterface|null $time = null): bool
    {
        if (!$this->hasSchedule()) {
            return false;
        }

        $time ??= new \DateTimeImmutable();
        $key = $time->format('Y-m-d H:i');

        if ($key === $this->lastDueKey || !$this->isDue($time)) {
            return false;
        }

        $this->lastDueKey = $key;

        return true;
    }

    /**
     * @param list<string> $extensions File extensions (without the dot) to watch; empty means all files.
     */
    public function watch(string $path, array $extensions = []): static
    {
        $this->watchPath = $path;
        $this->watchExtensions = array_map(fn (string $extension) => strtolower($extension), $extensions);

        return $this;
    }

    public function hasWatch(): bool
    {
        return $this->watchPath !== null;
    }

    public function getWatchPath(): string|null
    {
        return $this->watchPath;
    }

    /**
     * Returns true the first time it detects the watched folder differs
     * from its previously recorded state. The first call after watch() is
     * configured only records a baseline and returns false, so the task
     * doesn't run immediately on startup.
     */
    public function watchChanged(): bool
    {
        if ($this->watchPath === null) {
            return false;
        }

        $signature = $this->computeWatchSignature();

        if (!$this->watchInitialized) {
            $this->watchSignature = $signature;
            $this->watchInitialized = true;

            return false;
        }

        if ($signature === $this->watchSignature) {
            return false;
        }

        $this->watchSignature = $signature;

        return true;
    }

    private function computeWatchSignature(): string
    {
        if ($this->watchPath === null || !is_dir($this->watchPath)) {
            return '';
        }

        $entries = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->watchPath, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file->isFile()) {
                continue;
            }

            if ($this->watchExtensions !== [] && !in_array(strtolower($file->getExtension()), $this->watchExtensions, true)) {
                continue;
            }

            $entries[] = $file->getPathname() . ':' . $file->getMTime() . ':' . $file->getSize();
        }

        sort($entries);

        return hash('xxh128', implode('|', $entries));
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
