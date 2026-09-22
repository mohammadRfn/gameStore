<?php

declare(strict_types=1);

namespace Modules\AuditLog\Listeners;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Events\Dispatcher;
use Illuminate\Log\Events\MessageLogged;
use Modules\AuditLog\Enums\AuditAction;
use Modules\AuditLog\Enums\LogChannel;
use Modules\AuditLog\Enums\LogLevel;
use Modules\AuditLog\Services\AuditLogger;
use Throwable;

/**
 * پل بین لاگر پیش‌فرض لاراول و Audit Trail:
 *   - هر Log::error()/critical() و هر استثنای report شده اینجا گرفته می‌شود
 *     (بدون نیاز به دست‌زدن به bootstrap/app.php).
 *   - کوئری‌های کند (اختیاری) نیز ثبت می‌شوند.
 */
class ApplicationLogSubscriber
{
    private bool $handling = false;

    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function handleMessageLogged(MessageLogged $event): void
    {
        if ($this->handling || ! (bool) config('auditlog.capture.error.enabled', true)) {
            return;
        }

        $levels = (array) config('auditlog.capture.error.levels', ['error', 'critical', 'alert', 'emergency']);

        if (! in_array(strtolower($event->level), $levels, true)) {
            return;
        }

        // پیام‌های خود ماژول دوباره لاگ نمی‌شوند
        if (str_starts_with($event->message, '[AuditLog]')) {
            return;
        }

        $this->handling = true;

        try {
            $exception = $event->context['exception'] ?? null;

            if ($exception instanceof Throwable) {
                $this->audit->exception($exception, array_diff_key($event->context, ['exception' => true]), LogLevel::fromPsr($event->level));

                return;
            }

            $this->audit->log(
                channel: LogChannel::Error,
                action: AuditAction::ErrorReported,
                description: mb_substr($event->message, 0, 1024),
                level: LogLevel::fromPsr($event->level),
                context: $event->context,
                tags: ['laravel-log'],
            );
        } catch (Throwable) {
            // بی‌صدا؛ نباید لاگر اصلی را بشکند
        } finally {
            $this->handling = false;
        }
    }

    public function handleQueryExecuted(QueryExecuted $event): void
    {
        if (! (bool) config('auditlog.capture.slow_query.enabled', false)) {
            return;
        }

        $threshold = (float) config('auditlog.capture.slow_query.threshold_ms', 500);

        if ($event->time < $threshold) {
            return;
        }

        $this->audit->log(
            channel: LogChannel::System,
            action: AuditAction::SlowQuery,
            description: 'کوئری کند شناسایی شد',
            level: LogLevel::Notice,
            context: [
                'sql'        => mb_substr($event->sql, 0, 2000),
                'time_ms'    => $event->time,
                'connection' => $event->connectionName,
            ],
            tags: ['performance'],
        );
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            MessageLogged::class => 'handleMessageLogged',
            QueryExecuted::class => 'handleQueryExecuted',
        ];
    }
}
