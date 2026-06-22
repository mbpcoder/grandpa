<?php

declare(strict_types=1);

$root = __DIR__;
$pharFile = $root . '/grandpa.phar';

if (file_exists($pharFile)) {
    unlink($pharFile);
}

$phar = new Phar($pharFile);
$phar->startBuffering();

foreach (['src', 'vendor'] as $dir) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root . '/' . $dir, FilesystemIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        $localPath = $dir . '/' . substr($file->getPathname(), strlen($root . '/' . $dir) + 1);
        $phar->addFile($file->getPathname(), $localPath);
    }
}

$phar->addFile('bin/grandpa', 'bin/grandpa');

$stub = <<<'STUB'
#!/usr/bin/env php
<?php
Phar::mapPhar('grandpa.phar');
require 'phar://grandpa.phar/bin/grandpa';
__HALT_COMPILER();
STUB;

$phar->setStub($stub);
$phar->stopBuffering();

chmod($pharFile, 0755);

echo "Built {$pharFile}\n";
