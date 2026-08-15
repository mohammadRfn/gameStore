<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * تنظیمات ماژول بکاپ (key/value با scope اختیاری فروشگاه).
 */
class BackupSetting extends Model
{
    protected $table = 'backup_settings';

    protected $guarded = ['id'];

    protected $casts = [
        'is_locked' => 'boolean',
    ];

    /** تبدیل مقدار متنی ذخیره‌شده به تایپ واقعی. */
    public function typedValue(): mixed
    {
        return match ($this->value_type) {
            'integer' => $this->value === null ? null : (int) $this->value,
            'boolean' => $this->value === null ? null : filter_var($this->value, FILTER_VALIDATE_BOOL),
            'json'    => $this->value === null ? null : json_decode((string) $this->value, true),
            default   => $this->value,
        };
    }
}
