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

    public function testTickRunsDueCronTaskOnceAndWatchTaskOnChange(): void
    {
        $dir = sys_get_temp_dir() . '/grandpa-tick-test-' . uniqid();
        mkdir($dir);
        file_put_contents($dir . '/a.txt', 'one');

        try {
            $cronRuns = 0;
            $watchRuns = 0;

            Grandpa::instance()->task('cron', function () use (&$cronRuns): void {
                $cronRuns++;
            })->everyMinute();

            Grandpa::instance()->task('watcher', function () use (&$watchRuns): void {
                $watchRuns++;
            })->watch($dir);

            $time = new \DateTimeImmutable('2024-06-15 12:00:00');

            Grandpa::instance()->tick($time);
            self::assertSame(1, $cronRuns);
            self::assertSame(0, $watchRuns);

            // Same minute: cron task must not re-run, watch baseline already recorded.
            Grandpa::instance()->tick($time);
            self::assertSame(1, $cronRuns);
            self::assertSame(0, $watchRuns);

            sleep(1);
            file_put_contents($dir . '/a.txt', 'two');

            Grandpa::instance()->tick($time);
            self::assertSame(1, $cronRuns);
            self::assertSame(1, $watchRuns);
        } finally {
            @unlink($dir . '/a.txt');
            @rmdir($dir);
        }
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
