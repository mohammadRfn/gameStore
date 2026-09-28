<?php

declare(strict_types=1);

namespace Modules\Licensing\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Licensing\Services\LicensingService;
use Throwable;

class SendHeartbeatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, SerializesModels;

    public function handle(LicensingService $licensing): void
    {
        try {
            $licensing->sendHeartbeatIfDue();
            $licensing->checkForChangesIfDue();
        } catch (Throwable) {
            // در چرخه‌ی بعدی دوباره تلاش می‌شود
        }
    }
}