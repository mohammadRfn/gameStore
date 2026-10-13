<?php

declare(strict_types=1);

namespace Modules\Setting\Listeners;

use Modules\Setting\Services\Desktop\DesktopShell;

/**
 * کلیک/دابل‌کلیک روی آیکن Tray یا «نمایش برنامه» در منوی آن → پنجره‌ی اصلی را نشان بده.
 */
class ShowMainWindow
{
    public function handle(object $event): void
    {
        DesktopShell::showMainWindow();
    }
}