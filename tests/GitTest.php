<?php

declare(strict_types=1);

namespace Grandpa\Tests;

use Grandpa\Git;
use Grandpa\Storage;
use PHPUnit\Framework\TestCase;

final class GitTest extends TestCase
{
    private string $repo;

    protected function setUp(): void
    {
        $this->repo = sys_get_temp_dir() . '/grandpa-git-' . uniqid();
        mkdir($this->repo, recursive: true);

        exec('git -C ' . escapeshellarg($this->repo) . ' init -q -b main');
        exec('git -C ' . escapeshellarg($this->repo) . ' config user.email test@example.com');
        exec('git -C ' . escapeshellarg($this->repo) . ' config user.name Test');
        touch($this->repo . '/file.txt');
        exec('git -C ' . escapeshellarg($this->repo) . ' add -A');
        exec('git -C ' . escapeshellarg($this->repo) . ' commit -q -m initial');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->repo);
    }

    public function testIsRepositoryDetectsGitDirectory(): void
    {
        $git = new Git($this->createMock(Storage::class));

        self::assertTrue($git->isRepository($this->repo));
        self::assertFalse($git->isRepository(sys_get_temp_dir()));
    }

    public function testCurrentBranchReturnsCheckedOutBranch(): void
    {
        $git = new Git($this->createMock(Storage::class));

        self::assertSame('main', $git->currentBranch($this->repo));
    }

    private function removeDirectory(string $path): void
    {
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            $entryPath = $path . '/' . $entry;
            is_dir($entryPath) ? $this->removeDirectory($entryPath) : unlink($entryPath);
        }

        rmdir($path);
    }
}
