<?php

declare(strict_types=1);

namespace App\Enums\Settings;

/**
 * نحوه‌ی نمایش قیمت در UI.
 */
enum PriceDisplayMode: string
{
    case WithUnit = 'with_unit';     // ۱٬۲۰۰٬۰۰۰ تومان
    case WithoutUnit = 'without_unit'; // ۱٬۲۰۰٬۰۰۰
    case WithCurrencyCode = 'with_currency_code'; // ۱٬۲۰۰٬۰۰۰ IRT

    public function label(): string
    {
        return match ($this) {
            self::WithUnit => 'با واحد (تومان)',
            self::WithoutUnit => 'بدون واحد',
            self::WithCurrencyCode => 'با کد ارز (IRT)',
        };
    }
}
