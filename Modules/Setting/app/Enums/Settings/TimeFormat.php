<?php

declare(strict_types=1);

namespace Modules\Setting\Enums\Settings;

enum TimeFormat: string
{
    case H12 = '12';
    case H24 = '24';

    public function label(): string
    {
        return match ($this) {
            self::H12 => '۱۲ ساعته',
            self::H24 => '۲۴ ساعته',
        };
    }

    /**
     * قالب PHP Carbon مطابق این تنظیم.
     */
    public function carbonFormat(): string
    {
        return match ($this) {
            self::H12 => 'h:i:s A',
            self::H24 => 'H:i:s',
        };
    }
}
