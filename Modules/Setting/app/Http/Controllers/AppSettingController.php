<?php

declare(strict_types=1);

namespace Modules\Setting\Http\Controllers;

use Modules\Setting\Enums\Settings\SettingGroup;
use Modules\Setting\Events\SettingsChanged;
use App\Http\Controllers\Controller;
use Modules\Setting\Http\Requests\UpdateSettingRequest;
use Modules\Setting\Services\Setting\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * AppSettingController
 * ============================================================================
 * کنترلر RESTful برای مدیریت تنظیمات مرکزی اپلیکیشن.
 *
 * مسیرها (routes/api.php یا routes/web.php):
 *   GET    /api/settings                -> index
 *   GET    /api/settings/group/{group}  -> byGroup
 *   GET    /api/settings/{key}          -> show
 *   PUT    /api/settings                -> update
 *   POST   /api/settings/bulk           -> update (alias)
 *   DELETE /api/settings/{key}          -> reset
 *   POST   /api/settings/reset-group/{group}  -> resetGroup
 *   POST   /api/settings/reset-all      -> resetAll
 *   GET    /api/settings/meta           -> meta
 *   GET    /api/settings/export         -> export
 *   POST   /api/settings/import         -> import
 *   POST   /api/settings/check-updates  -> checkForUpdates
 */
class AppSettingController extends Controller
{
    public function __construct(
        private readonly SettingService $settings,
    ) {
    }

    /*
    |--------------------------------------------------------------------------
    | خواندن تنظیمات
    |--------------------------------------------------------------------------
    */

    /**
     * گرفتن همه‌ی تنظیمات سیستم.
     * پاسخ شامل دو بخش است:
     *  - values: مقادیر فعلی
     *  - meta:  متادیتا (label, type, rules, default) برای ساخت فرم UI
     *
     * @example GET /api/settings
     */
    public function index(): JsonResponse
    {
        $values = $this->settings->getAll();
        $meta = [];
        foreach (array_keys($values) as $key) {
            $meta[$key] = $this->buildMeta($key);
        }

        return response()->json([
            'ok'     => true,
            'values' => $this->serializeValues($values),
            'meta'   => $meta,
            'groups' => SettingGroup::toArray(),
        ]);
    }

    /**
     * گرفتن تنظیمات یک گروه خاص.
     *
     * @example GET /api/settings/group/general
     */
    public function byGroup(string $group): JsonResponse
    {
        try {
            $g = SettingGroup::from($group);
        } catch (\ValueError) {
            return response()->json([
                'ok' => false,
                'message' => "Invalid setting group [{$group}].",
            ], 404);
        }

        $values = $this->settings->getGroup($g);
        $meta = [];
        foreach (array_keys($values) as $key) {
            $meta[$key] = $this->buildMeta($key);
        }

        return response()->json([
            'ok'     => true,
            'group'  => $g->value,
            'label'  => $g->label(),
            'icon'   => $g->icon(),
            'values' => $this->serializeValues($values),
            'meta'   => $meta,
        ]);
    }

    /**
     * گرفتن مقدار یک تنظیم خاص.
     *
     * @example GET /api/settings/general.theme
     */
    public function show(string $key): JsonResponse
    {
        if (! $this->settings->has($key)) {
            return response()->json([
                'ok' => false,
                'message' => "Setting [{$key}] is not defined.",
            ], 404);
        }

        $value = $this->settings->get($key);

        return response()->json([
            'ok'    => true,
            'key'   => $key,
            'value' => $this->serializeValue($value),
            'meta'  => $this->buildMeta($key),
        ]);
    }

