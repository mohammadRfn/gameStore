<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class MonthlySale extends Model
{
    use SoftDeletes;

    protected $table = 'monthly_sales';

    protected $fillable = [
        'year',
        'month',
        'total_invoices',
        'confirmed_invoices',
        'total_revenue',
        'products_revenue',
        'services_revenue',
        'total_cost',
        'profit',
        'unique_customers',
        'new_customers',
    ];

    protected $casts = [
        'year'               => 'integer',
        'month'              => 'integer',
        'total_invoices'     => 'integer',
        'confirmed_invoices' => 'integer',
        'total_revenue'      => 'decimal:2',
        'products_revenue'   => 'decimal:2',
        'services_revenue'   => 'decimal:2',
        'total_cost'         => 'decimal:2',
        'profit'             => 'decimal:2',
        'unique_customers'   => 'integer',
        'new_customers'      => 'integer',
    ];
}