<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DailyItemStat extends Model
{
    use SoftDeletes;

    protected $table = 'daily_item_stats';

    protected $fillable = [
        'item_id',
        'stat_date',
        'product_name',
        'opening_stock',
        'closing_stock',
        'sold_quantity',
        'purchased_quantity',
        'adjusted_in_quantity',
        'adjusted_out_quantity',
        'revenue',
        'total_cost',
        'profit',
    ];

    protected $casts = [
        'stat_date'             => 'date',
        'opening_stock'         => 'integer',
        'closing_stock'         => 'integer',
        'sold_quantity'         => 'integer',
        'purchased_quantity'    => 'integer',
        'adjusted_in_quantity'  => 'integer',
        'adjusted_out_quantity' => 'integer',
        'revenue'               => 'decimal:2',
        'total_cost'            => 'decimal:2',
        'profit'                => 'decimal:2',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}