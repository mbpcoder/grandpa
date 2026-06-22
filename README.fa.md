<div dir="rtl">

# 👴 Grandpa — یک ابزار ساده‌ی PHP برای دیپلوی و زمان‌بندی وظایف

[English](README.md) | فارسی

Grandpa یک ابزار سبک و کم‌وابستگی PHP برای بیلد، دیپلوی و زمان‌بندی وظایف
پروژه‌های PHP است. آن را می‌توان جایگزینی مینیمال برای
[Deployer](https://deployer.org/) یا [Envoy](https://laravel.com/docs/envoy)
دانست: وظایف دیپلوی و نگه‌داری را یک‌بار در یک فایل PHP ساده تعریف می‌کنید،
سپس آن‌ها را از طریق خط فرمان، اسکریپت‌های Composer یا یک زمان‌بندی cron اجرا
می‌کنید.

از Grandpa برای این کارها استفاده کنید:

- 📤 **دیپلوی روی FTP/FTPS** به هاست‌های اشتراکی (cPanel، DirectAdmin) که
  دسترسی SSH ندارند، با آپلود فقط فایل‌هایی که از آخرین دیپلوی تغییر کرده‌اند.
- 🔐 **دیپلوی به یک VPS از طریق SSH**، با اجرای `composer install`،
  `artisan migrate`، گرم کردن کش، یا هر دستور دیگری پس از دیپلوی.
- ⏰ **زمان‌بندی وظایف تکرارشونده‌ی PHP** (پاک‌سازی کش، کارهای نظافتی، بررسی
  سلامت) با یک API روان به سبک Laravel (`->daily()`، `->everyMinute()`،
  `->cron('* * * * *')`) که با یک ورودی cron واحد اجرا می‌شود.
- ⚡ **ساخت خودکار اسکریپت دیپلوی** برای پروژه‌های Vite/Webpack/Laravel
  Mix/Next.js/Angular/Vue با `grandpa init`.
- 💬 **ارسال اعلان تلگرام** از داخل یک وظیفه، مثلاً برای گزارش وضعیت دیپلوی.

## 📋 فهرست مطالب

- [نصب](#نصب)
- [راه‌اندازی](#راه‌اندازی)
- [نوشتن وظایف](#نوشتن-وظایف)
- [اجرای یک دیپلوی](#اجرای-یک-دیپلوی)
- [مرجع خط فرمان](#مرجع-خط-فرمان)
- [دستورالعمل‌ها](#دستورالعمل‌ها)
- [زمان‌بندی وظایف](#زمان‌بندی-وظایف)

## 📦 نصب

Grandpa را به‌عنوان یک وابستگی توسعه در هر پروژه‌ی PHP 8.1+ با Composer نصب
کنید:

```
composer require --dev mbpcoder/grandpa
```

این کار فایل اجرایی `grandpa` را در `vendor/bin/grandpa` قرار می‌دهد، پس
می‌توانید آن را این‌گونه اجرا کنید:

```
php vendor/bin/grandpa deploy
```

### اختیاری: یک دستور سراسری `grandpa`

برای فراخوانی `grandpa` به‌طور مستقیم (بدون `php` یا پیشوند مسیر)، آن را
به‌صورت سراسری نصب کنید:

```
composer global require mbpcoder/grandpa
```

مطمئن شوید مسیر سراسری `vendor/bin` کامپوزر (با اجرای `composer global
config bin-dir --absolute` آن را پیدا کنید) در `PATH` شِل شما قرار دارد. پس
از آن، هر دستور موجود در این README نیز به‌صورت `grandpa ...` ساده، به‌جای
`php bin/grandpa ...`، کار می‌کند.

### کار مستقیم روی این مخزن

اگر روی خود Grandpa (این مخزن) کار می‌کنید، وابستگی‌ها را با این دستور نصب
کنید:

```
composer install
```

و فایل اجرایی را مستقیماً از داخل مخزن با `php bin/grandpa ...` اجرا کنید،
همان‌طور که در ادامه‌ی این README استفاده شده است.

## 🚀 دیپلوی

### راه‌اندازی

1. Grandpa را نصب کنید (به بخش [نصب](#نصب) در بالا مراجعه کنید).
2. فایل `.env.example` را به `.env` کپی کرده و اطلاعات اعتباری خود را وارد
   کنید.

   ```
   DEPLOY_FTP_HOST=ftp.example.com
   DEPLOY_FTP_USERNAME=
   DEPLOY_FTP_PASSWORD=
   DEPLOY_FTP_PORT=21
   DEPLOY_FTP_PATH=/
   DEPLOY_FTP_PASSIVE=true

   DEPLOY_SSH_HOST=user@example.com

   GRANDPA_TELEGRAM_BOT_TOKEN=
   GRANDPA_TELEGRAM_BASE_URL=https://api.telegram.org
   GRANDPA_TELEGRAM_CHAT_ID=
   GRANDPA_TELEGRAM_TOPIC_ID=
   ```

   - `DEPLOY_FTP_PATH` پوشه‌ی پایه‌ی ریموت است که همه‌چیز نسبت به آن آپلود
     می‌شود.
   - `DEPLOY_SSH_HOST` فقط برای اجرای دستورات پس از دیپلوی از طریق SSH استفاده
     می‌شود (FTP نمی‌تواند دستور اجرا کند).
   - متغیرهای `GRANDPA_TELEGRAM_*` فقط زمانی لازم‌اند که یک وظیفه `telegram()`
     را برای ارسال اعلان فراخوانی کند.
   - به‌صورت اختیاری `vlucas/phpdotenv` را نصب کنید
     (`composer require vlucas/phpdotenv`) برای پارس کامل‌تر `.env`؛ در صورت
     عدم نصب، Grandpa از یک پارسر داخلی ساده استفاده می‌کند.

   > [!WARNING]
   > هرگز `.env` را کامیت نکنید — این فایل اطلاعات اعتباری FTP، SSH و تلگرام
   > شما را در خود دارد.

3. فایل `runner.php.example` را به `runner.php` در ریشه‌ی پروژه‌ی خود کپی
   کرده و آن را متناسب با نیازتان تنظیم کنید.

   به‌جای آن، می‌توانید یک `runner.php` را به‌صورت خودکار بسازید:

   ```
   php bin/grandpa init
   ```

   `init` پوشه‌ی فعلی را برای `composer.json`، `package.json` و یک پوشه‌ی
   `.git` بررسی می‌کند تا نوع پروژه را تشخیص دهد، ابزار بیلد را از
   وابستگی‌های `package.json` پیدا می‌کند (Vite، Webpack، Laravel Mix، Create
   React App، Next.js، Angular CLI، Vue CLI)، و یک `runner.php` با وظیفه‌ی
   `deploy` متناسب با آنچه پیدا کرده می‌نویسد: یک مرحله‌ی بیلد
   (`npm`/`yarn`/`pnpm run build`) در صورت وجود اسکریپت `build`، آپلود
   تدریجی مبتنی بر git در صورت بودن یک مخزن گیت، و
   `ftp()->purge()`/`uploadDir()` برای پوشه‌ی خروجی بیلد شناسایی‌شده.

   پرچم `-i` یا `--interactive` را برای دریافت اطلاعات اعتباری FTP/SSH از طریق
   پرامپت اضافه کنید که در `.env` نوشته می‌شوند (یک `.env` موجود دست‌نخورده
   باقی می‌ماند):

   ```
   php bin/grandpa init -i
   ```

### نوشتن وظایف

`runner.php` (یا `deploy.php`) یک فایل PHP ساده است. وظایف را با `task()` ثبت
کنید و از توابع کمکی زیر در درون callback وظیفه استفاده کنید:

```php
<?php

task('deploy', function () {
    $files = git()->changedFiles();          // فایل‌های اضافه/تغییریافته از آخرین دیپلوی

    ftp()->upload($files);                    // فقط فایل‌های تغییریافته را آپلود کن
    ftp()->delete(git()->deletedFiles());    // فایل‌های حذف‌شده از git را حذف کن

    ftp()->purge('public/build');             // یک پوشه‌ی ریموت را پاک کن
    ftp()->uploadDir('public/build');        // یک پوشه‌ی محلی کامل را آپلود کن (مثلاً اسکریپت‌های بیلدشده)

    git()->saveRevision();                    // کامیت دیپلوی‌شده را روی سرور ثبت کن

    ssh()->run('cd /var/www/app && php artisan migrate --force && php artisan optimize');

    say('Deployed');
});
```

نحوه‌ی کار آپلود تدریجی:

- Grandpa یک فایل `.revision` روی سرور ریموت نگه می‌دارد که حاوی هش آخرین
  کامیت دیپلوی‌شده است.
- `git()->changedFiles()` / `git()->deletedFiles()` با استفاده از
  `git diff --name-only --diff-filter=ACMR|D <revision>..HEAD`، `HEAD` فعلی را
  با آن نسخه مقایسه می‌کنند.
- اگر هنوز `.revision` ریموتی وجود نداشته باشد، به‌عنوان اولین دیپلوی در نظر
  گرفته می‌شود و همه‌ی فایل‌های ردیابی‌شده (`git ls-files`) آپلود می‌شوند.
- `git()->saveRevision()` هش `HEAD` فعلی را در فایل `.revision` ریموت
  می‌نویسد.

توابع کمکی موجود: `task()`، `git()`، `ftp()`، `ssh()`، `http()`،
`telegram()`، `say()`، `env()`.

`http()` یک کلاینت کوچک مبتنی بر Guzzle برای فراخوانی URL‌ها در طول دیپلوی
(مثلاً مسیرهای پاک‌سازی کش/بررسی سلامت) برمی‌گرداند: `http()->get($url)`،
`http()->post($url, ['json' => [...]])`، یا
`http()->request($method, $url, $options)`. آرایه‌ی `$options` مستقیماً به
Guzzle ارسال می‌شود، پس هر گزینه‌ی درخواست Guzzle کار می‌کند. درخواست‌هایی که
شکست بخورند یک `RuntimeException` پرتاب می‌کنند.

پیش از یک درخواست `->retry($times, $delayMs)` را زنجیر کنید تا در صورت شکست
دوباره تلاش شود، مثلاً `http()->retry(3, 500)->get($url)` تا ۳ بار درخواست را
تلاش می‌کند و بین تلاش‌ها ۵۰۰ میلی‌ثانیه صبر می‌کند.

`telegram()` یک پیام را از طریق Telegram Bot API ارسال می‌کند و از
`GRANDPA_TELEGRAM_BOT_TOKEN`/`GRANDPA_TELEGRAM_CHAT_ID` در `.env` به‌عنوان
مقادیر پیش‌فرض استفاده می‌کند:
`telegram()->message('Deployed!')->send()`. چت یا موضوع را برای هر فراخوانی
با `->to($chatId)` / `->topic($topicId)` بازنویسی کنید.

### اجرای یک دیپلوی

```
php bin/grandpa deploy
```

یا، چون یک اسکریپت Composer متصل شده است:

```
composer deploy
```

هر دو، وظیفه‌ی `deploy` تعریف‌شده در فایل `runner.php`/`deploy.php` پیداشده در
پوشه‌ی کاری فعلی را اجرا می‌کنند.

اگر یک وظیفه زمان‌بندی پیوست‌شده داشته باشد (مثلاً `->daily()`)، اجرای آن با
نام فقط زمانی اجرا می‌شود که زمان‌بندی فعلاً سررسید شده باشد؛ در غیر این
صورت grandpa به‌جای اجرا یک پیام چاپ می‌کند:

```
$ php bin/grandpa deploy
Schedule for task "deploy" hasn't been met yet. Use --force/-f to run it anyway.
```

برای اجرای آن بدون توجه به زمان‌بندی، `--force` یا `-f` را پاس دهید:

```
php bin/grandpa deploy --force
```

وظایف بدون زمان‌بندی همیشه فوراً اجرا می‌شوند.

همچنین می‌توانید مستقیماً به یک فایل وظیفه اشاره کنید، مشابه نحوه‌ی پذیرش یک
فایل تست توسط PHPUnit:

```
php bin/grandpa runner.php deploy
php bin/grandpa --file=runner.php deploy
```

اجرای آن بدون نام وظیفه، هر وظیفه‌ای را که زمان‌بندی ندارد یا زمان‌بندی‌اش
فعلاً سررسید شده اجرا می‌کند (وظایفی با زمان‌بندی سررسیدنشده به‌طور خاموش رد
می‌شوند):

```
php bin/grandpa runner.php
```

## ⌨️ مرجع خط فرمان

| دستور | کاری که انجام می‌دهد |
| --- | --- |
| `grandpa init` | تولید یک `runner.php` برای پروژه‌ی فعلی با تشخیص `composer.json`/`package.json`/`.git` و ابزار بیلد JS مورد استفاده. |
| `grandpa init -i` / `grandpa init --interactive` | مشابه `init`، به‌همراه پرامپت برای اطلاعات اعتباری FTP/SSH و نوشتن آن‌ها در `.env`. |
| `grandpa <task>` | اجرای `<task>` از `runner.php` (یا `deploy.php`) شناسایی‌شده در پوشه‌ی فعلی. در صورت زمان‌بندی سررسیدنشده، وظیفه را با یک پیام رد می‌کند. |
| `grandpa <task> --force` / `grandpa <task> -f` | اجرای فوری `<task>`، بدون توجه به زمان‌بندی آن. |
| `grandpa <file.php>` | اجرای هر وظیفه‌ی واجد شرایط (بدون زمان‌بندی، یا زمان‌بندی فعلاً سررسیدشده) از `<file.php>` به‌جای شناسایی خودکار آن. |
| `grandpa <file.php> <task>` | اجرای `<task>` از `<file.php>` به‌جای فایل شناسایی‌شده خودکار. |
| `grandpa --file=<file.php> [task]` | مشابه آرگومان مکانی فایل بالا؛ زمانی مفید است که `<file.php>` شبیه مسیری نباشد که Grandpa به‌طور خودکار تشخیص دهد. |
| `grandpa <task> --dir=<path>` / `grandpa <task> -d=<path>` | اجرای `<task>` در پروژه‌ی `<path>` به‌جای پوشه‌ی فعلی: `runner.php`/`deploy.php`/`.env` در آن‌جا جست‌وجو می‌شوند، و فرآیند پیش از اجرا `chdir()` به آن می‌کند، پس مسیرهای git/FTP نسبت به آن پروژه حل می‌شوند. |
| `grandpa schedule:run` | اجرای هر وظیفه‌ای که زمان‌بندی‌اش فعلاً سررسید شده. برای فراخوانی هر یک دقیقه توسط یک ورودی cron واحد در نظر گرفته شده است. |

پرچم‌ها را می‌توان ترکیب کرد، مثلاً `grandpa --file=runner.php deploy
--force` وظیفه‌ی `deploy` را از `runner.php` اجرا می‌کند حتی اگر زمان‌بندی‌اش
سررسید نشده باشد.

### اجرای وظایف روی یک پوشه‌ی دیگر

به‌طور پیش‌فرض Grandpa به‌دنبال `runner.php`/`deploy.php`/`.env` در پوشه‌ی
کاری فعلی است، و مسیرهای نسبی git/FTP نسبت به آن حل می‌شوند. پرچم
`--dir=<path>` (یا `-d=<path>`، نسبی یا مطلق) را پاس دهید تا Grandpa را به
پوشه‌ی پروژه‌ی دیگری اشاره دهید — مفید برای نگه‌داری یک پروژه‌ی «grandpa»
عمومی با وظایفی برای چندین پروژه‌ی دیگر شما:

```
php bin/grandpa deploy --dir=/path/to/some-other-project
php bin/grandpa --dir=../another-project deploy --force
```

### 🍳 دستورالعمل‌ها

چند سناریوی رایج دیپلوی، آماده برای کپی در `deploy.php`/`runner.php`.

#### هاستینگ اشتراکی (cPanel / DirectAdmin) از طریق FTP، به‌همراه یک URL پاک‌سازی کش/بررسی سلامت

بیشتر هاست‌های cPanel/DirectAdmin فقط FTP/FTPS را در معرض قرار می‌دهند، نه
SSH. فایل‌های تغییریافته را آپلود کنید، سپس یک URL روی خود سایت (مثلاً
مسیری که کش را پاک یا گرم می‌کند) را فراخوانی کنید تا دیپلوی کامل شود:

```php
<?php

task('deploy', function () {
    $files = git()->changedFiles();

    ftp()->upload($files);
    ftp()->delete(git()->deletedFiles());

    git()->saveRevision();

    // یک مسیر روی سایت زنده را برای پاک‌سازی کش / گرم‌کردن / بررسی سلامت فراخوانی کن.
    $response = http()->get('https://example.com/__deploy/clear-cache');

    say('Deployed and cache cleared: ' . $response);
});
```

> [!NOTE]
> `ftp()` با یک سرور FTP/FTPS ساده صحبت می‌کند، که چیزی است که بیشتر
> هاست‌های اشتراکی فراهم می‌کنند. در این نوع هاست SSH وجود ندارد، پس هر
> مرحله‌ی «artisan migrate» یا «پاک‌سازی کش» باید از طریق یک نقطه‌ی پایانی
> HTTP که اپلیکیشن شما برای این هدف در معرض قرار می‌دهد انجام شود (آن را با
> یک توکن/هدر مخفی محافظت کنید).

#### VPS با دسترسی SSH، با اجرای دستورات پس از دیپلوی

اگر هاست شما SSH می‌دهد (یک VPS، یا cPanel/DirectAdmin با SSH فعال‌شده)،
طبق روال روی FTP آپلود کنید و سپس دستورات را مستقیماً روی سرور اجرا کنید —
نیازی به یک نقطه‌ی پایانی HTTP نیست:

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

`ssh()->run()` با استفاده از `DEPLOY_SSH_HOST` (مثلاً `deploy@example.com`)
به فایل اجرایی محلی `ssh` فراخوانی می‌شود، پس به کلید/ایجنت SSH شما که از
پیش راه‌اندازی شده وابسته است — هیچ فیلد رمز عبوری برای آن وجود ندارد. پیش
از اجرای `grandpa deploy`، یک کلید SSH با هاست راه‌اندازی کنید
(`ssh-copy-id deploy@example.com`) و مطمئن شوید
`ssh deploy@example.com` بدون پرامپت کار می‌کند.

> [!NOTE]
> کمک‌کننده‌ی داخلی `ftp()` در Grandpa فقط FTP/FTPS را صحبت می‌کند (از طریق
> `league/flysystem-ftp`)، نه SFTP. اگر هاست شما فقط SFTP را می‌پذیرد و به
> انتقال فایل واقعی (نه فقط اجرای دستورات روی SSH) نیاز دارید،
> `rsync`/`git pull` را روی سرور از طریق `ssh()->run()` به‌جای
> `ftp()->upload()` اجرا کنید، مثلاً:
>
> ```php
> ssh()->run('cd /var/www/app && git pull --ff-only && php artisan migrate --force');
> ```

#### اطلاع‌رسانی به یک چت تلگرام پس از دیپلوی

موفقیت یا شکست را با پوشاندن دیپلوی در یک try/catch و فراخوانی `telegram()`
به یک چت تلگرام گزارش دهید:

```php
<?php

task('deploy', function () {
    try {
        $files = git()->changedFiles();

        ftp()->upload($files);
        ftp()->delete(git()->deletedFiles());

        git()->saveRevision();

        telegram()->message('Deploy succeeded for ' . git()->currentBranch())->send();
    } catch (\Throwable $e) {
        telegram()->message('Deploy failed: ' . $e->getMessage())->send();

        throw $e;
    }
});
```

#### به‌روزرسانی هر مخزن گیت زیر یک پوشه‌ی پایه

اگر چند پروژه را در کنار هم چک‌اوت کرده‌اید، با `subDirectories()` یک سطح از
زیرپوشه‌ها را پیمایش کنید، هر چیزی که مخزن گیت نباشد را با
`git()->isRepository()` رد کنید، و شاخه به‌همراه فایل‌هایی که `git pull`
برای هر یک تغییر داده را گزارش دهید:

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

`subDirectories()`، `git()->isRepository()`، `git()->currentBranch()` و
`git()->pull()` کمک‌کننده‌های ساده‌ای هستند که می‌توانید برای اسکریپت‌های
مشابه دوباره ترکیب کنید (مثلاً فیلتر بر اساس نام شاخه، یا گزارش‌دهی فقط
مخازنی که تغییر دارند) به‌جای یک دستور ثابت واحد.

## ⏰ زمان‌بندی وظایف

`task()` نمونه‌ی `Task` را برمی‌گرداند، پس می‌توانید کمک‌کننده‌های زمان‌بندی
به سبک Laravel را به آن زنجیر کنید. وظایف زمان‌بندی‌شده‌ای که با نام
فراخوانی می‌شوند (`grandpa <task>`) فقط زمانی اجرا می‌شوند که زمان‌بندی‌شان
فعلاً سررسید شده باشد — `--force`/`-f` را پاس دهید تا به‌هر‌حال اجرا شوند.
دستور `schedule:run` هر وظیفه‌ی ثبت‌شده را بررسی می‌کند و آن‌هایی که سررسید
شده‌اند را اجرا می‌کند، که این چیزی است که پشت یک ورودی cron واحد می‌خواهید:

```php
<?php

task('deploy', function () {
    // ...
})->everyMinute();

task('clear-old-logs', function () {
    // ...
})->dailyAt('1:00');
```

کمک‌کننده‌های زمان‌بندی موجود: `everyMinute()`، `everyTwoMinutes()`،
`everyFiveMinutes()`، `everyTenMinutes()`، `everyFifteenMinutes()`،
`everyThirtyMinutes()`، `hourly()`، `hourlyAt(int $minute)`، `daily()`،
`dailyAt(string $time)`، `weekly()`،
`weeklyOn(int $dayOfWeek, string $time)`، `monthly()`،
`monthlyOn(int $dayOfMonth, string $time)`، `yearly()`، یا یک
`cron(string $expression)` خام برای هرچیز دلخواه (نحو استاندارد cron با ۵
فیلد).

زمان‌بند را یک‌بار با این دستور اجرا کنید:

```
php bin/grandpa schedule:run
```

یا `composer schedule`. مانند Laravel، یک ورودی cron واحد را به این دستور
اشاره دهید و بگذارید هر دقیقه روی سرور اجرا شود؛ Grandpa تشخیص می‌دهد کدام
وظایف سررسید شده‌اند:

```
* * * * * cd /path/to/project && php bin/grandpa schedule:run >> /dev/null 2>&1
```

</div>
