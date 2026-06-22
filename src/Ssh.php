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

        $usePlink = $this->password !== '' && \PHP_OS_FAMILY === 'Windows' && $this->plinkPath !== '';

        [$result, $exitCode] = $this->exec($this->buildArgs($target, $command, $usePlink));

        if ($usePlink) {
            if (stripos($result, 'host key is not cached') !== false) {
                $trustCommand = "{$this->plinkPath} -ssh {$target}";

                throw new \RuntimeException(
                    "SSH command failed: {$command}\n{$result}\n\n"
                    . "WARNING: plink refused to connect because it does not yet trust this host's SSH key "
                    . "(this is a security check to prevent connecting to an impostor server).\n"
                    . "Run this command manually once and answer \"y\" to cache the host key, then re-run grandpa:\n"
                    . "  {$trustCommand}"
                );
            }

            if (stripos($result, 'access denied') !== false) {
                throw new \RuntimeException(
                    "SSH command failed: {$command}\n{$result}\n\n"
                    . "WARNING: plink was rejected by the server during authentication. "
                    . "Check that GRANDPA_SSH_USERNAME and GRANDPA_SSH_PASSWORD are correct, "
                    . "and that the account is allowed to log in over SSH."
                );
            }

            foreach (['fatal error', 'connection abandoned', 'connection refused', 'network error'] as $marker) {
                if (stripos($result, $marker) !== false) {
                    throw new \RuntimeException("SSH command failed: {$command}\n{$result}");
                }
            }
        }

        if ($exitCode !== 0) {
            throw new \RuntimeException("SSH command failed: {$command}\n{$result}");
        }

        if ($result !== '') {
            echo $result . PHP_EOL;
        }

        return $result;
    }

    /**
     * @param list<string> $args
     * @return array{0: string, 1: int}
     */
    private function exec(array $args): array
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        // Passing $args as an array (rather than a shell string) bypasses
        // cmd.exe/escapeshellarg entirely, so passwords with special
        // characters reach the child process intact.
        $process = proc_open($args, $descriptors, $pipes);

        if ($process === false) {
            throw new \RuntimeException('Failed to start SSH process: ' . implode(' ', $args));
        }

        fclose($pipes[0]);

        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $exitCode = proc_close($process);

        $result = trim(implode("\n", array_filter([$stdout, $stderr])));

        return [$result, $exitCode];
    }

    /**
     * @return list<string>
     */
    private function buildArgs(string $target, string $command, bool $usePlink): array
    {
        if ($usePlink) {
            $args = [
                $this->plinkPath,
                '-ssh',
                '-batch',
                '-P', (string) $this->port,
                '-pw', $this->password,
            ];
        } else {
            $args = [];

            if ($this->password !== '' && \PHP_OS_FAMILY !== 'Windows') {
                $args[] = 'sshpass';
                $args[] = '-p';
                $args[] = $this->password;
            }

            $args[] = 'ssh';
            $args[] = '-p';
            $args[] = (string) $this->port;
        }

        if ($this->privateKey !== '') {
            $args[] = '-i';
            $args[] = $this->privateKey;
        }

        $args[] = $target;
        $args[] = $command;

        return $args;
    }
}
