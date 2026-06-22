<?php

declare(strict_types=1);

namespace Grandpa\Tests;

use Grandpa\CronExpression;
use PHPUnit\Framework\TestCase;

final class CronExpressionTest extends TestCase
{
    public function testWildcardMatchesAnyTime(): void
    {
        $cron = new CronExpression('* * * * *');

        self::assertTrue($cron->isDue(new \DateTimeImmutable('2024-01-01 12:34:00')));
    }

    public function testStepExpressionMatchesOnlyMultiples(): void
    {
        $cron = new CronExpression('*/15 * * * *');

        self::assertTrue($cron->isDue(new \DateTimeImmutable('2024-01-01 12:30:00')));
        self::assertFalse($cron->isDue(new \DateTimeImmutable('2024-01-01 12:31:00')));
    }

    public function testRangeExpressionMatchesWithinBounds(): void
    {
        $cron = new CronExpression('0 9-17 * * *');

        self::assertTrue($cron->isDue(new \DateTimeImmutable('2024-01-01 09:00:00')));
        self::assertTrue($cron->isDue(new \DateTimeImmutable('2024-01-01 17:00:00')));
        self::assertFalse($cron->isDue(new \DateTimeImmutable('2024-01-01 18:00:00')));
    }

    public function testListExpressionMatchesAnyListedValue(): void
    {
        $cron = new CronExpression('0 0 1,15 * *');

        self::assertTrue($cron->isDue(new \DateTimeImmutable('2024-01-01 00:00:00')));
        self::assertTrue($cron->isDue(new \DateTimeImmutable('2024-01-15 00:00:00')));
        self::assertFalse($cron->isDue(new \DateTimeImmutable('2024-01-02 00:00:00')));
    }

    public function testExactValueMatchesOnlyThatValue(): void
    {
        $cron = new CronExpression('30 6 * * *');

        self::assertTrue($cron->isDue(new \DateTimeImmutable('2024-01-01 06:30:00')));
        self::assertFalse($cron->isDue(new \DateTimeImmutable('2024-01-01 06:31:00')));
    }

    public function testWeekdayFieldRestrictsToSpecificDay(): void
    {
        $cron = new CronExpression('0 0 * * 1');

        // 2024-01-01 is a Monday.
        self::assertTrue($cron->isDue(new \DateTimeImmutable('2024-01-01 00:00:00')));
        self::assertFalse($cron->isDue(new \DateTimeImmutable('2024-01-02 00:00:00')));
    }

    public function testInvalidExpressionThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new CronExpression('* * *'))->isDue(new \DateTimeImmutable());
    }
}
