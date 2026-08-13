<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InvoiceAdjustment extends Model
{
    protected $fillable = ['invoice_id', 'title', 'type', 'direction', 'value'];

    protected $casts = [
        'value' => 'float',
    ];

    const TYPE_PERCENTAGE = 'percentage';
    const TYPE_FIXED = 'fixed';

    const DIRECTION_INCREASE = 'increase';
    const DIRECTION_DECREASE = 'decrease';

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * مبلغ نهایی این تعدیل با توجه به مبلغ پایه فاکتور محاسبه می‌شود.
     */
    public function resolveAmount(float $baseAmount): float
    {
        return $this->type === self::TYPE_PERCENTAGE
            ? $baseAmount * ($this->value / 100)
            : $this->value;
    }
}