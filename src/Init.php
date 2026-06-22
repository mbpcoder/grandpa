<?php

declare(strict_types=1);

namespace Grandpa;

class Init
{
    /**
     * @var array<string, string> map of npm package => default build output dir
     */
    private const BUILD_TOOLS = [
        'vite' => 'dist',
        'laravel-mix' => 'public/build',
        '@vue/cli-service' => 'dist',
        'webpack' => 'dist',
        'parcel' => 'dist',
        'next' => '.next',
        'react-scripts' => 'build',
        '@angular/cli' => 'dist',
    ];

    public function run(string $cwd, array $argv): void
    {
        $runnerPath = $cwd . '/runner.php';

        if (file_exists($runnerPath)) {
            fwrite(STDERR, "runner.php already exists in {$cwd}\n");
            exit(1);
        }

        $interactive = in_array('-i', $argv, true) || in_array('--interactive', $argv, true);

        $project = $this->detectProject($cwd);

        if ($interactive) {
            $this->writeEnv($cwd, $this->ask());
        }

        file_put_contents($runnerPath, $this->buildRecipe($project));

        echo "Detected: {$project['summary']}\n";
        echo "Created runner.php\n";
    }

    /**
     * @return array{
     *     hasGit: bool,
     *     hasComposer: bool,
     *     hasPackageJson: bool,
     *     buildTool: string|null,
     *     buildCommand: string|null,
     *     buildDir: string|null,
     *     summary: string,
     * }
     */
    private function detectProject(string $cwd): array
    {
        $hasGit = is_dir($cwd . '/.git');
        $hasComposer = file_exists($cwd . '/composer.json');
        $hasPackageJson = file_exists($cwd . '/package.json');

        $buildTool = null;
        $buildCommand = null;
        $buildDir = null;

        if ($hasPackageJson) {
            $package = json_decode((string) file_get_contents($cwd . '/package.json'), true) ?: [];
            $deps = array_merge($package['dependencies'] ?? [], $package['devDependencies'] ?? []);

            [$buildTool, $buildDir] = $this->detectBuildTool($cwd, $deps);

            if (isset($package['scripts']['build'])) {
                $buildCommand = $this->detectPackageManager($cwd) . ' run build';
            }
        }

        $summary = array_filter([
            $hasGit ? 'git' : null,
            $hasComposer ? 'composer' : null,
            $hasPackageJson ? 'package.json' : null,
            $buildTool,
        ]);

        return [
            'hasGit' => $hasGit,
            'hasComposer' => $hasComposer,
            'hasPackageJson' => $hasPackageJson,
            'buildTool' => $buildTool,
            'buildCommand' => $buildCommand,
            'buildDir' => $buildDir,
            'summary' => $summary === [] ? 'plain PHP project' : implode(', ', $summary),
        ];
    }

    private function detectPackageManager(string $cwd): string
    {
        if (file_exists($cwd . '/pnpm-lock.yaml')) {
            return 'pnpm';
        }

        if (file_exists($cwd . '/yarn.lock')) {
            return 'yarn';
        }

        return 'npm';
    }

    /**
     * @param array<string, string> $deps
     * @return array{0: string|null, 1: string|null}
     */
    private function detectBuildTool(string $cwd, array $deps): array
    {
        foreach (self::BUILD_TOOLS as $package => $defaultDir) {
            if (isset($deps[$package])) {
                return [$package, $this->resolveOutDir($cwd, $package, $defaultDir)];
            }
        }

        return [null, null];
    }

    private function resolveOutDir(string $cwd, string $tool, string $default): string
    {
        if ($tool === 'vite') {
            foreach (['vite.config.js', 'vite.config.ts'] as $file) {
                $path = $cwd . '/' . $file;

                if (file_exists($path) && preg_match('/outDir\s*:\s*[\'"]([^\'"]+)[\'"]/', (string) file_get_contents($path), $matches)) {
                    return $matches[1];
                }
            }
        }

        return $default;
    }

    /**
     * @return array{ftpHost: string, ftpUser: string, ftpPass: string, ftpPort: string, ftpPath: string, sshHost: string}
     */
    private function ask(): array
    {
        echo "Configuring deploy credentials (leave blank to skip a field).\n";

        return [
            'ftpHost' => $this->prompt('FTP host'),
            'ftpUser' => $this->prompt('FTP username'),
            'ftpPass' => $this->prompt('FTP password'),
            'ftpPort' => $this->prompt('FTP port', '21'),
            'ftpPath' => $this->prompt('FTP remote path', '/'),
            'sshHost' => $this->prompt('SSH host (user@host, blank if none)'),
        ];
    }

    private function prompt(string $label, string $default = ''): string
    {
        $suffix = $default === '' ? '' : " [{$default}]";
        echo "{$label}{$suffix}: ";

        $value = trim((string) fgets(STDIN));

        return $value === '' ? $default : $value;
    }

    /**
     * @param array{ftpHost: string, ftpUser: string, ftpPass: string, ftpPort: string, ftpPath: string, sshHost: string} $answers
     */
    private function writeEnv(string $cwd, array $answers): void
    {
        $path = $cwd . '/.env';

        if (file_exists($path)) {
            echo ".env already exists, leaving it untouched.\n";

            return;
        }

        $lines = [
            'GRANDPA_FTP_HOST=' . $answers['ftpHost'],
            'GRANDPA_FTP_USERNAME=' . $answers['ftpUser'],
            'GRANDPA_FTP_PASSWORD=' . $answers['ftpPass'],
            'GRANDPA_FTP_PORT=' . $answers['ftpPort'],
            'GRANDPA_FTP_PATH=' . $answers['ftpPath'],
            'GRANDPA_FTP_PASSIVE=true',
            '',
            'GRANDPA_SSH_HOST=' . $answers['sshHost'],
            '',
        ];

        file_put_contents($path, implode(PHP_EOL, $lines));

        echo "Wrote .env with deploy credentials.\n";
    }

    /**
     * @param array{hasGit: bool, hasComposer: bool, hasPackageJson: bool, buildTool: string|null, buildCommand: string|null, buildDir: string|null, summary: string} $project
     */
    private function buildRecipe(array $project): string
    {
        $lines = [
            '<?php',
            '',
            'declare(strict_types=1);',
            '',
            "task('deploy', function () {",
        ];

        if ($project['buildCommand'] !== null) {
            $lines[] = '    run(' . var_export($project['buildCommand'], true) . ');';
            $lines[] = '';
        }

        if ($project['hasGit']) {
            $lines[] = '    $files = git()->changedFiles();';
            $lines[] = '';
            $lines[] = '    storage()->ftp()->upload($files);';
            $lines[] = '    storage()->ftp()->delete(git()->deletedFiles());';
        } else {
            $lines[] = "    storage()->ftp()->uploadDir('.');";
        }

        if ($project['buildDir'] !== null) {
            $lines[] = '';
            $lines[] = '    storage()->ftp()->purge(' . var_export($project['buildDir'], true) . ');';
            $lines[] = '    storage()->ftp()->uploadDir(' . var_export($project['buildDir'], true) . ');';
        }

        if ($project['hasGit']) {
            $lines[] = '';
            $lines[] = '    git()->saveRevision();';
        }

        $lines[] = '';
        $lines[] = "    say('Deployed');";
        $lines[] = '});';
        $lines[] = '';

        return implode(PHP_EOL, $lines);
    }
}
