<?php

declare(strict_types=1);

namespace Modules\Licensing\Patching\Core;

/** ابزارهای کوچک بدون وابستگی به Laravel (قابل تست مستقل) */
final class Util
{
    public static function b64uEncode(string $raw): string
    {
        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    public static function b64uDecode(string $value): string
    {
        $p = strtr($value, '-_', '+/');
        if (strlen($p) % 4 !== 0) {
            $p .= str_repeat('=', 4 - strlen($p) % 4);
        }
        $d = base64_decode($p, true);

        return $d === false ? '' : $d;
    }

    /** دقیقاً معادل Base64Url::encodeJson در StoreServer */
    public static function encodeJson(array $data): string
    {
        return self::b64uEncode((string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /** major*1000000 + minor*1000 + patch (هم‌راستا با SemVer::toCode سرور) */
    public static function semCode(string $version): int
    {
        if (preg_match('/^(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/', $version, $m) !== 1) {
            return 0;
        }

        return (int) $m[1] * 1000000 + (int) $m[2] * 1000 + (int) $m[3];
    }

    /** از هر رشته‌ای مثل "v1.2.3-beta" فقط x.y.z معتبر را بیرون می‌کشد، وگرنه null */
    public static function cleanVersion(?string $raw): ?string
    {
        if ($raw !== null && preg_match('/(\d{1,3})\.(\d{1,3})\.(\d{1,3})/', $raw, $m) === 1) {
            return "{$m[1]}.{$m[2]}.{$m[3]}";
        }

        return null;
    }

    public static function canonical(mixed $v): mixed
    {
        if (is_array($v)) {
            $isList = array_is_list($v);
            $out = array_map([self::class, 'canonical'], $v);
            if (! $isList) {
                ksort($out);
            }

            return $out;
        }

        return $v;
    }

    /** مقایسه‌ی عمیق، مستقل از ترتیب کلیدها */
    public static function sameJson(mixed $a, mixed $b): bool
    {
        return json_encode(self::canonical($a)) === json_encode(self::canonical($b));
    }

    /** اگر مسیر ناامن باشد پیام خطا، وگرنه null */
    public static function unsafePath(string $path): ?string
    {
        $n = str_replace('\\', '/', $path);
        if ($n === '' || $n[0] === '/' || preg_match('/^[a-zA-Z]:/', $n) === 1) {
            return "مسیر مطلق: {$path}";
        }
        foreach (explode('/', $n) as $seg) {
            if ($seg === '..' || $seg === '.' || $seg === '') {
                return "مسیر نامعتبر: {$path}";
            }
        }
        if (str_contains($n, "\0") || str_contains($n, ':')) {
            return "نویسه‌ی ممنوع در مسیر: {$path}";
        }

        return null;
    }

    public static function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($it as $f) {
            $f->isDir() && ! $f->isLink() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($dir);
    }
}
