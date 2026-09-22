<?php

declare(strict_types=1);

namespace Modules\AuditLog\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Modules\AuditLog\Database\Factories\AuditLogFactory;
use Modules\AuditLog\Enums\LogChannel;
use Modules\AuditLog\Enums\LogLevel;
use Modules\AuditLog\Enums\SyncStatus;
use RuntimeException;

/**
 * رکورد لاگ. این جدول append-only است؛ پس از درج فقط ستون‌های
 * همگام‌سازی اجازه‌ی تغییر دارند (نگهبان در متد booted).
 *
 * @property int $id
 * @property string $uuid
 * @property LogChannel $channel
 * @property LogLevel $level
 * @property string $action
 * @property string|null $description
 * @property SyncStatus $sync_status
 * @property Carbon $occurred_at
 */
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    protected $table = 'audit_logs';

    /** ستون‌هایی که پس از ایجاد رکورد قابل تغییر هستند. */
    public const MUTABLE_AFTER_CREATE = [
        'sync_status', 'sync_attempts', 'next_attempt_at', 'synced_at',
        'batch_uuid', 'last_error', 'remote_id', 'updated_at',
    ];

    protected $fillable = [
        'uuid', 'channel', 'level', 'action', 'description',
        'entity_type', 'entity_id', 'entity_label',
        'actor_id', 'actor_type', 'actor_name', 'actor_email',
        'request_id', 'correlation_id', 'session_id', 'method', 'route', 'url',
        'ip', 'user_agent', 'status_code', 'duration_ms', 'memory_kb',
        'old_values', 'new_values', 'changed_keys', 'context', 'tags',
        'environment', 'app_version', 'hostname',
        'sequence', 'hash', 'previous_hash',
        'sync_status', 'sync_attempts', 'next_attempt_at', 'synced_at',
        'batch_uuid', 'last_error', 'remote_id', 'occurred_at',
    ];

    protected $casts = [
        'channel'         => LogChannel::class,
        'level'           => LogLevel::class,
        'sync_status'     => SyncStatus::class,
        'old_values'      => 'array',
        'new_values'      => 'array',
        'changed_keys'    => 'array',
        'context'         => 'array',
        'tags'            => 'array',
        'entity_id'       => 'integer',
        'actor_id'        => 'integer',
        'status_code'     => 'integer',
        'duration_ms'     => 'integer',
        'memory_kb'       => 'integer',
        'sequence'        => 'integer',
        'sync_attempts'   => 'integer',
        'occurred_at'     => 'datetime',
        'next_attempt_at' => 'datetime',
        'synced_at'       => 'datetime',
    ];

    protected static function booted(): void
    {
        // محافظت از تغییر محتوای لاگ پس از درج
        static::updating(function (self $log): void {
            $forbidden = array_diff(array_keys($log->getDirty()), self::MUTABLE_AFTER_CREATE);

            if ($forbidden !== []) {
                throw new RuntimeException(
                    'رکوردهای audit_logs غیرقابل تغییر هستند. ستون‌های غیرمجاز: ' . implode(', ', $forbidden)
                );
            }
        });

        static::deleting(function (self $log): void {
            // حذف فقط از مسیر سرویس نگه‌داری (pruner) مجاز است
            if (! app()->bound('auditlog.pruning')) {
                throw new RuntimeException('حذف مستقیم رکورد audit_logs مجاز نیست؛ از auditlog:prune استفاده کنید.');
            }
        });
    }

    public function getConnectionName(): ?string
    {
        return config('auditlog.storage.connection') ?: parent::getConnectionName();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(AuditLogBatch::class, 'batch_uuid', 'uuid');
    }

    /** رکوردهایی که آماده‌ی ارسال به StoreServer هستند. */
    public function scopeShippable(Builder $query): Builder
    {
        return $query
            ->whereIn('sync_status', [SyncStatus::Pending->value, SyncStatus::Failed->value])
            ->where(function (Builder $q): void {
                $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now());
            })
            ->orderBy('id');
    }

    public function scopeChannel(Builder $query, LogChannel|string $channel): Builder
    {
        return $query->where('channel', $channel instanceof LogChannel ? $channel->value : $channel);
    }

    public function scopeLevelAtLeast(Builder $query, LogLevel $level): Builder
    {
        $allowed = array_values(array_filter(
            LogLevel::cases(),
            static fn (LogLevel $case): bool => $case->atLeast($level),
        ));

        return $query->whereIn('level', array_map(static fn (LogLevel $c): string => $c->value, $allowed));
    }

    public function scopeBetweenDates(Builder $query, ?string $from, ?string $to): Builder
    {
        return $query
            ->when($from, fn (Builder $q, string $value) => $q->where('occurred_at', '>=', Carbon::parse($value)))
            ->when($to, fn (Builder $q, string $value) => $q->where('occurred_at', '<=', Carbon::parse($value)));
    }

    protected static function newFactory(): AuditLogFactory
    {
        return AuditLogFactory::new();
    }
}
