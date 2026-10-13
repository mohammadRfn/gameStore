<?php

declare(strict_types=1);

namespace Modules\Setting\Listeners;

use Modules\Setting\Events\SettingsChanged;
use Modules\Setting\Services\Desktop\DesktopShell;

/**
 * تغییر تنظیم‌های دسکتاپ را بلافاصله به Electron می‌رساند:
 *  - desktop.auto_launch      → اجرای خودکار با ویندوز
 *  - desktop.minimize_to_tray → بستن پنجره به Tray
 */
class SyncDesktopAutoLaunch
{
    public function handle(SettingsChanged $event): void
    {
        if (! class_exists(\Native\Laravel\Facades\App::class)) {
            return; // مثلاً وقتی از مرورگر/تست اجرا می‌شه، این facade وجود نداره
        }

        $changed = $event->changedKeys();

        if (in_array('desktop.auto_launch', $changed, true)) {
            DesktopShell::syncAutoLaunch((bool) ($event->newValues['desktop.auto_launch'] ?? false));
        }

        if (in_array('desktop.minimize_to_tray', $changed, true)) {
            DesktopShell::syncHideOnClose((bool) ($event->newValues['desktop.minimize_to_tray'] ?? false));
        }
    }
}