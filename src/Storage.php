<?php

declare(strict_types=1);

namespace Grandpa;

use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Ftp\FtpConnectionOptions;
use League\Flysystem\StorageAttributes;

class Storage
{
    private FilesystemOperator|null $filesystem = null;

    public function __construct(
        private readonly string $host,
        private readonly string $username,
        private readonly string $password,
        private readonly int $port = 21,
        private readonly string $root = '',
        private readonly bool $passive = true,
    ) {
    }

    public static function fromEnv(): self
    {
        return new self(
            host: (string) env('DEPLOY_FTP_HOST', ''),
            username: (string) env('DEPLOY_FTP_USERNAME', ''),
            password: (string) env('DEPLOY_FTP_PASSWORD', ''),
            port: (int) env('DEPLOY_FTP_PORT', 21),
            root: (string) env('DEPLOY_FTP_PATH', ''),
            passive: filter_var(env('DEPLOY_FTP_PASSIVE', true), FILTER_VALIDATE_BOOLEAN),
        );
    }

    public function exists(string $path): bool
    {
        return $this->filesystem()->fileExists($path);
    }

    public function get(string $path): string|null
    {
        try {
            return $this->filesystem()->read($path);
        } catch (\Throwable) {
            return null;
        }
    }

    public function put(string $path, string $contents): bool
    {
        try {
            $this->filesystem()->write($path, $contents);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function size(string $path): int
    {
        return $this->filesystem()->fileSize($path);
    }

    public function lastModified(string $path): int
    {
        return $this->filesystem()->lastModified($path);
    }

    public function copy(string $from, string $to): bool
    {
        try {
            $this->filesystem()->copy($from, $to);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function move(string $from, string $to): bool
    {
        try {
            $this->filesystem()->move($from, $to);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param string|list<string> $paths
     */
    public function delete(string|array $paths): void
    {
        foreach ((array) $paths as $path) {
            try {
                $this->filesystem()->delete($path);
            } catch (\Throwable) {
                // file may already be gone on the remote, nothing to do
            }
        }
    }

    /**
     * Upload a list of local files (paths relative to the project root) to the
     * same relative path on the remote server.
     *
     * @param list<string> $files
     */
    public function upload(array $files): void
    {
        foreach ($files as $file) {
            $stream = fopen($file, 'r');
            $this->filesystem()->writeStream($file, $stream);
            fclose($stream);
        }
    }

    /**
     * Recursively upload a local directory to the remote server.
     */
    public function uploadDir(string $localDirectory, string|null $remoteDirectory = null): void
    {
        $localDirectory = rtrim($localDirectory, '/');
        $remoteDirectory = rtrim($remoteDirectory ?? $localDirectory, '/');

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($localDirectory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );

        foreach ($iterator as $fileInfo) {
            /** @var \SplFileInfo $fileInfo */
            $relative = substr($fileInfo->getPathname(), strlen($localDirectory) + 1);
            $remotePath = $remoteDirectory . '/' . $relative;

            if ($fileInfo->isDir()) {
                $this->makeDirectory($remotePath);

                continue;
            }

            $stream = fopen($fileInfo->getPathname(), 'r');
            $this->filesystem()->writeStream($remotePath, $stream);
            fclose($stream);
        }
    }

    /**
     * Recursively delete every file and directory inside the given remote directory,
     * without removing the directory itself.
     */
    public function purge(string $directory): void
    {
        $contents = iterator_to_array($this->filesystem()->listContents($directory, true));

        foreach ($contents as $item) {
            if ($item->isFile()) {
                $this->filesystem()->delete($item->path());
            }
        }

        $directories = array_filter($contents, static fn (StorageAttributes $item): bool => $item->isDir());
        usort($directories, static fn (StorageAttributes $a, StorageAttributes $b): int => strlen($b->path()) - strlen($a->path()));

        foreach ($directories as $dir) {
            $this->filesystem()->deleteDirectory($dir->path());
        }
    }

    public function files(string $directory = ''): array
    {
        return $this->listPaths($directory, recursive: false, type: 'file');
    }

    public function allFiles(string $directory = ''): array
    {
        return $this->listPaths($directory, recursive: true, type: 'file');
    }

    public function directories(string $directory = ''): array
    {
        return $this->listPaths($directory, recursive: false, type: 'dir');
    }

    public function makeDirectory(string $directory): bool
    {
        try {
            $this->filesystem()->createDirectory($directory);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function deleteDirectory(string $directory): bool
    {
        try {
            $this->filesystem()->deleteDirectory($directory);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<string>
     */
    private function listPaths(string $directory, bool $recursive, string $type): array
    {
        $items = array_filter(
            iterator_to_array($this->filesystem()->listContents($directory, $recursive)),
            static fn (StorageAttributes $item): bool => $item->type() === $type,
        );

        return array_values(array_map(static fn (StorageAttributes $item): string => $item->path(), $items));
    }

    private function filesystem(): FilesystemOperator
    {
        if (!extension_loaded('ftp')) {
            throw new \RuntimeException(
                'The PHP "ftp" extension is required for FTP deployment but is not enabled. '
                . 'Enable it in your php.ini (extension=ftp) and restart, then try again.'
            );
        }

        return $this->filesystem ??= new Filesystem(new FtpAdapter(
            FtpConnectionOptions::fromArray([
                'host' => $this->host,
                'username' => $this->username,
                'password' => $this->password,
                'port' => $this->port,
                'root' => $this->root,
                'passive' => $this->passive,
                'ssl' => false,
                'timeout' => 30,
            ]),
        ));
    }

    public function __destruct()
    {
        $this->filesystem = null;
    }
}
