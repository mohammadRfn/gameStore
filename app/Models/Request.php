<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Request extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'customer_id',
        'customer_name',
        'description',
        'status',
    ];

    public function categories()
    {
        return $this->belongsToMany(Category::class, 'request_categories');
    }

    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function serviceJobs()
    {
        return $this->hasMany(ServiceJob::class);
    }
}