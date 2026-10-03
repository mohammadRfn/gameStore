<?php

declare(strict_types=1);

namespace Modules\Licensing\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Licensing\Exceptions\LicenseServerException;
use Modules\Licensing\Models\LicensePatch;
use Modules\Licensing\Models\LicenseState;
use Modules\Licensing\Patching\Core\Downloader;
use Modules\Licensing\Patching\Core\Installer;
use Modules\Licensing\Patching\Core\PatchEnv;
use Modules\Licensing\Patching\Core\PatchFailure;
use Modules\Licensing\Patching\Core\Util;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * هماهنگ‌کننده‌ی دریافت و نصب پچ:
 *   sync()          فهرست پیشنهادهای سرور را در جدول license_patches به‌روز می‌کند
 *   startInstall()  نصب را در پس‌زمینه شروع می‌کند (برای UI)
 *   runInstall()    خودِ نصب (توسط دستور licensing:patch-apply در فرایند پس‌زمینه)
 *   rollback()      بازگردانی آخرین پچ (فقط فایل‌ها)
 */
class PatchService
{
    private const MAINTENANCE_FILE = 'framework/patching.json';

    public function __construct(private readonly StoreServerLicenseClient $client) {}

    // ───────────────────────── وضعیت برای UI ─────────────────────────

    /** @return array<string,mixed> */
    public function listing(): array
    {
        PatchSchema::ensure();
        $this->failStale();

        $state = LicenseState::current();
        $last  = Cache::store('file')->get('licensing:patch-last-sync');

        $open = LicensePatch::query()
            ->whereIn('status', [LicensePatch::AVAILABLE, LicensePatch::QUEUED, LicensePatch::RUNNING, LicensePatch::FAILED])
            ->get()
            ->sortBy(fn (LicensePatch $p) => Util::semCode((string) $p->to_version))
            ->values();

        $history = LicensePatch::query()
            ->whereIn('status', [LicensePatch::APPLIED, LicensePatch::ROLLED_BACK])
            ->orderByDesc('finished_at')->orderByDesc('id')->limit(20)->get();

        $latestApplied = LicensePatch::query()->where('status', LicensePatch::APPLIED)
            ->orderByDesc('finished_at')->orderByDesc('id')->first();

        $target = $this->targetInfo();

        return [
            'ok'               => true,
            'version'          => AppVersion::current(),
            'installer_version' => AppVersion::installer(),
            'patch_level'      => AppVersion::patchLevel(),
            'enabled'          => (bool) config('licensing.patch.enabled', true),
            'license'          => [
                'status' => $state->status,
                'active' => $state->isActive() && ! $state->isLocked(),
                'locked' => $state->isLocked(),
                'reason' => $state->lock_reason,
            ],
            'target'           => $target,
            'busy'             => LicensePatch::query()->whereIn('status', LicensePatch::BUSY)->value('patch_code'),
            'last_sync_at'     => $last ? (string) $last : null,
            'patches'          => $open->map->toUi()->all(),
            'history'          => $history->map->toUi()->all(),
            'can_rollback'     => $latestApplied?->patch_code,
            'restart_pending'  => LicensePatch::query()->where('restart_pending', true)->exists(),
        ];
    }

    /** @return array{path:string, is_default:bool, safe:bool, problem:?string} */
    public function targetInfo(): array
    {
        $path = $this->targetPath();
        $isDefault = realpath($path) === realpath(base_path());
        $problem = null;

        if (! is_dir($path)) {
            $problem = 'مسیر هدف پچ وجود ندارد.';
        } elseif (! config('licensing.patch.allow_git_target', false) && file_exists($path . '/.git')) {
            $problem = 'مسیر هدف یک مخزن git است؛ برای جلوگیری از بازنویسی سورس، نصب پچ غیرفعال شده. GS_PATCH_TARGET_PATH را روی یک کپی بگذارید.';
        }

        return ['path' => $path, 'is_default' => $isDefault, 'safe' => $problem === null, 'problem' => $problem];
    }

    // ───────────────────────── همگام‌سازی با سرور ─────────────────────────

