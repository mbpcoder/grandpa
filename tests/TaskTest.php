<?php

declare(strict_types=1);

namespace Grandpa\Tests;

use Grandpa\Task;
use Grandpa\TaskStatus;
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

    public function testRunDoesNotRetryWhenCallbackReturnsNothing(): void
    {
        $calls = 0;
        $task = (new Task('demo', function () use (&$calls): void {
            $calls++;
        }))->retry(3);

        $task->run();

        self::assertSame(1, $calls);
    }

    public function testRunDoesNotRetryWhenCallbackReturnsSuccess(): void
    {
        $calls = 0;
        $task = (new Task('demo', function () use (&$calls): TaskStatus {
            $calls++;

            return TaskStatus::Success;
        }))->retry(3);

        $task->run();

        self::assertSame(1, $calls);
    }

    public function testRunRetriesUntilSuccess(): void
    {
        $calls = 0;
        $task = (new Task('demo', function () use (&$calls): TaskStatus {
            $calls++;

            return $calls < 3 ? TaskStatus::Error : TaskStatus::Success;
        }))->retry(5);

        $task->run();

        self::assertSame(3, $calls);
    }

    public function testRunThrowsAfterExhaustingRetriesOnError(): void
    {
        $calls = 0;
        $task = (new Task('demo', function () use (&$calls): TaskStatus {
            $calls++;

            return TaskStatus::Error;
        }))->retry(3);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Task "demo" failed after 3 attempt(s).');

        try {
            $task->run();
        } finally {
            self::assertSame(3, $calls);
        }
    }

    public function testRunWithoutRetryDoesNotRetryOnError(): void
    {
        $calls = 0;
        $task = new Task('demo', function () use (&$calls): TaskStatus {
            $calls++;

            return TaskStatus::Error;
        });

        $this->expectException(\RuntimeException::class);

        try {
            $task->run();
        } finally {
            self::assertSame(1, $calls);
        }
    }

    public function testRunWithoutRepeatRunsOnce(): void
    {
        $calls = 0;
        $task = new Task('demo', function () use (&$calls): void {
            $calls++;
        });

        $task->run();

        self::assertSame(1, $calls);
    }

    public function testRepeatRunsCallbackGivenNumberOfTimes(): void
    {
        $calls = 0;
        $task = (new Task('demo', function () use (&$calls): void {
            $calls++;
        }))->repeat(3);

        $task->run();

        self::assertSame(3, $calls);
    }

    public function testRepeatRunsEveryTimeRegardlessOfOutcome(): void
    {
        $calls = 0;
        $task = (new Task('demo', function () use (&$calls): TaskStatus {
            $calls++;

            return TaskStatus::Error;
        }))->repeat(3);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Task "demo" failed 3 of 3 run(s).');

        try {
            $task->run();
        } finally {
            self::assertSame(3, $calls);
        }
    }

    public function testRepeatContinuesPastAFailedRunAndReportsItAtTheEnd(): void
    {
        $calls = 0;
        $task = (new Task('demo', function () use (&$calls): TaskStatus {
            $calls++;

            return $calls === 1 ? TaskStatus::Error : TaskStatus::Success;
        }))->repeat(3);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Task "demo" failed 1 of 3 run(s).');

        try {
            $task->run();
        } finally {
            self::assertSame(3, $calls);
        }
    }

    public function testRepeatCombinesWithRetryPerRun(): void
    {
        $calls = 0;
        $task = (new Task('demo', function () use (&$calls): TaskStatus {
            $calls++;

            // Fails on the first attempt of every run, succeeds on the second.
            return $calls % 2 === 1 ? TaskStatus::Error : TaskStatus::Success;
        }))->retry(2)->repeat(3);

        $task->run();

        self::assertSame(6, $calls);
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
}
