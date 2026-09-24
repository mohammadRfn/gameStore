<?php

declare(strict_types=1);

namespace Modules\Licensing\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Licensing\Models\LicenseState;
use Symfony\Component\HttpFoundation\Response;

/**
 * قبل از هر چیز دیگری (حتی صفحه‌ی لاگین) بررسی می‌کند که این نصب فعال و
 * قفل‌نشده باشد؛ در غیر این صورت به صفحه‌ی فعال‌سازی هدایت می‌کند.
 */
class RequireActiveLicense
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('licensing.*') || $request->is('build/*') || $request->is('up')) {
            return $next($request);
        }

        $state = LicenseState::current();

        if ($state->isActive() && ! $state->isLocked()) {
            return $next($request);
        }

        return redirect()->route('licensing.activate');
    }
}