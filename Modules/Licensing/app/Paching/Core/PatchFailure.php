<?php

declare(strict_types=1);

namespace Modules\Licensing\Patching\Core;

use RuntimeException;

/**
 * خطای قابل‌گزارش در چرخه‌ی پچ. errorCode یک کد ماشینی است (مثل SIGNATURE_INVALID)
 * که در UI و گزارش به سرور استفاده می‌شود.
 */
final class PatchFailure extends RuntimeException
{
    /** آیا سیستم (فایل/دیتابیس) تغییر کرده بود؟ */
    public bool $touched = false;

    /** آیا تغییرات با موفقیت برگردانده شد؟ */
    public bool $rolledBack = false;

    public function __construct(public readonly string $errorCode, string $message, bool $touched = false)
    {
        parent::__construct($message);
        $this->touched = $touched;
    }
}
