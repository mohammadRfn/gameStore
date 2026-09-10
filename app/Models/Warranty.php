<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;

class Warranty extends Model
{
    use SoftDeletes;

    const UNIT_DAY   = 'day';
    const UNIT_MONTH = 'month';
    const UNIT_YEAR  = 'year';

    protected $fillable = [
        'item_serial_number_id',
        'order_item_id',
        'warranty_provider_id',
        'duration_value',
        'duration_unit',
        'starts_at',
        'expires_at',
        'notes',
    ];

    protected $casts = [
        'starts_at'  => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected $appends = ['status'];

    public function itemSerialNumber()
    {
        return $this->belongsTo(ItemSerialNumber::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function provider()
    {
        return $this->belongsTo(WarrantyProvider::class, 'warranty_provider_id');
    }

    public function getStatusAttribute(): string
    {
        if (!$this->starts_at) {
            return 'pending';
        }

        if ($this->expires_at && Carbon::now()->greaterThan($this->expires_at)) {
            return 'expired';
        }

        return 'active';
    }

    public function calculateExpiry(Carbon $from): Carbon
    {
        return match ($this->duration_unit) {
            self::UNIT_DAY   => $from->copy()->addDays($this->duration_value),
            self::UNIT_MONTH => $from->copy()->addMonths($this->duration_value),
            self::UNIT_YEAR  => $from->copy()->addYears($this->duration_value),
            default          => $from->copy(),
        };
    }

    public function startCounting(): void
    {
        if ($this->starts_at) {
            return;
        }

        $this->starts_at  = Carbon::now();
        $this->expires_at = $this->calculateExpiry($this->starts_at);
        $this->save();
    }
}