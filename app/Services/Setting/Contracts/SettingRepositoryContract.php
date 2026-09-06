<?php

declare(strict_types=1);

namespace App\Services\Setting\Contracts;

use App\Enums\Settings\SettingGroup;

/**
 * قرارداد رسمی برای مخزن تنظیمات.
 * با تعریف یک Interface، می‌توان driver را عوض کرد (database / file / redis)
 * بدون این‌که کد کلاینت تغییری کند.
 */
interface SettingRepositoryContract
{
    /**
     * گرفتن یک تنظیم با کلید کامل (مثلاً 'general.calendar').
     * اگر تنظیم موجود نبود، مقدار پیش‌فرض یا null برگردانده می‌شود.
     */
    public function get(string $key, mixed $default = null): mixed;

    /**
     * به‌روزرسانی یک تنظیم.
     * کلید می‌تواند به شکل 'group.key' یا 'group.key.sub' باشد.
     */
    public function set(string $key, mixed $value): void;

    /**
     * به‌روزرسانی دسته‌ای.
     *
     * @param array<string,mixed> $values
     */
    public function setMany(array $values): void;

    /**
     * گرفتن همه‌ی تنظیمات یک گروه به‌صورت آرایه‌ی کلید=>مقدار.
     *
     * @return array<string,mixed>
     */
    public function getGroup(SettingGroup $group): array;

    /**
     * گرفتن همه‌ی تنظیمات به‌صورت flat array.
     *
     * @return array<string,mixed>
     */
    public function all(): array;

    /**
     * آیا تنظیمی با این کلید وجود دارد؟
     */
    public function has(string $key): bool;

    /**
     * حذف یک تنظیم (و برگشت به مقدار پیش‌فرض).
     */
    public function forget(string $key): void;

    /**
     * حذف همه‌ی تنظیمات یک گروه.
     */
    public function flushGroup(SettingGroup $group): void;

    /**
     * حذف همه‌ی تنظیمات.
     */
    public function flush(): void;

    /**
     * پاک‌سازی کش تنظیمات.
     */
    public function flushCache(): void;
}
