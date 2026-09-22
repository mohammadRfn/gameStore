<?php

declare(strict_types=1);

namespace Modules\AuditLog\Enums;

/**
 * اکشن‌های پرکاربرد. ستون action یک string آزاد است، این enum فقط
 * برای جلوگیری از تایپ اشتباه در کدهای داخلی استفاده می‌شود.
 */
enum AuditAction: string
{
    case Created  = 'created';
    case Updated  = 'updated';
    case Deleted  = 'deleted';
    case Restored = 'restored';
    case ForceDeleted = 'force_deleted';

    case Login        = 'auth.login';
    case Logout       = 'auth.logout';
    case LoginFailed  = 'auth.login_failed';
    case Lockout      = 'auth.lockout';
    case PasswordReset = 'auth.password_reset';
    case Registered   = 'auth.registered';

    case HttpRequest  = 'http.request';
    case JobFailed    = 'job.failed';
    case JobProcessed = 'job.processed';
    case CommandRun   = 'console.command';
    case ErrorReported = 'error.reported';
    case SlowQuery    = 'db.slow_query';

    case BackupRun        = 'system.backup';
    case CacheMaintenance = 'system.cache_maintenance';
    case SettingChanged   = 'system.setting_changed';
    case ArchiveRun       = 'system.archive';
    case ExportGenerated  = 'system.export';

    case LogsShipped      = 'sync.logs_shipped';
    case LogsShipFailed   = 'sync.logs_ship_failed';
    case LogsPruned       = 'sync.logs_pruned';

    public function label(): string
    {
        return match ($this) {
            self::Created      => 'ایجاد',
            self::Updated      => 'ویرایش',
            self::Deleted      => 'حذف',
            self::Restored     => 'بازیابی',
            self::ForceDeleted => 'حذف دائمی',
            self::Login        => 'ورود',
            self::Logout       => 'خروج',
            self::LoginFailed  => 'ورود ناموفق',
            self::Lockout      => 'قفل شدن حساب',
            self::PasswordReset => 'بازنشانی رمز',
            self::Registered   => 'ثبت‌نام',
            self::HttpRequest  => 'درخواست HTTP',
            self::JobFailed    => 'شکست job',
            self::JobProcessed => 'اجرای job',
            self::CommandRun   => 'اجرای دستور',
            self::ErrorReported => 'خطا',
            self::SlowQuery    => 'کوئری کند',
            self::BackupRun    => 'پشتیبان‌گیری',
            self::CacheMaintenance => 'نگه‌داری کش',
            self::SettingChanged => 'تغییر تنظیمات',
            self::ArchiveRun   => 'آرشیو',
            self::ExportGenerated => 'خروجی گرفتن',
            self::LogsShipped  => 'ارسال لاگ',
            self::LogsShipFailed => 'شکست ارسال لاگ',
            self::LogsPruned   => 'پاک‌سازی لاگ',
        };
    }
}
