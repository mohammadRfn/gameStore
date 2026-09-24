<?php

declare(strict_types=1);

namespace Modules\Licensing\Console\Commands;

use Illuminate\Console\Command;
use Modules\Licensing\Services\LicensingService;
use Throwable;

class SendHeartbeatCommand extends Command
{
    protected $signature = 'licensing:heartbeat {--force : ارسال فوری صرف‌نظر از فاصله‌ی زمانی}';

    protected $description = 'ارسال heartbeat به StoreServer در صورت رسیدن موعد (یا فوری با --force)';

    public function handle(LicensingService $licensing): int
    {
        try {
            if ($this->option('force')) {
                $licensing->sendHeartbeatNow();
            } else {
                $licensing->sendHeartbeatIfDue();
            }
        } catch (Throwable $e) {
            $this->error('ارسال heartbeat ناموفق بود: ' . $e->getMessage());

            return self::FAILURE;
        }

        $state = $licensing->state();
        $this->info("وضعیت لایسنس: {$state->status}" . ($state->last_heartbeat_at ? ' - آخرین heartbeat: ' . $state->last_heartbeat_at->toIso8601String() : ''));

        return self::SUCCESS;
    }
}