<?php

declare(strict_types=1);

namespace Modules\Licensing\Services;

/**
 * اجرای یک دستور artisan در پس‌زمینه، مستقل از درخواست وب.
 * دلیل: `php artisan serve` تک‌نخی است؛ اگر نصب پچ داخل همان درخواست انجام شود،
 * polling پیشرفت هم مسدود می‌شود. فرایند فرزند باید بعد از پایان درخواست زنده بماند
 * (Symfony Process در destructor فرزند را می‌کشد، پس از popen/exec استفاده می‌شود).
 */
final class BackgroundRunner
{
    /** @param list<string> $args */
    public static function artisan(array $args): bool
    {
        $parts = array_merge([self::php(), base_path('artisan')], $args);
        $cmd   = implode(' ', array_map('escapeshellarg', $parts));

        if (PHP_OS_FAMILY === 'Windows') {
            if (! function_exists('popen')) {
                return false;
            }
            $h = @popen('start /B "" ' . $cmd . ' > NUL 2>&1', 'r');
            if ($h === false) {
                return false;
            }
            pclose($h);

            return true;
        }

        if (! function_exists('exec')) {
            return false;
        }
        @exec($cmd . ' > /dev/null 2>&1 &');

        return true;
    }

    public static function php(): string
    {
        $configured = (string) config('licensing.patch.php_binary', '');
        if ($configured !== '') {
            return $configured;
        }

        $bin  = PHP_BINARY;
        $base = strtolower(basename($bin));
        if ($bin === '' || str_contains($base, 'fpm') || str_contains($base, 'httpd') || str_contains($base, 'apache')) {
            return 'php';
        }

        return $bin;
    }
}
