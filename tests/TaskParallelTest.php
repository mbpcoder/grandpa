<?php

declare(strict_types=1);

namespace Grandpa\Tests;

use PHPUnit\Framework\TestCase;

final class TaskParallelTest extends TestCase
{
    private string $projectDir;
    private string $bin;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/grandpa-parallel-test-' . uniqid();
        mkdir($this->projectDir);
        $this->bin = dirname(__DIR__) . '/bin/grandpa';
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->projectDir . '/*') ?: []);
        @rmdir($this->projectDir);
    }

    public function testAsParallelRunsEveryRepeatInASeparateProcess(): void
    {
        $log = $this->projectDir . '/log.txt';
        file_put_contents($this->projectDir . '/runner.php', <<<PHP
            <?php
            task('warm', function () {
                file_put_contents('{$log}', getmypid() . PHP_EOL, FILE_APPEND);
            })->repeat(6)->asParallel(3);
            PHP);

        $output = $this->runCli('warm');

        self::assertSame('', trim($output));

        $pids = array_filter(explode(PHP_EOL, trim((string) file_get_contents($log))));
        self::assertCount(6, $pids);
        self::assertCount(6, array_unique($pids), 'Each repeat should run in its own process.');
    }

    public function testAsParallelAggregatesFailuresAndExitsNonZero(): void
    {
        file_put_contents($this->projectDir . '/runner.php', <<<'PHP'
            <?php
            use Grandpa\TaskStatus;
            task('flaky', function () {
                return TaskStatus::Error;
            })->repeat(3)->asParallel();
            PHP);

        $output = $this->runCli('flaky', $exitCode);

        self::assertSame(1, $exitCode);
        self::assertStringContainsString('Task "flaky" failed 3 of 3 parallel run(s).', $output);
    }

    public function testAsParallelWarnsWhenCombinedWithARepeatInterval(): void
    {
        file_put_contents($this->projectDir . '/runner.php', <<<'PHP'
            <?php
            task('warn', function () {
                echo "ran" . PHP_EOL;
            })->repeat(2, 500)->asParallel();
            PHP);

        $output = $this->runCli('warn');

        self::assertStringContainsString('ignores its repeat interval while running in parallel', $output);
        self::assertSame(2, substr_count($output, 'ran'));
    }

    private function runCli(string $task, int|null &$exitCode = null): string
    {
        $command = sprintf(
            'php %s --dir=%s %s 2>&1',
            escapeshellarg($this->bin),
            escapeshellarg($this->projectDir),
            escapeshellarg($task),
        );

        exec($command, $outputLines, $exitCode);

        return implode(PHP_EOL, $outputLines);
    }
}
