<?php

namespace App\Http\Controllers;

use App\Http\Requests\AppSettingsUpdateRequest;
use App\Models\AppSetting;
use App\Models\SettingGroup;
use App\Services\SettingsService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;

class AppSettingsController extends Controller
{
    public function __construct(
        protected SettingsService $settings
    ) {}

    /**
     * Index — settings grouped by group, ready for a tabbed UI
     * (Inertia for web, JSON for the Electron renderer).
     */
    public function index(Request $request)
    {
        $groupCode = $request->get('group');
        $groups = SettingGroup::ordered()->withCount('settings')->get();

        $settings = AppSetting::with('group')
            ->when($groupCode, fn ($q) => $q->whereHas('group', fn ($g) => $g->where('code', $groupCode)))
            ->orderBy('setting_key')
            ->get();

        $data = [
            'groups' => $groups,
            'settings' => $settings->map(fn (AppSetting $s) => [
                'id'            => $s->id,
                'setting_key'   => $s->setting_key,
                'setting_value' => $s->is_encrypted ? '••••••••' : $s->setting_value,
                'value_type'    => $s->value_type,
                'is_locked'     => $s->is_locked,
                'is_autoload'   => $s->is_autoload,
                'description'   => $s->description,
                'group'         => $s->group?->code,
            ])->values(),
            'active_group' => $groupCode,
        ];

        if ($request->wantsJson()) {
            return response()->json(['data' => $data]);
        }

        return Inertia::render('Settings/Index', $data);
    }

    public function show(Request $request, string $key)
    {
        $setting = AppSetting::with('group')->where('setting_key', $key)->firstOrFail();

        if ($request->wantsJson()) {
            return response()->json(['data' => $setting]);
        }

        return Inertia::render('Settings/Show', ['setting' => $setting]);
    }

    /** Update one setting by key via SettingsService (cached, audited, locked-aware). */
    public function update(AppSettingsUpdateRequest $request, string $key)
    {
        try {
            $setting = $this->settings->set($key, $request->input('setting_value'), auth()->id());

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'data' => $setting]);
            }

            return redirect()->back()->with('success', 'تنظیم ذخیره شد.');
        } catch (RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->back()->withErrors(['setting' => $e->getMessage()]);
        }
    }

    /** Bulk update — atomic, one flush. */
    public function bulkUpdate(Request $request)
    {
        $request->validate([
            'values' => ['required', 'array'],
            'values.*.key'   => ['required', 'string'],
            'values.*.value' => ['nullable'],
        ]);

        try {
            $pairs = collect($request->input('values'))->pluck('value', 'key')->all();

            $updated = $this->settings->setMany($pairs, auth()->id());

            return response()->json(['success' => true, 'updated' => count($updated)]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    /** Reset one setting to its registry default. */
    public function reset(Request $request, string $key)
    {
        try {
            $setting = $this->settings->resetToDefault($key);

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'data' => $setting]);
            }

            return redirect()->back()->with('success', 'تنظیم به مقدار پیش‌فرض بازگشت.');
        } catch (RuntimeException $e) {
            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return redirect()->back()->withErrors(['setting' => $e->getMessage()]);
        }
    }

    /** Lock / unlock a setting (admin only in real deployments). */
    public function toggleLock(Request $request, string $key)
    {
        $setting = AppSetting::where('setting_key', $key)->firstOrFail();
        $setting->update(['is_locked' => ! $setting->is_locked]);
        $this->settings->flush();

        return response()->json(['success' => true, 'is_locked' => $setting->is_locked]);
    }

    /** Sync registry (config/settings.php) into the DB — install/maintenance. */
    public function sync(Request $request)
    {
        $report = $this->settings->syncRegistry(auth()->id());

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'report' => $report]);
        }

        return redirect()->back()->with('success', 'رجیستری همگام‌سازی شد.');
    }
}
