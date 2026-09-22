<?php

declare(strict_types=1);

namespace Modules\AuditLog\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\AuditLog\Models\AuditLog;

/**
 * @mixin AuditLog
 */
class AuditLogResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'uuid'        => $this->uuid,
            'occurred_at' => $this->occurred_at?->toIso8601String(),
            'channel'     => [
                'value' => $this->channel->value,
                'label' => $this->channel->label(),
            ],
            'level'       => $this->level->value,
            'action'      => $this->action,
            'description' => $this->description,
            'entity'      => [
                'type'  => $this->entity_type,
                'short' => $this->entity_type !== null ? class_basename($this->entity_type) : null,
                'id'    => $this->entity_id,
                'label' => $this->entity_label,
            ],
            'actor'       => [
                'id'   => $this->actor_id,
                'type' => $this->actor_type,
                'name' => $this->actor_name,
            ],
            'request'     => [
                'id'          => $this->request_id,
                'method'      => $this->method,
                'route'       => $this->route,
                'url'         => $this->url,
                'ip'          => $this->ip,
                'status_code' => $this->status_code,
                'duration_ms' => $this->duration_ms,
            ],
            'changes'     => [
                'old'  => $this->old_values,
                'new'  => $this->new_values,
                'keys' => $this->changed_keys,
            ],
            'context'     => $this->context,
            'tags'        => $this->tags,
            'sync'        => [
                'status'          => $this->sync_status->value,
                'attempts'        => $this->sync_attempts,
                'synced_at'       => $this->synced_at?->toIso8601String(),
                'next_attempt_at' => $this->next_attempt_at?->toIso8601String(),
                'batch_uuid'      => $this->batch_uuid,
                'last_error'      => $this->last_error,
            ],
            'integrity'   => [
                'sequence' => $this->sequence,
                'hash'     => $this->hash,
            ],
        ];
    }
}
