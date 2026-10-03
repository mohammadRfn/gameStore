<?php

declare(strict_types=1);

namespace Modules\Licensing\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Licensing\Models\LicenseState;
use Modules\Licensing\Services\AppVersion;
use Throwable;

/**
 * بررسی سلامت پس از اعمال پچ. در یک فرایند تازه با کدِ پچ‌شده اجرا می‌شود؛ هر خطای
 * فاتال هنگام بوت (provider/کلاس خراب) یا نبودن دیتابیس یعنی exit code غیرصفر → rollback.
 */
class PatchHealthCommand extends Command
{
    protected $signature = 'licensing:patch-health';

    protected $description = 'بررسی سلامت برنامه پس از نصب پچ (بوت، دیتابیس، وضعیت لایسنس)';

    public function handle(): int
    {
        try {
            DB::connection()->select('select 1');
            LicenseState::current();
            $this->info('OK version=' . AppVersion::current());

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('HEALTH_FAIL: ' . $e->getMessage());

            return self::FAILURE;
        }
    }
}
