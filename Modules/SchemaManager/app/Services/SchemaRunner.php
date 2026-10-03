<?php

declare(strict_types=1);

namespace Modules\SchemaManager\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\SchemaManager\Schema\SchemaBootstrapper;
use Throwable;

/**
 * چسب Laravel برای SchemaBootstrapper: مسیرها، اتصال دیتابیس و لاگ.
 * روی نصب تازه (دیتابیس خالی) اسکیمای کامل را می‌سازد؛ روی نصب موجود فقط افزایشی هم‌سطح می‌کند.
 */
final class SchemaRunner
{
    /** @var array<string,mixed>|null گزارش آخرین اجرا در همین پروسه */
    private static ?array $last = null;

    public function schemaDir(): string
    {
        return (string) config('schemamanager.dir', database_path('schema'));
    }

    public function hasBaseline(): bool
    {
        return is_file($this->schemaDir() . '/baseline.sql');
    }

    /** @return array<string,mixed> */
    public function ensure(bool $force = false): array
    {
        if (! $force && self::$last !== null) {
            return self::$last;
        }

        $skip = fn (string $why): array => self::$last = ['status' => 'skipped', 'reason' => $why, 'fresh' => false, 'created_tables' => [], 'added_columns' => [], 'upgrades' => [], 'warnings' => [], 'error' => null];

        if (! config('schemamanager.enabled', true)) {
            return $skip('disabled');
        }
        if (! $this->hasBaseline()) {
            return $skip('no_baseline');
        }

        $conn = DB::connection();
        if ($conn->getDriverName() !== 'sqlite') {
            return $skip('not_sqlite');
        }
        $name = $conn->getDatabaseName();
        if (! is_string($name) || $name === ':memory:' || ! is_file($name)) {
            return $skip('db_file_missing'); // فایل را خودمان نمی‌سازیم (NativePHP / مدیر پروژه می‌سازد)
        }

        try {
            $boot = new SchemaBootstrapper($conn->getPdo(), $this->schemaDir(), storage_path('app/schema-backups'));
            $r    = $boot->ensure();
        } catch (Throwable $e) {
            $r = ['status' => 'error', 'fresh' => false, 'created_tables' => [], 'added_columns' => [], 'upgrades' => [], 'warnings' => [], 'error' => $e->getMessage()];
        }

        $ctx = ['tables' => $r['created_tables'], 'columns' => $r['added_columns'], 'upgrades' => $r['upgrades']];
        match (true) {
            $r['status'] === 'error'                                     => Log::error('schema: ' . $r['error']),
            in_array($r['status'], ['fresh', 'upgraded'], true)          => Log::info('schema: ' . $r['status'], $ctx),
            default                                                      => null,
        };
        foreach ($r['warnings'] as $w) {
            Log::warning('schema: ' . $w);
        }

        return self::$last = $r;
    }
}