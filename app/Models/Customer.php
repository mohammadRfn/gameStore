<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'email', 'phone', 'address'];

    public function requests()
    {
        return $this->hasMany(Request::class);
    }

    public function invoices()
    {
        return $this->hasMany(Invoice::class);
    }

    public function serviceJobs()
    {
        return $this->hasMany(ServiceJob::class);
    }
}