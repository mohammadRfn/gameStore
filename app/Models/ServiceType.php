<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceType extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'platform',
        'category',
        'base_price',
        'description',
        'is_active',
    ];

    protected $casts = [
        'base_price' => 'decimal:2',
        'is_active'  => 'boolean',
    ];

    public function serviceJobs(): HasMany
    {
        return $this->hasMany(ServiceJob::class);
    }
}