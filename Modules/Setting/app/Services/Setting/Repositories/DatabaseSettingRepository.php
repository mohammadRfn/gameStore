<?php

declare(strict_types=1);

namespace Modules\Setting\Services\Setting\Repositories;

use Modules\Setting\Enums\Settings\SettingGroup;
use Modules\Setting\Models\Setting;
use Modules\Setting\Services\Setting\Contracts\SettingRepositoryContract;

/**
 * پیاده‌سازی repository مبتنی بر دیتابیس.
 *
 * نکته‌ی مهم: برای پرفورمنس، در setMany از upsert دسته‌ای استفاده می‌شود.
 */
class DatabaseSettingRepository implements SettingRepositoryContract
{
    public function get(string $key, mixed $default = null): mixed
    {
        $row = Setting::where('key', $key)->first();
        return $row?->value ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        [$group, $shortKey] = $this->parseKey($key);

        Setting::updateOrCreate(
            ['key' => $key],
            [
                'group' => $group,
                'value' => $this->encode($value),
                'type' => $this->detectType($value),
                'updated_by' => auth()->id(),
            ],
        );
    }

    /**
     * @param array<string,mixed> $values
     */
    public function setMany(array $values): void
    {
        if (empty($values)) {
            return;
        }

        $userId = auth()->id();
        $rows = [];
        foreach ($values as $key => $value) {
            [$group] = $this->parseKey($key);
            $rows[] = [
                'group' => $group,
                'key' => $key,
                'value' => $this->encode($value),
                'type' => $this->detectType($value),
                'updated_by' => $userId,
                'updated_at' => now(),
                'created_at' => now(),
            ];
        }

        // upsert بر اساس یکتایی group+key
        Setting::upsert(
            $rows,
            ['group', 'key'],
            ['value', 'type', 'updated_by', 'updated_at']
        );
    }

    /**
     * @return array<string,mixed>
     */
    public function getGroup(SettingGroup $group): array
    {
        $rows = Setting::ofGroup($group)->get(['key', 'value']);
        $out = [];
        foreach ($rows as $row) {
            $out[$row->key] = $this->decode($row->value, $row->type);
        }
        return $out;
    }

    /**
     * @return array<string,mixed>
     */
    public function all(): array
    {
        $rows = Setting::all(['key', 'value', 'type']);
        $out = [];
        foreach ($rows as $row) {
            $out[$row->key] = $this->decode($row->value, $row->type);
        }
        return $out;
    }

    public function has(string $key): bool
    {
        return Setting::where('key', $key)->exists();
    }

    public function forget(string $key): void
    {
        Setting::where('key', $key)->delete();
    }

    public function flushGroup(SettingGroup $group): void
    {
        Setting::ofGroup($group)->delete();
    }

    public function flush(): void
    {
        Setting::query()->delete();
    }

    public function flushCache(): void
    {
        // این repository کش را مدیریت نمی‌کند؛ SettingService این کار را می‌کند.
    }

    /**
     * تجزیه‌ی کلید به group و shortKey.
     * @return array{0:string,1:string}
     */
    private function parseKey(string $key): array
    {
        $parts = explode('.', $key, 2);
        $group = $parts[0] ?? 'general';
        $short = $parts[1] ?? $key;
        return [$group, $short];
    }

    /**
     * encode کردن مقدار برای ذخیره در ستون text.
     * مقدار به صورت JSON یا scalar string ذخیره می‌شود.
     */
    private function encode(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value) || is_string($value)) {
            return (string) $value;
        }
        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
        return (string) $value;
    }

    /**
     * decode کردن مقدار هنگام خواندن.
     */
    private function decode(?string $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }
        return match ($type) {
            'bool' => $value === '1' || strtolower($value) === 'true',
            'int' => (int) $value,
            'float' => (float) $value,
            'json' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * تشخیص نوع برای ستون type.
     */
    private function detectType(mixed $value): string
    {
        if (is_bool($value)) {
            return 'bool';
        }
        if (is_int($value)) {
            return 'int';
        }
        if (is_float($value)) {
            return 'float';
        }
        if (is_array($value) || is_object($value)) {
            return 'json';
        }
        return 'string';
    }
}
