<?php

declare(strict_types=1);

namespace Modules\Licensing\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * جدول تک‌ردیفی (id=1) که وضعیت فعال‌سازی این نصب گیم‌استور را نگه می‌دارد.
 * جایگزین رویکرد شکننده‌ی «نوشتن در .env در زمان اجرا» است.
 *
 * @property string|null $fingerprint
 * @property string|null $license_uuid
 * @property string|null $token
 * @property string $status unactivated|pending|active|locked|rejected
 * @property string|null $activation_request_uuid
 * @property string|null $plan_code
 * @property array|null $entitlements
 * @property array|null $limits
 * @property string|null $reject_reason
 * @property string|null $lock_code
 * @property string|null $lock_reason
 * @property \Illuminate\Support\Carbon|null $expires_at
 * @property \Illuminate\Support\Carbon|null $valid_until
 * @property int $heartbeat_interval_minutes
 * @property int $poll_after_seconds
 * @property \Illuminate\Support\Carbon|null $last_heartbeat_at
 * @property bool $last_heartbeat_ok
 */
class LicenseState extends Model
{
    public const STATUS_UNACTIVATED = 'unactivated';
    public const STATUS_PENDING     = 'pending';
    public const STATUS_ACTIVE      = 'active';
    public const STATUS_LOCKED      = 'locked';
    public const STATUS_REJECTED    = 'rejected';

    protected $table = 'license_state';

    public $timestamps = false;

    protected $fillable = [
        'fingerprint', 'license_uuid', 'token', 'status',
        'activation_request_uuid', 'plan_code', 'entitlements', 'limits',
        'reject_reason', 'lock_code', 'lock_reason',
        'expires_at', 'valid_until', 'heartbeat_interval_minutes', 'poll_after_seconds',
        'last_heartbeat_at', 'last_heartbeat_ok', 'updated_at',
    ];

    protected $casts = [
        'entitlements'               => 'array',
        'limits'                     => 'array',
        'expires_at'                 => 'datetime',
        'valid_until'                => 'datetime',
        'last_heartbeat_at'          => 'datetime',
        'last_heartbeat_ok'          => 'boolean',
        'heartbeat_interval_minutes' => 'integer',
        'poll_after_seconds'         => 'integer',
    ];

    /**
     * همیشه همان ردیف id=1 را برمی‌گرداند؛ اگر وجود نداشته باشد می‌سازد.
     */
    public static function current(): self
    {
        return static::query()->find(1) ?? static::query()->create([
            'id'     => 1,
            'status' => self::STATUS_UNACTIVATED,
        ]);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && $this->token !== null;
    }

    public function isLocked(): bool
    {
        return $this->status === self::STATUS_LOCKED;
    }
}