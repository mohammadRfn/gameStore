<?php

declare(strict_types=1);

namespace Modules\Licensing\Services;

use Illuminate\Support\Carbon;
use Modules\Licensing\Models\LicenseState;

/**
 * لایه‌ی orchestration: فرم‌ها/دستورات این سرویس را صدا می‌زنند، این سرویس
 * StoreServerLicenseClient را صدا می‌زند و نتیجه را در LicenseState ذخیره
 * می‌کند - هیچ HTTP یا جزئیات هدر بیرون از این ماژول دیده نمی‌شود.
 */
class LicensingService
{
    public function __construct(
        private readonly StoreServerLicenseClient $client,
        private readonly DeviceFingerprint $fingerprint,
    ) {}

    public function state(): LicenseState
    {
        return LicenseState::current();
    }

    /** @return array{ok: bool, message?: string} */
    public function submitActivationRequest(?string $customerName, ?string $customerPhone): array
    {
        $result = $this->client->activationRequest([
            'fingerprint'    => $this->fingerprint->current(),
            'customer_name'  => $customerName,
            'customer_phone' => $customerPhone,
            'app_version'    => $this->appVersion(),
            'system_info'    => $this->systemInfo(),
        ]);

        if ($result['status'] >= 200 && $result['status'] < 300) {
            $data = (array) ($result['body']['data'] ?? []);

            $this->state()->forceFill([
                'status'                   => LicenseState::STATUS_PENDING,
                'activation_request_uuid'  => $data['request_uuid'] ?? null,
                'poll_after_seconds'       => (int) ($data['poll_after_seconds'] ?? config('licensing.default_poll_seconds', 30)),
                'reject_reason'            => null,
                'updated_at'               => Carbon::now('UTC'),
            ])->save();

            return ['ok' => true];
        }

        return ['ok' => false, 'message' => $this->errorMessage($result)];
    }

    /**
     * وضعیت درخواست فعال‌سازی در انتظار را از سرور می‌پرسد و LicenseState را
     * به‌روز می‌کند. برای پولینگ دوره‌ای از صفحه‌ی فعال‌سازی صدا زده می‌شود.
     */
    public function refreshPendingStatus(): LicenseState
    {
        $state = $this->state();

        if ($state->status !== LicenseState::STATUS_PENDING || $state->activation_request_uuid === null) {
            return $state;
        }

        $result = $this->client->activationStatus($state->activation_request_uuid);
        $data   = (array) ($result['body']['data'] ?? []);

        if ($result['status'] === 404) {
            $state->forceFill(['status' => LicenseState::STATUS_UNACTIVATED, 'activation_request_uuid' => null])->save();

            return $state;
        }

        switch ($data['status'] ?? null) {
            case 'approved':
                return $this->applyApproved($state, $data);

            case 'rejected':
                $state->forceFill([
                    'status'        => LicenseState::STATUS_REJECTED,
                    'reject_reason' => $data['reason'] ?? null,
                ])->save();

                return $state;

            default:
                $state->forceFill([
                    'poll_after_seconds' => (int) ($data['poll_after_seconds'] ?? $state->poll_after_seconds),
                ])->save();

                return $state;
        }
    }

    /** @return array{ok: bool, message?: string} */
    public function redeemCode(string $code): array
    {
        $result = $this->client->activationRedeem([
            'code'        => $code,
            'fingerprint' => $this->fingerprint->current(),
            'app_version' => $this->appVersion(),
            'system_info' => $this->systemInfo(),
        ]);

        if ($result['status'] >= 200 && $result['status'] < 300) {
            $this->applyApproved($this->state(), (array) ($result['body']['data'] ?? []));

            return ['ok' => true];
        }

        return ['ok' => false, 'message' => $this->errorMessage($result)];
    }

    /**
     * heartbeat را در صورت رسیدن موعد ارسال می‌کند؛ از یک request-terminating
     * hook (مثل AuditLog) و هم از Schedule صدا زده می‌شود، پس idempotent است.
     */
    public function sendHeartbeatIfDue(): void
    {
        $state = $this->state();

        if (! $state->isActive() && ! $state->isLocked()) {
            return; // هنوز فعال نشده؛ چیزی برای heartbeat نیست
        }

        $intervalMinutes = $state->heartbeat_interval_minutes ?: (int) config('licensing.default_heartbeat_minutes', 60);
        $due = $state->last_heartbeat_at === null || $state->last_heartbeat_at->addMinutes($intervalMinutes)->isPast();

        if (! $due) {
            return;
        }

        $this->sendHeartbeatNow();
    }

