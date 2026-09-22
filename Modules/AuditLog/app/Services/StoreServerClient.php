<?php

declare(strict_types=1);

namespace Modules\AuditLog\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\AuditLog\Exceptions\LogShippingException;
use Modules\AuditLog\Support\ClientIdentity;
use Throwable;

/**
 * کلاینت HTTP امن برای StoreServer.
 *
 * هدرهای ارسالی (هم‌راستا با ClientApi\Http\Middleware\VerifyClientSignature):
 *   Authorization: Bearer <license-token>
 *   X-GS-Timestamp, X-GS-Nonce, X-GS-Fingerprint
 *   X-GS-Signature      = base64(HMAC-SHA256(canonical, client_secret))
 *   X-GS-Signature-Alg  = HMAC-SHA256
 *   X-GS-Client         = gamestore/<version>
 *   X-GS-Batch-Id / Idempotency-Key = uuid دسته (برای جلوگیری از درج تکراری)
 *
 * canonical = METHOD \n PATH \n timestamp \n nonce \n sha256(body)
 */
class StoreServerClient
{
    public function __construct(private readonly ClientIdentity $identity)
    {
    }

    public function ingestUrl(): string
    {
        return $this->url((string) config('auditlog.shipping.endpoints.ingest', '/api/v1/logs/ingest'));
    }

    public function pingUrl(): string
    {
        return $this->url((string) config('auditlog.shipping.endpoints.ping', '/api/v1/logs/ping'));
    }

    public function url(string $path): string
    {
        $base = rtrim((string) config('auditlog.shipping.base_url', ''), '/');

        if ($base === '') {
            throw LogShippingException::misconfigured('آدرس StoreServer تنظیم نشده است (STORE_SERVER_URL).');
        }

        return $base . '/' . ltrim($path, '/');
    }

    /**
     * ارسال یک دسته لاگ.
     *
     * @param  array<string, mixed>  $payload
     * @return array{status:int, body:array<string, mixed>, duration_ms:int}
     *
     * @throws LogShippingException
     */
    public function sendBatch(string $batchUuid, array $payload): array
    {
        $url  = $this->ingestUrl();
        $body = $this->encode($payload, $gzip);

        $startedAt = microtime(true);

        try {
            $response = $this->request($url, $body, $gzip, $batchUuid)->send('POST', $url, [
                'body' => $body,
            ]);
        } catch (ConnectionException $e) {
            throw LogShippingException::transport($e);
        } catch (Throwable $e) {
            throw LogShippingException::transport($e);
        }

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);

        return $this->handle($response, $durationMs);
    }

    /**
     * تست اتصال و اعتبار کلید/توکن.
     *
     * @return array{status:int, body:array<string, mixed>, duration_ms:int}
     */
    public function ping(): array
    {
        $url  = $this->pingUrl();
        $body = $this->encode(['schema' => LogPayloadBuilder::SCHEMA_VERSION, 'client' => $this->identity->toArray()], $gzip);

        $startedAt = microtime(true);

        try {
            $response = $this->request($url, $body, $gzip, (string) Str::uuid())->send('POST', $url, ['body' => $body]);
        } catch (Throwable $e) {
            throw LogShippingException::transport($e);
        }

        return $this->handle($response, (int) round((microtime(true) - $startedAt) * 1000));
    }

    /**
     * @return array{status:int, body:array<string, mixed>, duration_ms:int}
     */
    private function handle(Response $response, int $durationMs): array
    {
        /** @var array<string, mixed> $json */
        $json = is_array($response->json()) ? (array) $response->json() : [];

        if ($response->failed()) {
            throw LogShippingException::rejected(
                $response->status(),
                (string) (data_get($json, 'error.message') ?? data_get($json, 'message') ?? 'پاسخ ناموفق از StoreServer'),
                $json,
            );
        }

        // قرارداد پاسخ StoreServer: { ok: bool, data: {...} }
        if (array_key_exists('ok', $json) && $json['ok'] === false) {
            throw LogShippingException::rejected(
                $response->status(),
                (string) (data_get($json, 'error.message') ?? 'سرور دسته را نپذیرفت'),
                $json,
            );
        }

        return ['status' => $response->status(), 'body' => $json, 'duration_ms' => $durationMs];
    }

    private function request(string $url, string $body, bool $gzip, string $batchUuid): PendingRequest
    {
        $config = (array) config('auditlog.shipping');

        $request = Http::withHeaders($this->headers($url, $body, $gzip, $batchUuid))
            ->timeout((int) ($config['timeout'] ?? 20))
            ->connectTimeout((int) ($config['connect_timeout'] ?? 5))
            ->retry(
                max(1, (int) ($config['retries'] ?? 2)),
                (int) ($config['retry_delay_ms'] ?? 750),
                throw: false,
            );

        if (($config['verify_ssl'] ?? true) === false) {
            $request = $request->withoutVerifying();
        }

        $token = $this->identity->licenseToken();

        if ($token !== null && $token !== '') {
            $request = $request->withToken($token);
        }

        return $request;
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $url, string $body, bool $gzip, string $batchUuid): array
    {
        /** @var array<string, string> $names */
        $names = (array) config('auditlog.shipping.headers', []);

        $timestamp = (string) now()->utc()->getTimestamp();
        $nonce     = bin2hex(random_bytes(16)); // 32 کاراکتر؛ در بازه‌ی مجاز StoreServer

        $headers = [
            'Accept'                                     => 'application/json',
            'Content-Type'                               => 'application/json',
            $names['timestamp']   ?? 'X-GS-Timestamp'    => $timestamp,
            $names['nonce']       ?? 'X-GS-Nonce'        => $nonce,
            $names['fingerprint'] ?? 'X-GS-Fingerprint'  => $this->identity->fingerprint(),
            $names['client']      ?? 'X-GS-Client'       => 'gamestore/' . $this->identity->appVersion(),
            $names['batch']       ?? 'X-GS-Batch-Id'     => $batchUuid,
            $names['idempotency'] ?? 'Idempotency-Key'   => $batchUuid,
        ];

        if ($gzip) {
            $headers['Content-Encoding'] = 'gzip';
        }

        if ((bool) config('auditlog.shipping.auth.sign', true)) {
            $secret = (string) ($this->identity->clientSecret() ?? '');

            if ($secret === '') {
                throw LogShippingException::misconfigured('کلید امضا تنظیم نشده است (STORE_SERVER_CLIENT_SECRET).');
            }

            $canonical = implode("\n", [
                'POST',
                (string) parse_url($url, PHP_URL_PATH),
                $timestamp,
                $nonce,
                hash('sha256', $body),
            ]);

            $headers[$names['signature'] ?? 'X-GS-Signature'] = base64_encode(hash_hmac('sha256', $canonical, $secret, true));
            $headers[$names['algorithm'] ?? 'X-GS-Signature-Alg'] = 'HMAC-SHA256';
        }

        return $headers;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function encode(array $payload, ?bool &$gzip = null): string
    {
        $json = (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);

        $threshold = (int) config('auditlog.shipping.gzip_threshold_bytes', 16384);
        $gzip = (bool) config('auditlog.shipping.gzip', true) && strlen($json) > $threshold && function_exists('gzencode');

        if ($gzip) {
            $compressed = gzencode($json, 6);

            if ($compressed !== false) {
                return $compressed;
            }

            $gzip = false;
        }

        return $json;
    }
}
