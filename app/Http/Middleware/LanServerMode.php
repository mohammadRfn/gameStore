<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * فقط در پروسه‌ی سرور LAN (پورت 8000) فعال است.
 */
class LanServerMode
{
    public function handle(Request $request, Closure $next)
    {
        if (getenv('GAMESHOP_LAN_SERVER') !== '1') {
            return $next($request);
        }

        // اندپوینت‌های داخلی NativePHP هرگز نباید از شبکه در دسترس باشند
        abort_if($request->is('_native/*'), 404);

        // گارد «جلوگیری از مرورگر معمولی» فقط برای همین پروسه‌ی LAN رد می‌شود
        config(['nativephp-internal.running' => false]);

        return $next($request);
    }
}