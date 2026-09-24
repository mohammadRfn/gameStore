<?php

declare(strict_types=1);

namespace Modules\Licensing\Services;

use Illuminate\Support\Carbon;
use Modules\Licensing\Models\LicenseState;

/**
 * تنها منبع حقیقت برای «این نصب کدام ماژول‌ها را مجاز است».
 * entitlements از توکن امضاشده (Ed25519) خوانده می‌شود، نه از ستون قابل‌ویرایش جدول.
 */
class LicenseGate
{
    /** @var array<string, mixed>|null */
    private ?array $payload = null;

    private bool $resolved = false;

    public function __construct(private readonly DeviceFingerprint $fingerprint) {}

    public function flush(): void
    {
        $this->payload  = null;
        $this->resolved = false;
    }

    /** @return array<string, mixed>|null */
    public function payload(): ?array
    {
        if ($this->resolved) {
            return $this->payload;
        }

        $this->resolved = true;

        $state = LicenseState::current();

        if (! $state->isActive()) {
            return null;
        }

        $payload = $this->verify((string) $state->token);

        if ($payload === null) {
            return null;
        }

        if (! hash_equals(strtolower((string) ($payload['fingerprint'] ?? '')), strtolower($this->fingerprint->current()))) {
            return null;
        }

        return $this->payload = $payload;
    }

    // امضا معتبر + اثرانگشت درست + منقضی نشده + مهلت آفلاین تمام نشده
    public function isUsable(): bool
    {
        $p = $this->payload();

        if ($p === null) {
            return false;
        }

        $now = Carbon::now('UTC')->getTimestamp();

        if (isset($p['expires_at']) && (int) $p['expires_at'] < $now) {
            return false;
        }

        return (int) ($p['valid_until'] ?? 0) >= $now;
    }

    /** @return list<string> */
    public function modules(): array
    {
        return $this->isUsable()
            ? array_values((array) ($this->payload()['entitlements'] ?? []))
            : [];
    }

    public function allows(string $module): bool
    {
        return in_array($module, $this->modules(), true);
    }

    /** @return array<string, mixed>|null */
    private function verify(string $token): ?array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            return null;
        }

        [$h, $p, $s] = $parts;

        $header  = json_decode($this->b64($h), true);
        $payload = json_decode($this->b64($p), true);

        if (! is_array($header) || ! is_array($payload)) {
            return null;
        }

        $key = (config('licensing.public_keys') ?? [])[$header['kid'] ?? ''] ?? null;

        if (! is_string($key)) {
            return null;
        }

        $public = $this->b64($key);
        $sig    = $this->b64($s);

        if (strlen($public) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return null;
        }

        return sodium_crypto_sign_verify_detached($sig, $h . '.' . $p, $public) ? $payload : null;
    }

    private function b64(string $value): string
    {
        $value = strtr($value, '-_', '+/');
        $value .= str_repeat('=', (4 - strlen($value) % 4) % 4);

        return (string) base64_decode($value, true);
    }
}