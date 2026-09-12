<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use Morilog\Jalali\Jalalian;

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

    protected $appends = ['status', 'remaining_label'];

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
    public function getRemainingLabelAttribute(): ?string
    {
        if ($this->status === 'pending') {
            return null;
        }

        if ($this->status === 'expired') {
            return 'منقضی شده';
        }

        $diff = Carbon::now()->diff($this->expires_at);
        $parts = [];
        if ($diff->y > 0) $parts[] = $diff->y . ' سال';
        if ($diff->m > 0) $parts[] = $diff->m . ' ماه';
        if ($diff->d > 0) $parts[] = $diff->d . ' روز';
        if (empty($parts)) $parts[] = 'کمتر از یک روز';

        return implode(' و ', $parts) . ' مانده';
    }
    public function calculateExpiry(Carbon $from): Carbon
    {
        $jalaliFrom = Jalalian::fromCarbon($from);

        $jalaliExpiry = match ($this->duration_unit) {
            self::UNIT_DAY   => $jalaliFrom->addDays($this->duration_value),
            self::UNIT_MONTH => $jalaliFrom->addMonths($this->duration_value),
            self::UNIT_YEAR  => $jalaliFrom->addYears($this->duration_value),
            default          => $jalaliFrom,
        };

        return $jalaliExpiry->toCarbon();
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
