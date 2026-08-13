<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceJob extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'request_id',
        'service_type_id',
        'invoice_id',
        'device_type',
        'device_serial',
        'customer_problem_description',
        'diagnosis_description',
        'status',
        'estimated_price',
        'final_price',
        'received_at',
        'started_at',
        'completed_at',
        'delivered_at',
    ];

    protected $casts = [
        'estimated_price' => 'decimal:2',
        'final_price'     => 'decimal:2',
        'received_at'     => 'datetime',
        'started_at'      => 'datetime',
        'completed_at'    => 'datetime',
        'delivered_at'    => 'datetime',
    ];

    public const STATUS_RECEIVED          = 'received';
    public const STATUS_DIAGNOSING        = 'diagnosing';
    public const STATUS_WAITING_FOR_PARTS = 'waiting_for_parts';
    public const STATUS_IN_PROGRESS       = 'in_progress';
    public const STATUS_COMPLETED         = 'completed';
    public const STATUS_DELIVERED         = 'delivered';
    public const STATUS_CANCELED          = 'canceled';

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(ServiceType::class);
    }
    public function serviceTypes(): HasMany
    {
        return $this->hasMany(ServiceJobServiceType::class);
    }
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ServiceJobItem::class);
    }
}
