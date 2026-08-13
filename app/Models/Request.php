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

    public const STATUS_PENDING     = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_CANCELED    = 'canceled';

    /**
     * فقط وقتی درخواست هنوز «در انتظار» است به «در جریان» می‌بریمش —
     * تا مثلاً یک درخواست لغوشده دوباره فعال نشود.
     */
    public function markInProgress(): void
    {
        if ($this->status === self::STATUS_PENDING) {
            $this->update(['status' => self::STATUS_IN_PROGRESS]);
        }
    }

    public function markCompleted(): void
    {
        $this->update(['status' => self::STATUS_COMPLETED]);
    }

    public function markCanceled(): void
    {
        $this->update(['status' => self::STATUS_CANCELED]);
    }

    /**
     * وقتی پرداخت لغو می‌شود، اگر قبلاً completed شده بود برش‌گردان به in_progress.
     */
    public function revertToInProgress(): void
    {
        if ($this->status === self::STATUS_COMPLETED) {
            $this->update(['status' => self::STATUS_IN_PROGRESS]);
        }
    }

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
