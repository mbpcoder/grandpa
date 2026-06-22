<?php

declare(strict_types=1);

namespace Grandpa\Tests;

use Grandpa\Grandpa;
use Grandpa\Task;
use PHPUnit\Framework\TestCase;

final class FunctionsTest extends TestCase
{
    protected function setUp(): void
    {
        $this->resetSingleton();
    }

    protected function tearDown(): void
    {
        $this->resetSingleton();
    }

    public function testTaskHelperRegistersOnSingleton(): void
    {
        $task = task('demo', static function (): void {});

        self::assertInstanceOf(Task::class, $task);
        self::assertSame('demo', $task->getName());
    }

    public function testSayHelperPrintsMessage(): void
    {
        say('hi there');

        $this->expectOutputString('hi there' . PHP_EOL);
    }

    public function testEnvHelperReturnsDefaultWhenMissing(): void
    {
        self::assertSame('fallback', env('SOME_UNDEFINED_KEY', 'fallback'));
    }

    private function resetSingleton(): void
    {
        $property = new \ReflectionProperty(Grandpa::class, 'instance');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }
}
