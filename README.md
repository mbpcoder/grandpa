# Grandpa
Grandpa Build System is a Pure PHP Build system.

## Deploy

Grandpa ships a minimal, dependency-light deploy tool (similar in spirit to
Envoy/Deployer): you describe deploy tasks in a `deploy.php` file, then run
them from the CLI or via Composer.

### Setup

1. Install dependencies: `composer install`.
2. Copy `.env.example` to `.env` and fill in your credentials. **Never commit `.env`.**

   ```
   DEPLOY_FTP_HOST=ftp.example.com
   DEPLOY_FTP_USERNAME=
   DEPLOY_FTP_PASSWORD=
   DEPLOY_FTP_PORT=21
   DEPLOY_FTP_PATH=/
   DEPLOY_FTP_PASSIVE=true

   DEPLOY_SSH_HOST=user@example.com
   ```

   - `DEPLOY_FTP_PATH` is the remote base directory everything is uploaded relative to.
   - `DEPLOY_SSH_HOST` is only used for running post-deploy commands over SSH (FTP can't run commands).
   - Optionally require `vlucas/phpdotenv` (`composer require vlucas/phpdotenv`) for fuller `.env` parsing; Grandpa falls back to a built-in parser if it's not installed.

3. Copy `deploy.php.example` to `deploy.php` (or `runner.php.example` to `runner.php`)
   in your project root and adjust it to your needs. `runner.php` is the more general
   name to use once you also have scheduled tasks; if both files exist, `runner.php`
   takes priority.

### Writing tasks

`runner.php` (or `deploy.php`) is plain PHP. Register tasks with `task()` and use
the helper functions below inside the task callback:

```php
<?php

task('deploy', function () {
    $files = git()->changedFiles();          // added/modified files since the last deploy

    ftp()->upload($files);                    // upload only changed files
    ftp()->delete(git()->deletedFiles());    // remove files deleted from git

    ftp()->purge('public/build');             // wipe a remote directory
    ftp()->uploadDir('public/build');        // push a whole local directory (e.g. built assets)

    git()->saveRevision();                    // record the deployed commit on the server

    ssh()->run('cd /var/www/app && php artisan migrate --force && php artisan optimize');

    say('Deployed');
});
```

How the incremental upload works:

- Grandpa keeps a `.revision` file on the remote server containing the last deployed commit hash.
- `git()->changedFiles()` / `git()->deletedFiles()` diff the current `HEAD` against that revision
  using `git diff --name-only --diff-filter=ACMR|D <revision>..HEAD`.
- If no remote `.revision` exists yet, it's treated as a first deploy and every tracked file
  (`git ls-files`) is uploaded.
- `git()->saveRevision()` writes the current `HEAD` hash back to the remote `.revision` file.

Available helpers: `task()`, `git()`, `ftp()`, `ssh()`, `say()`, `env()`.

### Running a deploy

```
php bin/grandpa deploy
```

or, since a Composer script is wired up:

```
composer deploy
```

Both run the `deploy` task defined in the `runner.php`/`deploy.php` file found in the
current working directory.

### Scheduling tasks

`task()` returns the `Task` instance, so you can chain Laravel-style schedule helpers
onto it. Scheduled tasks aren't run when invoked by name — they're run by the
`schedule:run` command, which checks every registered task and runs the ones that are
due:

```php
<?php

task('deploy', function () {
    // ...
})->everyMinute();

task('clear-old-logs', function () {
    // ...
})->dailyAt('1:00');
```

Available schedule helpers: `everyMinute()`, `everyTwoMinutes()`, `everyFiveMinutes()`,
`everyTenMinutes()`, `everyFifteenMinutes()`, `everyThirtyMinutes()`, `hourly()`,
`hourlyAt(int $minute)`, `daily()`, `dailyAt(string $time)`, `weekly()`,
`weeklyOn(int $dayOfWeek, string $time)`, `monthly()`,
`monthlyOn(int $dayOfMonth, string $time)`, `yearly()`, or a raw `cron(string $expression)`
for anything custom (standard 5-field cron syntax).

Run the scheduler once via:

```
php bin/grandpa schedule:run
```

or `composer schedule`. Like Laravel, point a single cron entry at this command and
let it run every minute on the server; Grandpa figures out which tasks are due:

```
* * * * * cd /path/to/project && php bin/grandpa schedule:run >> /dev/null 2>&1
```
