<?php

declare(strict_types=1);

namespace Grandpa;

class Ssh
{
    public function __construct(private readonly string $host)
    {
    }

    public static function fromEnv(): self
    {
        return new self((string) env('DEPLOY_SSH_HOST', ''));
    }

    public function run(string $command): string
    {
        if ($this->host === '') {
            throw new \RuntimeException('DEPLOY_SSH_HOST is not configured.');
        }

        $fullCommand = sprintf('ssh %s %s', escapeshellarg($this->host), escapeshellarg($command));

        $output = [];
        exec($fullCommand . ' 2>&1', $output, $exitCode);
        $result = implode("\n", $output);

        if ($exitCode !== 0) {
            throw new \RuntimeException("SSH command failed: {$command}\n{$result}");
        }

        if ($result !== '') {
            echo $result . PHP_EOL;
        }

        return $result;
    }
}
