<?php

namespace Modules\Category\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Invoice\Models\OrderItem;
use Modules\Request\Models\Request;
use Modules\Stock\Models\Item;

class Category extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'default_tracks_stock'];

    protected $casts = [
        'default_tracks_stock' => 'boolean',
    ];

    public function items()
    {
        return $this->hasMany(Item::class);
    }
    public function requests()
    {
        return $this->belongsToMany(Request::class, 'request_categories');
    }

    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }
}
