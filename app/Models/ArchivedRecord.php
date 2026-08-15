<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ArchivedRecord extends Model
{
    use SoftDeletes;

    protected $table = 'archived_records';

    public const TYPE_INVOICE     = 'invoice';
    public const TYPE_REQUEST     = 'request';
    public const TYPE_SERVICE_JOB = 'service_job';

    public const STATUS_COPIED      = 'copied';
    public const STATUS_TRANSFERRED = 'transferred';

    protected $fillable = [
        'source_type',
        'source_table',
        'source_id',
        'invoice_id',
        'invoice_number',
        'customer_id',
        'customer_name',
        'title',
        'payment_status',
        'total_amount',
        'paid_at',
        'source_created_at',
        'source_updated_at',
        'snapshot_json',
        'snapshot_hash',
        'archive_status',
        'archived_by',
        'archived_at',
        'removed_from_source_at',
        'deletion_mode',
        'reason',
        'metadata_json',
    ];

    protected $casts = [
        'snapshot_json'          => 'array',
        'metadata_json'          => 'array',
        'total_amount'           => 'decimal:2',
        'paid_at'                => 'datetime',
        'source_created_at'      => 'datetime',
        'source_updated_at'      => 'datetime',
        'archived_at'            => 'datetime',
        'removed_from_source_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function actions(): HasMany
    {
        return $this->hasMany(ArchiveAction::class);
    }

    public function isTransferred(): bool
    {
        return $this->archive_status === self::STATUS_TRANSFERRED;
    }

    /**
     * دسترسی امن به مقادیر داخل snapshot_json با dot-notation،
     * مثال: $record->snapshotValue('source.device_type')
     */
    public function snapshotValue(string $path, mixed $default = null): mixed
    {
        return data_get($this->snapshot_json, $path, $default);
    }
}
