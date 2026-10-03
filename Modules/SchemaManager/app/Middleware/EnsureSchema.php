<?php

declare(strict_types=1);

namespace Modules\SchemaManager\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\SchemaManager\Services\SchemaRunner;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * پیش از هر چیز (حتی احراز هویت و گیت لایسنس) مطمئن می‌شود اسکیمای دیتابیس کامل است.
 * روی نصب تازه‌ی مشتری، اولین درخواست جدول‌ها را می‌سازد.
 */
class EnsureSchema
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            app(SchemaRunner::class)->ensure();
        } catch (Throwable $e) {
            report($e); // خطای اسکیما نباید صفحه را از کار بیندازد؛ در لاگ می‌ماند
        }

        return $next($request);
    }
}