    /** @return array{ok:bool, message:string, count?:int} */
    public function sync(): array
    {
        PatchSchema::ensure();

        $state = LicenseState::current();
        if ($state->token === null || ! $state->isActive() || $state->isLocked()) {
            return ['ok' => false, 'message' => 'لایسنس فعال نیست؛ دریافت بروزرسانی ممکن نیست.'];
        }

        try {
            $res = $this->client->patches($state->token);
        } catch (LicenseServerException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        }

        if ($res['status'] === 403 && ($res['body']['data']['locked'] ?? false)) {
            return ['ok' => false, 'message' => 'لایسنس توسط سرور قفل شده است.'];
        }
        if ($res['status'] !== 200 || ! ($res['body']['ok'] ?? false)) {
            return ['ok' => false, 'message' => (string) ($res['body']['error']['message'] ?? "پاسخ نامعتبر از سرور (HTTP {$res['status']})")];
        }

        $offers = (array) ($res['body']['data']['patches'] ?? []);
        $seen   = [];

        foreach ($offers as $o) {
            $code = (string) ($o['patch_code'] ?? '');
            if ($code === '') {
                continue;
            }
            $seen[] = $code;

            $row = LicensePatch::query()->firstOrNew(['patch_code' => $code]);
            $row->fill([
                'title'            => (string) ($o['title'] ?? $code),
                'description'      => $o['description'] ?? null,
                'type'             => (string) ($o['type'] ?? 'files'),
                'from_min'         => (string) ($o['from_min'] ?? '0.0.0'),
                'from_max'         => (string) ($o['from_max'] ?? '999.999.999'),
                'to_version'       => (string) ($o['to_version'] ?? '0.0.0'),
                'requires_restart' => (bool) ($o['requires_restart'] ?? false),
                'mandatory'        => (bool) ($o['mandatory'] ?? false),
                'size'             => (int) ($o['size'] ?? 0),
                'sha256'           => $o['sha256'] ?? null,
                'depends_on'       => (array) ($o['depends_on'] ?? []),
                'last_seen_at'     => Carbon::now('UTC'),
            ]);
            if (! $row->exists) {
                $row->status = LicensePatch::AVAILABLE;
            }
            $row->save();
        }

        // پیشنهادهای نصب‌نشده‌ای که سرور دیگر نمی‌دهد (withdraw/تغییر هدف) از فهرست حذف شوند
        LicensePatch::query()->where('status', LicensePatch::AVAILABLE)->whereNotIn('patch_code', $seen ?: [''])->delete();

        Cache::store('file')->forever('licensing:patch-last-sync', Carbon::now('UTC')->toIso8601String());
        $this->flushPendingReports($state->token);

        return ['ok' => true, 'message' => $offers === [] ? 'بروزرسانی جدیدی موجود نیست.' : count($offers) . ' بروزرسانی موجود است.', 'count' => count($offers)];
    }

    public function clearAvailable(): void
    {
        PatchSchema::ensure();
        LicensePatch::query()->where('status', LicensePatch::AVAILABLE)->delete();
        Cache::store('file')->forever('licensing:patch-last-sync', Carbon::now('UTC')->toIso8601String());
    }

    // ───────────────────────── شروع نصب (درخواست وب) ─────────────────────────

    /** @return array{ok:bool, message:string} */
    public function startInstall(string $code): array
    {
        PatchSchema::ensure();

        if (! config('licensing.patch.enabled', true)) {
            return ['ok' => false, 'message' => 'سیستم بروزرسانی غیرفعال است.'];
        }
        $state = LicenseState::current();
        if (! $state->isActive() || $state->isLocked()) {
            return ['ok' => false, 'message' => 'لایسنس فعال نیست.'];
        }
        $target = $this->targetInfo();
        if (! $target['safe']) {
            return ['ok' => false, 'message' => (string) $target['problem']];
        }
        if (LicensePatch::query()->whereIn('status', LicensePatch::BUSY)->exists()) {
            return ['ok' => false, 'message' => 'یک بروزرسانی دیگر در حال انجام است.'];
        }

        $row = LicensePatch::query()->where('patch_code', $code)->first();
        if ($row === null) {
            return ['ok' => false, 'message' => 'این بروزرسانی پیدا نشد؛ ابتدا «بررسی» را بزنید.'];
        }
        if (! in_array($row->status, [LicensePatch::AVAILABLE, LicensePatch::FAILED, LicensePatch::ROLLED_BACK], true)) {
            return ['ok' => false, 'message' => 'وضعیت این بروزرسانی اجازه‌ی نصب نمی‌دهد.'];
        }

        $row->forceFill([
            'status' => LicensePatch::QUEUED, 'stage' => 'queued', 'progress' => 0,
            'message' => 'در صف نصب…', 'error_code' => null, 'finished_at' => null,
        ])->save();

        if (! BackgroundRunner::artisan(['licensing:patch-apply', $code])) {
            // محیط اجرای پس‌زمینه را نمی‌دهد؛ ناچاریم داخل همین درخواست نصب کنیم
            @set_time_limit(0);
            $ok = $this->runInstall($code);

            return ['ok' => $ok, 'message' => $ok ? 'نصب انجام شد.' : 'نصب ناموفق بود.'];
        }

        return ['ok' => true, 'message' => 'نصب شروع شد.'];
    }

