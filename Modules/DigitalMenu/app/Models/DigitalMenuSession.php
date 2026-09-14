<?php

namespace Modules\DigitalMenu\Models;

use Illuminate\Database\Eloquent\Model;
use Modules\Invoice\Models\Invoice;

class DigitalMenuSession extends Model
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_EXPIRED   = 'expired';

    protected $fillable = [
        'invoice_id',
        'code',
        'session_token',
        'category_ids',
        'status',
        'entered_at',
        'submitted_at',
        'expires_at',
    ];

    protected $casts = [
        'category_ids' => 'array',
        'entered_at'   => 'datetime',
        'submitted_at' => 'datetime',
        'expires_at'   => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function selections()
    {
        return $this->hasMany(DigitalMenuSelection::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}