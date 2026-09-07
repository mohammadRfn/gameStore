<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CacheMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'targets'      => ['nullable', 'array'],
            'targets.*'    => ['string', Rule::in([
                'app',
                'settings',
                'config',
                'route',
                'view',
                'event',
                'compiled',
                'optimize',
                'bootstrap',
                'framework_files',
                'expired_database_cache',
                'logs',
                'sessions',
                'all',
            ])],
            'dry_run'             => ['nullable', 'boolean'],
            'include_logs'        => ['nullable', 'boolean'],
            'logs_older_than_days'=> ['nullable', 'integer', 'min:0', 'max:3650'],
            'include_sessions'    => ['nullable', 'boolean'],
            'warm_after_clear'    => ['nullable', 'boolean'],
            'warm_config'         => ['nullable', 'boolean'],
            'warm_views'          => ['nullable', 'boolean'],
            'warm_settings'       => ['nullable', 'boolean'],
            'run_sqlite_vacuum'   => ['nullable', 'boolean'],
            'force'               => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'targets'               => 'بخش‌های کش',
            'dry_run'               => 'اجرای آزمایشی',
            'include_logs'          => 'حذف لاگ‌ها',
            'logs_older_than_days'  => 'سن لاگ‌ها',
            'include_sessions'      => 'حذف نشست‌ها',
            'warm_after_clear'      => 'گرم‌سازی بعد از پاکسازی',
            'warm_config'           => 'گرم‌سازی config',
            'warm_views'            => 'گرم‌سازی viewها',
            'warm_settings'         => 'گرم‌سازی تنظیمات',
            'run_sqlite_vacuum'     => 'اجرای VACUUM SQLite',
            'force'                 => 'اجبار اجرا',
        ];
    }
}
