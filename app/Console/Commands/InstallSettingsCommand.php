<?php

namespace App\Console\Commands;

use App\Services\SettingsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

class InstallSettingsCommand extends Command
{
    protected $signature = 'settings:install
                            {--force : Run even if settings already exist}';

    protected $description = 'Install the enterprise settings registry (groups + defaults) from config/settings.php';

    public function handle(SettingsService $settings): int
    {
        $hasSettings = \DB::table('app_settings')->exists();

        if ($hasSettings && ! $this->option('force')) {
            $this->warn('تنظیمات از قبل نصب شده است. برای اجرای مجدد از --force استفاده کنید.');

            return self::SUCCESS;
        }

        if (! \Schema::hasTable('app_settings')) {
            $this->info('اجرای مایگریشن‌های ماژول تنظیمات...');
            Artisan::call('migrate', ['--force' => true]);
        }

        $report = $settings->syncRegistry();

        $this->info(sprintf(
            '✅ رجیستری تنظیمات نصب شد: %d گروه، %d تنظیم (کش پاک شد).',
            $report['groups'],
            $report['settings']
        ));

        return self::SUCCESS;
    }
}