    /**
     * گرفتن متادیتای کامل تنظیمات برای UI.
     *
     * @example GET /api/settings/meta
     */
    public function meta(): JsonResponse
    {
        $out = [];
        foreach (SettingGroup::cases() as $g) {
            $groupMeta = [];
            foreach ($this->settings->metaForGroup($g) as $key => $m) {
                $groupMeta[$key] = $this->buildMeta($key);
            }
            $out[$g->value] = [
                'label' => $g->label(),
                'icon'  => $g->icon(),
                'items' => $groupMeta,
            ];
        }

        return response()->json([
            'ok'     => true,
            'groups' => $out,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | نوشتن تنظیمات
    |--------------------------------------------------------------------------
    */

    /**
     * به‌روزرسانی تنظیمات.
     * payload: آرایه‌ای از key=>value (مختلط از گروه‌های مختلف مجاز است).
     *
     * @example PUT /api/settings { "general.theme": "dark", "desktop.auto_launch": true }
     *
     * @throws ValidationException
     */
    public function update(UpdateSettingRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $payload = $validated['settings'] ?? $validated;

        // اطمینان از ساختار key=>value
        if (! is_array($payload) || empty($payload)) {
            return response()->json([
                'ok' => false,
                'message' => 'Payload must be a non-empty key/value map.',
            ], 422);
        }

        $this->settings->updateMany($payload);

        return response()->json([
            'ok'      => true,
            'message' => 'Settings updated successfully.',
            'changed' => array_keys($payload),
        ]);
    }

    /**
     * ریست یک تنظیم به مقدار پیش‌فرض.
     *
     * @example DELETE /api/settings/general.theme
     */
    public function reset(string $key): JsonResponse
    {
        if (! $this->settings->has($key)) {
            return response()->json([
                'ok' => false,
                'message' => "Setting [{$key}] is not defined.",
            ], 404);
        }

        $this->settings->reset($key);

        return response()->json([
            'ok'      => true,
            'message' => "Setting [{$key}] reset to default.",
            'default' => $this->serializeValue($this->settings->get($key)),
        ]);
    }

    /**
     * ریست همه‌ی تنظیمات یک گروه.
     *
     * @example POST /api/settings/reset-group/general
     */
    public function resetGroup(string $group): JsonResponse
    {
        try {
            $g = SettingGroup::from($group);
        } catch (\ValueError) {
            return response()->json([
                'ok' => false,
                'message' => "Invalid setting group [{$group}].",
            ], 404);
        }

        $this->settings->resetGroup($g);

        return response()->json([
            'ok'      => true,
            'message' => "Group [{$g->label()}] reset to defaults.",
        ]);
    }

    /**
     * ریست کامل همه‌ی تنظیمات.
     *
     * @example POST /api/settings/reset-all
     */
    public function resetAll(): JsonResponse
    {
        $this->settings->resetAll();

        return response()->json([
            'ok'      => true,
            'message' => 'All settings reset to defaults.',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Import / Export
    |--------------------------------------------------------------------------
    */

    /**
     * خروجی گرفتن از تنظیمات فعلی.
     *
     * @example GET /api/settings/export
     */
    public function export(): JsonResponse
    {
        return response()->json([
            'ok'        => true,
            'exported_at' => now()->toIso8601String(),
            'settings'  => $this->settings->export(),
        ]);
    }

    /**
     * وارد کردن تنظیمات از payload.
     *
     * @example POST /api/settings/import { "settings": {...} }
     */
    public function import(Request $request): JsonResponse
    {
        $payload = $request->input('settings', []);
        if (! is_array($payload) || empty($payload)) {
            return response()->json([
                'ok' => false,
                'message' => 'Payload must contain a non-empty "settings" object.',
            ], 422);
        }

        try {
            $this->settings->import($payload, validate: true);
        } catch (ValidationException $e) {
            return response()->json([
                'ok'      => false,
                'message' => 'Import validation failed.',
                'errors'  => $e->errors(),
            ], 422);
        }

        return response()->json([
            'ok'      => true,
            'message' => 'Settings imported successfully.',
            'count'   => count($payload),
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | عملیات‌های دسکتاپ
    |--------------------------------------------------------------------------
    */

    /**
     * بررسی بروزرسانی.
     * این endpoint در Electron به auto-updater وصل می‌شود.
     *
     * @example POST /api/settings/check-updates
     */
    public function checkForUpdates(): JsonResponse
    {
        $updateUrl = $this->settings->getString('desktop.auto_update_url', '');
        $autoCheck = $this->settings->getBool('desktop.auto_update_check', true);
        $isDesktop = $this->isDesktopEnvironment();

        $response = [
            'ok' => true,
            'auto_check_enabled' => $autoCheck,
            'update_url' => $updateUrl ?: null,
            'is_desktop' => $isDesktop,
        ];

        if (! $isDesktop) {
            $response['update_available'] = false;
            $response['message'] = 'Update check is only available in desktop environment.';
            return response()->json($response);
        }

        if (empty($updateUrl)) {
            $response['update_available'] = false;
            $response['message'] = 'No update URL configured.';
            return response()->json($response);
        }

        // در Electron از electron-updater استفاده می‌کنیم.
        // این یک placeholder است؛ در محیط واقعی باید به NativePHP/Electron hook شود.
        if (class_exists(\Native\Laravel\Facades\Updater::class)) {
            try {
                // NativePHP API برای بررسی آپدیت
                $updateInfo = \Native\Laravel\Facades\Updater::check();
                $response['update_available'] = $updateInfo['available'] ?? false;
                $response['current_version'] = $updateInfo['current'] ?? null;
                $response['latest_version'] = $updateInfo['latest'] ?? null;
            } catch (\Throwable $e) {
                $response['update_available'] = false;
                $response['error'] = $e->getMessage();
            }
        } else {
            // fallback برای توسعه
            $response['update_available'] = false;
            $response['message'] = 'Updater is not available in this environment.';
        }

        return response()->json($response);
    }

    /**
     * دانلود و نصب بروزرسانی.
     *
     * @example POST /api/settings/install-update
     */
    public function installUpdate(): JsonResponse
    {
        $isDesktop = $this->isDesktopEnvironment();

        if (! $isDesktop) {
            return response()->json([
                'ok' => false,
                'message' => 'Update installation is only available in desktop environment.',
            ], 422);
        }

        if (! class_exists(\Native\Laravel\Facades\Updater::class)) {
            return response()->json([
                'ok' => false,
                'message' => 'Updater is not available.',
            ], 422);
        }

        try {
            \Native\Laravel\Facades\Updater::download();
            return response()->json([
                'ok' => true,
                'message' => 'Update download started. Application will restart after installation.',
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'ok' => false,
                'message' => 'Update installation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /**
     * ساخت meta قابل‌نمایش برای UI.
     */
    private function buildMeta(string $key): array
    {
        $m = $this->settings->meta($key);
        if ($m === null) {
            return [
                'key' => $key,
                'type' => 'string',
                'label' => $key,
                'default' => null,
                'options' => [],
            ];
        }

        $options = [];
        if (($m['type'] ?? '') === 'enum' && isset($m['enum']) && enum_exists($m['enum'])) {
            foreach ($m['enum']::cases() as $case) {
                $options[] = [
                    'value' => $case->value,
                    'label' => method_exists($case, 'label') ? $case->label() : (string) $case->value,
                ];
            }
        }

        return [
            'key'     => $key,
            'group'   => $m['group']->value,
            'section' => $m['section'] ?? null,
            'type'    => $m['type'],
            'label'   => $m['label'] ?? $key,
            'default' => $this->serializeValue($m['default']),
            'rules'   => $m['rules'] ?? [],
            'options' => $options,
        ];
    }

    /**
     * serialize مقدار (تبدیل enum به value و ...).
     */
    private function serializeValue(mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            return $value->value;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->toIso8601String();
        }
        return $value;
    }

    /**
     * serialize آرایه‌ی values.
     */
    private function serializeValues(array $values): array
    {
        $out = [];
        foreach ($values as $k => $v) {
            $out[$k] = $this->serializeValue($v);
        }
        return $out;
    }

    /**
     * تشخیص محیط دسکتاپ.
     * NativePHP یک متغیر محیطی یا header خاص می‌فرستد.
     */
    private function isDesktopEnvironment(): bool
    {
        // NativePHP این header را ست می‌کند
        if (request()->header('X-NativePHP') === '1') {
            return true;
        }

        // همچنین می‌توانیم از متغیر محیطی استفاده کنیم
        return env('NATIVEPHP_RUNNING', false) === true
            || env('IS_DESKTOP', false) === true;
    }
}