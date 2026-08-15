<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArchiveAction extends Model
{
    protected $table = 'archive_actions';

    /** این جدول فقط created_at دارد؛ چون یک لاگ ممیزی است و هرگز آپدیت نمی‌شود. */
    const UPDATED_AT = null;

    protected $fillable = [
        'archived_record_id',
        'source_type',
        'source_id',
        'action',
        'actor_id',
        'reason',
        'payload_json',
    ];

    protected $casts = [
        'payload_json' => 'array',
    ];

    public function archivedRecord(): BelongsTo
    {
        return $this->belongsTo(ArchivedRecord::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
