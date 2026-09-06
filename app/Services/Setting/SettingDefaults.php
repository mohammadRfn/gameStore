<?php

declare(strict_types=1);

namespace App\Services\Setting;

use App\Enums\Settings\SettingGroup;

/**
 * کلاس مرکزی برای نگه‌داری مقادیر پیش‌فرض تنظیمات.
 * این کلاس meta را از config می‌خواند و API ساده‌ای برای دسترسی به defaults
 * فراهم می‌کند. در نبود مقدار ذخیره‌شده در دیتابیس، از این مقادیر استفاده می‌شود.
 */
final class SettingDefaults
{
    /** @var array<string,array{default:mixed,type:string,enum?:class-string|null,rules:array<int,string>,group:SettingGroup,label:string,section:string}> */
    private array $meta;

    /** @var array<string,array<string>> */
    private array $groupIndex = [];

    public function __construct()
    {
        /** @var array<string,array> $raw */
        $raw = config('setting.meta', []);
        $this->meta = $raw;
        $this->buildGroupIndex();
    }

    /**
     * مقدار پیش‌فرض برای یک کلید.
     */
    public function defaultFor(string $key): mixed
    {
        if (! isset($this->meta[$key])) {
            return null;
        }

        return $this->meta[$key]['default'];
    }

    /**
     * همه‌ی مقادیر پیش‌فرض به‌صورت آرایه flat.
     *
     * @return array<string,mixed>
     */
    public function all(): array
    {
        $out = [];
        foreach ($this->meta as $key => $entry) {
            $out[$key] = $entry['default'];
        }
        return $out;
    }

    /**
     * همه‌ی مقادیر پیش‌فرض یک گروه.
     *
     * @return array<string,mixed>
     */
    public function defaultsForGroup(SettingGroup $group): array
    {
        $out = [];
        foreach ($this->meta as $key => $entry) {
            if ($entry['group'] === $group) {
                $out[$key] = $entry['default'];
            }
        }
        return $out;
    }

    /**
     * متادیتای کامل یک کلید (type, rules, default, ...).
     *
     * @return array{default:mixed,type:string,enum?:class-string|null,rules:array<int,string>,group:SettingGroup,label:string,section:string}|null
     */
    public function metaFor(string $key): ?array
    {
        return $this->meta[$key] ?? null;
    }

    /**
     * همه‌ی متادیتا.
     *
     * @return array<string,array>
     */
    public function allMeta(): array
    {
        return $this->meta;
    }

    /**
     * لیست کلیدهای متعلق به یک گروه.
     *
     * @return array<int,string>
     */
    public function keysForGroup(SettingGroup $group): array
    {
        return $this->groupIndex[$group->value] ?? [];
    }

    /**
     * آیا کلید معتبر است و در meta تعریف شده؟
     */
    public function exists(string $key): bool
    {
        return isset($this->meta[$key]);
    }

    /**
     * گروه مربوط به یک کلید.
     */
    public function groupOf(string $key): ?SettingGroup
    {
        return $this->meta[$key]['group'] ?? null;
    }

    /**
     * rules validation برای یک کلید.
     *
     * @return array<int,string>
     */
    public function rulesFor(string $key): array
    {
        return $this->meta[$key]['rules'] ?? [];
    }

    /**
     * ساخت ایندکس سریع برای جست‌وجو بر اساس گروه.
     */
    private function buildGroupIndex(): void
    {
        foreach ($this->meta as $key => $entry) {
            $group = $entry['group'] instanceof SettingGroup
                ? $entry['group']->value
                : (string) $entry['group'];
            $this->groupIndex[$group][] = $key;
        }
    }
}
