<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\Settings\SettingGroup;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * رویداد تغییر تنظیمات.
 * پس از هر به‌روزرسانی تنظیمات منتشر می‌شود تا لیسنرها
 * (مثل invalidate cache یا log audit) واکنش نشان دهند.
 */
class SettingsChanged
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param array<string,mixed> $oldValues مقادیر پیش از تغییر
     * @param array<string,mixed> $newValues مقادیر پس از تغییر
     */
    public function __construct(
        public readonly array $oldValues,
        public readonly array $newValues,
        public readonly ?SettingGroup $group = null,
        public readonly ?int $userId = null,
    ) {
    }

    /**
     * کلیدهای تغییرکرده.
     *
     * @return array<int,string>
     */
    public function changedKeys(): array
    {
        return array_keys(
            array_filter(
                $this->newValues,
                fn ($key) => ($this->oldValues[$key] ?? null) !== $this->newValues[$key],
                ARRAY_FILTER_USE_KEY,
            )
        );
    }
}
