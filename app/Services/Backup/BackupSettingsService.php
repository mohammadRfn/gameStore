<?php

namespace App\Services\Backup;

use App\Models\BackupSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * لایه‌ی دسترسی به تنظیمات ماژول بکاپ.
 *
 * اولویت مقادیر:  دیتابیس (backup_settings)  >  config/backup.php  >  پیش‌فرض کد
 * مقادیر کش می‌شوند تا در حلقه‌های سنگین اکسپورت، کوئری اضافه زده نشود.
 */
class BackupSettingsService
{
    private const CACHE_KEY = 'backup.settings';
    private const CACHE_TTL = 300;

    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    /** @return array<string, mixed> */
    public function all(?int $shopId = null): array
    {
        if ($this->cache !== null && $shopId === null) {
            return $this->cache;
        }

        $values = Cache::remember(self::CACHE_KEY . ':' . ($shopId ?? 'global'), self::CACHE_TTL, function () use ($shopId) {
            if (! Schema::hasTable('backup_settings')) {
                return [];
            }

            return BackupSetting::query()
                ->when($shopId !== null, fn ($q) => $q->where(fn ($w) => $w->whereNull('shop_id')->orWhere('shop_id', $shopId)))
                ->when($shopId === null, fn ($q) => $q->whereNull('shop_id'))
                ->orderBy('shop_id') // مقادیر اختصاصی فروشگاه، مقادیر عمومی را override می‌کنند
                ->get()
                ->mapWithKeys(fn (BackupSetting $s) => [$s->key => $s->typedValue()])
                ->all();
        });

        if ($shopId === null) {
            $this->cache = $values;
        }

        return $values;
    }

    public function get(string $key, mixed $default = null, ?int $shopId = null): mixed
    {
        $value = $this->all($shopId)[$key] ?? null;

        if ($value === null || $value === '') {
            return $default ?? config("backup.{$this->configPath($key)}", $default);
        }

        return $value;
    }

    public function bool(string $key, bool $default = false): bool
    {
        $value = $this->get($key, $default);

        return is_bool($value) ? $value : filter_var($value, FILTER_VALIDATE_BOOL);
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }

    /** @param array<string, mixed> $values */
    public function setMany(array $values, ?int $shopId = null, ?int $actorId = null): array
    {
        foreach ($values as $key => $value) {
            $this->set($key, $value, $shopId, $actorId);
        }

        return $this->all($shopId);
    }

    public function set(string $key, mixed $value, ?int $shopId = null, ?int $actorId = null): BackupSetting
    {
        $type = match (true) {
            is_bool($value)              => 'boolean',
            is_int($value)               => 'integer',
            is_array($value)             => 'json',
            str_ends_with($key, '_path') => 'path',
            default                      => 'string',
        };

        $stored = match ($type) {
            'boolean' => $value ? '1' : '0',
            'json'    => json_encode($value, JSON_UNESCAPED_UNICODE),
            default   => $value === null ? null : (string) $value,
        };

        $setting = BackupSetting::query()->updateOrCreate(
            ['shop_id' => $shopId, 'key' => $key],
            ['value' => $stored, 'value_type' => $type, 'updated_by' => $actorId],
        );

        $this->flush($shopId);

        return $setting;
    }

    public function flush(?int $shopId = null): void
    {
        $this->cache = null;
        Cache::forget(self::CACHE_KEY . ':' . ($shopId ?? 'global'));
        Cache::forget(self::CACHE_KEY . ':global');
    }

    /** نگاشت کلید تنظیمات به مسیر معادل در config/backup.php */
    private function configPath(string $key): string
    {
        return match ($key) {
            'export_root_path'        => 'paths.export_root',
            'import_root_path'        => 'paths.import_root',
            'csv_delimiter'           => 'csv.delimiter',
            'csv_enclosure'           => 'csv.enclosure',
            'csv_null_marker'         => 'csv.null_marker',
            'csv_bom'                 => 'csv.bom',
            'chunk_size'              => 'runtime.chunk_size',
            'retention_copies'        => 'runtime.retention_copies',
            'auto_safety_backup'      => 'runtime.auto_safety_backup',
            'verify_checksums'        => 'runtime.verify_checksums',
            'include_soft_deleted'    => 'runtime.include_soft_deleted',
            'include_media'           => 'media.disk',
            default                   => "runtime.{$key}",
        };
    }
}
