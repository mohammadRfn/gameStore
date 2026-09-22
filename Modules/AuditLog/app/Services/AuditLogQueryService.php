<?php

declare(strict_types=1);

namespace Modules\AuditLog\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Modules\AuditLog\Enums\LogLevel;
use Modules\AuditLog\Models\AuditLog;

/**
 * کوئری‌های خواندنی برای API پنل مدیریت گیم‌استور.
 */
class AuditLogQueryService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<AuditLog>
     */
    public function query(array $filters = []): Builder
    {
        return AuditLog::query()
            ->when($filters['channel'] ?? null, fn (Builder $q, $v) => $q->whereIn('channel', (array) $v))
            ->when($filters['level'] ?? null, fn (Builder $q, $v) => $q->whereIn('level', (array) $v))
            ->when($filters['min_level'] ?? null, fn (Builder $q, $v) => $q->levelAtLeast(LogLevel::fromPsr((string) $v)))
            ->when($filters['action'] ?? null, fn (Builder $q, $v) => $q->where('action', 'like', $v . '%'))
            ->when($filters['actor_id'] ?? null, fn (Builder $q, $v) => $q->where('actor_id', (int) $v))
            ->when($filters['entity_type'] ?? null, fn (Builder $q, $v) => $q->where('entity_type', $v))
            ->when($filters['entity_id'] ?? null, fn (Builder $q, $v) => $q->where('entity_id', (int) $v))
            ->when($filters['request_id'] ?? null, fn (Builder $q, $v) => $q->where('request_id', $v))
            ->when($filters['sync_status'] ?? null, fn (Builder $q, $v) => $q->whereIn('sync_status', (array) $v))
            ->when($filters['ip'] ?? null, fn (Builder $q, $v) => $q->where('ip', $v))
            ->betweenDates($filters['from'] ?? null, $filters['to'] ?? null)
            ->when($filters['q'] ?? null, function (Builder $q, string $term): void {
                $like = '%' . $term . '%';

                $q->where(function (Builder $inner) use ($like): void {
                    foreach (['action', 'description', 'entity_label', 'entity_type', 'actor_name', 'ip', 'route', 'url'] as $column) {
                        $inner->orWhere($column, 'like', $like);
                    }
                });
            })
            ->orderByDesc('id');
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<AuditLog>
     */
    public function paginate(array $filters, int $perPage): LengthAwarePaginator
    {
        $max = (int) config('auditlog.api.max_per_page', 200);

        return $this->query($filters)->paginate(min($perPage, $max))->withQueryString();
    }

    /**
     * آمار تجمیعی برای داشبورد.
     *
     * @return array<string, mixed>
     */
    public function stats(int $days = 7): array
    {
        $since = now()->subDays($days);

        return [
            'since'        => $since->toDateTimeString(),
            'total'        => AuditLog::query()->count(),
            'in_period'    => AuditLog::query()->where('occurred_at', '>=', $since)->count(),
            'by_channel'   => AuditLog::query()->where('occurred_at', '>=', $since)
                ->select('channel', DB::raw('count(*) as aggregate'))
                ->groupBy('channel')->pluck('aggregate', 'channel'),
            'by_level'     => AuditLog::query()->where('occurred_at', '>=', $since)
                ->select('level', DB::raw('count(*) as aggregate'))
                ->groupBy('level')->pluck('aggregate', 'level'),
            'by_sync'      => AuditLog::query()
                ->select('sync_status', DB::raw('count(*) as aggregate'))
                ->groupBy('sync_status')->pluck('aggregate', 'sync_status'),
            'top_actions'  => AuditLog::query()->where('occurred_at', '>=', $since)
                ->select('action', DB::raw('count(*) as aggregate'))
                ->groupBy('action')->orderByDesc('aggregate')->limit(10)->pluck('aggregate', 'action'),
            'top_actors'   => AuditLog::query()->where('occurred_at', '>=', $since)
                ->whereNotNull('actor_name')
                ->select('actor_name', DB::raw('count(*) as aggregate'))
                ->groupBy('actor_name')->orderByDesc('aggregate')->limit(10)->pluck('aggregate', 'actor_name'),
            'errors'       => AuditLog::query()->where('occurred_at', '>=', $since)
                ->whereIn('level', ['error', 'critical', 'alert', 'emergency'])->count(),
        ];
    }

    /** تایم‌لاین کامل یک درخواست (همه‌ی لاگ‌های هم‌ریشه). */
    public function timeline(string $requestId): Builder
    {
        return AuditLog::query()
            ->where('request_id', $requestId)
            ->orderBy('id');
    }
}
