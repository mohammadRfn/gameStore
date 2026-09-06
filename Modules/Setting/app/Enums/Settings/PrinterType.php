<?php

declare(strict_types=1);

namespace Modules\Setting\App\Enums\Settings;


/**
 * نوع چاپگر پیش‌فرض برای فاکتور و رسید.
 */
enum PrinterType: string
{
    case Thermal = 'thermal'; // چاپگر حرارتی ۵۸/۸۰ میلی‌متری
    case A4 = 'a4';           // چاپگر A4 معمولی
    case Label = 'label';     // چاپگر لیبل (بارکد/موجودی)

    public function label(): string
    {
        return match ($this) {
            self::Thermal => 'چاپگر حرارتی (رسید)',
            self::A4 => 'چاپگر A4',
            self::Label => 'چاپگر لیبل',
        };
    }
}
