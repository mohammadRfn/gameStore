<?php

declare(strict_types=1);

namespace Modules\AuditLog\Support;

use Modules\Licensing\Models\LicenseState;
use Modules\Licensing\Services\DeviceFingerprint;
use Throwable;

/**
 * هویت این نصب از گیم‌استور؛ در هدرها و بدنه‌ی ارسال به StoreServer
 * استفاده می‌شود تا سرور بداند لاگ از کدام فروشگاه/دستگاه آمده است.
 *
 * license_uuid/license_token/fingerprint از ماژول Licensing (که فلوی
 * فعال‌سازی واقعی و ذخیره‌سازی توکن را انجام می‌دهد) خوانده می‌شوند، نه از
 * env() که هیچ‌جا در زمان اجرا نوشته نمی‌شد. مقادیر auditlog.shipping.auth.*
 * فقط به‌عنوان override دستی (مثلاً برای تست) نگه داشته شده‌اند.
 */
class ClientIdentity
{
    public function __construct(private readonly DeviceFingerprint $fingerprint) {}

    public function environment(): string
    {
        return (string) config('app.env', 'production');
    }

    public function appVersion(): string
    {
        return (string) config('app.version', env('APP_VERSION', '1.0.0'));
    }

    public function hostname(): string
    {
        return mb_substr((string) (gethostname() ?: 'unknown'), 0, 191);
    }

    public function licenseUuid(): ?string
    {
        $override = config('auditlog.shipping.auth.license_uuid');

        return $override ?: (LicenseState::current()->license_uuid ?: null);
    }

    public function licenseToken(): ?string
    {
        $override = config('auditlog.shipping.auth.license_token');

        return $override ?: (LicenseState::current()->token ?: null);
    }

    /**
     * اثر انگشت دستگاه - از ماژول Licensing گرفته می‌شود تا با مقداری که در
     * فعال‌سازی و heartbeat فرستاده شده دقیقاً یکی باشد (سرور این دو را
     * مقایسه می‌کند).
     */
    public function fingerprint(): string
    {
        $configured = (string) (config('auditlog.shipping.auth.fingerprint') ?? '');

        return $configured !== '' ? $configured : $this->fingerprint->current();
    }

    /**
     * اطلاعات فروشگاه برای غنی‌سازی payload (اختیاری، در صورت وجود ماژول Profile).
     *
     * @return array<string, mixed>
     */
    public function storeProfile(): array
    {
        $class = '\\Modules\\Profile\\Models\\StoreProfile';

        if (! class_exists($class)) {
            return [];
        }

        try {
            /** @var \Illuminate\Database\Eloquent\Model|null $profile */
            $profile = $class::query()->first();

            if ($profile === null) {
                return [];
            }

            return array_filter([
                'id'    => $profile->getKey(),
                'name'  => $profile->getAttribute('name') ?? $profile->getAttribute('store_name'),
                'phone' => $profile->getAttribute('phone'),
                'city'  => $profile->getAttribute('city'),
            ], static fn ($value) => $value !== null);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'app'              => 'gamestore',
            'app_version'      => $this->appVersion(),
            'environment'      => $this->environment(),
            'hostname'         => $this->hostname(),
            'fingerprint'      => $this->fingerprint(),
            'license_uuid'     => $this->licenseUuid(),
            'php_version'      => PHP_VERSION,
            'laravel_version'  => app()->version(),
            'timezone'         => (string) config('app.timezone', 'UTC'),
            'store'            => $this->storeProfile(),
        ];
    }
}