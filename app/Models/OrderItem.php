<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OrderItem extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'invoice_id',
        'item_id',
        'category_id',
        'product_name',
        'quantity',
        'price',
        'total_price',
        'image_path',
        'deduct_from_stock',
        'restocked_at',
    ];

    protected $casts = [
        'price'             => 'decimal:2',
        'total_price'       => 'decimal:2',
        'quantity'          => 'integer',
        'deduct_from_stock' => 'boolean',
        'restocked_at'      => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }
}
