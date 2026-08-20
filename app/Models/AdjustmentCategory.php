<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AdjustmentCategory extends Model
{
    public $timestamps = true;

    protected $fillable = ['key', 'label', 'default_counts_as_revenue', 'sort_order', 'is_active'];

    protected $casts = [
        'default_counts_as_revenue' => 'boolean',
        'is_active'                 => 'boolean',
    ];

    public function adjustments()
    {
        return $this->hasMany(InvoiceAdjustment::class, 'category_key', 'key');
    }
}