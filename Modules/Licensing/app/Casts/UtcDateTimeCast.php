<?php

declare(strict_types=1);

namespace Modules\Licensing\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * timestampهای حیاتی لایسنس (last_heartbeat_at، expires_at، valid_until) صرف‌نظر
 * از config('app.timezone') همیشه به‌صورت UTC خونده و نوشته می‌شن؛ چون این مقادیر
 * پایه‌ی محاسبه‌ی due-check (فاصله‌ی heartbeat) هستن و نباید به تنظیم timezone
 * نمایشیِ بقیه‌ی اپ (مثلاً Asia/Tehran) وابسته باشن.
 */
class UtcDateTimeCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        return Carbon::createFromFormat('Y-m-d H:i:s', $value, 'UTC');
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return Carbon::parse($value)->utc()->format('Y-m-d H:i:s');
    }
}