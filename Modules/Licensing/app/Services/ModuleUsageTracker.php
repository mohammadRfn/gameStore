<?php

declare(strict_types=1);

namespace Modules\Licensing\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * شمارنده‌ی محلی مصرف ماژول‌ها؛ در هر heartbeat به StoreServer گزارش می‌شود.
 * جدول: license_module_usage (SQL خام پایین صفحه). هر خطا بی‌صدا نادیده گرفته می‌شود
 * تا ثبت مصرف هیچ‌وقت درخواست کاربر را خراب نکند.
 */
class ModuleUsageTracker
{
    private const TABLE = 'license_module_usage';

    public function touch(string $module): void
    {
        try {
            $now = Carbon::now('UTC')->format('Y-m-d H:i:s');

            DB::statement(
                'INSERT INTO ' . self::TABLE . ' (module, hits_total, hits_unreported, first_used_at, last_used_at)
                 VALUES (?, 1, 1, ?, ?)
                 ON CONFLICT(module) DO UPDATE SET
                     hits_total = hits_total + 1,
                     hits_unreported = hits_unreported + 1,
                     last_used_at = excluded.last_used_at',
                [$module, $now, $now],
            );
        } catch (Throwable) {
            // جدول هنوز ساخته نشده یا دیتابیس قفل است؛ مهم نیست
        }
    }

    /**
     * مصرف گزارش‌نشده از heartbeat قبلی.
     *
     * @return array<string, array{hits: int, last_used_at: string}>
     */
    public function pending(): array
    {
        try {
            $rows = DB::table(self::TABLE)
                ->where('hits_unreported', '>', 0)
                ->get(['module', 'hits_unreported', 'last_used_at']);
        } catch (Throwable) {
            return [];
        }

        $out = [];

        foreach ($rows as $row) {
            $out[(string) $row->module] = [
                'hits'         => (int) $row->hits_unreported,
                'last_used_at' => Carbon::parse((string) $row->last_used_at, 'UTC')->toIso8601String(),
            ];
        }

        return $out;
    }

    /**
     * فقط همان مقداری را کم می‌کند که فرستاده شد (نه صفر کردن کامل)،
     * تا استفاده‌ی هم‌زمان با ارسال heartbeat گم نشود.
     *
     * @param array<string, array{hits: int}> $sent
     */
    public function acknowledge(array $sent): void
    {
        try {
            foreach ($sent as $module => $info) {
                DB::table(self::TABLE)->where('module', $module)->update([
                    'hits_unreported' => DB::raw('MAX(hits_unreported - ' . (int) $info['hits'] . ', 0)'),
                ]);
            }
        } catch (Throwable) {
            // در heartbeat بعدی دوباره گزارش می‌شود
        }
    }
}