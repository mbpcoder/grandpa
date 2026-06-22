<?php

declare(strict_types=1);

namespace Grandpa;

class Git
{
    private const REVISION_FILE = '.revision';

    public function __construct(private readonly Storage $storage)
    {
    }

    public function pull(): void
    {
        $this->exec('git pull');
    }

    public function push(): void
    {
        $this->exec('git push');
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
        $revision ??= $this->lastRevision();

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

    public function changedFiles(): array
    {
        return $this->getChanges()['added'];
    }

    public function deletedFiles(): array
    {
        return $this->getChanges()['deleted'];
    }

    public function currentHead(): string
    {
        return trim((string) shell_exec('git rev-parse HEAD'));
    }

    public function lastRevision(): string|null
    {
        if (!$this->storage->exists(self::REVISION_FILE)) {
            return null;
        }

        $revision = trim((string) $this->storage->get(self::REVISION_FILE));

        return $revision === '' ? null : $revision;
    }

    public function saveRevision(): void
    {
        $this->storage->put(self::REVISION_FILE, $this->currentHead());
    }

    public function logs(int $limit = 10): array
    {
        return $this->exec(sprintf('git log -%d --oneline', $limit));
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