    // ───────────────────────── خودِ نصب (فرایند پس‌زمینه) ─────────────────────────

    public function runInstall(string $code): bool
    {
        PatchSchema::ensure();

        $row = LicensePatch::query()->where('patch_code', $code)->first();
        if ($row === null) {
            Log::warning("patch: {$code} not found");

            return false;
        }

        $state = LicenseState::current();
        $versionBefore = AppVersion::current();
        $reported = [];
        $token = (string) $state->token;

        $row->forceFill([
            'status' => LicensePatch::RUNNING, 'stage' => 'preflight', 'progress' => 1, 'message' => 'شروع نصب',
            'error_code' => null, 'version_before' => $versionBefore, 'started_at' => Carbon::now('UTC'),
            'finished_at' => null, 'attempts' => $row->attempts + 1,
        ])->save();

        $this->setMaintenance($code);

        try {
            if (! $state->isActive() || $state->isLocked() || $token === '') {
                throw new PatchFailure('LICENSE_NOT_ACTIVE', 'لایسنس فعال نیست.');
            }

            // پیشنهاد تازه (لینک دانلود امضاشده کوتاه‌مدت است و نباید از قبل ذخیره شود)
            $offer = $this->findOffer($code, $token);
            if ($offer === null) {
                throw new PatchFailure('OFFER_GONE', 'این بروزرسانی دیگر از طرف سرور برای این دستگاه پیشنهاد نمی‌شود.');
            }

            $env = $this->makeEnv(function (string $stage, int $pct, string $msg) use ($row, $code, $token, $versionBefore, &$reported): void {
                $row->forceFill(['stage' => $stage, 'progress' => $pct, 'message' => $msg])->save();

                $map = ['downloading' => 'downloading', 'verifying' => 'downloaded', 'applying' => 'applying'];
                if (isset($map[$stage]) && ! isset($reported[$map[$stage]])) {
                    $reported[$map[$stage]] = true;
                    $this->report($code, $token, ['status' => $map[$stage], 'version_before' => $versionBefore]);
                }
            });

            $installer = new Installer($env, new Downloader((bool) config('licensing.server.verify_ssl', true)));
            $applied = LicensePatch::query()->where('status', LicensePatch::APPLIED)->pluck('patch_code')->all();

            $result = $installer->install(
                $offer,
                fn (): array => $this->client->downloadHeaders($token),
                $versionBefore,
                $applied,
            );

            $row->forceFill([
                'status' => LicensePatch::APPLIED, 'stage' => 'done', 'progress' => 100,
                'message' => $result['requires_restart'] ? 'نصب شد؛ برای اعمال کامل، برنامه را دوباره اجرا کنید.' : 'با موفقیت نصب شد.',
                'error_code' => null, 'version_after' => $result['version_after'],
                'applied_files' => $result['files'], 'backup_path' => $result['backup'],
                'restart_pending' => $result['requires_restart'], 'finished_at' => Carbon::now('UTC'),
            ])->save();

            $this->refreshCaches();
            $this->report($code, $token, ['status' => 'applied', 'version_before' => $versionBefore, 'version_after' => $result['version_after']]);
            Log::info("patch {$code} applied", ['to' => $result['version_after'], 'sql' => $result['sql']]);

            return true;
        } catch (PatchFailure $e) {
            $this->failRow($row, $e, $token, $versionBefore);

            return false;
        } catch (Throwable $e) {
            $f = new PatchFailure('UNEXPECTED', $e->getMessage());
            $this->failRow($row, $f, $token, $versionBefore);

            return false;
        } finally {
            $this->clearMaintenance();
        }
    }

