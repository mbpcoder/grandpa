<?php

declare(strict_types=1);

namespace Grandpa\Tests;

use Grandpa\Grandpa;
use PHPUnit\Framework\TestCase;

final class GrandpaTest extends TestCase
{
    protected function setUp(): void
    {
        $this->resetSingleton();
    }

    protected function tearDown(): void
    {
        $this->resetSingleton();
    }

    public function testInstanceReturnsSameObject(): void
    {
        self::assertSame(Grandpa::instance(), Grandpa::instance());
    }

    public function testTaskRegistersAndRunTaskExecutesIt(): void
    {
        $ran = false;
        Grandpa::instance()->task('demo', function () use (&$ran): void {
            $ran = true;
        });

        Grandpa::instance()->runTask('demo');

        self::assertTrue($ran);
    }

    public function testRunTaskThrowsForUnknownTask(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Task "missing" is not defined.');

        Grandpa::instance()->runTask('missing');
    }

    public function testRunDueTasksOnlyRunsDueTasks(): void
    {
        $dueRan = false;
        $notDueRan = false;

        Grandpa::instance()->task('due', function () use (&$dueRan): void {
            $dueRan = true;
        })->everyMinute();

        Grandpa::instance()->task('not-due', function () use (&$notDueRan): void {
            $notDueRan = true;
        })->yearly();

        Grandpa::instance()->runDueTasks(new \DateTimeImmutable('2024-06-15 12:00:00'));

        self::assertTrue($dueRan);
        self::assertFalse($notDueRan);
    }

    public function testSayPrintsMessage(): void
    {
        Grandpa::instance()->say('hello');

        $this->expectOutputString('hello' . PHP_EOL);
    }

    public function testTickRunsDueCronTaskOnlyOncePerResolution(): void
    {
        $minuteRuns = 0;
        $secondRuns = 0;

        Grandpa::instance()->task('minutely', function () use (&$minuteRuns): void {
            $minuteRuns++;
        })->everyMinute();

        Grandpa::instance()->task('secondly', function () use (&$secondRuns): void {
            $secondRuns++;
        })->everySecond();

        $time = new \DateTimeImmutable('2024-06-15 12:00:00');

        Grandpa::instance()->tick($time);
        self::assertSame(1, $minuteRuns);
        self::assertSame(1, $secondRuns);

        // Same second: neither task should re-run.
        Grandpa::instance()->tick($time);
        self::assertSame(1, $minuteRuns);
        self::assertSame(1, $secondRuns);

        // A second later: the per-second task fires again, the per-minute task doesn't.
        Grandpa::instance()->tick($time->modify('+1 second'));
        self::assertSame(1, $minuteRuns);
        self::assertSame(2, $secondRuns);
    }

    public function testTickCatchesExceptionsFromOneTaskAndContinues(): void
    {
        $otherRan = false;

        Grandpa::instance()->task('broken', function (): void {
            throw new \RuntimeException('boom');
        })->everyMinute();

        Grandpa::instance()->task('other', function () use (&$otherRan): void {
            $otherRan = true;
        })->everyMinute();

        Grandpa::instance()->tick(new \DateTimeImmutable('2024-06-15 12:00:00'));

        self::assertTrue($otherRan);
    }

    private function resetSingleton(): void
    {
        $property = new \ReflectionProperty(Grandpa::class, 'instance');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }
}
