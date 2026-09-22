<?php

declare(strict_types=1);

namespace Modules\AuditLog\Console\Commands;

use Illuminate\Console\Command;
use Modules\AuditLog\Exceptions\LogShippingException;
use Modules\AuditLog\Services\LogShippingService;
use Modules\AuditLog\Services\StoreServerClient;

/**
 * php artisan auditlog:status --ping
 */
class AuditLogStatusCommand extends Command
{
    protected $signature = 'auditlog:status {--ping : تست اتصال به StoreServer}';

    protected $description = 'نمایش وضعیت صف ارسال لاگ‌ها و سلامت اتصال به StoreServer';

    public function handle(LogShippingService $shipping, StoreServerClient $client): int
    {
        $status = $shipping->status();

        $this->info('وضعیت ماژول AuditLog');
        $this->table(['شاخص', 'مقدار'], [
            ['ارسال فعال', $status['enabled'] ? 'بله' : 'خیر'],
            ['اندپوینت', $status['endpoint'] !== '' ? $status['endpoint'] : '— تنظیم نشده —'],
            ['در انتظار ارسال', $status['pending']],
            ['ناموفق دائمی (dead)', $status['dead']],
            ['آخرین اجرا', $status['last_run_at'] ?? '—'],
            ['قدیمی‌ترین رکورد در صف', $status['oldest_pending'] ?? '—'],
        ]);

        $counts = [];

        foreach ($status['counts'] as $key => $value) {
            $counts[] = [$key, $value];
        }

        if ($counts !== []) {
            $this->table(['وضعیت همگام‌سازی', 'تعداد'], $counts);
        }

        if ($this->option('ping')) {
            try {
                $result = $client->ping();
                $this->info(sprintf('اتصال برقرار است (HTTP %d در %d میلی‌ثانیه).', $result['status'], $result['duration_ms']));
            } catch (LogShippingException $e) {
                $this->error(sprintf('[%s] %s', $e->errorCode, $e->getMessage()));

                return self::FAILURE;
            }
        }

        return self::SUCCESS;
    }
}
