<?php

declare(strict_types=1);

use Modules\AuditLog\Services\AuditLogger;

if (! function_exists('audit')) {
    /**
     * دسترسی سریع به سرویس لاگ‌گیری در هر جای پروژه:
     *   audit()->business('invoice.created', 'فاکتور ثبت شد', $invoice);
     */
    function audit(): AuditLogger
    {
        return app(AuditLogger::class);
    }
}

if (! function_exists('audit_without_logging')) {
    /**
     * اجرای بخشی از کد بدون ثبت لاگ (مثلاً seed یا import انبوه).
     */
    function audit_without_logging(callable $callback): mixed
    {
        return app(AuditLogger::class)->withoutLogging($callback);
    }
}
