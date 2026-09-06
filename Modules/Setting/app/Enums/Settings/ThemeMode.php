<?php

declare(strict_types=1);

namespace Modules\Setting\App\Enums\Settings;


enum ThemeMode: string
{
    case Light = 'light';
    case Dark = 'dark';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Light => 'روشن',
            self::Dark => 'تیره',
            self::System => 'پیش‌فرض سیستم',
        };
    }
}
