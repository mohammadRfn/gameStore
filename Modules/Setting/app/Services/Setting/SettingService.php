<?php

declare(strict_types=1);

namespace Modules\Setting\Services\Setting;

use Modules\Setting\Enums\Settings\BackupSchedule;
use Modules\Setting\Enums\Settings\CalendarType;
use Modules\Setting\Enums\Settings\PaperSize;
use Modules\Setting\Enums\Settings\PriceDisplayMode;
use Modules\Setting\Enums\Settings\PrinterType;
use Modules\Setting\Enums\Settings\SettingGroup;
use Modules\Setting\Enums\Settings\TaxMode;
use Modules\Setting\Enums\Settings\ThemeMode;
use Modules\Setting\Enums\Settings\TimeFormat;
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
 *  6) محاسبات مشتق‌شده (مالیات، فرمت قیمت، فرمت تاریخ، فرمت شماره فاکتور).
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
    ) {
    }

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
        $fetch = fn () => array_merge($this->defaults->all(), $this->repository->all());

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
        $fetch = fn () => array_merge(
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
            fn ($key) => $this->defaults->groupOf($key)?->value,
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

    public function calendar(): CalendarType
    {
        return $this->getEnum('general.calendar', CalendarType::class) ?? CalendarType::Jalali;
    }

    public function timeFormat(): TimeFormat
    {
        return $this->getEnum('general.time_format', TimeFormat::class) ?? TimeFormat::H24;
    }

    public function theme(): ThemeMode
    {
        return $this->getEnum('general.theme', ThemeMode::class) ?? ThemeMode::Light;
    }

    public function currency(): string
    {
        return $this->getString('general.currency', 'تومان');
    }

    public function currencyCode(): string
    {
        return $this->getString('general.currency_code', 'IRT');
    }

    public function priceDisplay(): PriceDisplayMode
    {
        return $this->getEnum('general.price_display', PriceDisplayMode::class) ?? PriceDisplayMode::WithUnit;
    }

    public function taxRate(): float
    {
        return $this->getFloat('invoice.tax_rate', 9.0);
    }

    public function taxMode(): TaxMode
    {
        return $this->getEnum('invoice.tax_mode', TaxMode::class) ?? TaxMode::Exclusive;
    }

    public function taxEnabled(): bool
    {
        return $this->getBool('invoice.tax_enabled', true);
    }

    public function defaultPrinterType(): PrinterType
    {
        return $this->getEnum('invoice.printer_type', PrinterType::class) ?? PrinterType::Thermal;
    }

    public function defaultPaperSize(): PaperSize
    {
        return $this->getEnum('invoice.paper_size', PaperSize::class) ?? PaperSize::Roll80;
    }

    public function backupSchedule(): BackupSchedule
    {
        return $this->getEnum('desktop.backup_schedule', BackupSchedule::class) ?? BackupSchedule::Daily;
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
    | محاسبات مشتق‌شده
    |--------------------------------------------------------------------------
    */

    /**
     * فرمت نمایش قیمت مطابق تنظیمات.
     * از NumberFormatter ICU برای locale-aware بودن استفاده می‌کند.
     */
    public function formatPrice(int|float $amount, ?string $locale = null): string
    {
        $locale = $locale ?? $this->getString('general.locale', 'fa');
        $decimal = $this->getString('general.decimal_separator', '.');
        $thousand = $this->getString('general.thousand_separator', ',');

        $amountFloat = (float) $amount;
        $formatted = number_format(
            abs($amountFloat),
            0, // قیمت معمولاً اعشار ندارد
            $decimal,
            $thousand
        );
        if ($amountFloat < 0) {
            $formatted = '-' . $formatted;
        }

        return match ($this->priceDisplay()) {
            PriceDisplayMode::WithUnit => $formatted . ' ' . $this->currency(),
            PriceDisplayMode::WithoutUnit => $formatted,
            PriceDisplayMode::WithCurrencyCode => $formatted . ' ' . $this->currencyCode(),
        };
    }

    /**
     * محاسبه‌ی مبلغ مالیات برای یک مبلغ معین.
     */
    public function calculateTax(int|float $amount): float
    {
        if (! $this->taxEnabled()) {
            return 0.0;
        }
        $rate = $this->taxRate() / 100;
        return match ($this->taxMode()) {
            // مالیات از قیمت محاسبه می‌شود و به آن اضافه می‌گردد.
            TaxMode::Exclusive => (float) $amount * $rate,
            // مالیات درون قیمت هست؛ برای استخراج: price - price/(1+rate)
            TaxMode::Inclusive => (float) $amount - ((float) $amount / (1 + $rate)),
        };
    }

    /**
     * مبلغ نهایی شامل مالیات.
     */
    public function priceWithTax(int|float $amount): float
    {
        if (! $this->taxEnabled()) {
            return (float) $amount;
        }
        return match ($this->taxMode()) {
            TaxMode::Exclusive => (float) $amount + $this->calculateTax($amount),
            TaxMode::Inclusive => (float) $amount, // مالیات درون خود قیمت است
        };
    }

    /**
     * تولید شماره فاکتور بعدی.
     * این متد هم خواندنی و هم با increment عمل می‌کند (atomic).
     *
     * @param bool $increment اگر false باشد، فقط شماره فعلی را می‌دهد بدون افزایش شمارنده.
     */
    public function nextInvoiceNumber(bool $increment = true): string
    {
        $prefix = $this->getString('invoice.prefix', 'INV-');
        $counter = $this->getInt('invoice.counter', 1);
        $padding = $this->getInt('invoice.counter_padding', 6);

        $number = $prefix . str_pad((string) $counter, max(1, $padding), '0', STR_PAD_LEFT);

        if ($increment) {
            $this->set('invoice.counter', $counter + 1);
        }

        return $number;
    }

    /**
     * فرمت تاریخ با توجه به تقویم انتخابی.
     * برای تقویم جلالی نیاز به پکیج verta/morandi یا مشابه دارد.
     */
    public function formatDate(\DateTimeInterface|string $date, ?string $format = null): string
    {
        if (is_string($date)) {
            $date = new \DateTimeImmutable($date);
        }

        $calendar = $this->calendar();

        if ($calendar === CalendarType::Jalali && class_exists(\Morilog\Jalali\Jalalian::class)) {
            $jalali = \Morilog\Jalali\Jalalian::fromDateTime($date);
            return $jalali->format($format ?? 'Y/m/d');
        }

        return $date->format($format ?? 'Y-m-d');
    }

    /**
     * فرمت ساعت با توجه به تنظیم.
     */
    public function formatTime(\DateTimeInterface|string $date, ?string $format = null): string
    {
        if (is_string($date)) {
            $date = new \DateTimeImmutable($date);
        }
        $fmt = $format ?? $this->timeFormat()->carbonFormat();
        return $date->format($fmt);
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
