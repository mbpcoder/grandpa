<?php

declare(strict_types=1);

namespace Grandpa;

class Ssh
{
    public function __construct(
        private readonly string $host,
        private readonly string $username = '',
        private readonly string $password = '',
        private readonly string $privateKey = '',
        private readonly int $port = 22,
        private readonly string $plinkPath = '',
    ) {
    }

    public static function fromEnv(): self
    {
        return new self(
            (string) env('GRANDPA_SSH_HOST', ''),
            (string) env('GRANDPA_SSH_USERNAME', ''),
            (string) env('GRANDPA_SSH_PASSWORD', ''),
            (string) env('GRANDPA_SSH_PRIVATE_KEY', ''),
            (int) env('GRANDPA_SSH_PORT', 22),
            (string) env('GRANDPA_PLINK_PATH', ''),
        );
    }

    public function run(string $command): string
    {
        if ($this->host === '') {
            throw new \RuntimeException('GRANDPA_SSH_HOST is not configured.');
        }

        $target = $this->username !== '' ? "{$this->username}@{$this->host}" : $this->host;

        $fullCommand = implode(' ', $this->buildArgs($target, $command));

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

    private function buildArgs(string $target, string $command): array
    {
        $usePlink = $this->password !== '' && \PHP_OS_FAMILY === 'Windows' && $this->plinkPath !== '';

        if ($usePlink) {
            $args = [
                escapeshellarg($this->plinkPath),
                '-ssh',
                '-P', escapeshellarg((string) $this->port),
                '-pw', escapeshellarg($this->password),
            ];
        } else {
            $args = [];

            if ($this->password !== '' && \PHP_OS_FAMILY !== 'Windows') {
                $args[] = 'sshpass';
                $args[] = '-p';
                $args[] = escapeshellarg($this->password);
            }

            $args[] = 'ssh';
            $args[] = '-p';
            $args[] = escapeshellarg((string) $this->port);
        }

        if ($this->privateKey !== '') {
            $args[] = '-i';
            $args[] = escapeshellarg($this->privateKey);
        }

        $args[] = escapeshellarg($target);
        $args[] = escapeshellarg($command);

        return $args;
    }
}
