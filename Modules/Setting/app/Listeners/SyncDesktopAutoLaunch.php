<?php

declare(strict_types=1);

namespace Modules\Setting\Listeners;

use Modules\Setting\Events\SettingsChanged;
use Throwable;

class SyncDesktopAutoLaunch
{
    public function handle(SettingsChanged $event): void
    {
        if (! in_array('desktop.auto_launch', $event->changedKeys(), true)) {
            return;
        }

        if (! class_exists(\Native\Laravel\Facades\App::class)) {
            return; // مثلاً وقتی از مرورگر/تست اجرا می‌شه، این facade وجود نداره
        }

        try {
            \Native\Laravel\Facades\App::openAtLogin(
                (bool) ($event->newValues['desktop.auto_launch'] ?? false)
            );
        } catch (Throwable $e) {
            logger()->channel('settings')->warning(
                'Sync auto_launch failed: ' . $e->getMessage()
            );
        }
    }
}