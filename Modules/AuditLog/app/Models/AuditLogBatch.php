<?php

declare(strict_types=1);

namespace Modules\AuditLog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * یک دسته ارسال لاگ به StoreServer (Outbox).
 *
 * @property string $uuid
 * @property string $status
 */
class AuditLogBatch extends Model
{
    protected $table = 'audit_log_batches';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENDING = 'sending';
    public const STATUS_SENT    = 'sent';
    public const STATUS_FAILED  = 'failed';

    protected $fillable = [
        'uuid', 'status', 'log_count', 'checksum', 'payload_bytes', 'endpoint',
        'attempts', 'http_status', 'error_code', 'error_message', 'response',
        'duration_ms', 'dispatched_at', 'acknowledged_at',
    ];

    protected $casts = [
        'response'        => 'array',
        'log_count'       => 'integer',
        'payload_bytes'   => 'integer',
        'attempts'        => 'integer',
        'http_status'     => 'integer',
        'duration_ms'     => 'integer',
        'dispatched_at'   => 'datetime',
        'acknowledged_at' => 'datetime',
    ];

    public function getConnectionName(): ?string
    {
        return config('auditlog.storage.connection') ?: parent::getConnectionName();
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AuditLog::class, 'batch_uuid', 'uuid');
    }

    public function markSent(int $httpStatus, array $response, int $durationMs): void
    {
        $this->forceFill([
            'status'          => self::STATUS_SENT,
            'http_status'     => $httpStatus,
            'response'        => $response,
            'duration_ms'     => $durationMs,
            'acknowledged_at' => now(),
            'error_code'      => null,
            'error_message'   => null,
        ])->save();
    }

    public function markFailed(?int $httpStatus, ?string $code, string $message, int $durationMs, array $response = []): void
    {
        $this->forceFill([
            'status'        => self::STATUS_FAILED,
            'http_status'   => $httpStatus,
            'error_code'    => $code !== null ? mb_substr($code, 0, 64) : null,
            'error_message' => mb_substr($message, 0, 2000),
            'response'      => $response,
            'duration_ms'   => $durationMs,
        ])->save();
    }
}
