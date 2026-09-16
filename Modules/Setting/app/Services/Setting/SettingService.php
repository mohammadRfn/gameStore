<?php

declare(strict_types=1);

namespace Modules\Setting\Services\Setting;

use Modules\Setting\Enums\Settings\SettingGroup;
use Modules\Setting\Enums\Settings\ThemeMode;
use Modules\Setting\Events\SettingsChanged;
use Modules\Setting\Services\Setting\Contracts\SettingRepositoryContract;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use NumberFormatter;
use RuntimeException;

/**
 * SettingService
 * ============================================================================
 * سرویس مرکزی تنظیمات اپلیکیشن. این کلاس تنها نقطه‌ی تماس بین لایه‌های
 * برنامه و ذخیره‌ساز تنظیمات است.
 *
 * مسئولیت‌ها:
 *  1) کش‌گذاری سراسری تنظیمات در cache برای پرفورمنس.
 *  2) type-casting خودکار هنگام خواندن و نوشتن (bool, int, float, enum).
 *  3) اعتبارسنجی پیش از ذخیره بر اساس rules تعریف‌شده در config.
 *  4) انتشار رویداد SettingsChanged پس از هر تغییر (برای invalidate کش و audit).
 *  5) API‌های typed (getString / getInt / getEnum / ...) برای جلوگیری از خطای برنامه‌نویس.
 *  6) محاسبات مشتق‌شده (فرمت قیمت، فرمت تاریخ).
 *
 * نکته: هیچ‌یک از مقادیر تنظیمات به‌صورت مستقیم در این کلاس نگه‌داری نمی‌شوند؛
 * همه از repository گرفته می‌شوند و در cache قرار می‌گیرند.
 */
final class SettingService
{
    private const CACHE_ALL_KEY_SUFFIX = 'all';
    private const CACHE_GROUP_PREFIX = 'group.';

