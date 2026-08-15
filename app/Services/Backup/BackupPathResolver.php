<?php

namespace App\Services\Backup;

use Illuminate\Support\Str;
use Morilog\Jalali\Jalalian;
use RuntimeException;

/**
 * تشخیص، اعتبارسنجی و ساخت مسیرهای بکاپ روی سیستم کاربر.
 *
 * این کلاس با در نظر گرفتن اجرای برنامه به‌صورت اپ دسکتاپ (NativePHP/Electron)
 * نوشته شده است: مسیر پیش‌فرض داخل «Documents» کاربر ساخته می‌شود و کاربر
 * می‌تواند هر دایرکتوری دلخواهی روی سیستم خودش انتخاب کند.
 */
class BackupPathResolver
{
    public function __construct(private readonly BackupSettingsService $settings)
    {
    }

    /* ------------------------------------------------------------------ */
    /* مسیرهای پیش‌فرض                                                    */
    /* ------------------------------------------------------------------ */

    public function defaultExportRoot(): string
    {
        $configured = $this->settings->get('export_root_path') ?: config('backup.paths.export_root');

        return $this->normalize($configured ?: $this->userDocumentsPath(config('backup.paths.folder_name', 'GameStore-Backups')));
    }

    public function defaultImportRoot(): string
    {
        $configured = $this->settings->get('import_root_path') ?: config('backup.paths.import_root');

        return $this->normalize($configured ?: $this->userDocumentsPath(config('backup.paths.inbox_name', 'GameStore-Restore')));
    }

    /** مسیر «Documents» کاربر بر اساس سیستم‌عامل. */
    public function userDocumentsPath(string $append = ''): string
    {
        $home = $this->userHomePath();

        $documents = is_dir($home . DIRECTORY_SEPARATOR . 'Documents')
            ? $home . DIRECTORY_SEPARATOR . 'Documents'
            : $home;

        return $this->normalize($append === '' ? $documents : $documents . DIRECTORY_SEPARATOR . $append);
    }

