<?php

declare(strict_types=1);

namespace Modules\Setting\Enums\Settings;

/**
 * نوع تقویم فعال در سیستم.
 * در اپ‌های فارسی معمولاً جلالی (Shamsi) پیش‌فرض است.
 */
enum CalendarType: string
{
    case Jalali = 'jalali';
    case Gregorian = 'gregorian';

    public function label(): string
    {
        return match ($this) {
            self::Jalali => 'جلالی (شمسی)',
            self::Gregorian => 'میلادی',
        };
    }
}
