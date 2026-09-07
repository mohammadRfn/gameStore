<?php

namespace App\Http\Controllers;

use App\Http\Requests\CacheMaintenanceRequest;
use App\Services\CacheMaintenanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class CacheMaintenanceController extends Controller
{
    public function __construct(
        protected CacheMaintenanceService $cacheMaintenance
    ) {}

    /**
     * Overview/Inspect — وضعیت فعلی کش‌ها، حجم فایل‌ها، تعداد رکوردهای cache و پیشنهادها.
     */
    public function overview(Request $request): JsonResponse
    {
        $data = $this->cacheMaintenance->inspect($request->user()?->id);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * لیست targetهای قابل پاکسازی با توضیح و درجه ایمنی.
     */
    public function targets(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->cacheMaintenance->availableTargets(),
        ]);
    }

    /**
     * اجرای پاکسازی یا dry-run.
     */
    public function clear(CacheMaintenanceRequest $request): JsonResponse
    {
        try {
            $run = $this->cacheMaintenance->clear($request->validated(), $request->user()?->id);

            return response()->json([
                'success' => in_array($run->status, ['completed', 'partial'], true),
                'message' => $run->is_dry_run
                    ? 'بررسی آزمایشی پاکسازی کش انجام شد؛ هیچ چیزی حذف نشد.'
                    : 'عملیات پاکسازی کش اجرا شد.',
                'data' => $run,
            ], $run->status === 'failed' ? 422 : 200);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * Warm-up/Optimize — بعد از نصب یا آپدیت برنامه.
     */
    public function optimize(CacheMaintenanceRequest $request): JsonResponse
    {
        try {
            $run = $this->cacheMaintenance->optimize($request->validated(), $request->user()?->id);

            return response()->json([
                'success' => $run->status === 'completed',
                'message' => 'عملیات بهینه‌سازی و گرم‌سازی کش اجرا شد.',
                'data' => $run,
            ], $run->status === 'failed' ? 422 : 200);
        } catch (RuntimeException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    /**
     * تاریخچه اجراها.
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'operation' => ['nullable', 'string', 'in:clear,optimize,inspect'],
            'status'    => ['nullable', 'string', 'in:pending,running,completed,partial,failed'],
            'per_page'  => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json([
            'success' => true,
            'data' => $this->cacheMaintenance->paginateRuns($filters),
        ]);
    }

    /**
     * جزئیات یک اجرا.
     */
    public function show(int $runId): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $this->cacheMaintenance->findRun($runId),
        ]);
    }
}
