<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CacheMaintenanceRun extends Model
{
    protected $fillable = [
        'operation',
        'status',
        'is_dry_run',
        'targets_json',
        'options_json',
        'before_metrics_json',
        'after_metrics_json',
        'summary_json',
        'errors_json',
        'console_output',
        'user_id',
        'started_at',
        'finished_at',
        'duration_ms',
    ];

    protected $casts = [
        'is_dry_run'           => 'boolean',
        'targets_json'         => 'array',
        'options_json'         => 'array',
        'before_metrics_json'  => 'array',
        'after_metrics_json'   => 'array',
        'summary_json'         => 'array',
        'errors_json'          => 'array',
        'started_at'           => 'datetime',
        'finished_at'          => 'datetime',
        'duration_ms'          => 'integer',
    ];

    public const OPERATION_CLEAR    = 'clear';
    public const OPERATION_OPTIMIZE = 'optimize';
    public const OPERATION_INSPECT  = 'inspect';

    public const STATUS_PENDING   = 'pending';
    public const STATUS_RUNNING   = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_PARTIAL   = 'partial';
    public const STATUS_FAILED    = 'failed';

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function markRunning(): void
    {
        $this->update([
            'status'     => self::STATUS_RUNNING,
            'started_at' => now(),
        ]);
    }

    public function markFinished(string $status, array $summary = [], array $errors = [], ?string $output = null): void
    {
        $started = $this->started_at ?: now();
        $finished = now();

        $this->update([
            'status'         => $status,
            'summary_json'   => $summary,
            'errors_json'    => $errors,
            'console_output' => $output,
            'finished_at'    => $finished,
            'duration_ms'    => max(0, $started->diffInMilliseconds($finished)),
        ]);
    }
}
