<?php

declare(strict_types=1);

namespace Modules\AuditLog\Services;

use Illuminate\Support\Collection;
use Modules\AuditLog\Models\AuditLog;
use Modules\AuditLog\Support\ClientIdentity;

/**
 * ساخت بدنه‌ی JSON ارسالی به StoreServer.
 * قرارداد کامل در Modules/AuditLog/docs/storeserver-endpoint.md آمده است.
 */
class LogPayloadBuilder
{
    public const SCHEMA_VERSION = '1.0';

    public function __construct(private readonly ClientIdentity $identity)
    {
    }

    /**
     * @param  Collection<int, AuditLog>  $logs
     * @return array<string, mixed>
     */
    public function build(string $batchUuid, Collection $logs): array
    {
        $items = $logs->map(fn (AuditLog $log): array => $this->transform($log))->values()->all();

        return [
            'schema'  => self::SCHEMA_VERSION,
            'batch'   => [
                'uuid'       => $batchUuid,
                'count'      => count($items),
                'created_at' => now()->utc()->toIso8601String(),
                'checksum'   => $this->checksum($items),
                'first_uuid' => $items[0]['uuid'] ?? null,
                'last_uuid'  => $items === [] ? null : $items[count($items) - 1]['uuid'],
            ],
            'client'  => $this->identity->toArray(),
            'logs'    => $items,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function checksum(array $items): string
    {
        return hash('sha256', (string) json_encode(
            array_map(static fn (array $item): string => (string) $item['uuid'], $items),
            JSON_UNESCAPED_UNICODE,
        ));
    }

    /**
     * نگاشت رکورد محلی به ساختار مورد انتظار StoreServer.
     *
     * @return array<string, mixed>
     */
    public function transform(AuditLog $log): array
    {
        return [
            'uuid'        => $log->uuid,
            'occurred_at' => $log->occurred_at?->clone()->utc()->toIso8601String(),
            'channel'     => $log->channel->value,
            'level'       => $log->level->value,
            'action'      => $log->action,
            'description' => $log->description,

            'entity' => array_filter([
                'type'  => $log->entity_type,
                'id'    => $log->entity_id,
                'label' => $log->entity_label,
            ], static fn ($value) => $value !== null),

            'actor' => array_filter([
                'id'    => $log->actor_id,
                'type'  => $log->actor_type,
                'name'  => $log->actor_name,
                'email' => $log->actor_email,
            ], static fn ($value) => $value !== null),

            'request' => array_filter([
                'id'             => $log->request_id,
                'correlation_id' => $log->correlation_id,
                'session_id'     => $log->session_id,
                'method'         => $log->method,
                'route'          => $log->route,
                'url'            => $log->url,
                'ip'             => $log->ip,
                'user_agent'     => $log->user_agent,
                'status_code'    => $log->status_code,
                'duration_ms'    => $log->duration_ms,
                'memory_kb'      => $log->memory_kb,
            ], static fn ($value) => $value !== null),

            'changes' => array_filter([
                'old'  => $log->old_values,
                'new'  => $log->new_values,
                'keys' => $log->changed_keys,
            ], static fn ($value) => $value !== null && $value !== []),

            'context' => $log->context ?? [],
            'tags'    => $log->tags ?? [],

            'integrity' => [
                'sequence'      => $log->sequence,
                'hash'          => $log->hash,
                'previous_hash' => $log->previous_hash,
            ],

            'source' => [
                'environment' => $log->environment,
                'app_version' => $log->app_version,
                'hostname'    => $log->hostname,
            ],
        ];
    }
}