    public function sendHeartbeatNow(): void
    {
        $state = $this->state();

        if ($state->token === null) {
            return;
        }

        $result = $this->client->heartbeat(['app_version' => $this->appVersion()], $state->token);
        $data   = (array) ($result['body']['data'] ?? []);

        if ($result['status'] === 423 || ($data['locked'] ?? false) === true) {
            $state->forceFill([
                'status'      => LicenseState::STATUS_LOCKED,
                'lock_code'   => (string) ($result['body']['error']['code'] ?? 'LICENSE_LOCKED'),
                'lock_reason' => (string) ($result['body']['error']['message'] ?? 'لایسنس قفل شده است.'),
                'token'       => null,
                'last_heartbeat_at' => Carbon::now('UTC'),
                'last_heartbeat_ok' => false,
            ])->save();

            return;
        }

        if ($result['status'] >= 200 && $result['status'] < 300) {
            $state->forceFill([
                'status'                     => LicenseState::STATUS_ACTIVE,
                'token'                      => $data['token'] ?? $state->token,
                'plan_code'                  => $data['plan'] ?? $state->plan_code,
                'entitlements'               => $data['entitlements'] ?? $state->entitlements,
                'expires_at'                 => isset($data['expires_at']) ? Carbon::parse($data['expires_at']) : $state->expires_at,
                'valid_until'                => isset($data['valid_until']) ? Carbon::parse($data['valid_until']) : $state->valid_until,
                'heartbeat_interval_minutes' => (int) ($data['heartbeat_interval_minutes'] ?? $state->heartbeat_interval_minutes),
                'lock_code'                  => null,
                'lock_reason'                => null,
                'last_heartbeat_at'          => Carbon::now('UTC'),
                'last_heartbeat_ok'          => true,
            ])->save();

            return;
        }

        // خطای موقتی (شبکه/سرور) - وضعیت محلی دست‌نخورده می‌ماند، دفعه‌ی بعد دوباره تلاش می‌شود
        $state->forceFill(['last_heartbeat_at' => Carbon::now('UTC'), 'last_heartbeat_ok' => false])->save();
    }

    /** @param array<string, mixed> $data */
    private function applyApproved(LicenseState $state, array $data): LicenseState
    {
        $state->forceFill([
            'status'                     => LicenseState::STATUS_ACTIVE,
            'license_uuid'               => $data['license_uuid'] ?? $state->license_uuid,
            'token'                      => $data['token'] ?? $state->token,
            'plan_code'                  => $data['plan'] ?? $state->plan_code,
            'entitlements'               => $data['entitlements'] ?? $state->entitlements,
            'expires_at'                 => isset($data['expires_at']) ? Carbon::parse($data['expires_at']) : null,
            'valid_until'                => isset($data['valid_until']) ? Carbon::parse($data['valid_until']) : null,
            'heartbeat_interval_minutes' => (int) ($data['heartbeat_interval_minutes'] ?? config('licensing.default_heartbeat_minutes', 60)),
            'activation_request_uuid'    => null,
            'reject_reason'              => null,
        ])->save();

        return $state;
    }

    /** @return array<string, mixed> */
    private function systemInfo(): array
    {
        return array_filter([
            'os'         => PHP_OS_FAMILY,
            'os_version' => php_uname('r'),
            'cpu'        => php_uname('m'),
            'hostname'   => gethostname() ?: null,
            'timezone'   => (string) config('app.timezone', date_default_timezone_get()),
        ], static fn ($v) => $v !== null && $v !== '');
    }

    private function appVersion(): string
    {
        return (string) config('app.version', env('APP_VERSION', '1.0.0'));
    }

    /** @param array{status: int, body: array<string, mixed>} $result */
    private function errorMessage(array $result): string
    {
        return (string) ($result['body']['error']['message'] ?? 'خطای نامشخص از سمت سرور.');
    }
}