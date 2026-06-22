# 👴 Grandpa — a Pure PHP Deploy & Task Scheduler

English | [فارسی](README.fa.md)

Grandpa is a lightweight, dependency-light PHP build, deploy, and task
scheduling tool for PHP projects. Think of it as a minimal alternative to
[Deployer](https://deployer.org/) or [Envoy](https://laravel.com/docs/envoy):
describe deploy and maintenance tasks once in a plain PHP file, then run them
from the command line, via Composer scripts, or on a cron schedule.

Use Grandpa to:

- 📤 **Deploy over FTP/FTPS** to shared hosting (cPanel, DirectAdmin) that has
  no SSH access, uploading only the files that changed since the last deploy.
- 🔐 **Deploy to a VPS over SSH**, running `composer install`, `artisan migrate`,
  cache warm-ups, or any other post-deploy command.
- ⏰ **Schedule recurring PHP tasks** (cache clearing, cleanup jobs, health
  checks) with a Laravel-style fluent API (`->daily()`, `->everyMinute()`,
  `->cron('* * * * *')`) driven by a single cron entry.
- ⚡ **Scaffold a deploy script automatically** for Vite/Webpack/Laravel Mix/
  Next.js/Angular/Vue projects with `grandpa init`.
- 💬 **Send Telegram notifications** from a task, e.g. to report deploy status.

## 📋 Table of contents

- [Installation](#installation)
- [Setup](#setup)
- [Writing tasks](#writing-tasks)
- [Running a deploy](#running-a-deploy)
- [CLI reference](#cli-reference)
- [Recipes](#recipes)
- [Scheduling tasks](#scheduling-tasks)

## 📦 Installation

Install Grandpa as a dev dependency in any PHP 8.1+ project with Composer:

```
composer require --dev mbpcoder/grandpa
```

This adds the `grandpa` binary to `vendor/bin/grandpa`, so you can run it as:

```
php vendor/bin/grandpa deploy
```

### Optional: a global `grandpa` command

To call `grandpa` directly (without `php` or a path prefix), install it
globally instead:

```
composer global require mbpcoder/grandpa
```

Make sure Composer's global `vendor/bin` directory (run `composer global
config bin-dir --absolute` to find it) is on your shell's `PATH`. Once it is,
every command in this README also works as plain `grandpa ...` instead of
`php bin/grandpa ...`.

### Without Composer: download the `.phar`

If you don't want to add Grandpa as a Composer dependency, download the
pre-built [`grandpa.phar`](https://raw.githubusercontent.com/mbpcoder/grandpa/claude/tender-davinci-rkpskn/grandpa.phar)
and drop it into your project — it bundles all of Grandpa's dependencies, so
it works standalone with just PHP:

```
curl -LO https://raw.githubusercontent.com/mbpcoder/grandpa/claude/tender-davinci-rkpskn/grandpa.phar
chmod +x grandpa.phar
php grandpa.phar deploy
```

#### Installing the `.phar` as a global `grandpa` command

**Linux / macOS**

Download it, make it executable, and move it onto your `PATH` (dropping the
`.phar` extension so it reads as a normal command):

```
curl -LO https://raw.githubusercontent.com/mbpcoder/grandpa/claude/tender-davinci-rkpskn/grandpa.phar
chmod +x grandpa.phar
sudo mv grandpa.phar /usr/local/bin/grandpa
```

Now you can run `grandpa deploy` from anywhere.

**Windows**

Download `grandpa.phar` into a folder that's on your `PATH` (e.g.
`C:\tools\grandpa\`), then create a `grandpa.bat` next to it so Windows knows
how to execute the phar through PHP:

```
@echo off
php "%~dp0grandpa.phar" %*
```

Save that as `C:\tools\grandpa\grandpa.bat`, add `C:\tools\grandpa` to your
`PATH` environment variable, and `grandpa deploy` will work from any
PowerShell or Command Prompt window.

### Using Grandpa in a Laravel project

Grandpa isn't Laravel-specific, but it's a natural fit for deploying one.
Either way works:

- **Composer (recommended for a repo you control):**
  `composer require --dev mbpcoder/grandpa`, then run it with
  `php vendor/bin/grandpa deploy`.
- **`.phar` (no Composer footprint):** download `grandpa.phar` into the
  project root (see above) and run `php grandpa.phar deploy`. Add
  `/grandpa.phar` to `.gitignore` if you don't want to commit the binary.

Then add a `deploy.php`/`runner.php` task that uploads the changed files and
runs Artisan commands afterwards, e.g. over SSH:

```php
<?php

task('deploy', function () {
    $revision = storage()->ftp()->get('.revision');

    storage()->ftp()->upload(git()->changedFiles($revision));
    storage()->ftp()->delete(git()->deletedFiles($revision));

    storage()->ftp()->put('.revision', git()->currentHead());

    ssh()->run('cd /var/www/app && composer install --no-dev && php artisan migrate --force');
    ssh()->run('cd /var/www/app && php artisan config:cache && php artisan optimize');

    say('Deployed');
});
```

If you're on shared hosting without SSH, swap the `ssh()->run(...)` calls for
an `http()->get(...)` call to a protected route that runs those Artisan
commands instead — see the
[shared hosting recipe](#shared-hosting-cpanel-directadmin-over-ftp-with-a-cache-clear-health-check-url)
below.

You can also let Grandpa run Laravel's own scheduler for you instead of (or
alongside) cron, by adding a task with `->everyMinute()` that runs
`php artisan schedule:run`, then triggering Grandpa itself once a minute —
see [Scheduling tasks](#-scheduling-tasks).

### Working on this repository directly

If you're hacking on Grandpa itself (this repo), install dependencies with:

```
composer install
```

and run the binary straight out of the repo with `php bin/grandpa ...`, as
used throughout the rest of this README.

## 🚀 Deploy

### Setup

1. Install Grandpa (see [Installation](#installation) above).
2. Copy `.env.example` to `.env` and fill in your credentials.

   ```
   GRANDPA_FTP_HOST=ftp.example.com
   GRANDPA_FTP_USERNAME=
   GRANDPA_FTP_PASSWORD=
   GRANDPA_FTP_PORT=21
   GRANDPA_FTP_PATH=/
   GRANDPA_FTP_PASSIVE=true

   GRANDPA_SSH_HOST=example.com
   GRANDPA_SSH_USERNAME=
   GRANDPA_SSH_PASSWORD=
   GRANDPA_SSH_PRIVATE_KEY=
   GRANDPA_SSH_PORT=22
   GRANDPA_PLINK_PATH=

   GRANDPA_TELEGRAM_BOT_TOKEN=
   GRANDPA_TELEGRAM_BASE_URL=https://api.telegram.org
   GRANDPA_TELEGRAM_CHAT_ID=
   GRANDPA_TELEGRAM_TOPIC_ID=
   ```

   - `GRANDPA_FTP_PATH` is the remote base directory everything is uploaded relative to.
   - `GRANDPA_SSH_HOST` is only used for running post-deploy commands over SSH (FTP can't run commands). Set `GRANDPA_SSH_USERNAME`/`GRANDPA_SSH_PRIVATE_KEY` to use a key, or `GRANDPA_SSH_USERNAME`/`GRANDPA_SSH_PASSWORD` to authenticate with a password instead (password auth requires `sshpass` to be installed on Linux/macOS, or `GRANDPA_PLINK_PATH` set to a `plink.exe` path on Windows).
   - `GRANDPA_TELEGRAM_*` vars are only needed if a task calls `telegram()` to send notifications.
   - Optionally require `vlucas/phpdotenv` (`composer require vlucas/phpdotenv`) for fuller `.env` parsing; Grandpa falls back to a built-in parser if it's not installed.

   #### Storage backends

   `storage()` gives access to several interchangeable file storage backends, each
   with the same `upload()`/`delete()`/`uploadDir()`/`purge()` API as `Storage`.
   Pick whichever applies and configure only that one — unused backends are never
   connected to.

   | Backend | Helper | Env vars |
   | --- | --- | --- |
   | FTP/FTPS | `storage()->ftp()` | `GRANDPA_FTP_HOST`, `GRANDPA_FTP_USERNAME`, `GRANDPA_FTP_PASSWORD`, `GRANDPA_FTP_PORT`, `GRANDPA_FTP_PATH`, `GRANDPA_FTP_PASSIVE` |
   | SFTP | `storage()->sftp()` | `GRANDPA_SFTP_HOST`, `GRANDPA_SFTP_USERNAME`, `GRANDPA_SFTP_PASSWORD` or `GRANDPA_SFTP_PRIVATE_KEY`/`GRANDPA_SFTP_PASSPHRASE`, `GRANDPA_SFTP_PORT`, `GRANDPA_SFTP_PATH` |
   | S3 / S3-compatible | `storage()->s3()` | `GRANDPA_S3_KEY`, `GRANDPA_S3_SECRET`, `GRANDPA_S3_REGION`, `GRANDPA_S3_BUCKET`, `GRANDPA_S3_PATH`, `GRANDPA_S3_ENDPOINT` (set for MinIO/DigitalOcean Spaces/etc.), `GRANDPA_S3_USE_PATH_STYLE` |
   | GitLab repository | `storage()->gitlab()` | `GRANDPA_GITLAB_PROJECT_ID`, `GRANDPA_GITLAB_BRANCH`, `GRANDPA_GITLAB_BASE_URL`, `GRANDPA_GITLAB_TOKEN`, `GRANDPA_GITLAB_PATH` |
   | Google Drive | `storage()->googleDrive()` | `GRANDPA_GOOGLE_DRIVE_CLIENT_ID`, `GRANDPA_GOOGLE_DRIVE_CLIENT_SECRET`, `GRANDPA_GOOGLE_DRIVE_REFRESH_TOKEN`, `GRANDPA_GOOGLE_DRIVE_PATH` |

   > [!WARNING]
   > Never commit `.env` — it holds your FTP, SSH, and Telegram credentials.

3. Copy `runner.php.example` to `runner.php` in your project root and adjust it
   to your needs.

   Alternatively, generate a `runner.php` automatically:

   ```
   php bin/grandpa init
   ```

   `init` looks at the current directory for `composer.json`, `package.json`, and a
   `.git` folder to figure out what kind of project it is, picks up the build tool
   from `package.json` dependencies (Vite, Webpack, Laravel Mix, Create React App,
   Next.js, Angular CLI, Vue CLI), and writes a `runner.php` with a `deploy` task
   tailored to what it found: a build step (`npm`/`yarn`/`pnpm run build`) if a
   `build` script exists, incremental git-based upload if it's a git repo, and
   `storage()->ftp()->purge()`/`uploadDir()` of the detected build output folder.

   Pass `-i` or `--interactive` to also be prompted for FTP/SSH credentials, which
   get written to `.env` (an existing `.env` is left untouched):

   ```
   php bin/grandpa init -i
   ```

### Writing tasks

`runner.php` (or `deploy.php`) is plain PHP. Register tasks with `task()` and use
the helper functions below inside the task callback:

```php
<?php

task('deploy', function () {
    $revision = storage()->ftp()->get('.revision');   // last deployed commit hash, or null on first deploy

    storage()->ftp()->upload(git()->changedFiles($revision));    // upload added/modified files
    storage()->ftp()->delete(git()->deletedFiles($revision));    // remove files deleted from git

    storage()->ftp()->purge('public/build');             // wipe a remote directory
    storage()->ftp()->uploadDir('public/build');        // push a whole local directory (e.g. built assets)

    storage()->ftp()->put('.revision', git()->currentHead());    // record the deployed commit on the server

    ssh()->run('cd /var/www/app && php artisan migrate --force && php artisan optimize');

    say('Deployed');
});
```

How the incremental upload works:

- You read a `.revision` file from wherever you stored it (the remote server, a database, etc.)
  containing the last deployed commit hash, and pass it explicitly to `git()`.
- `git()->changedFiles($revision)` / `git()->deletedFiles($revision)` diff the current `HEAD` against
  that revision using `git diff --name-only --diff-filter=ACMR|D <revision>..HEAD`.
- If `$revision` is `null` (e.g. no `.revision` file exists yet), every tracked file (`git ls-files`)
  is treated as added — useful for a first deploy.
- After uploading, write `git()->currentHead()` back to your `.revision` file so the next deploy can
  diff against it.

Available helpers: `task()`, `git()`, `storage()`, `ssh()`, `http()`, `telegram()`, `say()`, `env()`.

`http()` returns a small Guzzle-backed client for hitting URLs during a
deploy (e.g. cache-clear/health-check routes): `http()->get($url)`,
`http()->post($url, ['json' => [...]])`, or `http()->request($method, $url, $options)`.
The `$options` array is passed straight through to Guzzle, so any Guzzle
request option works. Requests that fail throw a `RuntimeException`.

Chain `->retry($times, $delayMs)` before a request to retry on failure, e.g.
`http()->retry(3, 500)->get($url)` attempts the request up to 3 times,
waiting 500ms between attempts.

`telegram()` sends a message via the Telegram Bot API, using
`GRANDPA_TELEGRAM_BOT_TOKEN`/`GRANDPA_TELEGRAM_CHAT_ID` from `.env` as
defaults: `telegram()->message('Deployed!')->send()`. Override the chat or
topic per call with `->to($chatId)` / `->topic($topicId)`.

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

If a task has a schedule attached (e.g. `->daily()`), running it by name only
runs it when the schedule is currently due; otherwise grandpa prints a message
instead of running it:

```
$ php bin/grandpa deploy
Schedule for task "deploy" hasn't been met yet. Use --force/-f to run it anyway.
```

Pass `--force` or `-f` to run it regardless of its schedule:

```
php bin/grandpa deploy --force
```

Tasks with no schedule always run immediately.

You can also point at a task file explicitly, similar to how PHPUnit takes a test
file:

```
php bin/grandpa runner.php deploy
php bin/grandpa --file=runner.php deploy
```

Running it without a task name runs every task that has no schedule, or whose
schedule is currently due (tasks with an unmet schedule are skipped silently):

```
php bin/grandpa runner.php
```

## ⌨️ CLI reference

| Command | What it does |
| --- | --- |
| `grandpa init` | Generate a `runner.php` for the current project by detecting `composer.json`/`package.json`/`.git` and the JS build tool in use. |
| `grandpa init -i` / `grandpa init --interactive` | Same as `init`, plus prompts for FTP/SSH credentials and writes them to `.env`. |
| `grandpa <task>` | Run `<task>` from the auto-discovered `runner.php` (or `deploy.php`) in the current directory. Skips the task with a message if it has an unmet schedule. |
| `grandpa <task> --force` / `grandpa <task> -f` | Run `<task>` immediately, ignoring its schedule. |
| `grandpa <file.php>` | Run every eligible task (no schedule, or schedule currently due) from `<file.php>` instead of auto-discovering it. |
| `grandpa <file.php> <task>` | Run `<task>` from `<file.php>` instead of the auto-discovered file. |
| `grandpa --file=<file.php> [task]` | Same as the positional file argument above; useful when `<file.php>` doesn't look like a path Grandpa would auto-detect. |
| `grandpa <task> --dir=<path>` / `grandpa <task> -d=<path>` | Run `<task>` against the project in `<path>` instead of the current directory: `runner.php`/`deploy.php`/`.env` are looked up there, and the process `chdir()`s into it before running, so git/FTP paths resolve relative to that project. |
| `grandpa schedule:run` | Run every task whose schedule is currently due. Intended to be triggered once a minute by a single cron entry. |

Flags can be combined, e.g. `grandpa --file=runner.php deploy --force` runs
the `deploy` task from `runner.php` even if its schedule isn't due yet.

### Running tasks against another directory

By default Grandpa looks for `runner.php`/`deploy.php`/`.env` in, and
git/FTP-relative paths resolve against, the current working directory. Pass
`--dir=<path>` (or `-d=<path>`, relative or absolute) to point Grandpa at a
different project directory instead — handy for keeping one general
"grandpa" project with tasks for several of your other projects:

```
php bin/grandpa deploy --dir=/path/to/some-other-project
php bin/grandpa --dir=../another-project deploy --force
```

### 🍳 Recipes

A few common deploy scenarios, ready to copy into `deploy.php`/`runner.php`.

#### Shared hosting (cPanel / DirectAdmin) over FTP, with a cache-clear/health-check URL

Most cPanel/DirectAdmin hosts only expose FTP/FTPS, not SSH. Upload the changed
files, then hit a URL on the site itself (e.g. a route that clears cache or
warms it up) to finish the deploy:

```php
<?php

task('deploy', function () {
    $revision = storage()->ftp()->get('.revision');

    storage()->ftp()->upload(git()->changedFiles($revision));
    storage()->ftp()->delete(git()->deletedFiles($revision));

    storage()->ftp()->put('.revision', git()->currentHead());

    // Hit a route on the live site to clear cache / warm up / health-check.
    $response = http()->get('https://example.com/__deploy/clear-cache');

    say('Deployed and cache cleared: ' . $response);
});
```

> [!NOTE]
> `storage()->ftp()` talks to a plain FTP/FTPS server, which is what most shared hosts
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
    $revision = storage()->ftp()->get('.revision');

    storage()->ftp()->upload(git()->changedFiles($revision));
    storage()->ftp()->delete(git()->deletedFiles($revision));

    storage()->ftp()->purge('public/build');
    storage()->ftp()->uploadDir('public/build');

    storage()->ftp()->put('.revision', git()->currentHead());

    ssh()->run('cd /var/www/app && composer install --no-dev && php artisan migrate --force');
    ssh()->run('cd /var/www/app && php artisan optimize:clear && php artisan optimize');

    say('Deployed');
});
```

`ssh()->run()` shells out to the local `ssh` binary using `GRANDPA_SSH_HOST`,
`GRANDPA_SSH_USERNAME` and `GRANDPA_SSH_PORT`. By default it relies on your
SSH key/agent already being set up — set up an SSH key with the host
beforehand (`ssh-copy-id user@example.com`) and make sure
`ssh user@example.com` works without a prompt before running `grandpa deploy`,
or point `GRANDPA_SSH_PRIVATE_KEY` at a specific key file. Alternatively, set
`GRANDPA_SSH_PASSWORD` to authenticate with a password (this shells out to
`sshpass`, which must be installed separately). On Windows, where `sshpass`
isn't available, set `GRANDPA_PLINK_PATH` to the path of PuTTY's `plink.exe`
and password auth will be run through `plink` instead.

> [!NOTE]
> If your host only accepts SFTP, use `storage()->sftp()` instead of
> `storage()->ftp()` — same `upload()`/`delete()`/`uploadDir()` API, configured
> via `GRANDPA_SFTP_*` env vars (see below).

#### Notifying a Telegram chat after a deploy

Report success or failure to a Telegram chat by wrapping the deploy in a
try/catch and calling `telegram()`:

```php
<?php

task('deploy', function () {
    try {
        $revision = storage()->ftp()->get('.revision');

        storage()->ftp()->upload(git()->changedFiles($revision));
        storage()->ftp()->delete(git()->deletedFiles($revision));

        storage()->ftp()->put('.revision', git()->currentHead());

        telegram()->message('Deploy succeeded for ' . git()->currentBranch())->send();
    } catch (\Throwable $e) {
        telegram()->message('Deploy failed: ' . $e->getMessage())->send();

        throw $e;
    }
});
```

#### Updating every git repository under a base directory

If you keep several projects checked out side by side, walk one level of
subdirectories with `subDirectories()`, skip anything that isn't a git
repository with `git()->isRepository()`, and report the branch plus the
files `git pull` changed for each:

```php
<?php

task('git:update-all', function () {
    foreach (subDirectories('/var/www/projects') as $dir) {
        if (!git()->isRepository($dir)) {
            continue;
        }

        say($dir);
        say('  branch: ' . git()->currentBranch($dir));

        foreach (git()->pull($dir) as $line) {
            say('  ' . $line);
        }
    }
});
```

`subDirectories()`, `git()->isRepository()`, `git()->currentBranch()` and
`git()->pull()` are plain helpers you can recombine for similar scripts
(e.g. filtering by branch name, or only reporting repos with changes)
instead of a single fixed command.

## ⏰ Scheduling tasks

`task()` returns the `Task` instance, so you can chain Laravel-style schedule helpers
onto it. Scheduled tasks invoked by name (`grandpa <task>`) only run when their
schedule is currently due — pass `--force`/`-f` to run them anyway. The
`schedule:run` command checks every registered task and runs the ones that are
due, which is what you want behind a single cron entry:

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

## 🔁 Repeating and retrying tasks

Chain `->repeat($times, $intervalMs)` onto a task to run it `$times` times in
total, waiting `$intervalMs` between each run — every run happens regardless
of the previous run's outcome:

```php
task('ping-health-check', function () {
    http()->get('https://example.com/health');
})->repeat(5, 10_000); // run 5 times, 10s apart
```

Chain `->retry($times, $delayMs)` instead to retry a single run on failure:
return `TaskStatus::Error` from the callback (or throw) and the task is
re-run up to `$times` attempts, waiting `$delayMs` between attempts, stopping
early on the first success. Return `TaskStatus::Success` or nothing to mark
the run as successful immediately.

```php
task('flaky-deploy', function () {
    return deploy() ? TaskStatus::Success : TaskStatus::Error;
})->retry(3, 500);
```

`retry()` and `repeat()` can be combined: each of the `repeat()` runs gets its
own full set of `retry()` attempts.

### Running repeats in parallel

Chain `->asParallel($maxConcurrent)` after `->repeat()` to run the repeats
concurrently instead of one after another. Each repeat runs as its own `php`
process (works the same on Linux, macOS, and Windows), capped at
`$maxConcurrent` running at once — pass `0` (the default) to run them all at
once:

```php
task('warm-caches', function () {
    http()->get('https://example.com/cache-warm');
})->repeat(10)->asParallel(5); // 10 runs total, 5 at a time
```

A `repeat($times, $intervalMs)` interval is ignored (with a warning) once
`asParallel()` is added, since there's no "wait between runs" when they're
firing concurrently. Failures are aggregated the same way as sequential
`repeat()`: if any of the parallel runs fail, the task throws after all of
them have finished, reporting how many failed.

Run the scheduler once via:

```
php bin/grandpa schedule:run
```

or `composer schedule`. Like Laravel, point a single cron entry at this command and
let it run every minute on the server; Grandpa figures out which tasks are due:

```
* * * * * cd /path/to/project && php bin/grandpa schedule:run >> /dev/null 2>&1
```
