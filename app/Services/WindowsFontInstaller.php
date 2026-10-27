<?php

namespace App\Services;

/**
 * نصب فونت وزیرمتن برای «کاربر جاری ویندوز» (بدون نیاز به دسترسی ادمین).
 *
 * چرا؟ فایل‌های اکسل نمی‌توانند فونت را داخل خودشان ببرند؛ اکسل فقط اسم فونت (Vazirmatn) را ذخیره
 * می‌کند و اگر فونت روی سیستم مشتری نصب نباشد، با فونت پیش‌فرض باز می‌شود. این کلاس هنگام اجرای برنامه،
 * فونت‌های همراه برنامه را (یک‌بار) در پوشهٔ فونت‌های کاربر کپی و در رجیستری ثبت می‌کند.
 */
class WindowsFontInstaller
{
    /** فایل فونت => نام ثبت در رجیستری (Regular و Bold برای اکسل کافی است) */
    private const FONTS = [
        'Vazirmatn-Regular.ttf' => 'Vazirmatn Regular (TrueType)',
        'Vazirmatn-Bold.ttf'    => 'Vazirmatn Bold (TrueType)',
    ];

    private const REG_KEY = 'HKCU\\Software\\Microsoft\\Windows NT\\CurrentVersion\\Fonts';

    public static function ensure(): void
    {
        if (PHP_OS_FAMILY !== 'Windows') {
            return;
        }

        try {
            $local = getenv('LOCALAPPDATA');
            if (!$local) {
                return;
            }

            $targetDir = $local . '\\Microsoft\\Windows\\Fonts';
            if (!is_dir($targetDir) && !@mkdir($targetDir, 0777, true) && !is_dir($targetDir)) {
                return;
            }

            $sourceDir = resource_path('fonts' . DIRECTORY_SEPARATOR . 'Vazirmatn');
            $installed = [];

            foreach (self::FONTS as $file => $regName) {
                $src = $sourceDir . DIRECTORY_SEPARATOR . $file;
                $dst = $targetDir . '\\' . $file;

                if (!is_file($src)) {
                    continue;
                }
                if (is_file($dst) && filesize($dst) === filesize($src)) {
                    continue; // قبلاً نصب شده
                }
                if (!@copy($src, $dst)) {
                    continue;
                }

                self::run(['reg', 'add', self::REG_KEY, '/v', $regName, '/t', 'REG_SZ', '/d', $dst, '/f']);
                $installed[] = $dst;
            }

            if ($installed) {
                self::announce($installed);
            }
        } catch (\Throwable $e) {
            // نصب فونت نباید هیچ‌وقت جلوی اجرای برنامه را بگیرد
            logger()->warning('Font install skipped: ' . $e->getMessage());
        }
    }

    /** فونت را در همین نشست ویندوز هم فعال می‌کند تا نیازی به خروج/ورود دوباره نباشد. */
    private static function announce(array $paths): void
    {
        $list = implode(',', array_map(fn ($p) => "'" . str_replace("'", "''", $p) . "'", $paths));

        $script = <<<PS
Add-Type -Namespace Gs -Name Fnt -MemberDefinition '
[DllImport("gdi32.dll", CharSet=CharSet.Unicode)] public static extern int AddFontResource(string f);
[DllImport("user32.dll")] public static extern IntPtr SendMessageTimeout(IntPtr h, uint m, IntPtr w, IntPtr l, uint fl, uint t, out IntPtr r);
';
foreach (\$f in @($list)) { [Gs.Fnt]::AddFontResource(\$f) | Out-Null }
\$r = [IntPtr]::Zero
[Gs.Fnt]::SendMessageTimeout([IntPtr]0xffff, 0x1D, [IntPtr]::Zero, [IntPtr]::Zero, 2, 1000, [ref]\$r) | Out-Null
PS;

        self::run(['powershell', '-NoProfile', '-NonInteractive', '-WindowStyle', 'Hidden', '-Command', $script]);
    }

    private static function run(array $cmd): void
    {
        $proc = @proc_open($cmd, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($proc)) {
            return;
        }
        foreach ($pipes as $p) {
            @stream_get_contents($p);
            @fclose($p);
        }
        proc_close($proc);
    }
}
