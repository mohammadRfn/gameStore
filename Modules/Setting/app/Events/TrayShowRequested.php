<?php

declare(strict_types=1);

namespace Modules\Setting\Events;

/**
 * کلیک روی «نمایش برنامه» در منوی آیکن Tray.
 * Electron با payload به شکل {item, combo} این کلاس را می‌سازد؛ پس این دو پارامتر باید وجود داشته باشند.
 */
final class TrayShowRequested
{
    /**
     * @param array<string,mixed> $item
     * @param array<string,mixed> $combo
     */
    public function __construct(
        public readonly array $item = [],
        public readonly array $combo = [],
    ) {
    }
}