<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SettingGroup extends Model
{
    protected $table = 'setting_groups';

    protected $fillable = ['code', 'label', 'icon', 'description', 'sort_order'];

    public function settings(): HasMany
    {
        return $this->hasMany(AppSetting::class, 'group_id');
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('label');
    }
}
