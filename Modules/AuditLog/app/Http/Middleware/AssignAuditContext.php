<?php

declare(strict_types=1);

namespace Modules\AuditLog\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\AuditLog\Support\AuditContext;
use Symfony\Component\HttpFoundation\Response;

/**
 * اولین middleware زنجیره: request_id تولید/تشخیص داده می‌شود و
 * زمینه‌ی جاری برای همه‌ی لاگ‌های این درخواست ساخته می‌شود.
 */
class AssignAuditContext
{
    public function __construct(private readonly AuditContext $context)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $this->context->hydrateFromRequest($request);

        $request->attributes->set('request_id', $this->context->requestId());

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Request-Id', $this->context->requestId());

        return $response;
    }
}
