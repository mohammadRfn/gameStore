<?php

namespace Modules\Invoice\Exports\Concerns;

use Illuminate\Support\Carbon;
use Morilog\Jalali\Jalalian;

/**
 * رنگ‌ها و کمکی‌های مشترک شیت‌های خروجی سود و زیان
 * (همان پالت تیره/طلایی خروجی‌های بایگانی؛ بدون تعیین فونت تا با فونت پیش‌فرض اکسل باز شود).
 */
trait FormatsProfitExport
{
    protected static string $colorHeaderBg   = '1A1A2E';
    protected static string $colorHeaderFont = 'FFD700';
    protected static string $colorSectionBg  = '16213E';
    protected static string $colorSectionFont = 'E8D5B7';
    protected static string $colorRowEven    = 'F5EAD1';
    protected static string $colorRowOdd     = 'FBF6E9';
    protected static string $colorRowFont    = '3B2F1E';
    protected static string $colorBorder     = 'D9C289';
    protected static string $colorGold       = 'C9A227';
    protected static string $colorProfit     = '1E6B2E';
    protected static string $colorLoss       = 'B3261E';
    protected static string $colorMuted      = '8A8272';

    protected function jalaliDate(mixed $date, string $format = 'Y/m/d'): string
    {
        if (!$date) {
            return '-';
        }

        $carbon = $date instanceof Carbon ? $date : Carbon::parse($date);

        return class_exists(Jalalian::class)
            ? Jalalian::fromCarbon($carbon)->format($format)
            : $carbon->format(str_replace(['Y/m/d'], ['Y-m-d'], $format));
    }

    protected function statusLabel(?string $status): string
    {
        return match ($status) {
            'paid'     => 'پرداخت‌شده',
            'unpaid'   => 'پرداخت‌نشده',
            'returned' => 'مرجوع‌شده',
            default    => (string) $status,
        };
    }

    protected function methodLabel(?string $method): string
    {
        return match ($method) {
            'cash'         => 'نقدی',
            'card_to_card' => 'کارت به کارت',
            'pos_terminal' => 'دستگاه POS',
            default        => $method ?: '-',
        };
    }
}
