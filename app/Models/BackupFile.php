<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * مانیفست یک فایل تصویری در خروجی یا ورودی.
 */
class BackupFile extends Model
{
    public const STATUS_COPIED     = 'copied';
    public const STATUS_SKIPPED    = 'skipped';
    public const STATUS_DUPLICATED = 'duplicated';
    public const STATUS_MISSING    = 'missing';
    public const STATUS_FAILED     = 'failed';
    public const STATUS_RELINKED   = 'relinked';

    protected $table = 'backup_files';

    protected $guarded = ['id'];

    public function run(): BelongsTo
    {
        return $this->belongsTo(BackupRun::class, 'backup_run_id');
    }
}
