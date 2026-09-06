<?php

declare(strict_types=1);

namespace Modules\Setting\Enums\Settings;

/**
 * بازه‌ی زمانی بکاپ خودکار.
 */
enum BackupSchedule: string
{
    case Disabled = 'disabled';
    case Hourly = 'hourly';
    case Daily = 'daily';
    case Weekly = 'weekly';

    public function label(): string
    {
        return match ($this) {
            self::Disabled => 'غیرفعال',
            self::Hourly => 'هر ساعت',
            self::Daily => 'روزانه',
            self::Weekly => 'هفتگی',
        };
    }

    /**
     * cron expression معادل برای scheduler لاراول.
     */
    public function cronExpression(): ?string
    {
        return match ($this) {
            self::Disabled => null,
            self::Hourly => '0 * * * *',
            self::Daily => '0 3 * * *', // ۳ صبح
            self::Weekly => '0 3 * * 0', // ۳ صبح یکشنبه
        };
    }
}
