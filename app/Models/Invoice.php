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
        'payment_status',
        'payment_method',
        'payment_terminal_mode',
        'stock_deducted',
        'is_returned',
        'returned_at',
        'receipt_image_path',
        'paid_at',
    ];
    protected $casts = [
        'stock_deducted' => 'boolean',
        'is_returned'    => 'boolean',
        'returned_at'    => 'datetime',
        'paid_at'        => 'datetime',
    ];

    const PAYMENT_UNPAID = 'unpaid';
    const PAYMENT_PAID   = 'paid';
    const PAYMENT_RETURNED = 'returned';
    const PAYMENT_METHOD_CASH         = 'cash';
    const PAYMENT_METHOD_CARD_TO_CARD = 'card_to_card';
    const PAYMENT_METHOD_POS_TERMINAL = 'pos_terminal';

    const TERMINAL_MODE_MANUAL    = 'manual';
    const TERMINAL_MODE_AUTOMATIC = 'automatic';


    public function isPaid(): bool
    {
        return $this->payment_status === self::PAYMENT_PAID;
    }

    public function isLocked(): bool
    {
        return $this->payment_status !== self::PAYMENT_UNPAID;
    }
    public function request()
    {
        return $this->belongsTo(Request::class);
    }
    public function isReturned(): bool
    {
        return $this->payment_status === self::PAYMENT_RETURNED;
    }
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function serviceJobs()
    {
        return $this->hasMany(ServiceJob::class);
    }
    public function adjustments()
    {
        return $this->hasMany(InvoiceAdjustment::class);
    }

    public function recalculateAmounts(): void
    {
        $this->loadMissing('orderItems', 'adjustments', 'serviceJobs');

        $itemsBase    = (float) $this->orderItems->where('is_returned', false)->sum('total_price');
        $servicesBase = (float) $this->serviceJobs->sum('final_price');
        $base = $itemsBase + $servicesBase;

        $final = $base;
        foreach ($this->adjustments as $adjustment) {
            $amount = $adjustment->resolveAmount($base);
            $final += $adjustment->direction === 'increase' ? $amount : -$amount;
        }

        $this->total_amount = $base;
        $this->final_amount = max($final, 0);
        $this->save();
    }
}