    private function failRow(LicensePatch $row, PatchFailure $e, string $token, string $versionBefore): void
    {
        $status = $e->touched ? LicensePatch::ROLLED_BACK : LicensePatch::FAILED;

        $row->forceFill([
            'status' => $status, 'stage' => 'error', 'error_code' => $e->errorCode,
            'message' => $e->getMessage() . ($e->touched ? ($e->rolledBack ? ' — تغییرات به حالت قبل برگردانده شد.' : ' — بازگردانی کامل نشد؛ با پشتیبانی تماس بگیرید.') : ''),
            'finished_at' => Carbon::now('UTC'),
        ])->save();

        Log::error("patch {$row->patch_code} failed [{$e->errorCode}] {$e->getMessage()}", ['rolled_back' => $e->rolledBack]);

        if ($e->touched && $e->rolledBack) {
            $this->refreshCaches();
        }
        if ($token !== '') {
            $this->report($row->patch_code, $token, [
                'status' => $status === LicensePatch::ROLLED_BACK ? 'rolled_back' : 'failed',
                'version_before' => $versionBefore,
                'error_message' => mb_substr("[{$e->errorCode}] {$e->getMessage()}", 0, 1900),
            ]);
        }
    }

    // ───────────────────────── rollback دستی ─────────────────────────

    /**
     * فقط آخرین پچ اعمال‌شده و فقط فایل‌ها. تغییرات دیتابیس عمداً برگردانده نمی‌شود، چون
     * بازگرداندن بکاپ دیتابیس داده‌ی واردشده بعد از پچ (فاکتورها و ...) را از بین می‌برد.
     *
     * @return array{ok:bool, message:string}
     */
    public function rollback(string $code): array
    {
        PatchSchema::ensure();

        $latest = LicensePatch::query()->where('status', LicensePatch::APPLIED)->orderByDesc('finished_at')->orderByDesc('id')->first();
        if ($latest === null || $latest->patch_code !== $code) {
            return ['ok' => false, 'message' => 'فقط آخرین بروزرسانی نصب‌شده قابل بازگرداندن است.'];
        }
        if (LicensePatch::query()->whereIn('status', LicensePatch::BUSY)->exists()) {
            return ['ok' => false, 'message' => 'یک بروزرسانی در حال انجام است.'];
        }
        $backup = (string) $latest->backup_path;
        if ($backup === '' || ! is_file($backup . '/meta.json')) {
            return ['ok' => false, 'message' => 'بکاپ این بروزرسانی دیگر موجود نیست.'];
        }

        $this->setMaintenance($code);
        try {
            (new Installer($this->makeEnv(null)))->restoreFiles($backup);
        } catch (Throwable $e) {
            return ['ok' => false, 'message' => 'بازگردانی ناموفق بود: ' . $e->getMessage()];
        } finally {
            $this->clearMaintenance();
        }

        $versionAfter = $latest->version_after;
        $latest->forceFill([
            'status' => LicensePatch::ROLLED_BACK, 'stage' => 'rolled_back', 'progress' => 0,
            'message' => 'فایل‌ها به حالت قبل برگردانده شد (تغییرات دیتابیس حفظ شده است).',
            'restart_pending' => false, 'applied_files' => null, 'finished_at' => Carbon::now('UTC'),
        ])->save();

        $this->refreshCaches();

        $token = (string) LicenseState::current()->token;
        if ($token !== '') {
            $this->report($code, $token, [
                'status' => 'rolled_back', 'version_before' => $versionAfter,
                'error_message' => 'بازگردانی دستی توسط کاربر',
            ]);
        }

        return ['ok' => true, 'message' => 'بروزرسانی برگردانده شد.'];
    }

    /** @return array{ok:bool, message:string} */
    public function restart(): array
    {
        LicensePatch::query()->where('restart_pending', true)->update(['restart_pending' => false]);

        try {
            $desktop = class_exists(\Native\Laravel\Facades\App::class)
                && (request()->header('X-NativePHP') === '1' || (bool) config('nativephp-internal.running', false));

            if ($desktop) {
                \Native\Laravel\Facades\App::relaunch();

                return ['ok' => true, 'message' => 'برنامه در حال راه‌اندازی مجدد است…'];
            }
        } catch (Throwable) {
            // ادامه
        }

        return ['ok' => true, 'message' => 'در حالت توسعه، سرور (php artisan serve) را دستی دوباره اجرا کنید.'];
    }

