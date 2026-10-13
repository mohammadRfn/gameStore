<?php

declare(strict_types=1);

namespace Modules\Setting\Console\Commands;

use Illuminate\Console\Command;
use Modules\Setting\Services\Desktop\ElectronPatcher;

/**
 * قبل از ساخت نصب‌کننده (php artisan native:build win) و بعد از هر composer install/update اجرا شود:
 *   php artisan desktop:patch-electron
 */
class PatchElectronCommand extends Command
{
    protected $signature = 'desktop:patch-electron {--path= : مسیر electron-plugin/dist (پیش‌فرض: داخل vendor)}';

    protected $description = 'پچ Electron برای بستن پنجره به Tray (قبل از native:build)';

    public function handle(ElectronPatcher $patcher): int
    {
        $dist = (string) ($this->option('path') ?: ElectronPatcher::defaultDistPath());
        $failed = false;

        foreach ($patcher->patch($dist) as $file => $result) {
            $line = "{$file}: {$result['message']}";
            $result['ok'] ? $this->info($line) : $this->error($line);
            $failed = $failed || ! $result['ok'];
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}