    public function __construct(
        private readonly SettingRepositoryContract $repository,
        private readonly CacheRepository $cache,
        private readonly Dispatcher $events,
        private readonly SettingDefaults $defaults,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | API عمومی
    |--------------------------------------------------------------------------
    */

    /**
     * گرفتن مقدار یک تنظیم (با fallback به مقدار پیش‌فرض config).
     * مقدار از cache خوانده می‌شود؛ در صورت miss، کل مجموعه cache می‌شود.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->getAll();
        $value = $all[$key] ?? $this->defaults->defaultFor($key) ?? $default;

        return $this->cast($key, $value);
    }

    /**
     * گرفتن مقدار string.
     */
    public function getString(string $key, string $default = ''): string
    {
        $v = $this->get($key, $default);
        return is_string($v) || is_numeric($v) ? (string) $v : $default;
    }

    /**
     * گرفتن مقدار int.
     */
    public function getInt(string $key, int $default = 0): int
    {
        $v = $this->get($key, $default);
        return is_numeric($v) ? (int) $v : $default;
    }

    /**
     * گرفتن مقدار float.
     */
    public function getFloat(string $key, float $default = 0.0): float
    {
        $v = $this->get($key, $default);
        return is_numeric($v) ? (float) $v : $default;
    }

    /**
     * گرفتن مقدار bool.
     */
    public function getBool(string $key, bool $default = false): bool
    {
        $v = $this->get($key, $default);
        return filter_var($v, FILTER_VALIDATE_BOOLEAN, ['flags' => FILTER_NULL_ON_FAILURE]) ?? $default;
    }
    public function getJson(string $key, mixed $default = null): mixed
    {
        $value = $this->get($key, $default);

        if (is_array($value)) {
            return $value;
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : $default;
        }

        return $default;
    }
    /**
     * گرفتن مقدار به‌صورت enum مشخص.
     *
     * @template T of \BackedEnum
     * @param class-string<T> $enumClass
     * @return T|null
     */
    public function getEnum(string $key, string $enumClass): ?\BackedEnum
    {
        $v = $this->get($key);
        if ($v instanceof $enumClass) {
            return $v;
        }
        if (is_string($v) || is_int($v)) {
            try {
                return $enumClass::from($v);
            } catch (\ValueError) {
                return null;
            }
        }
        return null;
    }

    /**
     * گرفتن همه‌ی تنظیمات (cached).
     *
     * @return array<string,mixed>
     */
    public function getAll(): array
    {
        $fetch = fn() => array_merge($this->defaults->all(), $this->repository->all());

        if (! $this->cacheEnabled()) {
            return $fetch();
        }

        $key = $this->cacheKey(self::CACHE_ALL_KEY_SUFFIX);

        return $this->cache->remember(
            $key,
            (int) config('setting.cache.ttl', 86400),
            $fetch,
        );
    }

    /**
     * گرفتن همه‌ی تنظیمات یک گروه.
     *
     * @return array<string,mixed>
     */
    public function getGroup(SettingGroup $group): array
    {
        $fetch = fn() => array_merge(
            $this->defaults->defaultsForGroup($group),
            $this->repository->getGroup($group),
        );

        if (! $this->cacheEnabled()) {
            return $fetch();
        }

        $key = $this->cacheKey(self::CACHE_GROUP_PREFIX . $group->value);

        return $this->cache->remember(
            $key,
            (int) config('setting.cache.ttl', 86400),
            $fetch,
        );
    }

    /**
     * تنظیم یک کلید.
     *
     * @throws ValidationException
     */
    public function set(string $key, mixed $value): void
    {
        $this->assertValidKey($key);
        $this->validateSingle($key, $value);

        $old = $this->get($key);
        $normalized = $this->normalizeValue($key, $value);

        $this->repository->set($key, $normalized);
        $this->invalidateCache();

        $this->events->dispatch(new SettingsChanged(
            oldValues: [$key => $old],
            newValues: [$key => $normalized],
            group: $this->defaults->groupOf($key),
            userId: auth()->id(),
        ));
    }

    /**
     * به‌روزرسانی چند تنظیم به‌صورت اتمی.
     *
     * @param array<string,mixed> $values
     * @throws ValidationException
     */
    public function updateMany(array $values): void
    {
        // 1) اعتبارسنجی
        $this->validateBulk($values);

        // 2) گرفتن مقادیر فعلی
        $oldValues = [];
        $normalized = [];
        foreach ($values as $key => $value) {
            $oldValues[$key] = $this->get($key);
            $normalized[$key] = $this->normalizeValue($key, $value);
        }

        // 3) ذخیره‌ی اتمی
        $this->repository->setMany($normalized);
        $this->invalidateCache();

        // 4) انتشار رویداد
        $group = null;
        $groups = array_unique(array_map(
            fn($key) => $this->defaults->groupOf($key)?->value,
            array_keys($values)
        ));
        if (count($groups) === 1 && $groups[0] !== null) {
            $group = SettingGroup::from($groups[0]);
        }

        $this->events->dispatch(new SettingsChanged(
            oldValues: $oldValues,
            newValues: $normalized,
            group: $group,
            userId: auth()->id(),
        ));
    }

    /**
     * به‌روزرسانی کل یک گروه با payload یکپارچه.
     *
     * @param array<string,mixed> $values
     */
    public function updateGroup(SettingGroup $group, array $values): void
    {
        $prefixed = [];
        foreach ($values as $shortKey => $value) {
            $fullKey = str_contains($shortKey, '.') ? $shortKey : "{$group->value}.{$shortKey}";
            $prefixed[$fullKey] = $value;
        }
        $this->updateMany($prefixed);
    }

    /**
     * ریست یک کلید به مقدار پیش‌فرض.
     */
    public function reset(string $key): void
    {
        $this->repository->forget($key);
        $this->invalidateCache();
    }

    /**
     * ریست یک گروه کامل به مقادیر پیش‌فرض.
     */
    public function resetGroup(SettingGroup $group): void
    {
        $this->repository->flushGroup($group);
        $this->invalidateCache();
    }

    /**
     * ریست کامل همه‌ی تنظیمات.
     */
    public function resetAll(): void
    {
        $this->repository->flush();
        $this->invalidateCache();
    }

    /**
     * پاک‌سازی دستی cache.
     */
    public function flushCache(): void
    {
        $this->repository->flushCache();
        $this->cache->forget($this->cacheKey(self::CACHE_ALL_KEY_SUFFIX));
        foreach (SettingGroup::cases() as $group) {
            $this->cache->forget($this->cacheKey(self::CACHE_GROUP_PREFIX . $group->value));
        }
    }

    /**
     * آیا تنظیمی با این کلید در meta تعریف شده؟
     */
    public function has(string $key): bool
    {
        return $this->defaults->exists($key);
    }

    /**
     * گرفتن meta کامل یک کلید.
     */
    public function meta(string $key): ?array
    {
        return $this->defaults->metaFor($key);
    }

    /**
     * meta همه‌ی تنظیمات یک گروه.
     *
     * @return array<string,array>
     */
    public function metaForGroup(SettingGroup $group): array
    {
        $keys = $this->defaults->keysForGroup($group);
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $this->defaults->metaFor($key);
        }
        return $out;
    }

    /*
    |--------------------------------------------------------------------------
    | API‌های مخصوص دامنه (Domain-specific Helpers)
    |--------------------------------------------------------------------------
    | این متدها ترکیب‌های پرکاربرد تنظیمات را در قالب API تمیز ارائه می‌دهند.
    */

    public function theme(): ThemeMode
    {
        return $this->getEnum('general.theme', ThemeMode::class) ?? ThemeMode::Light;
    }

    public function autoLaunch(): bool
    {
        return $this->getBool('desktop.auto_launch', false);
    }

