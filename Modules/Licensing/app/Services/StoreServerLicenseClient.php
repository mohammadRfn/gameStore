<?php

declare(strict_types=1);

namespace Modules\Licensing\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\Licensing\Exceptions\LicenseServerException;

/**
 * کلاینت HTTP برای اندپوینت‌های فعال‌سازی/heartbeat روی StoreServer.
 *
 * هدرها دقیقاً هم‌راستا با آنچه سرور واقعاً می‌خواند:
 *   - client.sig (VerifyClientSignature): فقط X-GS-Timestamp / X-GS-Nonce (ضد replay)
 *   - license.token (AuthenticateLicenseToken): Authorization: Bearer <token> +
 *     X-GS-Fingerprint که باید با fingerprint داخل توکن یکی باشد
 * نکته: این هدرها عمداً همان‌هایی هستند که Modules\AuditLog\Services\StoreServerClient
 * می‌سازد؛ اگر یکی تغییر کرد آن یکی را هم به‌روز کنید تا از هم واگرا نشوند.
 */
class StoreServerLicenseClient
{
    public function __construct(private readonly DeviceFingerprint $fingerprint) {}

    /** @return array{status: int, body: array<string, mixed>} */
    public function activationRequest(array $payload): array
    {
        return $this->post($this->path('activation_request'), $payload, withToken: false);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function activationStatus(string $requestUuid): array
    {
        $path = str_replace('{uuid}', $requestUuid, (string) config('licensing.server.endpoints.activation_status'));
        $url  = $this->baseUrl() . $path;

        try {
            // این مسیر روی سرور اصلاً محافظت نشده (نه client.sig نه توکن)، پس بدون هدر امنیتی
            $response = Http::timeout($this->timeout())->connectTimeout($this->connectTimeout())
                ->withHeaders(['X-GS-Client' => $this->clientHeader()])
                ->get($url);
        } catch (ConnectionException $e) {
            throw LicenseServerException::transport($e);
        }

        return ['status' => $response->status(), 'body' => (array) $response->json()];
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function activationRedeem(array $payload): array
    {
        return $this->post($this->path('activation_redeem'), $payload, withToken: false);
    }

    /** @return array{status: int, body: array<string, mixed>} */
    public function heartbeat(array $payload, string $token): array
    {
        return $this->post($this->path('heartbeat'), $payload, withToken: true, token: $token);
    }
    /** @return array{status: int, body: array<string, mixed>} */
    public function state(string $token): array
    {
        $url = $this->baseUrl() . $this->path('state');

        try {
            // بدون retry و با timeout کوتاه؛ این پولینگ نباید چیزی را معطل کند
            $response = Http::withHeaders($this->headers(true, $token))
                ->withOptions(['verify' => (bool) config('licensing.server.verify_ssl', true)])
                ->timeout(5)->connectTimeout(3)
                ->get($url);
        } catch (ConnectionException $e) {
            throw LicenseServerException::transport($e);
        }

        return ['status' => $response->status(), 'body' => (array) $response->json()];
    }
    /**
     * کلیدهای عمومی Ed25519 سرور (فقط برای حالت TOFU در محیط تست).
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function keys(): array
    {
        return $this->get($this->path('keys'), withToken: false);
    }

    /**
     * فهرست کامل پچ‌های قابل‌اعمال برای این دستگاه، همراه manifest، امضا و لینک دانلود تازه.
     *
     * @return array{status: int, body: array<string, mixed>}
     */
    public function patches(string $token): array
    {
        return $this->get($this->path('patches'), withToken: true, token: $token);
    }

    /**
     * گزارش وضعیت پچ: downloading|downloaded|applying|applied|failed|rolled_back
     *
     * @param array<string, mixed> $payload status + version_before/version_after/error_message
     * @return array{status: int, body: array<string, mixed>}
     */
    public function patchStatus(string $patchCode, array $payload, string $token): array
    {
        $path = str_replace('{code}', rawurlencode($patchCode), (string) config('licensing.server.endpoints.patch_status'));

        return $this->post($path, $payload, withToken: true, token: $token);
    }

    /**
     * هدرهای دانلود پچ. لینک امضاشده نیاز به nonce ندارد (resume ممکن بماند)،
     * ولی توکن و اثرانگشت لازم است.
     *
     * @return list<string>
     */
    public function downloadHeaders(string $token): array
    {
        $out = [];
        foreach ($this->headers(true, $token) as $name => $value) {
            $out[] = "{$name}: {$value}";
        }

        return $out;
    }

    /** @return array{status: int, body: array<string, mixed>} */
    private function get(string $path, bool $withToken, ?string $token = null): array
    {
        $url = $this->baseUrl() . $path;

        try {
            $response = $this->request($withToken, $token)->get($url);
        } catch (ConnectionException $e) {
            throw LicenseServerException::transport($e);
        }

        return ['status' => $response->status(), 'body' => (array) $response->json()];
    }

    private function post(string $path, array $payload, bool $withToken, ?string $token = null): array
    {
        $url = $this->baseUrl() . $path;

        try {
            $response = $this->request($withToken, $token)->post($url, $payload);
        } catch (ConnectionException $e) {
            throw LicenseServerException::transport($e);
        }

        return ['status' => $response->status(), 'body' => (array) $response->json()];
    }

    private function request(bool $withToken, ?string $token): PendingRequest
    {
        return Http::withHeaders($this->headers($withToken, $token))
            ->withOptions(['verify' => (bool) config('licensing.server.verify_ssl', true)])
            ->timeout($this->timeout())
            ->connectTimeout($this->connectTimeout())
            ->retry((int) config('licensing.server.retries', 1), 500);
    }

    /** @return array<string, string> */
    private function headers(bool $withToken, ?string $token): array
    {
        $names = (array) config('licensing.headers', []);

        $headers = [
            'Accept'                                => 'application/json',
            ($names['timestamp'] ?? 'X-GS-Timestamp') => (string) now('UTC')->getTimestamp(),
            ($names['nonce'] ?? 'X-GS-Nonce')         => (string) Str::uuid(),
            ($names['client'] ?? 'X-GS-Client')       => $this->clientHeader(),
        ];

        if ($withToken) {
            $headers[$names['fingerprint'] ?? 'X-GS-Fingerprint'] = $this->fingerprint->current();

            if ($token !== null) {
                $headers['Authorization'] = 'Bearer ' . $token;
                // fallback: بعضی هاست‌ها (Nginx/PHP-FPM یا LiteSpeed بدون تنظیم pass-through)
                // هدر Authorization را قبل از رسیدن به PHP حذف می‌کنند؛ چون X-GS-* همیشه سالم
                // می‌رسد، توکن را اینجا هم می‌فرستیم تا سرور بتواند به آن fallback کند.
                $headers[$names['token'] ?? 'X-GS-License-Token'] = $token;
            }
        }

        return $headers;
    }

    private function clientHeader(): string
    {
        return 'gamestore/' . AppVersion::current();
    }

    private function baseUrl(): string
    {
        $url = (string) config('licensing.server.base_url', '');

        if ($url === '') {
            throw LicenseServerException::misconfigured('آدرس StoreServer تنظیم نشده است (STORE_SERVER_URL).');
        }

        return $url;
    }

    private function path(string $key): string
    {
        return (string) config("licensing.server.endpoints.{$key}");
    }

    private function timeout(): int
    {
        return (int) config('licensing.server.timeout', 15);
    }

    private function connectTimeout(): int
    {
        return (int) config('licensing.server.connect_timeout', 5);
    }
}
