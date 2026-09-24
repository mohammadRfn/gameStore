<?php

declare(strict_types=1);

namespace Modules\Licensing\Services;

use Illuminate\Support\Facades\Cache;
use Modules\Licensing\Models\LicenseState;

/**
 * تولید و تثبیت اثر انگشت این نصب از گیم‌استور.
 *
 * این fingerprint هم در فعال‌سازی (activation/request, activation/redeem) و هم
 * در هدر X-GS-Fingerprint هر درخواست بعدی (heartbeat و - از طریق
 * Modules\AuditLog\Support\ClientIdentity - ارسال لاگ) استفاده می‌شود؛ سرور
 * (AuthenticateLicenseToken) این مقدار را با fingerprint داخل توکن مقایسه
 * می‌کند، پس باید همیشه دقیقاً یکسان بماند - به همین دلیل، بعد از اولین
 * محاسبه، در LicenseState «منجمد» می‌شود و دیگر حتی با پاک‌شدن cache تغییر
 * نمی‌کند (بر خلاف نسخه‌ی قدیمی‌تر AuditLog که فقط روی cache تکیه می‌کرد).
 */
class DeviceFingerprint
{
    private const CACHE_KEY = 'licensing:device-fingerprint';

    public function current(): string
    {
        $frozen = LicenseState::current()->fingerprint;

        if ($frozen !== null && $frozen !== '') {
            return $frozen;
        }

        $computed = Cache::rememberForever(self::CACHE_KEY, fn (): string => $this->compute());

        // اولین باری که fingerprint خواسته می‌شود، در دیتابیس منجمد می‌شود
        LicenseState::current()->forceFill(['fingerprint' => $computed])->save();

        return $computed;
    }

    private function compute(): string
    {
        $parts = [
            php_uname('n'),
            php_uname('s'),
            php_uname('m'),
            (string) config('app.key'),
            base_path(),
        ];

        return hash('sha256', implode('|', $parts));
    }
}