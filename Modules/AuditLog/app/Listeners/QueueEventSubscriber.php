<?php

declare(strict_types=1);

namespace Modules\AuditLog\Listeners;

use Illuminate\Events\Dispatcher;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Modules\AuditLog\Enums\AuditAction;
use Modules\AuditLog\Enums\LogLevel;
use Modules\AuditLog\Services\AuditLogger;

/**
 * رصد صف‌ها: شکست jobها همیشه و موفقیت‌ها به‌صورت اختیاری لاگ می‌شود.
 */
class QueueEventSubscriber
{
    public function __construct(private readonly AuditLogger $audit)
    {
    }

    public function handleFailed(JobFailed $event): void
    {
        if (! (bool) config('auditlog.capture.queue.enabled', true) || ! (bool) config('auditlog.capture.queue.log_failed', true)) {
            return;
        }

        $this->audit->job(AuditAction::JobFailed, 'اجرای job با خطا مواجه شد: ' . $event->job->resolveName(), [
            'connection' => $event->connectionName,
            'queue'      => $event->job->getQueue(),
            'job'        => $event->job->resolveName(),
            'attempts'   => $event->job->attempts(),
            'exception'  => $event->exception::class,
            'message'    => mb_substr($event->exception->getMessage(), 0, 1000),
        ], LogLevel::Error);

        $this->audit->flush();
    }

    public function handleProcessed(JobProcessed $event): void
    {
        if (! (bool) config('auditlog.capture.queue.enabled', true) || ! (bool) config('auditlog.capture.queue.log_processed', false)) {
            return;
        }

        $this->audit->job(AuditAction::JobProcessed, 'job با موفقیت اجرا شد: ' . $event->job->resolveName(), [
            'connection' => $event->connectionName,
            'queue'      => $event->job->getQueue(),
            'job'        => $event->job->resolveName(),
        ], LogLevel::Debug);
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            JobFailed::class    => 'handleFailed',
            JobProcessed::class => 'handleProcessed',
        ];
    }
}
