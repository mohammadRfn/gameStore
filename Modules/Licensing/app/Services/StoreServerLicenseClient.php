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
            }
        }

        return $headers;
    }

    private function clientHeader(): string
    {
        return 'gamestore/' . (string) config('app.version', env('APP_VERSION', '1.0.0'));
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