    public function userHomePath(): string
    {
        $candidates = [
            env('BACKUP_HOME_PATH'),
            getenv('USERPROFILE') ?: null,                                  // Windows
            (getenv('HOMEDRIVE') && getenv('HOMEPATH')) ? getenv('HOMEDRIVE') . getenv('HOMEPATH') : null,
            getenv('HOME') ?: null,                                         // macOS / Linux
        ];

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && is_dir($candidate)) {
                return rtrim($candidate, "\\/");
            }
        }

        return rtrim(storage_path('app'), "\\/");
    }

    /* ------------------------------------------------------------------ */
    /* اعتبارسنجی و ساخت                                                  */
    /* ------------------------------------------------------------------ */

    /**
     * مسیر ریشه‌ی خروجی را نهایی می‌کند: نرمال‌سازی، بررسی امنیت، ساخت و
     * بررسی قابل نوشتن بودن + فضای آزاد.
     */
    public function prepareExportRoot(?string $path = null): string
    {
        $root = $this->normalize($path ?: $this->defaultExportRoot());

        $this->assertSafePath($root);
        $this->ensureDirectory($root);
        $this->assertWritable($root);
        $this->assertFreeSpace($root);

        return $root;
    }

    /** مسیر ریشه‌ی ورودی (باید از قبل موجود و خوانا باشد). */
    public function prepareImportRoot(?string $path = null): string
    {
        $root = $this->normalize($path ?: $this->defaultImportRoot());

        $this->assertSafePath($root);

        if (! is_dir($root)) {
            throw new RuntimeException("مسیر ورودی یافت نشد: {$root}");
        }

        if (! is_readable($root)) {
            throw new RuntimeException("مسیر ورودی قابل خواندن نیست: {$root}");
        }

        return $root;
    }

    /**
     * ساخت دایرکتوری اختصاصی یک اجرا با نام‌گذاری مرتب و قابل‌مرتب‌سازی:
     *   GameStore_backup_2026-01-20_14-30-05_1404-10-30
     */
    public function makeRunDirectory(string $root, string $direction, ?string $label = null): string
    {
        $stamp  = now()->format('Y-m-d_H-i-s');
        $jalali = Jalalian::now()->format('Y-m-d');
        $app    = Str::slug((string) config('backup.app_name', 'GameStore'), '-');
        $suffix = $label ? '_' . Str::slug($label, '-') : '';

        $dir = $this->normalize($root . DIRECTORY_SEPARATOR . "{$app}_{$direction}_{$stamp}_{$jalali}{$suffix}");

        // در صورت تکرار (اجرای هم‌زمان) یک شمارنده اضافه می‌کنیم.
        $unique = $dir;
        $i      = 1;
        while (is_dir($unique)) {
            $unique = $dir . '_' . $i++;
        }

        $this->ensureDirectory($unique);

        return $unique;
    }

    public function ensureDirectory(string $path): string
    {
        if (! is_dir($path) && ! @mkdir($path, 0775, true) && ! is_dir($path)) {
            throw new RuntimeException("امکان ساخت دایرکتوری وجود ندارد: {$path}");
        }

        return $path;
    }

    public function assertWritable(string $path): void
    {
        if (! is_writable($path)) {
            throw new RuntimeException("دسترسی نوشتن روی این مسیر وجود ندارد: {$path}");
        }

        // تست واقعی نوشتن (روی درایوهای شبکه/USB مهم است)
        $probe = $path . DIRECTORY_SEPARATOR . '.backup_write_test';
        if (@file_put_contents($probe, 'ok') === false) {
            throw new RuntimeException("نوشتن آزمایشی در مسیر ناموفق بود: {$path}");
        }
        @unlink($probe);
    }

    public function assertFreeSpace(string $path, ?int $minMegabytes = null): void
    {
        $min  = $minMegabytes ?? (int) config('backup.runtime.min_free_space_mb', 200);
        $free = @disk_free_space($path);

        if ($free !== false && $free < $min * 1024 * 1024) {
            throw new RuntimeException(sprintf(
                'فضای آزاد کافی نیست (موجود: %s مگابایت، حداقل لازم: %d مگابایت).',
                number_format($free / 1048576, 1),
                $min
            ));
        }
    }

    /** جلوگیری از مسیرهای خطرناک/حساس و path traversal. */
    public function assertSafePath(string $path): void
    {
        if (trim($path) === '') {
            throw new RuntimeException('مسیر انتخاب‌شده معتبر نیست.');
        }

        if (Str::contains($path, ["\0", '..'])) {
            throw new RuntimeException('مسیر انتخاب‌شده مجاز نیست.');
        }

        if (! $this->isAbsolute($path)) {
            throw new RuntimeException('مسیر باید به‌صورت مطلق (absolute) وارد شود.');
        }

        $blocked = [
            'C:\\Windows', 'C:\\Program Files', 'C:\\Program Files (x86)',
            '/System', '/bin', '/sbin', '/usr/bin', '/etc', '/var/lib', '/boot',
        ];

        $lower = strtolower($this->normalize($path));
        foreach ($blocked as $item) {
            if (Str::startsWith($lower, strtolower($item))) {
                throw new RuntimeException('انتخاب دایرکتوری‌های سیستمی مجاز نیست.');
            }
        }

        // جلوگیری از نوشتن داخل خودِ کد برنامه (در اپ نصب‌شده ممکن است read-only باشد)
        if (Str::startsWith($lower, strtolower($this->normalize(base_path())))
            && ! Str::startsWith($lower, strtolower($this->normalize(storage_path())))) {
            throw new RuntimeException('نوشتن داخل پوشه‌ی نصب برنامه مجاز نیست؛ یک مسیر دیگر انتخاب کنید.');
        }
    }

    public function isAbsolute(string $path): bool
    {
        return (bool) preg_match('/^([a-zA-Z]:[\\\\\/]|\\\\\\\\|\/)/', $path);
    }

    public function normalize(string $path): string
    {
        $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($path));

        return rtrim($path, DIRECTORY_SEPARATOR) ?: DIRECTORY_SEPARATOR;
    }

    public function join(string ...$segments): string
    {
        $parts = array_filter(array_map(fn ($s) => trim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $s), DIRECTORY_SEPARATOR), $segments), fn ($s) => $s !== '');
        $joined = implode(DIRECTORY_SEPARATOR, $parts);

        return $this->isAbsolute($segments[0] ?? '') && DIRECTORY_SEPARATOR === '/' ? DIRECTORY_SEPARATOR . $joined : $joined;
    }

    /** مسیر نسبی یک فایل نسبت به ریشه‌ی بسته (برای مانیفست). */
    public function relative(string $root, string $absolute): string
    {
        $root     = $this->normalize($root) . DIRECTORY_SEPARATOR;
        $absolute = $this->normalize($absolute);

        return str_replace('\\', '/', Str::after($absolute, $root));
    }

    /** پاک‌سازی نام فایل/پوشه برای سازگاری با ویندوز. */
    public function sanitizeFileName(string $name): string
    {
        $name = preg_replace('/[\x00-\x1F<>:"|?*\\\\\/]+/u', '_', $name) ?? $name;
        $name = trim($name, " .\t\n\r\0\x0B");

        return $name === '' ? 'file' : Str::limit($name, 120, '');
    }
}
