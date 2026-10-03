<?php

declare(strict_types=1);

namespace Modules\Licensing\Patching\Core;

/**
 * بررسی ایستای اسکریپت SQL پچ پیش از اجرا.
 *  - forbidden  : همیشه رد می‌شود (کنترل تراکنش/PRAGMA/ATTACH و ...)
 *  - destructive: فقط وقتی مجاز است که manifest امضاشده برای همان اسکریپت destructive=true بگذارد
 * این lint است نه sandbox؛ لایه‌ی اصلی محافظت: بکاپ + تراکنش + مقایسه‌ی تعداد ردیف‌ها.
 */
final class SqlGuard
{
    /** @return array{forbidden: string[], destructive: string[]} */
    public static function inspect(string $sql): array
    {
        $clean = self::strip($sql);
        $forbidden = [];
        $destructive = [];

        $forbiddenRules = [
            '/\bATTACH\b/i'                           => 'ATTACH',
            '/\bDETACH\b/i'                           => 'DETACH',
            '/\bPRAGMA\b/i'                           => 'PRAGMA (توسط کلاینت مدیریت می‌شود)',
            '/(^|;)\s*BEGIN\b/i'                      => 'BEGIN (کنترل تراکنش با کلاینت است)',
            '/(^|;)\s*(COMMIT|ROLLBACK|SAVEPOINT|RELEASE)\b/i' => 'COMMIT/ROLLBACK/SAVEPOINT',
            '/(^|;)\s*END\s+TRANSACTION\b/i'          => 'END TRANSACTION',
            '/(^|;)\s*VACUUM\b/i'                     => 'VACUUM (داخل تراکنش ممکن نیست)',
            '/\bload_extension\b/i'                   => 'load_extension',
        ];
        foreach ($forbiddenRules as $re => $label) {
            if (preg_match($re, $clean)) {
                $forbidden[] = $label;
            }
        }

        // عبارت‌های قیدی/تریگری که UPDATE/DELETE هستند ولی تغییر داده نیستند را حذف کن
        $n = preg_replace('/\b(ON|AFTER|BEFORE|OF)\s+(UPDATE|DELETE)\b/i', ' ', $clean);

        $destructiveRules = [
            '/\bDROP\s+TABLE\b/i'                          => 'DROP TABLE',
            '/\bDROP\s+COLUMN\b/i'                         => 'DROP COLUMN',
            '/\bDELETE\s+FROM\b/i'                         => 'DELETE FROM',
            '/\bTRUNCATE\b/i'                              => 'TRUNCATE',
            '/\bUPDATE\s+(OR\s+\w+\s+)?\S+\s+SET\b/i'      => 'UPDATE ... SET',
            '/\bDO\s+UPDATE\b/i'                           => 'UPSERT DO UPDATE',
            '/\bREPLACE\s+INTO\b/i'                        => 'REPLACE INTO',
            '/\bINSERT\s+OR\s+REPLACE\b/i'                 => 'INSERT OR REPLACE',
        ];
        foreach ($destructiveRules as $re => $label) {
            if (preg_match($re, $n)) {
                $destructive[] = $label;
            }
        }

        return ['forbidden' => $forbidden, 'destructive' => $destructive];
    }

    /** حذف کامنت‌ها و محتوای رشته‌ها تا کلیدواژه‌های داخل متن باعث تشخیص اشتباه نشوند */
    private static function strip(string $sql): string
    {
        $out = '';
        $len = strlen($sql);
        $i = 0;
        while ($i < $len) {
            $c = $sql[$i];
            $two = substr($sql, $i, 2);
            if ($two === '--') {
                $nl = strpos($sql, "\n", $i);
                $i = $nl === false ? $len : $nl;
                continue;
            }
            if ($two === '/*') {
                $end = strpos($sql, '*/', $i + 2);
                $i = $end === false ? $len : $end + 2;
                $out .= ' ';
                continue;
            }
            if ($c === "'" || $c === '"' || $c === '`') {
                $q = $c;
                $i++;
                while ($i < $len) {
                    if ($sql[$i] === $q) {
                        if (($sql[$i + 1] ?? '') === $q) { // escape دوتایی
                            $i += 2;
                            continue;
                        }
                        break;
                    }
                    $i++;
                }
                $i++;
                $out .= $q === "'" ? "''" : 'ident';
                continue;
            }
            $out .= $c;
            $i++;
        }
        return preg_replace('/\s+/', ' ', $out);
    }
}
