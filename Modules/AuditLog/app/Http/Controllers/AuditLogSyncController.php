<?php

declare(strict_types=1);

namespace Modules\AuditLog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\AuditLog\Exceptions\LogShippingException;
use Modules\AuditLog\Jobs\ShipAuditLogsJob;
use Modules\AuditLog\Models\AuditLogBatch;
use Modules\AuditLog\Services\LogShippingService;
use Modules\AuditLog\Services\StoreServerClient;

/**
 * مدیریت همگام‌سازی لاگ‌ها با StoreServer از سمت پنل گیم‌استور.
 */
class AuditLogSyncController extends Controller
{
    public function __construct(private readonly LogShippingService $shipping)
    {
    }

    public function status(): JsonResponse
    {
        return response()->json(['data' => $this->shipping->status()]);
    }

    /** ارسال دستی (همزمان یا از طریق صف). */
    public function ship(Request $request): JsonResponse
    {
        $batches = $request->integer('batches') ?: null;

        if ($request->boolean('queue')) {
            ShipAuditLogsJob::dispatch($batches);

            return response()->json(['data' => ['queued' => true]], 202);
        }

        $this->shipping->releaseStuck();

        return response()->json(['data' => $this->shipping->shipPending($batches)]);
    }

    /** بازگرداندن رکوردهای ناموفق به صف. */
    public function retry(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ['requeued' => $this->shipping->retryFailed($request->boolean('include_dead', true))],
        ]);
    }

    /** تست اتصال و امضا. */
    public function ping(StoreServerClient $client): JsonResponse
    {
        try {
            return response()->json(['data' => $client->ping()]);
        } catch (LogShippingException $e) {
            return response()->json([
                'error' => ['code' => $e->errorCode, 'message' => $e->getMessage()],
            ], 502);
        }
    }

    /** تاریخچه‌ی دسته‌های ارسال. */
    public function batches(Request $request): JsonResponse
    {
        $batches = AuditLogBatch::query()
            ->when($request->string('status')->toString() !== '', fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->latest('id')
            ->paginate((int) $request->integer('per_page', 30));

        return response()->json($batches);
    }
}
