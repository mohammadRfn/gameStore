<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockMovement extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'item_id',
        'invoice_id',
        'service_job_id',
        'movement_type',
        'quantity',
        'unit_cost',
        'reason',
        'note',
    ];

    protected $casts = [
        'unit_cost' => 'decimal:2',
        'quantity'  => 'integer',
    ];

    public const TYPE_IN         = 'in';
    public const TYPE_OUT        = 'out';
    public const TYPE_ADJUST_IN  = 'adjust_in';
    public const TYPE_ADJUST_OUT = 'adjust_out';

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function serviceJob(): BelongsTo
    {
        return $this->belongsTo(ServiceJob::class);
    }
}