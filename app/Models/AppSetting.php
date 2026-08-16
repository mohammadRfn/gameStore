<?php

namespace App\Models;

use App\Enums\SettingValueType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppSetting extends Model
{
    protected $table = 'app_settings';

    protected $fillable = [
        'group_id', 'setting_key', 'setting_value', 'value_type',
        'default_value', 'is_locked', 'is_autoload', 'is_encrypted',
        'description', 'updated_by',
    ];

    protected $casts = [
        'is_locked'    => 'boolean',
        'is_autoload'  => 'boolean',
        'is_encrypted' => 'boolean',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(SettingGroup::class, 'group_id');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /** Actual value: decrypt if needed, then cast to the native type. */
    public function typedValue(): mixed
    {
        $raw = $this->setting_value;

        if ($this->is_encrypted && $raw !== null) {
            try {
                $raw = decrypt($raw);
            } catch (\Throwable) {
                return null;
            }
        }

        $type = SettingValueType::tryFrom($this->value_type);

        return $type?->cast($raw) ?? $raw;
    }

    /** Fallback used when setting_value is NULL. */
    public function effectiveValue(): mixed
    {
        return $this->setting_value === null
            ? $this->default_value
            : $this->typedValue();
    }

    public function scopeGroupCode($query, string $code)
    {
        return $query->whereHas('group', fn ($q) => $q->where('code', $code));
    }

    public function scopeAutoload($query)
    {
        return $query->where('is_autoload', true);
    }

    public function scopeEditable($query)
    {
        return $query->where('is_locked', false);
    }
}
