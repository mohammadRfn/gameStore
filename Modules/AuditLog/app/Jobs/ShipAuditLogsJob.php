<?php

declare(strict_types=1);

namespace Modules\AuditLog\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\AuditLog\Services\LogShippingService;

/**
 * ارسال غیرهمزمان لاگ‌ها به StoreServer.
 * unique است تا چند worker هم‌زمان یک دسته را ارسال نکنند.
 */
class ShipAuditLogsJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;          // تلاش مجدد توسط خود سرویس (backoff) مدیریت می‌شود
    public int $timeout = 300;
    public int $uniqueFor = 600;

    public function __construct(public readonly ?int $maxBatches = null)
    {
    }

    public function uniqueId(): string
    {
        return 'auditlog-ship';
    }

    public function handle(LogShippingService $shipping): void
    {
        $shipping->releaseStuck();
        $shipping->shipPending($this->maxBatches);
    }
}
