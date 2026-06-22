<?php

declare(strict_types=1);

namespace Grandpa;

use League\Flysystem\Adapter\Ftp;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemInterface;

class Storage
{
    private FilesystemInterface|null $filesystem = null;

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
        return $this->filesystem()->has($path);
    }

    public function get(string $path): string|null
    {
        $contents = $this->filesystem()->read($path);

        return $contents === false ? null : $contents;
    }

    public function put(string $path, string $contents): bool
    {
        return $this->filesystem()->put($path, $contents);
    }

    public function size(string $path): int
    {
        return (int) $this->filesystem()->getSize($path);
    }

    public function lastModified(string $path): int
    {
        return (int) $this->filesystem()->getTimestamp($path);
    }

    public function copy(string $from, string $to): bool
    {
        return $this->filesystem()->copy($from, $to);
    }

    public function move(string $from, string $to): bool
    {
        return $this->filesystem()->rename($from, $to);
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
            $this->filesystem()->putStream($file, $stream);
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
            $this->filesystem()->putStream($remotePath, $stream);
            fclose($stream);
        }
    }

    /**
     * Recursively delete every file and directory inside the given remote directory,
     * without removing the directory itself.
     */
    public function purge(string $directory): void
    {
        $contents = $this->filesystem()->listContents($directory, true);

        foreach ($contents as $item) {
            if ($item['type'] === 'file') {
                $this->filesystem()->delete($item['path']);
            }
        }

        $directories = array_filter($contents, static fn (array $item): bool => $item['type'] === 'dir');
        usort($directories, static fn (array $a, array $b): int => strlen($b['path']) - strlen($a['path']));

        foreach ($directories as $dir) {
            $this->filesystem()->deleteDir($dir['path']);
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
        return $this->filesystem()->createDir($directory);
    }

    public function deleteDirectory(string $directory): bool
    {
        return $this->filesystem()->deleteDir($directory);
    }

    /**
     * @return list<string>
     */
    private function listPaths(string $directory, bool $recursive, string $type): array
    {
        $items = array_filter(
            $this->filesystem()->listContents($directory, $recursive),
            static fn (array $item): bool => $item['type'] === $type,
        );

        return array_values(array_map(static fn (array $item): string => $item['path'], $items));
    }

    private function filesystem(): FilesystemInterface
    {
        return $this->filesystem ??= new Filesystem(new Ftp([
            'host' => $this->host,
            'username' => $this->username,
            'password' => $this->password,
            'port' => $this->port,
            'root' => $this->root,
            'passive' => $this->passive,
            'ssl' => false,
            'timeout' => 30,
        ]));
    }

    public function __destruct()
    {
        $this->filesystem = null;
    }
}
