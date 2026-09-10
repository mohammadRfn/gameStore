<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WarrantyProvider extends Model
{
    protected $fillable = [
        'name',
        'phone',
        'email',
        'website',
        'instagram',
        'address',
        'description',
    ];

    public function warranties()
    {
        return $this->hasMany(Warranty::class);
    }
}