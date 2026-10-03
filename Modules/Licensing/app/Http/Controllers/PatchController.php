<?php

declare(strict_types=1);

namespace Modules\Licensing\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Licensing\Services\PatchService;
use Throwable;

/**
 * API بخش «بروزرسانی» در تنظیمات. همه‌ی پاسخ‌ها JSON فارسی‌اند.
 * روت‌ها عمداً settings.updates.* نام‌گذاری شده‌اند (نه licensing.*) تا میدلور
 * RequireActiveLicense روی آن‌ها اعمال شود.
 */
class PatchController extends Controller
{
    public function __construct(private readonly PatchService $patches) {}

    public function index(): JsonResponse
    {
        return $this->safe(fn () => $this->patches->listing());
    }

    public function check(): JsonResponse
    {
        return $this->safe(function () {
            $r = $this->patches->sync();

            return $this->patches->listing() + ['message' => $r['message'], 'sync_ok' => $r['ok']];
        });
    }

    public function install(Request $request, string $code): JsonResponse
    {
        return $this->safe(function () use ($code) {
            $r = $this->patches->startInstall($code);

            return $this->patches->listing() + ['message' => $r['message'], 'action_ok' => $r['ok']];
        });
    }

    public function rollback(string $code): JsonResponse
    {
        return $this->safe(function () use ($code) {
            $r = $this->patches->rollback($code);

            return $this->patches->listing() + ['message' => $r['message'], 'action_ok' => $r['ok']];
        });
    }

    public function restart(): JsonResponse
    {
        return $this->safe(function () {
            $r = $this->patches->restart();

            return ['ok' => $r['ok'], 'message' => $r['message']];
        });
    }

    /** @param callable():array<string,mixed> $fn */
    private function safe(callable $fn): JsonResponse
    {
        try {
            return response()->json($fn());
        } catch (Throwable $e) {
            report($e);

            return response()->json(['ok' => false, 'message' => 'خطا: ' . $e->getMessage()], 500);
        }
    }
}
