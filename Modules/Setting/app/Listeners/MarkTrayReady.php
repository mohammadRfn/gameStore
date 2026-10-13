<?php

declare(strict_types=1);

namespace Modules\Setting\Listeners;

use Modules\Setting\Services\Desktop\DesktopShell;

/**
 * Electron خبر داد آیکن Tray ساخته شد → حالا بستن پنجره به Tray مجاز است.
 */
class MarkTrayReady
{
    public function handle(object $event): void
    {
        DesktopShell::onTrayCreated();
    }
}