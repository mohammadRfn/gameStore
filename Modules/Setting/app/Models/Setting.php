<?php

declare(strict_types=1);

namespace Modules\Setting\Models;

use Modules\Setting\Enums\Settings\SettingGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * مدل تنظیمات.
 *
 * @property int $id
 * @property string $group
 * @property string $key
 * @property string|null $value
 * @property string $type
 * @property int|null $updated_by
 */
class Setting extends Model
{
    protected $fillable = [
        'group',
        'key',
        'value',
        'type',
        'updated_by',
    ];

    protected $casts = [
        'group' => SettingGroup::class,
        'updated_by' => 'integer',
    ];

    /**
     * Scope برای فیلتر بر اساس گروه.
     */
    public function scopeOfGroup($query, SettingGroup|string $group): \Illuminate\Database\Eloquent\Builder
    {
        $value = $group instanceof SettingGroup ? $group->value : $group;
        return $query->where('group', $value);
    }

    /**
     * کاربری که آخرین بار این تنظیم را تغییر داده.
     */
    public function updatedByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }
}