    public function minimizeToTray(): bool
    {
        return $this->getBool('desktop.minimize_to_tray', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Import / Export
    |--------------------------------------------------------------------------
    */

    /**
     * خروجی گرفتن از همه‌ی تنظیمات فعلی به‌صورت آرایه (برای ذخیره در فایل یا انتقال).
     *
     * @return array<string,mixed>
     */
    public function export(): array
    {
        $out = [];
        foreach ($this->getAll() as $key => $value) {
            if ($value instanceof \BackedEnum) {
                $value = $value->value;
            }
            $out[$key] = $value;
        }
        return $out;
    }

    /**
     * وارد کردن تنظیمات از یک payload.
     *
     * @param array<string,mixed> $payload
     */
    public function import(array $payload, bool $validate = true): void
    {
        if ($validate) {
            $this->validateBulk($payload);
        }
        $this->updateMany($payload);
    }

    /*
    |--------------------------------------------------------------------------
    | Internal
    |--------------------------------------------------------------------------
    */

    private function cacheEnabled(): bool
    {
        return (bool) config('setting.cache.enabled', true);
    }

    private function cacheKey(string $suffix): string
    {
        return config('setting.cache.key', 'app.settings.v1') . ':' . $suffix;
    }

    private function invalidateCache(): void
    {
        $this->cache->forget($this->cacheKey(self::CACHE_ALL_KEY_SUFFIX));
        foreach (SettingGroup::cases() as $group) {
            $this->cache->forget($this->cacheKey(self::CACHE_GROUP_PREFIX . $group->value));
        }
    }

    /**
     * اعتبارسنجی یک کلید در برابر meta.
     *
     * @throws \InvalidArgumentException
     */
    private function assertValidKey(string $key): void
    {
        if (! $this->defaults->exists($key)) {
            throw new \InvalidArgumentException("Setting key [{$key}] is not defined in config.");
        }
    }

    /**
     * اعتبارسنجی یک مقدار تنها.
     *
     * @throws ValidationException
     */
    private function validateSingle(string $key, mixed $value): void
    {
        $rules = $this->defaults->rulesFor($key);
        if (empty($rules)) {
            return;
        }

        $validator = Validator::make([$key => $value], [$key => $rules]);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * اعتبارسنجی دسته‌ای.
     *
     * @param array<string,mixed> $values
     * @throws ValidationException
     */
    private function validateBulk(array $values): void
    {
        $rules = [];
        foreach (array_keys($values) as $key) {
            if ($this->defaults->exists($key)) {
                $keyRules = $this->defaults->rulesFor($key);
                if (! empty($keyRules)) {
                    $rules[$key] = $keyRules;
                }
            }
        }

        if (empty($rules)) {
            return;
        }

        $validator = Validator::make($values, $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
    }

    /**
     * نرممال‌سازی مقدار بر اساس type تعریف‌شده در meta.
     * مثلاً enum را به value تبدیل می‌کند، bool را به '1'/'0' برای ذخیره.
     */
    private function normalizeValue(string $key, mixed $value): mixed
    {
        $meta = $this->defaults->metaFor($key);
        if ($meta === null) {
            return $value;
        }

        $type = $meta['type'];

        return match ($type) {
            'bool' => $this->toBool($value),
            'int' => is_numeric($value) ? (int) $value : $value,
            'float' => is_numeric($value) ? (float) $value : $value,
            'enum' => $value instanceof \BackedEnum ? $value->value : $value,
            default => $value,
        };
    }

    /**
     * تبدیل مقدار به نوع صحیح هنگام خواندن.
     */
    private function cast(string $key, mixed $value): mixed
    {
        $meta = $this->defaults->metaFor($key);
        if ($meta === null || $value === null) {
            return $value;
        }

        $type = $meta['type'];

        return match ($type) {
            'bool' => $this->toBool($value),
            'int' => is_numeric($value) ? (int) $value : $value,
            'float' => is_numeric($value) ? (float) $value : $value,
            'enum' => $this->castEnum($meta['enum'] ?? null, $value),
            default => $value,
        };
    }

    /**
     * @param class-string<\BackedEnum>|null $enumClass
     */
    private function castEnum(?string $enumClass, mixed $value): mixed
    {
        if ($enumClass === null || ! is_subclass_of($enumClass, \BackedEnum::class)) {
            return $value;
        }
        if ($value instanceof $enumClass) {
            return $value;
        }
        try {
            return $enumClass::from(is_string($value) || is_int($value) ? $value : (string) $value);
        } catch (\ValueError) {
            return $value;
        }
    }

    /**
     * تبدیل مقدار به bool به شکل پایدار.
     */
    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            $low = strtolower(trim($value));
            if (in_array($low, ['1', 'true', 'yes', 'on', 'y'], true)) {
                return true;
            }
            if (in_array($low, ['0', 'false', 'no', 'off', 'n', ''], true)) {
                return false;
            }
        }
        return (bool) $value;
    }
}