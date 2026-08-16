<?php

namespace App\Services;

use App\Enums\SettingValueType;
use App\Events\SettingUpdated;
use App\Models\AppSetting;
use App\Models\SettingGroup;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use RuntimeException;

/**
 * Enterprise settings accessor.
 *
 * Usage:
 *   app(SettingsService::class)->get('invoice.prefix', 'INV-');
 *   setting('general.app_name');            // via helpers.php
 *   setting()->set('invoice.prefix', 'F-');
 *
 * All reads are cached; every write flushes the cache and fires
 * a SettingUpdated event (audit + cache invalidation).
 */
class SettingsService
{
    public const CACHE_KEY = 'app_settings.all';

    /**
     * Read one setting, typed by its value_type, falling back to
     * default_value / provided default.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->all();

        $row = $settings->firstWhere('setting_key', $key);

        if (! $row) {
            return $default;
        }

        $value = $row['setting_value'];

        if ($value === null) {
            return $row['default_value'] ?? $default;
        }

        if ($row['is_encrypted']) {
            try {
                $value = Crypt::decryptString($value);
            } catch (\Throwable) {
                return $default;
            }
        }

        $type = SettingValueType::tryFrom($row['value_type']);

        return $type?->cast($value) ?? $value;
    }

    public function getString(string $key, string $default = ''): string
    {
        return (string) ($this->get($key, $default) ?? $default);
    }

    public function getInt(string $key, int $default = 0): int
    {
        return (int) ($this->get($key, $default) ?? $default);
    }

    public function getFloat(string $key, float $default = 0.0): float
    {
        return (float) ($this->get($key, $default) ?? $default);
    }

    public function getBool(string $key, bool $default = false): bool
    {
        return (bool) ($this->get($key, $default) ?? $default);
    }

    public function getJson(string $key, ?array $default = null): ?array
    {
        return $this->get($key, $default);
    }

    /** Write one setting (respects the lock flag). */
    public function set(string $key, mixed $value, ?int $updatedBy = null): AppSetting
    {
        $row = AppSetting::where('setting_key', $key)->first();

        if (! $row) {
            throw new RuntimeException("تنظیم [$key] در رجیستری تعریف نشده است.");
        }

        if ($row->is_locked) {
            throw new RuntimeException("تنظیم [$key] قفل است و قابل تغییر نیست.");
        }

        $oldValue = $row->typedValue();

        $type = SettingValueType::tryFrom($row->value_type) ?? SettingValueType::String;

        $row->update([
            'setting_value' => $row->is_encrypted && $value !== null
                ? Crypt::encryptString((string) $value)
                : $type->encode($value),
            'updated_by'    => $updatedBy ?? auth()->id(),
        ]);

        $this->flush();

        SettingUpdated::dispatch($key, $oldValue, $value, $updatedBy);

        return $row->fresh();
    }

    /** Bulk write — wrapped in a single transaction. */
    public function setMany(array $pairs, ?int $updatedBy = null): array
    {
        $updated = [];

        \DB::transaction(function () use ($pairs, $updatedBy, &$updated) {
            foreach ($pairs as $key => $value) {
                $updated[] = $this->set($key, $value, $updatedBy);
            }
        });

        return $updated;
    }

    /** All settings as a keyed collection (cached). */
    public function all(): Collection
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            return AppSetting::with('group')
                ->get(['setting_key', 'setting_value', 'value_type', 'default_value', 'is_locked', 'is_autoload', 'is_encrypted', 'group_id']);
        });
    }

    /** Autoload subset — warmed at boot for instant Electron access. */
    public function autoload(): Collection
    {
        return $this->all()->where('is_autoload', true);
    }

    public function group(string $code): Collection
    {
        return $this->all()->filter(
            fn ($row) => $row->group?->code === $code
        );
    }

    /** Typed value for one key via config() fallback (helpers use this). */
    public function getOrConfig(string $key): mixed
    {
        return $this->get($key, config('settings.defaults.' . $key));
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Sync the config/settings.php registry into the database.
     * Adds missing groups & settings; never touches locked rows.
     */
    public function syncRegistry(?int $updatedBy = null): array
    {
        $registry = config('settings');
        $report = ['groups' => 0, 'settings' => 0];

        \DB::transaction(function () use ($registry, $updatedBy, &$report) {
            $groups = collect($registry['groups']);

            foreach ($groups as $g) {
                SettingGroup::updateOrCreate(
                    ['code' => $g['code']],
                    ['label' => $g['label'], 'icon' => $g['icon'] ?? null, 'sort_order' => $g['sort_order']]
                );
                $report['groups']++;
            }

            foreach ($registry['settings'] as $def) {
                $group = SettingGroup::where('code', $def['group'])->first();

                AppSetting::firstOrCreate(
                    ['setting_key' => $def['key']],
                    [
                        'group_id'      => $group?->id,
                        'setting_value' => $def['default'],
                        'value_type'    => $def['type'],
                        'default_value' => $def['default'],
                        'is_locked'     => $def['locked'] ?? false,
                        'is_autoload'   => $def['autoload'] ?? false,
                        'is_encrypted'  => $def['encrypted'] ?? false,
                        'description'   => $def['label'],
                        'updated_by'    => $updatedBy,
                    ]
                );
                $report['settings']++;
            }
        });

        $this->flush();

        return $report;
    }

    /** Reset a single setting back to its registry default. */
    public function resetToDefault(string $key): AppSetting
    {
        $row = AppSetting::where('setting_key', $key)->firstOrFail();

        if ($row->is_locked) {
            throw new RuntimeException("تنظیم [$key] قفل است.");
        }

        $row->update(['setting_value' => $row->default_value]);
        $this->flush();

        return $row->fresh();
    }

    public function definition(string $key): ?array
    {
        return collect(config('settings.settings'))->firstWhere('key', $key);
    }
}
