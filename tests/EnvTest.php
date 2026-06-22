<?php

declare(strict_types=1);

namespace Grandpa\Tests;

use Grandpa\Env;
use PHPUnit\Framework\TestCase;

final class EnvTest extends TestCase
{
    private string $envFile;

    protected function setUp(): void
    {
        $this->envFile = tempnam(sys_get_temp_dir(), 'grandpa_env_') . '.env';
        file_put_contents($this->envFile, "FOO=bar\n# comment\n\nQUOTED=\"hello world\"\n");

        $this->resetLoadedState();
    }

    protected function tearDown(): void
    {
        @unlink($this->envFile);
        $this->resetLoadedState();
        putenv('FOO');
        putenv('QUOTED');
        unset($_ENV['FOO'], $_ENV['QUOTED'], $_SERVER['FOO'], $_SERVER['QUOTED']);
    }

    public function testLoadParsesSimpleAndQuotedValues(): void
    {
        Env::load($this->envFile);

        self::assertSame('bar', Env::get('FOO'));
        self::assertSame('hello world', Env::get('QUOTED'));
    }

    public function testGetReturnsDefaultWhenMissing(): void
    {
        self::assertSame('fallback', Env::get('DOES_NOT_EXIST', 'fallback'));
    }

    public function testLoadIsNoopWhenFileMissing(): void
    {
        Env::load('/nonexistent/path/.env');

        self::assertNull(Env::get('FOO'));
    }

    public function testLoadOnlyAppliesOnce(): void
    {
        Env::load($this->envFile);

        file_put_contents($this->envFile, "FOO=changed\n");
        Env::load($this->envFile);

        self::assertSame('bar', Env::get('FOO'));
    }

    private function resetLoadedState(): void
    {
        $property = new \ReflectionProperty(Env::class, 'loaded');
        $property->setAccessible(true);
        $property->setValue(null, false);
    }
}
