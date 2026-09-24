<?php

declare(strict_types=1);

namespace Modules\Licensing\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Licensing\Models\LicenseState;
use Modules\Licensing\Services\LicenseGate;
use Modules\Licensing\Services\LicensingService;
use Symfony\Component\HttpFoundation\Response;

/**
 * قبل از هر چیز دیگری (حتی صفحه‌ی لاگین) بررسی می‌کند که این نصب فعال و
 * قفل‌نشده باشد؛ در غیر این صورت به صفحه‌ی فعال‌سازی هدایت می‌کند.
 */
class RequireActiveLicense
{
    public function __construct(
        private readonly LicenseGate $gate,
        private readonly LicensingService $licensing,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->routeIs('licensing.*') || $request->is('build/*') || $request->is('up')) {
            return $next($request);
        }

        $state = LicenseState::current();

        if ($state->isActive() && ! $state->isLocked()) {
            if ($this->gate->isUsable()) {
                return $next($request);
            }

            // توکن منقضی یا نامعتبر: یک‌بار تلاش برای تمدید از سرور
            $this->licensing->sendHeartbeatNow();
            $this->gate->flush();

            if ($this->gate->isUsable()) {
                return $next($request);
            }

            $state = LicenseState::current();

            if ($state->isActive()) {
                $state->forceFill([
                    'status'      => LicenseState::STATUS_LOCKED,
                    'lock_code'   => 'LICENSE_INVALID',
                    'lock_reason' => 'اعتبار لایسنس تمام شده یا برای تمدید باید به اینترنت وصل شوید.',
                ])->save();
            }
        }

        return redirect()->route('licensing.activate');
    }
}