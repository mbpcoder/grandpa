<?php

declare(strict_types=1);

namespace Grandpa\Tests;

use Grandpa\Task;
use PHPUnit\Framework\TestCase;

final class TaskTest extends TestCase
{
    public function testRunInvokesCallback(): void
    {
        $called = false;
        $task = new Task('demo', function () use (&$called): void {
            $called = true;
        });

        $task->run();

        self::assertTrue($called);
    }

    public function testGetNameReturnsConstructedName(): void
    {
        $task = new Task('deploy', static function (): void {});

        self::assertSame('deploy', $task->getName());
    }

    public function testTaskWithoutCronIsNeverDue(): void
    {
        $task = new Task('demo', static function (): void {});

        self::assertFalse($task->isDue(new \DateTimeImmutable()));
    }

    public function testEveryMinuteIsAlwaysDue(): void
    {
        $task = (new Task('demo', static function (): void {}))->everyMinute();

        self::assertTrue($task->isDue(new \DateTimeImmutable('2024-01-01 00:00:00')));
    }

    public function testHourlyIsDueOnlyAtTopOfHour(): void
    {
        $task = (new Task('demo', static function (): void {}))->hourly();

        self::assertTrue($task->isDue(new \DateTimeImmutable('2024-01-01 05:00:00')));
        self::assertFalse($task->isDue(new \DateTimeImmutable('2024-01-01 05:01:00')));
    }

    public function testHourlyAtUsesGivenMinute(): void
    {
        $task = (new Task('demo', static function (): void {}))->hourlyAt(30);

        self::assertTrue($task->isDue(new \DateTimeImmutable('2024-01-01 05:30:00')));
        self::assertFalse($task->isDue(new \DateTimeImmutable('2024-01-01 05:00:00')));
    }

    public function testDailyAtUsesGivenTime(): void
    {
        $task = (new Task('demo', static function (): void {}))->dailyAt('13:45');

        self::assertTrue($task->isDue(new \DateTimeImmutable('2024-01-01 13:45:00')));
        self::assertFalse($task->isDue(new \DateTimeImmutable('2024-01-01 13:46:00')));
    }

    public function testWeeklyOnUsesGivenDayAndTime(): void
    {
        $task = (new Task('demo', static function (): void {}))->weeklyOn(1, '8:0');

        // 2024-01-01 is a Monday.
        self::assertTrue($task->isDue(new \DateTimeImmutable('2024-01-01 08:00:00')));
        self::assertFalse($task->isDue(new \DateTimeImmutable('2024-01-02 08:00:00')));
    }

    public function testMonthlyOnUsesGivenDayAndTime(): void
    {
        $task = (new Task('demo', static function (): void {}))->monthlyOn(15, '4:30');

        self::assertTrue($task->isDue(new \DateTimeImmutable('2024-03-15 04:30:00')));
        self::assertFalse($task->isDue(new \DateTimeImmutable('2024-03-16 04:30:00')));
    }

    public function testYearlyIsDueOnlyOnJanuaryFirst(): void
    {
        $task = (new Task('demo', static function (): void {}))->yearly();

        self::assertTrue($task->isDue(new \DateTimeImmutable('2024-01-01 00:00:00')));
        self::assertFalse($task->isDue(new \DateTimeImmutable('2024-02-01 00:00:00')));
    }

    public function testIsDueOnceOnlyTriggersOncePerMinute(): void
    {
        $task = (new Task('demo', static function (): void {}))->everyMinute();

        $time = new \DateTimeImmutable('2024-01-01 00:00:00');

        self::assertTrue($task->isDueOnce($time));
        self::assertFalse($task->isDueOnce($time));
        self::assertFalse($task->isDueOnce($time->modify('+30 seconds')));
        self::assertTrue($task->isDueOnce($time->modify('+1 minute')));
    }

    public function testHasWatchIsFalseWithoutWatch(): void
    {
        $task = new Task('demo', static function (): void {});

        self::assertFalse($task->hasWatch());
        self::assertFalse($task->watchChanged());
    }

    public function testWatchChangedIgnoresFirstScanThenDetectsChanges(): void
    {
        $dir = sys_get_temp_dir() . '/grandpa-watch-test-' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/a.txt', 'one');

        try {
            $task = (new Task('demo', static function (): void {}))->watch($dir);

            self::assertTrue($task->hasWatch());
            self::assertSame($dir, $task->getWatchPath());

            // First check only records the baseline.
            self::assertFalse($task->watchChanged());
            self::assertFalse($task->watchChanged());

            sleep(1);
            file_put_contents($dir . '/a.txt', 'two');

            self::assertTrue($task->watchChanged());
            self::assertFalse($task->watchChanged());
        } finally {
            @unlink($dir . '/a.txt');
            @rmdir($dir);
        }
    }

    public function testWatchExtensionsFilterIgnoresOtherFiles(): void
    {
        $dir = sys_get_temp_dir() . '/grandpa-watch-test-' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/a.log', 'one');

        try {
            $task = (new Task('demo', static function (): void {}))->watch($dir, ['txt']);

            self::assertFalse($task->watchChanged());

            sleep(1);
            file_put_contents($dir . '/a.log', 'two');

            self::assertFalse($task->watchChanged());
        } finally {
            @unlink($dir . '/a.log');
            @rmdir($dir);
        }
    }
}
