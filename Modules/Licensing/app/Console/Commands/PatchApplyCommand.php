<?php

declare(strict_types=1);

namespace Modules\Licensing\Console\Commands;

use Illuminate\Console\Command;
use Modules\Licensing\Services\PatchService;

/** نصب یک پچ؛ توسط UI در پس‌زمینه صدا زده می‌شود و برای دیباگ دستی هم قابل استفاده است. */
class PatchApplyCommand extends Command
{
    protected $signature = 'licensing:patch-apply {code : کد پچ (patch_code)}';

    protected $description = 'دانلود، بررسی و نصب یک پچ از StoreServer';

    public function handle(PatchService $patches): int
    {
        @set_time_limit(0);
        $ok = $patches->runInstall((string) $this->argument('code'));

        $row = \Modules\Licensing\Models\LicensePatch::query()->where('patch_code', $this->argument('code'))->first();
        $ok ? $this->info('OK: ' . $row?->message) : $this->error('FAILED: [' . $row?->error_code . '] ' . $row?->message);

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
