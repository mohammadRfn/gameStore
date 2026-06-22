<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'request_id',
        'customer_id',
        'invoice_number',
        'total_amount',
        'is_confirmed',
    ];

    const STATUS_CONFIRMED     = 'confirmed';
    const STATUS_NOT_CONFIRMED = 'not_confirmed';

    public function isConfirmed(): bool
    {
        return $this->is_confirmed === self::STATUS_CONFIRMED;
    }

    public function isNotConfirmed(): bool
    {
        return $this->is_confirmed === self::STATUS_NOT_CONFIRMED;
    }

    public function request()
    {
        return $this->belongsTo(Request::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function serviceJob()
    {
        return $this->hasOne(ServiceJob::class);
    }
}
