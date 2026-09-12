<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'purchase_price',
        'sale_price',
        'description',
        'image_path',
        'category_id',
        'tracks_stock',
        'has_serial_number',
        'has_warranty',
        'is_consignment',
    ];

    protected $casts = [
        'purchase_price'     => 'decimal:2',
        'sale_price'         => 'decimal:2',
        'tracks_stock'       => 'boolean',
        'has_serial_number'  => 'boolean',
        'has_warranty'       => 'boolean',
        'is_consignment'     => 'boolean',
    ];
    public function category()
    {
        return $this->belongsTo(Category::class);
    }
    public function serialNumbers()
    {
        return $this->hasMany(ItemSerialNumber::class);
    }
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function serviceJobItems()
    {
        return $this->hasMany(ServiceJobItem::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }
}
