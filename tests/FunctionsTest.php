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

    public function testSubDirectoriesReturnsOnlyImmediateDirectories(): void
    {
        $base = sys_get_temp_dir() . '/grandpa-subdirs-' . uniqid();
        mkdir($base . '/one', recursive: true);
        mkdir($base . '/two', recursive: true);
        touch($base . '/file.txt');

        $directories = subDirectories($base);

        self::assertSame([$base . '/one', $base . '/two'], $directories);

        $this->removeDirectory($base);
    }

    private function removeDirectory(string $path): void
    {
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            $entryPath = $path . '/' . $entry;
            is_dir($entryPath) ? $this->removeDirectory($entryPath) : unlink($entryPath);
        }

        rmdir($path);
    }

    private function resetSingleton(): void
    {
        $property = new \ReflectionProperty(Grandpa::class, 'instance');
        $property->setAccessible(true);
        $property->setValue(null, null);
    }
}
