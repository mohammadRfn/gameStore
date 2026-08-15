<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * یک اجرای بکاپ (خروجی) یا بازیابی (ورودی).
 */
class BackupRun extends Model
{
    use SoftDeletes;

    public const DIRECTION_EXPORT = 'export';
    public const DIRECTION_IMPORT = 'import';

    public const MODE_FULL     = 'full';
    public const MODE_DATABASE = 'database';
    public const MODE_MEDIA    = 'media';

    public const STRATEGY_MERGE         = 'merge';
    public const STRATEGY_REPLACE       = 'replace';
    public const STRATEGY_SKIP_EXISTING = 'skip_existing';
    public const STRATEGY_FAIL          = 'fail_on_conflict';

    public const STATUS_PENDING   = 'pending';
    public const STATUS_RUNNING   = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PARTIAL   = 'partial';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_CANCELED  = 'canceled';

    protected $table = 'backup_runs';

    protected $guarded = ['id'];

    protected $casts = [
        'options_json'   => 'array',
        'filters_json'   => 'array',
        'summary_json'   => 'array',
        'entities_json'  => 'array',
        'is_dry_run'     => 'boolean',
        'is_auto'        => 'boolean',
        'is_safety_copy' => 'boolean',
        'started_at'     => 'datetime',
        'finished_at'    => 'datetime',
    ];

    public function entities(): HasMany
    {
        return $this->hasMany(BackupRunEntity::class);
    }

    public function files(): HasMany
    {
        return $this->hasMany(BackupFile::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(BackupRunEvent::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeExports(Builder $query): Builder
    {
        return $query->where('direction', self::DIRECTION_EXPORT);
    }

    public function scopeImports(Builder $query): Builder
    {
        return $query->where('direction', self::DIRECTION_IMPORT);
    }

    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->whereIn('status', [self::STATUS_COMPLETED, self::STATUS_PARTIAL]);
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [
            self::STATUS_COMPLETED, self::STATUS_PARTIAL, self::STATUS_FAILED, self::STATUS_CANCELED,
        ], true);
    }
}
