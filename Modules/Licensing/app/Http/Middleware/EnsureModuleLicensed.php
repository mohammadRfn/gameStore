<?php

declare(strict_types=1);

namespace Modules\Licensing\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\Licensing\Services\LicenseGate;
use Symfony\Component\HttpFoundation\Response;

class EnsureModuleLicensed
{
    public function __construct(private readonly LicenseGate $gate) {}

    public function handle(Request $request, Closure $next, string $module): Response
    {
        if ($this->gate->allows($module)) {
            return $next($request);
        }

        // روت‌های عمومی (مثل منوی دیجیتال مشتری): وجود ماژول را لو نده
        if (! $request->user()) {
            abort(404);
        }

        $message = 'این بخش در پلن فعلی شما فعال نیست.';

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => $message, 'code' => 'MODULE_NOT_LICENSED'], 403);
        }

        return redirect()->route('dashboard')->with('error', $message);
    }
}