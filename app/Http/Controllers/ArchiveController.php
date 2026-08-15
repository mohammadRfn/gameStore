<?php

namespace App\Http\Controllers;

use App\Exports\ArchiveFullExport;
use App\Exports\ArchivedInvoicesExport;
use App\Exports\ArchivedRequestsExport;
use App\Exports\ArchivedServiceJobsExport;
use App\Services\ArchiveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Morilog\Jalali\Jalalian;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ArchiveController extends Controller
{
    /** نگاشت اسلاگ مسیر (kebab-case) به نوع داخلی بایگانی (snake_case) */
    private const ROUTE_TYPE_MAP = [
        'invoice'     => ArchiveService::TYPE_INVOICE,
        'request'     => ArchiveService::TYPE_REQUEST,
        'service-job' => ArchiveService::TYPE_SERVICE_JOB,
    ];

    public function __construct(protected ArchiveService $archiveService)
    {
    }

    /* ------------------------------------------------------------------ */
    /* CRUD بایگانی                                                       */
    /* ------------------------------------------------------------------ */

    public function index(Request $request): JsonResponse
    {
        $filters = $this->validatedFilters($request);

        return response()->json([
            'data' => $this->archiveService->paginate($filters),
        ]);
    }

    public function show(int $archivedRecordId): JsonResponse
    {
        return response()->json([
            'data' => $this->archiveService->findOrFail($archivedRecordId),
        ]);
    }

    /**
     * همگام‌سازی دستی: تمام فاکتورهای paid و درخواست‌ها/سرویس‌های مرتبط
     * با فاکتور paid را به‌صورت idempotent در بایگانی کپی/به‌روزرسانی می‌کند.
     */
    public function syncPaidCopies(Request $request): JsonResponse
    {
        $result = $this->archiveService->syncAllPaidCopies($request->user()?->id);

        return response()->json([
            'message' => 'کپی موارد پرداخت‌شده در بایگانی همگام‌سازی شد.',
            'data'    => $result,
        ]);
    }

    /** فقط کپی می‌گیرد؛ رکورد مبدا دست‌نخورده باقی می‌ماند. */
    public function copy(Request $request, string $sourceType, int $sourceId): JsonResponse
    {
        $type = $this->resolveType($sourceType);
        $data = $request->validate(['reason' => 'nullable|string|max:1000']);

        $record = $this->archiveService->copyPaidToArchive(
            $type,
            $sourceId,
            $request->user()?->id,
            $data['reason'] ?? null
        );

        return response()->json([
            'message' => 'کپی در بایگانی ثبت شد.',
            'data'    => $record,
        ]);
    }

    /**
     * دکمه‌ی «انتقال به بایگانی»: کپی می‌گیرد (اگر قبلاً نگرفته) و سپس
     * رکورد اصلی را از بخش مبدا (فاکتور/درخواست/سرویس) soft-delete می‌کند.
     */
    public function transfer(Request $request, string $sourceType, int $sourceId): JsonResponse
    {
        $type = $this->resolveType($sourceType);
        $data = $request->validate(['reason' => 'nullable|string|max:1000']);

        $record = $this->archiveService->transferToArchive(
            $type,
            $sourceId,
            $request->user()?->id,
            $data['reason'] ?? null
        );

        return response()->json([
            'message' => 'مورد به بایگانی منتقل و از بخش اصلی حذف شد.',
            'data'    => $record,
        ]);
    }

    /** همان «انتقال به بایگانی» ولی با شناسه‌ی خودِ رکورد بایگانی. */
    public function transferArchiveRecord(Request $request, int $archivedRecordId): JsonResponse
    {
        $data = $request->validate(['reason' => 'nullable|string|max:1000']);

        $record = $this->archiveService->transferArchiveRecord(
            $archivedRecordId,
            $request->user()?->id,
            $data['reason'] ?? null
        );

        return response()->json([
            'message' => 'انتقال به بایگانی انجام شد.',
            'data'    => $record,
        ]);
    }

    /** بازیابی رکورد مبدا از soft-delete (لغو انتقال). */
    public function restore(Request $request, int $archivedRecordId): JsonResponse
    {
        $record = $this->archiveService->restoreFromArchive($archivedRecordId, $request->user()?->id);

        return response()->json([
            'message' => 'مورد از بایگانی بازیابی و در بخش اصلی فعال شد.',
            'data'    => $record,
        ]);
    }

    /** حذف نرم خودِ ردیف بایگانی (نه رکورد مبدا). */
    public function destroy(Request $request, int $archivedRecordId): JsonResponse
    {
        $data = $request->validate(['reason' => 'nullable|string|max:1000']);

        $this->archiveService->softDeleteArchiveRecord(
            $archivedRecordId,
            $request->user()?->id,
            $data['reason'] ?? null
        );

        return response()->json([
            'message' => 'رکورد بایگانی حذف شد.',
        ]);
    }

    /* ------------------------------------------------------------------ */
    /* خروجی اکسل — هرکدام از بخش‌های بایگانی جداگانه                     */
    /* ------------------------------------------------------------------ */

    public function exportInvoices(Request $request): BinaryFileResponse
    {
        $filters = $this->validatedFilters($request);

        return Excel::download(
            new ArchivedInvoicesExport($filters, $request->user()?->id),
            $this->fileName('فاکتورها')
        );
    }

    public function exportRequests(Request $request): BinaryFileResponse
    {
        $filters = $this->validatedFilters($request);

        return Excel::download(
            new ArchivedRequestsExport($filters, $request->user()?->id),
            $this->fileName('درخواست‌ها')
        );
    }

    public function exportServiceJobs(Request $request): BinaryFileResponse
    {
        $filters = $this->validatedFilters($request);

        return Excel::download(
            new ArchivedServiceJobsExport($filters, $request->user()?->id),
            $this->fileName('سرویس‌ها')
        );
    }

    /** یک فایل اکسل با سه شیت جدا (فاکتورها/درخواست‌ها/سرویس‌ها). */
    public function exportAll(Request $request): BinaryFileResponse
    {
        $filters = $this->validatedFilters($request);

        return Excel::download(
            new ArchiveFullExport($filters, $request->user()?->id),
            $this->fileName('کامل')
        );
    }

    /* ------------------------------------------------------------------ */
    /* Helpers                                                            */
    /* ------------------------------------------------------------------ */

    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'source_type'    => 'nullable|in:invoice,request,service_job',
            'archive_status' => 'nullable|in:copied,transferred',
            'search'         => 'nullable|string|max:255',
            'from'           => 'nullable|date',
            'to'             => 'nullable|date',
            'per_page'       => 'nullable|integer|min:1|max:100',
        ]);
    }

    private function resolveType(string $routeSegment): string
    {
        return self::ROUTE_TYPE_MAP[$routeSegment] ?? $routeSegment;
    }

    private function fileName(string $label): string
    {
        $timestamp = class_exists(Jalalian::class)
            ? Jalalian::now()->format('Y-m-d_H-i')
            : now()->format('Y-m-d_H-i');

        return "بایگانی-{$label}-{$timestamp}.xlsx";
    }
}
