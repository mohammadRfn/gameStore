<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * لاگ ساختاریافته‌ی رویدادهای یک اجرا (Audit Trail).
 */
class BackupRunEvent extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'backup_run_events';

    protected $guarded = ['id'];

    protected $casts = [
        'context_json' => 'array',
        'created_at'   => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(BackupRun::class, 'backup_run_id');
    }
}
