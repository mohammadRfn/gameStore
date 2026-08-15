<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * جزئیات پردازش یک جدول در یک اجرای بکاپ.
 */
class BackupRunEntity extends Model
{
    protected $table = 'backup_run_entities';

    protected $guarded = ['id'];

    protected $casts = [
        'columns_json' => 'array',
        'meta_json'    => 'array',
        'started_at'   => 'datetime',
        'finished_at'  => 'datetime',
    ];

    public function run(): BelongsTo
    {
        return $this->belongsTo(BackupRun::class, 'backup_run_id');
    }
}
