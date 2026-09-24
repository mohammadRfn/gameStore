<?php

declare(strict_types=1);

namespace Modules\Licensing\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Modules\Licensing\Models\LicenseState;
use Modules\Licensing\Services\DeviceFingerprint;
use Modules\Licensing\Services\LicensingService;

class ActivationController extends Controller
{
    public function __construct(
        private readonly LicensingService $licensing,
        private readonly DeviceFingerprint $fingerprint,
    ) {}

    public function show(): InertiaResponse|RedirectResponse
    {
        $state = $this->licensing->state();

        // فعال و باز است؛ چیزی اینجا برای دیدن نیست - برگرد به داشبورد
        if ($state->isActive() && ! $state->isLocked()) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Licensing/Activate', [
            'state'       => $this->stateForFrontend($state),
            'fingerprint' => $this->fingerprint->current(),
        ]);
    }

    public function request(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'customer_name'  => ['nullable', 'string', 'max:191'],
            'customer_phone' => ['nullable', 'string', 'max:20'],
        ]);

        $result = $this->licensing->submitActivationRequest($data['customer_name'] ?? null, $data['customer_phone'] ?? null);

        if (! $result['ok']) {
            return back()->withErrors(['request' => $result['message']]);
        }

        return back()->with('success', 'درخواست فعال‌سازی ارسال شد؛ منتظر تأیید بمانید.');
    }

    // پولینگ AJAX از خود صفحه‌ی فعال‌سازی، نه ناوبری Inertia کامل
    public function poll(): JsonResponse
    {
        $state = $this->licensing->refreshPendingStatus();

        return response()->json(['state' => $this->stateForFrontend($state)]);
    }

    public function redeem(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:64'],
        ]);

        $result = $this->licensing->redeemCode($data['code']);

        if (! $result['ok']) {
            return back()->withErrors(['code' => $result['message']]);
        }

        return redirect()->route('dashboard')->with('success', 'فعال‌سازی با موفقیت انجام شد.');
    }

    /** @return array<string, mixed> */
    private function stateForFrontend(LicenseState $state): array
    {
        return [
            'status'                  => $state->status,
            'activation_request_uuid' => $state->activation_request_uuid,
            'poll_after_seconds'      => $state->poll_after_seconds ?: 30,
            'reject_reason'           => $state->reject_reason,
            'lock_reason'             => $state->lock_reason,
        ];
    }
}