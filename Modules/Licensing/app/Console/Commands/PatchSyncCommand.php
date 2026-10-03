<?php

declare(strict_types=1);

namespace Modules\Licensing\Console\Commands;

use Illuminate\Console\Command;
use Modules\Licensing\Services\PatchService;

class PatchSyncCommand extends Command
{
    protected $signature = 'licensing:patch-sync';

    protected $description = 'دریافت فهرست پچ‌های قابل‌اعمال از StoreServer';

    public function handle(PatchService $patches): int
    {
        $r = $patches->sync();
        $r['ok'] ? $this->info($r['message']) : $this->error($r['message']);

        foreach ($patches->listing()['patches'] as $p) {
            $this->line(sprintf('  %-24s %s → %s  [%s]', $p['code'], $p['from_min'], $p['to_version'], $p['status']));
        }

        return $r['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
