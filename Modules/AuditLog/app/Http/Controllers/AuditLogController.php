<?php

declare(strict_types=1);

namespace Modules\AuditLog\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\AuditLog\Enums\LogChannel;
use Modules\AuditLog\Enums\LogLevel;
use Modules\AuditLog\Enums\SyncStatus;
use Modules\AuditLog\Http\Requests\IndexAuditLogRequest;
use Modules\AuditLog\Http\Resources\AuditLogResource;
use Modules\AuditLog\Models\AuditLog;
use Modules\AuditLog\Services\AuditLogQueryService;
use Modules\AuditLog\Services\IntegrityVerifier;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * API خواندنی لاگ‌ها برای پنل مدیریت گیم‌استور (JSON).
 */
class AuditLogController extends Controller
{
    public function __construct(private readonly AuditLogQueryService $query)
    {
    }

    public function index(IndexAuditLogRequest $request): JsonResponse
    {
        $paginator = $this->query->paginate($request->filters(), $request->perPage());

        return AuditLogResource::collection($paginator)->response();
    }

    public function show(int $id): JsonResponse
    {
        $log = AuditLog::query()->findOrFail($id);

        return (new AuditLogResource($log))->response();
    }

    /** همه‌ی لاگ‌های مربوط به یک request_id (ردیابی end-to-end). */
    public function timeline(string $requestId): JsonResponse
    {
        return AuditLogResource::collection($this->query->timeline($requestId)->get())->response();
    }

    /** لاگ‌های یک موجودیت مشخص، مثلاً یک فاکتور. */
    public function forEntity(Request $request, string $type, int $id): JsonResponse
    {
        $paginator = $this->query->paginate([
            'entity_type' => str_replace('.', '\\', $type),
            'entity_id'   => $id,
        ], (int) $request->integer('per_page', (int) config('auditlog.api.per_page', 30)));

        return AuditLogResource::collection($paginator)->response();
    }

    public function stats(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->query->stats((int) $request->integer('days', 7)),
        ]);
    }

    /** لیست مقادیر مجاز برای فیلترها. */
    public function filters(): JsonResponse
    {
        return response()->json([
            'data' => [
                'channels' => array_map(
                    static fn (LogChannel $c): array => ['value' => $c->value, 'label' => $c->label()],
                    LogChannel::cases(),
                ),
                'levels'        => LogLevel::values(),
                'sync_statuses' => SyncStatus::values(),
                'entity_types'  => AuditLog::query()
                    ->whereNotNull('entity_type')
                    ->distinct()
                    ->limit(200)
                    ->pluck('entity_type'),
            ],
        ]);
    }

    /** خروجی CSV با استریم (بدون مصرف حافظه). */
    public function export(IndexAuditLogRequest $request): StreamedResponse
    {
        $query = $this->query->query($request->filters())
            ->limit((int) config('auditlog.api.export_limit', 50000));

        $fileName = 'gamestore-audit-' . now()->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($query): void {
            $handle = fopen('php://output', 'w');
            fwrite($handle, "\xEF\xBB\xBF"); // BOM برای نمایش صحیح فارسی در اکسل

            fputcsv($handle, [
                'occurred_at', 'channel', 'level', 'action', 'description',
                'entity_type', 'entity_id', 'entity_label', 'actor_name', 'ip',
                'method', 'route', 'status_code', 'duration_ms', 'request_id', 'sync_status',
            ]);

            foreach ($query->cursor() as $log) {
                fputcsv($handle, [
                    $log->occurred_at?->toDateTimeString(),
                    $log->channel->value,
                    $log->level->value,
                    $log->action,
                    $log->description,
                    $log->entity_type,
                    $log->entity_id,
                    $log->entity_label,
                    $log->actor_name,
                    $log->ip,
                    $log->method,
                    $log->route,
                    $log->status_code,
                    $log->duration_ms,
                    $log->request_id,
                    $log->sync_status->value,
                ]);
            }

            fclose($handle);
        }, $fileName, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** بررسی دست‌نخوردگی زنجیره‌ی هش. */
    public function verify(Request $request, IntegrityVerifier $verifier): JsonResponse
    {
        return response()->json([
            'data' => $verifier->verify(
                $request->integer('from') ?: null,
                $request->integer('limit') ?: 5000,
            ),
        ]);
    }
}