    // ───────────────────────── Maintenance flag ─────────────────────────

    public function maintenanceCode(): ?string
    {
        $file = storage_path(self::MAINTENANCE_FILE);
        if (! is_file($file)) {
            return null;
        }
        $d = json_decode((string) @file_get_contents($file), true);
        $ttl = (int) config('licensing.patch.maintenance_ttl_minutes', 10) * 60;

        if (! is_array($d) || (time() - (int) ($d['at'] ?? 0)) > $ttl) {
            @unlink($file); // پرچم یتیم (پروسه کرش کرده)

            return null;
        }

        return (string) ($d['code'] ?? '');
    }

    private function setMaintenance(string $code): void
    {
        @file_put_contents(storage_path(self::MAINTENANCE_FILE), json_encode(['code' => $code, 'at' => time()]));
    }

    private function clearMaintenance(): void
    {
        @unlink(storage_path(self::MAINTENANCE_FILE));
    }

    // ───────────────────────── کمکی ─────────────────────────

    /** @return array<string,mixed>|null */
    private function findOffer(string $code, string $token): ?array
    {
        $res = $this->client->patches($token);
        if ($res['status'] !== 200) {
            throw new PatchFailure('SERVER_ERROR', (string) ($res['body']['error']['message'] ?? "پاسخ سرور HTTP {$res['status']}"));
        }
        foreach ((array) ($res['body']['data']['patches'] ?? []) as $o) {
            if (($o['patch_code'] ?? null) === $code) {
                return $o;
            }
        }

        return null;
    }

    private function makeEnv(?\Closure $progress): PatchEnv
    {
        $dbFile = null;
        try {
            $name = DB::connection()->getDatabaseName();
            $dbFile = is_string($name) && is_file($name) ? $name : null;
        } catch (Throwable) {
            // ignore
        }

        $target = $this->targetPath();

        return new PatchEnv(
            targetPath: $target,
            dataPath: storage_path('app/patches'),
            allowedRoots: array_values((array) config('licensing.patch.allowed_roots', [])),
            forbiddenPatterns: [
                '#(^|/)\.env($|\.)#i', '#\.sqlite3?(-wal|-shm|-journal)?$#i', '#\.db$#i',
                '#^storage/#', '#^vendor/#', '#^node_modules/#', '#^bootstrap/cache/#', '#(^|/)\.git(/|$)#',
            ],
            pdo: static fn (): \PDO => DB::connection()->getPdo(),
            keyResolver: fn (string $kid): string => $this->resolveKey($kid),
            dbFile: $dbFile,
            healthCheck: fn () => $this->healthCheck($target),
            progress: $progress,
            closeDb: static function (): void {
                DB::disconnect();
            },
            phpBinary: BackgroundRunner::php(),
            allowGitTarget: (bool) config('licensing.patch.allow_git_target', false),
            keepBackups: (int) config('licensing.patch.keep_backups', 3),
        );
    }

    /** کلید عمومی خام Ed25519: اول کلیدهای pinned در کانفیگ؛ در صورت اجازه TOFU (فقط تست) */
    private function resolveKey(string $kid): string
    {
        $pinned = (array) config('licensing.public_keys', []);
        $file   = storage_path('app/patches/trusted_keys.json');
        $known  = $pinned + (is_file($file) ? (array) json_decode((string) file_get_contents($file), true) : []);

        if (isset($known[$kid])) {
            return $this->rawKey((string) $known[$kid]);
        }

        if (! config('licensing.patch.allow_tofu', false)) {
            throw new PatchFailure('UNTRUSTED_KEY', "کلید امضای {$kid} در LICENSING_PUBLIC_KEYS تعریف نشده است. کلید عمومی سرور باید داخل برنامه pinned شود.");
        }

        $res = $this->client->keys();
        foreach ((array) ($res['body']['data']['keys'] ?? []) as $k) {
            if (($k['kid'] ?? '') === $kid && ! empty($k['public_key'])) {
                if (! is_dir(dirname($file))) {
                    @mkdir(dirname($file), 0775, true);
                }
                $known[$kid] = $k['public_key'];
                @file_put_contents($file, json_encode(array_diff_key($known, $pinned)));
                Log::warning("patch: signing key {$kid} trusted on first use (GS_PATCH_ALLOW_TOFU) — فقط برای تست");

                return $this->rawKey((string) $k['public_key']);
            }
        }

        throw new PatchFailure('UNTRUSTED_KEY', "کلید {$kid} روی سرور هم پیدا نشد.");
    }

