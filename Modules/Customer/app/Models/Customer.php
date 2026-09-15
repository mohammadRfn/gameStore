<?php

namespace Modules\Customer\Models;

use Modules\Invoice\Models\Invoice;
use Modules\Service\Models\ServiceJob;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Modules\Request\Models\Request;

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