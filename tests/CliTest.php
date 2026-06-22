<?php

declare(strict_types=1);

namespace Grandpa\Tests;

use PHPUnit\Framework\TestCase;

final class CliTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/grandpa-cli-test-' . uniqid();
        mkdir($this->projectDir);
        file_put_contents(
            $this->projectDir . '/runner.php',
            "<?php\ntask('hello', function () {\n    say('Hello from ' . getcwd());\n});\n",
        );
    }

    protected function tearDown(): void
    {
        @unlink($this->projectDir . '/runner.php');
        @rmdir($this->projectDir);
    }

    public function testDirOptionRunsTaskFromAnotherDirectory(): void
    {
        $bin = dirname(__DIR__) . '/bin/grandpa';

        $output = shell_exec(sprintf('php %s --dir=%s hello 2>&1', escapeshellarg($bin), escapeshellarg($this->projectDir)));

        self::assertSame('Hello from ' . $this->projectDir, trim((string) $output));
    }

    public function testShortDirOptionCanAppearAfterTheTaskName(): void
    {
        $bin = dirname(__DIR__) . '/bin/grandpa';

        $output = shell_exec(sprintf('php %s hello -d=%s 2>&1', escapeshellarg($bin), escapeshellarg($this->projectDir)));

        self::assertSame('Hello from ' . $this->projectDir, trim((string) $output));
    }
}
