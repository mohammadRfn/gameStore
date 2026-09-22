<?php

declare(strict_types=1);

namespace Modules\AuditLog\Services;

use Illuminate\Database\Eloquent\Model;
use Modules\AuditLog\Enums\AuditAction;
use Modules\AuditLog\Enums\LogChannel;
use Modules\AuditLog\Enums\LogLevel;
use Modules\AuditLog\Support\AuditContext;
use Modules\AuditLog\Support\LogEntry;
use Modules\AuditLog\Support\LogSanitizer;
use Throwable;

/**
 * API عمومی لاگ‌گیری. در کل پروژه از این کلاس (یا فساد Audit) استفاده کنید:
 *
 *   Audit::business('invoice.paid', 'فاکتور تسویه شد', entity: $invoice);
 *   Audit::security('suspicious_login', 'تلاش مشکوک', ['ip' => $ip]);
 *   Audit::exception($e);
 */
class AuditLogger
{
    public function __construct(
        private readonly AuditRecorder $recorder,
        private readonly LogSanitizer $sanitizer,
        private readonly AuditContext $context,
    ) {
    }

    /**
     * ثبت عمومی یک رویداد.
     *
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     * @param  list<string>  $tags
     */
    public function log(
        LogChannel $channel,
        AuditAction|string $action,
        string $description,
        LogLevel $level = LogLevel::Info,
        ?Model $entity = null,
        ?array $old = null,
        ?array $new = null,
        array $context = [],
        array $tags = [],
        ?int $statusCode = null,
        ?int $durationMs = null,
    ): void {
        $entityType = $entity !== null
            ? $entity::class
            : (isset($context['entity_type']) ? (string) $context['entity_type'] : null);

        $entityId = $entity !== null && is_numeric($entity->getKey())
            ? (int) $entity->getKey()
            : (isset($context['entity_id']) && is_numeric($context['entity_id']) ? (int) $context['entity_id'] : null);

        $entityLabel = $entity !== null
            ? $this->entityLabel($entity)
            : (isset($context['entity_label']) ? mb_substr((string) $context['entity_label'], 0, 191) : null);

        $entry = new LogEntry(
            channel: $channel,
            action: $action instanceof AuditAction ? $action->value : $action,
            description: $description,
            level: $level,
            entityType: $entityType,
            entityId: $entityId,
            entityLabel: $entityLabel,
            oldValues: $this->sanitizer->sanitize($old),
            newValues: $this->sanitizer->sanitize($new),
            changedKeys: $this->changedKeys($old, $new),
            context: (array) $this->sanitizer->sanitize($context),
            tags: $tags,
            statusCode: $statusCode,
            durationMs: $durationMs,
        );

        $this->recorder->record($entry);
    }

    /** ثبت رویداد کسب‌وکار (فاکتور، سرویس، انبار، مشتری و ...). */
    public function business(
        AuditAction|string $action,
        string $description,
        ?Model $entity = null,
        array $context = [],
        LogLevel $level = LogLevel::Info,
    ): void {
        $this->log(LogChannel::Business, $action, $description, $level, $entity, context: $context);
    }

    /** ثبت تغییر داده‌ی یک مدل. */
    public function model(
        AuditAction|string $action,
        Model $model,
        ?array $old = null,
        ?array $new = null,
        ?string $description = null,
    ): void {
        $this->log(
            channel: LogChannel::Model,
            action: $action,
            description: $description ?? sprintf(
                '%s روی %s#%s',
                $action instanceof AuditAction ? $action->label() : $action,
                class_basename($model),
                (string) $model->getKey(),
            ),
            entity: $model,
            old: $old,
            new: $new,
        );
    }

    /** رویدادهای احراز هویت. */
    public function auth(AuditAction|string $action, string $description, array $context = [], LogLevel $level = LogLevel::Info): void
    {
        $this->log(LogChannel::Auth, $action, $description, $level, context: $context);
    }

    /** رویدادهای امنیتی (همیشه حداقل warning). */
    public function security(string $type, string $description, array $context = [], LogLevel $level = LogLevel::Warning): void
    {
        $this->log(LogChannel::Security, 'security.' . $type, $description, $level, context: $context, tags: ['security']);
    }

    /** رویدادهای سیستمی: بکاپ، کش، آرشیو، تنظیمات. */
    public function system(AuditAction|string $action, string $description, array $context = [], LogLevel $level = LogLevel::Info): void
    {
        $this->log(LogChannel::System, $action, $description, $level, context: $context);
    }

    /** رویدادهای همگام‌سازی با StoreServer. */
    public function sync(AuditAction|string $action, string $description, array $context = [], LogLevel $level = LogLevel::Info): void
    {
        $this->log(LogChannel::Sync, $action, $description, $level, context: $context);
    }

    /** لاگ درخواست HTTP. */
    public function http(string $description, array $context, ?int $statusCode, ?int $durationMs, LogLevel $level = LogLevel::Info): void
    {
        $this->log(
            channel: LogChannel::Http,
            action: AuditAction::HttpRequest,
            description: $description,
            level: $level,
            context: $context,
            statusCode: $statusCode,
            durationMs: $durationMs,
        );
    }

    /** ثبت استثنا با stack trace کوتاه‌شده. */
    public function exception(Throwable $e, array $context = [], LogLevel $level = LogLevel::Error): void
    {
        $lines = (int) config('auditlog.capture.error.trace_lines', 20);

        $this->log(
            channel: LogChannel::Error,
            action: AuditAction::ErrorReported,
            description: mb_substr($e->getMessage() !== '' ? $e->getMessage() : $e::class, 0, 1024),
            level: $level,
            context: array_merge($context, [
                'exception' => $e::class,
                'file'      => $e->getFile(),
                'line'      => $e->getLine(),
                'code'      => $e->getCode(),
                'trace'     => array_slice(explode("\n", $e->getTraceAsString()), 0, $lines),
                'previous'  => $e->getPrevious() !== null ? $e->getPrevious()::class : null,
            ]),
            tags: ['exception'],
        );
    }

    /** رویدادهای صف. */
    public function job(AuditAction|string $action, string $description, array $context = [], LogLevel $level = LogLevel::Info): void
    {
        $this->log(LogChannel::Job, $action, $description, $level, context: $context);
    }

    /** رویدادهای کنسول. */
    public function console(string $command, string $description, array $context = [], LogLevel $level = LogLevel::Info): void
    {
        $this->log(LogChannel::Console, AuditAction::CommandRun, $description, $level, context: array_merge($context, ['command' => $command]));
    }

    public function context(): AuditContext
    {
        return $this->context;
    }

    public function recorder(): AuditRecorder
    {
        return $this->recorder;
    }

    /** اجرای یک بلوک کد بدون لاگ‌گیری. */
    public function withoutLogging(callable $callback): mixed
    {
        return $this->recorder->withoutRecording($callback);
    }

    public function flush(): void
    {
        $this->recorder->flush();
    }

    private function entityLabel(Model $model): ?string
    {
        /** @var list<string> $candidates */
        $candidates = (array) config('auditlog.capture.model.label_attributes', ['name', 'title']);

        foreach ($candidates as $attribute) {
            $value = $model->getAttribute($attribute);

            if (is_scalar($value) && (string) $value !== '') {
                return mb_substr((string) $value, 0, 191);
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     * @return list<string>|null
     */
    private function changedKeys(?array $old, ?array $new): ?array
    {
        if ($old === null && $new === null) {
            return null;
        }

        return array_values(array_unique(array_merge(
            array_keys($old ?? []),
            array_keys($new ?? []),
        )));
    }
}
