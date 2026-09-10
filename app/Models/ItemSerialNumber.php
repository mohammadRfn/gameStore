<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemSerialNumber extends Model
{
    const STATUS_IN_STOCK = 'in_stock';
    const STATUS_RESERVED = 'reserved';
    const STATUS_SOLD     = 'sold';
    const STATUS_REMOVED  = 'removed';

    protected $fillable = [
        'item_id',
        'serial_number',
        'status',
        'stock_movement_id',
        'order_item_id',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function orderItem()
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function stockMovement()
    {
        return $this->belongsTo(StockMovement::class);
    }

    public function warranty()
    {
        return $this->hasOne(Warranty::class, 'item_serial_number_id');
    }
}