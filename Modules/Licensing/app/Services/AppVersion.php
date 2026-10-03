<?php

declare(strict_types=1);

namespace Modules\Licensing\Services;

use Modules\Licensing\Models\LicensePatch;
use Modules\Licensing\Patching\Core\Util;
use Throwable;

/**
 * نسخه‌ی مؤثر برنامه = بزرگ‌تر از «نسخه‌ی نصب‌کننده» و «آخرین پچ اعمال‌شده».
 * (قبلاً از config('app.version') خوانده می‌شد که وجود ندارد و همیشه 1.0.0 می‌داد.)
 * نصب کامل جدیدتر پچ‌های قدیمی را می‌بلعد، چون نسخه‌ی نصب‌کننده بزرگ‌تر می‌شود.
 */
final class AppVersion
{
    public static function installer(): string
    {
        $raw = config('nativephp.version') ?: env('NATIVEPHP_APP_VERSION') ?: env('APP_VERSION') ?: '1.0.0';

        return Util::cleanVersion((string) $raw) ?? '1.0.0';
    }

    public static function patchLevel(): ?string
    {
        try {
            $best = null;
            foreach (LicensePatch::query()->where('status', LicensePatch::APPLIED)->pluck('version_after') as $v) {
                if ($v && ($best === null || Util::semCode((string) $v) > Util::semCode($best))) {
                    $best = (string) $v;
                }
            }

            return $best;
        } catch (Throwable) {
            return null; // جدول هنوز ساخته نشده
        }
    }

    public static function current(): string
    {
        $installer = self::installer();
        $patch     = self::patchLevel();

        return $patch !== null && Util::semCode($patch) > Util::semCode($installer) ? $patch : $installer;
    }
}
