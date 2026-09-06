<?php

declare(strict_types=1);

namespace App\Enums\Settings;

/**
 * گروه‌های تنظیمات سیستم.
 * هر تنظیم دقیقاً به یک گروه تعلق دارد.
 */
enum SettingGroup: string
{
    case General = 'general';
    case Invoice = 'invoice';
    case Desktop = 'desktop';

    /**
     * برچسب فارسی برای UI
     */
    public function label(): string
    {
        return match ($this) {
            self::General => 'عمومی و محلی‌سازی',
            self::Invoice => 'مالیات و فاکتور',
            self::Desktop => 'دسکتاپ (Native/Electron)',
        };
    }

    /**
     * آیکون پیشنهادی برای UI
     */
    public function icon(): string
    {
        return match ($this) {
            self::General => 'settings',
            self::Invoice => 'receipt_long',
            self::Desktop => 'desktop_windows',
        };
    }

    /**
     * همه‌ی گروه‌ها به‌صورت آرایه‌ی ساده
     * @return array<string,string>
     */
    public static function toArray(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }
        return $out;
    }
}
