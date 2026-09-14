<?php

namespace Modules\Profile\Models;

use Illuminate\Database\Eloquent\Model;

class StoreProfile extends Model
{
    protected $table = 'store_profiles';

    protected $fillable = [
        'legal_name', 'brand_name', 'slug',
        'phone', 'secondary_phone', 'email', 'website', 'instagram', 'telegram',
        'address_street', 'address_city', 'address_province', 'address_postal', 'address_country',
        'owner_first_name', 'owner_last_name', 'owner_national_id', 'owner_phone', 'owner_email',
        'logo_path', 'cover_path',
        'is_primary', 'status',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];


    public function scopePrimary($query)
    {
        return $query->where('is_primary', true)->where('status', 'active');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function getDisplayNameAttribute(): string
    {
        return $this->brand_name ?: $this->legal_name;
    }

    /** Full structured address, ready for receipts / invoices. */
    public function getFullAddressAttribute(): string
    {
        return implode('، ', array_filter([
            $this->address_street,
            $this->address_city,
            $this->address_province,
            $this->address_postal,
            $this->address_country,
        ]));
    }

    /** آدرس مخصوص فاکتور: استان، شهر، خیابان — بدون کدپستی/کشور. */
    public function getInvoiceAddressAttribute(): string
    {
        return implode('، ', array_filter([
            $this->address_province,
            $this->address_city,
            $this->address_street,
        ]));
    }

    public function getOwnerFullNameAttribute(): ?string
    {
        return trim(($this->owner_first_name ?? '') . ' ' . ($this->owner_last_name ?? '')) ?: null;
    }
}

