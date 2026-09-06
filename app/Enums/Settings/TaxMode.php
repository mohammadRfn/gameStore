<?php

declare(strict_types=1);

namespace App\Enums\Settings;

/**
 * نحوه‌ی محاسبه‌ی مالیات نسبت به قیمت.
 * - inclusive: مالیات درون قیمت گنجانده شده است.
 * - exclusive: مالیات به قیمت اضافه می‌شود.
 */
enum TaxMode: string
{
    case Inclusive = 'inclusive';
    case Exclusive = 'exclusive';

    public function label(): string
    {
        return match ($this) {
            self::Inclusive => 'شامل قیمت',
            self::Exclusive => 'جدا از قیمت (به مبلغ اضافه می‌شود)',
        };
    }
}
