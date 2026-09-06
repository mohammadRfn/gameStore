<?php

declare(strict_types=1);

namespace Modules\Setting\App\Enums\Settings;


/**
 * اندازه‌ی کاغذ پیش‌فرض چاپگر.
 */
enum PaperSize: string
{
    case Roll58 = '58mm';
    case Roll80 = '80mm';
    case A4 = 'A4';
    case A5 = 'A5';
    case Label = 'label';

    public function label(): string
    {
        return match ($this) {
            self::Roll58 => 'رول ۵۸ میلی‌متر',
            self::Roll80 => 'رول ۸۰ میلی‌متر',
            self::A4 => 'A4',
            self::A5 => 'A5',
            self::Label => 'لیبل',
        };
    }
}
