<?php

declare(strict_types=1);

namespace Modules\AuditLog\Enums;

/**
 * کانال‌های لاگ؛ هر رکورد دقیقاً به یکی از این کانال‌ها تعلق دارد.
 */
enum LogChannel: string
{
    case Http     = 'http';       // درخواست‌های HTTP/API
    case Model    = 'model';      // تغییر داده‌های Eloquent
    case Auth     = 'auth';       // ورود/خروج/شکست ورود
    case Security = 'security';   // رویدادهای امنیتی
    case Job      = 'job';        // صف و jobها
    case Console  = 'console';    // دستورات آرتیزان
    case Error    = 'error';      // خطاها و استثناها
    case System   = 'system';     // بکاپ، کش، آرشیو، تنظیمات سیستمی
    case Business = 'business';   // رویدادهای کسب‌وکار (فاکتور، سرویس، انبار)
    case Sync     = 'sync';       // همگام‌سازی با StoreServer

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    public static function tryFromName(?string $value): ?self
    {
        return $value === null ? null : self::tryFrom($value);
    }

    public function label(): string
    {
        return match ($this) {
            self::Http     => 'درخواست HTTP',
            self::Model    => 'تغییر داده',
            self::Auth     => 'احراز هویت',
            self::Security => 'امنیت',
            self::Job      => 'صف',
            self::Console  => 'کنسول',
            self::Error    => 'خطا',
            self::System   => 'سیستم',
            self::Business => 'کسب‌وکار',
            self::Sync     => 'همگام‌سازی',
        };
    }
}
