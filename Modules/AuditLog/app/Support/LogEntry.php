<?php

declare(strict_types=1);

namespace Modules\AuditLog\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Modules\AuditLog\Enums\LogChannel;
use Modules\AuditLog\Enums\LogLevel;

/**
 * DTO رکورد لاگ. بین AuditLogger و AuditRecorder جابه‌جا می‌شود و
 * دقیقاً همان چیزی است که به دیتابیس درج می‌گردد.
 */
final class LogEntry
{
    public readonly string $uuid;
    public readonly Carbon $occurredAt;

    /**
     * @param array<string, mixed>|null $oldValues
     * @param array<string, mixed>|null $newValues
     * @param list<string>|null         $changedKeys
     * @param array<string, mixed>      $context
     * @param list<string>              $tags
     */
    public function __construct(
        public readonly LogChannel $channel,
        public readonly string $action,
        public readonly ?string $description = null,
        public readonly LogLevel $level = LogLevel::Info,
        public readonly ?string $entityType = null,
        public readonly ?int $entityId = null,
        public readonly ?string $entityLabel = null,
        public readonly ?array $oldValues = null,
        public readonly ?array $newValues = null,
        public readonly ?array $changedKeys = null,
        public readonly array $context = [],
        public readonly array $tags = [],
        public readonly ?int $statusCode = null,
        public readonly ?int $durationMs = null,
        ?string $uuid = null,
        ?Carbon $occurredAt = null,
    ) {
        $this->uuid = $uuid ?? (string) Str::uuid();
        $this->occurredAt = $occurredAt ?? Carbon::now('UTC');
    }

    /**
     * ترکیب DTO با اطلاعات محیطی/کاربری و خروجی آماده برای درج.
     *
     * @param  array<string, mixed>  $context  زمینه‌ی جاری (AuditContext::toArray)
     * @return array<string, mixed>
     */
    public function toDatabaseRow(array $context): array
    {
        return [
            'uuid'           => $this->uuid,
            'channel'        => $this->channel->value,
            'level'          => $this->level->value,
            'action'         => mb_substr($this->action, 0, 128),
            'description'    => $this->description !== null ? mb_substr($this->description, 0, 1024) : null,
            'entity_type'    => $this->entityType !== null ? mb_substr($this->entityType, 0, 191) : null,
            'entity_id'      => $this->entityId,
            'entity_label'   => $this->entityLabel !== null ? mb_substr($this->entityLabel, 0, 191) : null,
            'actor_id'       => $context['actor_id'] ?? null,
            'actor_type'     => $context['actor_type'] ?? 'system',
            'actor_name'     => $context['actor_name'] ?? null,
            'actor_email'    => $context['actor_email'] ?? null,
            'request_id'     => $context['request_id'] ?? null,
            'correlation_id' => $context['correlation_id'] ?? null,
            'session_id'     => $context['session_id'] ?? null,
            'method'         => $context['method'] ?? null,
            'route'          => $context['route'] ?? null,
            'url'            => $context['url'] ?? null,
            'ip'             => $context['ip'] ?? null,
            'user_agent'     => $context['user_agent'] ?? null,
            'status_code'    => $this->statusCode,
            'duration_ms'    => $this->durationMs,
            'memory_kb'      => (int) round(memory_get_peak_usage(true) / 1024),
            'old_values'     => $this->oldValues,
            'new_values'     => $this->newValues,
            'changed_keys'   => $this->changedKeys,
            'context'        => $this->context === [] ? null : $this->context,
            'tags'           => $this->tags === [] ? null : $this->tags,
            'environment'    => $context['environment'] ?? null,
            'app_version'    => $context['app_version'] ?? null,
            'hostname'       => $context['hostname'] ?? null,
            'occurred_at'    => $this->occurredAt,
        ];
    }
}
