<?php

declare(strict_types=1);

namespace Modules\AuditLog\Enums;

/**
 * وضعیت همگام‌سازی هر رکورد لاگ با StoreServer.
 */
enum SyncStatus: string
{
    case Pending = 'pending';   // آماده‌ی ارسال
    case Sending = 'sending';   // در حال ارسال (قفل‌شده توسط یک batch)
    case Synced  = 'synced';    // سرور تأیید کرده
    case Failed  = 'failed';    // ارسال ناموفق، منتظر تلاش بعدی
    case Skipped = 'skipped';   // طبق کانفیگ ارسال نمی‌شود
    case Dead    = 'dead';      // از سقف تلاش عبور کرده؛ نیاز به بررسی دستی

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Synced, self::Skipped, self::Dead], true);
    }
}
