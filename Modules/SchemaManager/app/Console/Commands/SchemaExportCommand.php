<?php

declare(strict_types=1);

namespace Modules\SchemaManager\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\SchemaManager\Schema\SchemaExporter;
use Modules\SchemaManager\Services\SchemaRunner;

/**
 * پیش از هر build نصبی اجرا شود: اسکیمای دیتابیس فعلی توسعه را به database/schema/baseline.sql تبدیل می‌کند.
 * هیچ داده‌ای از دیتابیس توسعه خارج نمی‌شود، مگر جدول‌هایی که صراحتاً با --seed بدهید.
 */
class SchemaExportCommand extends Command
{
    protected $signature = 'schema:export
        {--seed=* : جدول‌هایی که ردیف‌هایشان به‌عنوان داده‌ی اولیه نصب تازه باشد (پیش‌فرض: schemamanager.seed_tables)}
        {--exclude=* : جدول‌های اضافی که نباید وارد baseline شوند}
        {--release= : برچسب نسخه برای meta (پیش‌فرض: نسخه‌ی نصب‌کننده)}';

    protected $description = 'ساخت baseline اسکیمای نصب تازه از دیتابیس فعلی (database/schema)';

    public function handle(SchemaRunner $schema): int
    {
        $conn = DB::connection();
        if ($conn->getDriverName() !== 'sqlite') {
            $this->error('فقط SQLite پشتیبانی می‌شود.');

            return self::FAILURE;
        }

        $seed    = array_values(array_filter((array) ($this->option('seed') ?: config('schemamanager.seed_tables', ['migrations']))));
        $exclude = array_values(array_unique(array_merge(
            ['schema_patches', 'license_patches'],
            (array) config('schemamanager.exclude', []),
            (array) $this->option('exclude'),
        )));

        $this->line('دیتابیس: ' . $conn->getDatabaseName());
        $exporter = new SchemaExporter($conn->getPdo(), $exclude);

        try {
            $r = $exporter->export($schema->schemaDir(), $seed, (string) ($this->option('release') ?: config('nativephp.version', '')));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info(count($r['tables']) . ' جدول وارد baseline شد:');
        $this->line('  ' . implode('، ', $r['tables']));
        $this->line("  شیء‌های ساختاری (جدول/ایندکس/تریگر/ویو): {$r['objects']}");
        $this->line("  ردیف‌های seed: {$r['seed_rows']} از " . ($seed ? implode('، ', $seed) : '—'));
        if ($r['upgrades']) {
            $this->line('  upgrades شامل‌شده در baseline: ' . implode('، ', $r['upgrades']));
        }
        $this->newLine();

        // بلافاصله اثبات کن که خروجی درست است
        return $this->call('schema:verify');
    }
}