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

Available helpers: `task()`, `git()`, `ftp()`, `ssh()`, `http()`, `say()`, `env()`.

`http()` returns a small Guzzle-backed client for hitting URLs during a
deploy (e.g. cache-clear/health-check routes): `http()->get($url)`,
`http()->post($url, ['json' => [...]])`, or `http()->request($method, $url, $options)`.
The `$options` array is passed straight through to Guzzle, so any Guzzle
request option works. Requests that fail throw a `RuntimeException`.

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

### Recipes

A few common deploy scenarios, ready to copy into `deploy.php`/`runner.php`.

#### Shared hosting (cPanel / DirectAdmin) over FTP, with a cache-clear/health-check URL

Most cPanel/DirectAdmin hosts only expose FTP/FTPS, not SSH. Upload the changed
files, then hit a URL on the site itself (e.g. a route that clears cache or
warms it up) to finish the deploy:

```php
<?php

task('deploy', function () {
    $files = git()->changedFiles();

    ftp()->upload($files);
    ftp()->delete(git()->deletedFiles());

    git()->saveRevision();

    // Hit a route on the live site to clear cache / warm up / health-check.
    $response = http()->get('https://example.com/__deploy/clear-cache');

    say('Deployed and cache cleared: ' . $response);
});
```

> `ftp()` talks to a plain FTP/FTPS server, which is what most shared hosts
> provide. There's no SSH on this kind of host, so any "artisan migrate" or
> "clear cache" step has to happen through an HTTP endpoint your app exposes
> for that purpose (protect it with a secret token/header).

#### VPS with SSH access, running commands after deploy

If your host gives you SSH (a VPS, or cPanel/DirectAdmin with SSH enabled),
upload over FTP as usual and then run commands on the server directly —
no need for an HTTP endpoint:

```php
<?php

task('deploy', function () {
    $files = git()->changedFiles();

    ftp()->upload($files);
    ftp()->delete(git()->deletedFiles());

    ftp()->purge('public/build');
    ftp()->uploadDir('public/build');

    git()->saveRevision();

    ssh()->run('cd /var/www/app && composer install --no-dev && php artisan migrate --force');
    ssh()->run('cd /var/www/app && php artisan optimize:clear && php artisan optimize');

    say('Deployed');
});
```

`ssh()->run()` shells out to the local `ssh` binary using `DEPLOY_SSH_HOST`
(e.g. `deploy@example.com`), so it relies on your SSH key/agent already being
set up — there's no password field for it. Set up an SSH key with the host
beforehand (`ssh-copy-id deploy@example.com`) and make sure `ssh deploy@example.com`
works without a prompt before running `grandpa deploy`.

> Note: Grandpa's built-in `ftp()` helper only speaks FTP/FTPS (via
> `league/flysystem-ftp`), not SFTP. If your host only accepts SFTP and you
> need actual file transfer (not just running commands over SSH), drive
> `rsync`/`git pull` on the server through `ssh()->run()` instead of
> `ftp()->upload()`, for example:
>
> ```php
> ssh()->run('cd /var/www/app && git pull --ff-only && php artisan migrate --force');
> ```

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
