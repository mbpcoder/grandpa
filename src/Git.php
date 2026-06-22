<?php

declare(strict_types=1);

namespace Grandpa;

class Git
{
    public function pull(string|null $dir = null): array
    {
        return $this->exec($this->command('pull', $dir));
    }

    public function push(): void
    {
        $this->exec('git push');
    }

    public function isRepository(string $dir): bool
    {
        return is_dir($dir . '/.git');
    }

    public function currentBranch(string|null $dir = null): string
    {
        $output = $this->exec($this->command('rev-parse --abbrev-ref HEAD', $dir));

        return $output[0] ?? '';
    }

    public function commit(string $message): void
    {
        $this->exec('git add -A && git commit -m ' . escapeshellarg($message));
    }

    /**
     * @return array{added: list<string>, deleted: list<string>}
     */
    public function getChanges(string|null $revision = null): array
    {
        if ($revision === null) {
            return [
                'added' => $this->exec('git ls-files'),
                'deleted' => [],
            ];
        }

        return [
            'added' => $this->exec(sprintf('git diff --name-only --diff-filter=ACMR %s..HEAD', escapeshellarg($revision))),
            'deleted' => $this->exec(sprintf('git diff --name-only --diff-filter=D %s..HEAD', escapeshellarg($revision))),
        ];
    }

    public function changedFiles(string|null $revision = null): array
    {
        return $this->getChanges($revision)['added'];
    }

    public function deletedFiles(string|null $revision = null): array
    {
        return $this->getChanges($revision)['deleted'];
    }

    public function currentHead(): string
    {
        return trim((string) shell_exec('git rev-parse HEAD'));
    }

    public function logs(int $limit = 10): array
    {
        return $this->exec(sprintf('git log -%d --oneline', $limit));
    }

    private function command(string $args, string|null $dir): string
    {
        return $dir === null ? "git {$args}" : 'git -C ' . escapeshellarg($dir) . " {$args}";
    }

    /**
     * @return list<string>
     */
    private function exec(string $command): array
    {
        $output = [];
        exec($command . ' 2>&1', $output, $exitCode);

        if ($exitCode !== 0) {
            throw new \RuntimeException("Git command failed: {$command}\n" . implode("\n", $output));
        }

        return array_values(array_filter($output, static fn (string $line): bool => $line !== ''));
    }
}
