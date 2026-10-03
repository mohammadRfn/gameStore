<?php

declare(strict_types=1);

namespace Modules\Licensing\Patching\Core;

use Closure;
use PDO;

/**
 * همه‌ی وابستگی‌های بیرونی Installer در یک شیء تا هسته بدون Laravel تست‌پذیر بماند.
 */
final class PatchEnv
{
    /**
     * @param string                        $targetPath        ریشه‌ی کدی که فایل‌های پچ در آن نوشته می‌شود
     * @param string                        $dataPath          پوشه‌ی دانلود/staging/بکاپ (باید در userData باشد)
     * @param list<string>                  $allowedRoots      پوشه‌های سطح‌اول مجاز
     * @param list<string>                  $forbiddenPatterns regex مسیرهای ممنوع
     * @param Closure():PDO                 $pdo               اتصال دیتابیس برنامه
     * @param Closure(string):string        $keyResolver       kid → کلید عمومی خام (۳۲ بایت) یا PatchFailure
     * @param Closure():void|null           $healthCheck       پس از اعمال؛ در شکست PatchFailure پرتاب کند
     * @param Closure(string,int,string):void|null $progress   (stage, percent, message)
     * @param Closure():void|null           $closeDb           بستن اتصال دیتابیس پیش از جایگزینی فایل در restore
     */
    public function __construct(
        public readonly string $targetPath,
        public readonly string $dataPath,
        public readonly array $allowedRoots,
        public readonly array $forbiddenPatterns,
        public readonly Closure $pdo,
        public readonly Closure $keyResolver,
        public readonly ?string $dbFile = null,
        public readonly ?Closure $healthCheck = null,
        public readonly ?Closure $progress = null,
        public readonly ?Closure $closeDb = null,
        public readonly string $phpBinary = PHP_BINARY,
        public readonly bool $allowGitTarget = false,
        public readonly int $keepBackups = 3,
    ) {}

    public function report(string $stage, int $percent, string $message = ''): void
    {
        if ($this->progress !== null) {
            ($this->progress)($stage, max(0, min(100, $percent)), $message);
        }
    }
}
