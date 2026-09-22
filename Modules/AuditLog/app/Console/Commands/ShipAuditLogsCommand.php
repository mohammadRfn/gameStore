<?php

declare(strict_types=1);

namespace Modules\AuditLog\Console\Commands;

use Illuminate\Console\Command;
use Modules\AuditLog\Jobs\ShipAuditLogsJob;
use Modules\AuditLog\Services\LogShippingService;

/**
 * php artisan auditlog:ship --batches=5
 */
class ShipAuditLogsCommand extends Command
{
    protected $signature = 'auditlog:ship
                            {--batches= : حداکثر تعداد دسته در این اجرا}
                            {--queue : ارسال از طریق صف به‌جای اجرای همزمان}
                            {--retry-failed : ابتدا رکوردهای ناموفق را دوباره در صف قرار بده}
                            {--release-stuck : آزادسازی رکوردهای گیرکرده در وضعیت sending}';

    protected $description = 'ارسال لاگ‌های ثبت‌شده‌ی گیم‌استور به StoreServer';

    public function handle(LogShippingService $shipping): int
    {
        if ($this->option('retry-failed')) {
            $this->info(sprintf('%d رکورد ناموفق دوباره در صف قرار گرفت.', $shipping->retryFailed()));
        }

        if ($this->option('release-stuck')) {
            $this->info(sprintf('%d رکورد گیرکرده آزاد شد.', $shipping->releaseStuck()));
        }

        $batches = $this->option('batches') !== null ? (int) $this->option('batches') : null;

        if ($this->option('queue')) {
            ShipAuditLogsJob::dispatch($batches);
            $this->info('ارسال لاگ‌ها به صف سپرده شد.');

            return self::SUCCESS;
        }

        $summary = $shipping->shipPending($batches);

        $this->table(['شاخص', 'مقدار'], [
            ['دسته‌های ارسال‌شده', $summary['batches']],
            ['رکوردهای موفق', $summary['sent']],
            ['رکوردهای ناموفق', $summary['failed']],
            ['رد شده', $summary['skipped'] ? 'بله' : 'خیر'],
        ]);

        foreach ($summary['errors'] as $error) {
            $this->warn('• ' . $error);
        }

        return $summary['failed'] > 0 ? self::FAILURE : self::SUCCESS;
    }
}