    private function rawKey(string $b64): string
    {
        $raw = Util::b64uDecode($b64);
        if (strlen($raw) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new PatchFailure('KEY_INVALID', 'کلید عمومی امضا نامعتبر است.');
        }

        return $raw;
    }

    /** یک فرایند تازه‌ی PHP که با کدِ پچ‌شده بوت می‌شود؛ خطای فاتال در provider/کلاس‌ها اینجا گرفته می‌شود */
    private function healthCheck(string $target): void
    {
        $artisan = rtrim($target, '/\\') . '/artisan';
        if (! is_file($artisan) || ! is_dir(rtrim($target, '/\\') . '/vendor')) {
            Log::notice('patch healthcheck skipped: target has no artisan/vendor');

            return;
        }

        $p = new Process([BackgroundRunner::php(), $artisan, 'licensing:patch-health'], $target, null, null, (float) config('licensing.patch.health_timeout', 120));
        $p->run();

        if (! $p->isSuccessful()) {
            $out = trim($p->getErrorOutput() . ' ' . $p->getOutput());
            throw new PatchFailure('HEALTHCHECK_FAILED', 'برنامه پس از پچ درست بالا نیامد: ' . mb_substr($out, -400), true);
        }
    }

    private function refreshCaches(): void
    {
        try {
            $wasCached = app()->configurationIsCached();
            foreach (['config:clear', 'route:clear', 'view:clear', 'event:clear'] as $cmd) {
                Artisan::call($cmd);
            }
            if ($wasCached) {
                Artisan::call('optimize'); // نسخه‌ی release: کش‌ها دوباره ساخته شود
            }
        } catch (Throwable $e) {
            Log::notice('patch: cache refresh failed: ' . $e->getMessage());
        }
    }

    /** @param array<string,mixed> $payload */
    private function report(string $code, string $token, array $payload): void
    {
        try {
            $r = $this->client->patchStatus($code, $payload, $token);
            if ($r['status'] >= 500 || $r['status'] === 429) {
                throw new \RuntimeException('http_' . $r['status']);
            }
        } catch (Throwable) {
            // آفلاین: آخرین وضعیت نگه داشته می‌شود و در sync بعدی ارسال می‌شود
            if (in_array($payload['status'] ?? '', ['applied', 'failed', 'rolled_back'], true)) {
                LicensePatch::query()->where('patch_code', $code)->update(['pending_report' => json_encode($payload)]);
            }

            return;
        }
        LicensePatch::query()->where('patch_code', $code)->whereNotNull('pending_report')->update(['pending_report' => null]);
    }

    private function flushPendingReports(string $token): void
    {
        foreach (LicensePatch::query()->whereNotNull('pending_report')->get() as $row) {
            $payload = (array) $row->pending_report;
            if ($payload !== []) {
                $this->report($row->patch_code, $token, $payload);
            }
        }
    }

    /** نصبی که فرایندش مرده (کرش/بسته‌شدن برنامه) نباید برای همیشه «در حال انجام» بماند */
    private function failStale(): void
    {
        $limit = Carbon::now()->subMinutes((int) config('licensing.patch.stale_minutes', 15));
        foreach (LicensePatch::query()->whereIn('status', LicensePatch::BUSY)->where('updated_at', '<', $limit)->get() as $row) {
            $row->forceFill([
                'status' => LicensePatch::FAILED, 'stage' => 'error', 'error_code' => 'STALE',
                'message' => 'نصب نیمه‌کاره ماند (برنامه بسته شده یا فرایند متوقف شد). دوباره تلاش کنید.',
                'finished_at' => Carbon::now('UTC'),
            ])->save();
        }
    }

    private function targetPath(): string
    {
        $p = (string) config('licensing.patch.target_path', '');

        return rtrim($p !== '' ? $p : base_path(), '/\\');
    }
}
