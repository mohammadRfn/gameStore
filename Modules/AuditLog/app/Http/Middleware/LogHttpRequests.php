<?php

declare(strict_types=1);

namespace Modules\AuditLog\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Modules\AuditLog\Enums\LogLevel;
use Modules\AuditLog\Services\AuditLogger;
use Modules\AuditLog\Support\AuditContext;
use Modules\AuditLog\Support\LogSanitizer;
use Symfony\Component\HttpFoundation\Response;

/**
 * لاگ درخواست‌های HTTP. لاگ پس از ارسال پاسخ به کاربر (terminate)
 * نوشته می‌شود تا هیچ تأخیری به تجربه‌ی کاربر اضافه نکند.
 */
class LogHttpRequests
{
    private float $startedAt;

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly LogSanitizer $sanitizer,
        private readonly AuditContext $context,
    ) {
        $this->startedAt = defined('LARAVEL_START') ? (float) LARAVEL_START : microtime(true);
    }

    public function handle(Request $request, Closure $next): Response
    {
        $this->startedAt = microtime(true);

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            if (! $this->shouldLog($request, $response)) {
                return;
            }

            // ممکن است کاربر در طول درخواست لاگین شده باشد
            $this->context->resolveActor($request->user());

            $durationMs = (int) round((microtime(true) - $this->startedAt) * 1000);
            $status = $response->getStatusCode();

            $this->audit->http(
                description: sprintf('%s %s → %d', $request->getMethod(), $request->path(), $status),
                context: $this->context($request, $response),
                statusCode: $status,
                durationMs: $durationMs,
                level: $this->level($status, $durationMs),
            );

            $this->audit->flush();
        } catch (\Throwable) {
            // لاگ‌گیری نباید چرخه‌ی پاسخ را خراب کند
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function context(Request $request, Response $response): array
    {
        $context = [
            'path'       => $request->path(),
            'route_name' => $request->route()?->getName(),
            'action'     => $request->route()?->getActionName(),
            'query'      => $this->sanitizer->sanitize($request->query()),
            'referer'    => $request->headers->get('referer'),
            'ajax'       => $request->ajax(),
        ];

        $maxKb = (int) config('auditlog.capture.http.max_body_kb', 64);

        if ((bool) config('auditlog.capture.http.store_payload', true) && ! $request->isMethod('GET')) {
            $payload = (array) $this->sanitizer->sanitize($request->except(['password', 'password_confirmation']));
            $context['payload'] = $this->sanitizer->limitSize($payload, $maxKb);

            if ($request->allFiles() !== []) {
                $context['files'] = array_keys($request->allFiles());
            }
        }

        if ((bool) config('auditlog.capture.http.store_response', false)) {
            $content = $response->getContent();
            $context['response'] = is_string($content)
                ? mb_substr($content, 0, $maxKb * 1024)
                : null;
        }

        return array_filter($context, static fn ($value) => $value !== null && $value !== []);
    }

    private function shouldLog(Request $request, Response $response): bool
    {
        if (! (bool) config('auditlog.capture.http.enabled', true)) {
            return false;
        }

        foreach ((array) config('auditlog.capture.http.except', []) as $pattern) {
            if ($request->is((string) $pattern)) {
                return false;
            }
        }

        $status = $response->getStatusCode();

        if ($status >= (int) config('auditlog.capture.http.error_status', 400)) {
            return true;
        }

        $durationMs = (microtime(true) - $this->startedAt) * 1000;

        if ($durationMs >= (int) config('auditlog.capture.http.slow_ms', 1500)) {
            return true;
        }

        if (in_array($request->getMethod(), (array) config('auditlog.capture.http.methods', []), true)) {
            return true;
        }

        return (bool) config('auditlog.capture.http.log_reads', false);
    }

    private function level(int $status, int $durationMs): LogLevel
    {
        return match (true) {
            $status >= 500 => LogLevel::Error,
            $status >= 400 => LogLevel::Warning,
            $durationMs >= (int) config('auditlog.capture.http.slow_ms', 1500) => LogLevel::Notice,
            default        => LogLevel::Info,
        };
    }
}
