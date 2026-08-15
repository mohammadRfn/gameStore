<?php

namespace App\Http\Controllers;

use App\Services\StatsService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StatsController extends Controller
{
    public function __construct(
        protected StatsService $statsService
    ) {}

    /**
     * مرکز گزارشات — صفحهٔ اصلی ایندکس.
     * stats.daily و stats.monthly هم همین را رندر می‌کنند تا منوی فعلی نشکند.
     */
    public function index(Request $request): Response
    {
        return Inertia::render('Stats/Index', $this->payload($request));
    }

    public function dailyStats(Request $request): Response
    {
        if (! $request->filled('from') && ! $request->filled('to')) {
            $request->merge([
                'from' => now()->toDateString(),
                'to'   => now()->toDateString(),
            ]);
        }

        return $this->index($request);
    }
    public function ranking(Request $request): Response
    {
        return Inertia::render('Stats/Ranking', $this->payload($request));
    }
    public function monthlyStats(Request $request): Response
    {
        if (! $request->filled('from') && ! $request->filled('to')) {
            $request->merge([
                'from' => now()->startOfMonth()->toDateString(),
                'to'   => now()->endOfMonth()->toDateString(),
            ]);
        }

        return $this->index($request);
    }

    public function products(Request $request): Response
    {
        return Inertia::render('Stats/Product', $this->payload($request));
    }

    public function services(Request $request): Response
    {
        return Inertia::render('Stats/Service', $this->payload($request));
    }

    public function overview(Request $request): Response
    {
        return Inertia::render('Stats/Overview', $this->payload($request));
    }

    private function payload(Request $request): array
    {
        $range = $request->input('range', 'month');
        [$from, $to] = $this->resolveRange($request, $range);
        $paidOnly = $request->boolean('paid_only', false);

        $data = $this->statsService->dashboard($from, $to, $paidOnly);
        $data['range'] = $range;

        return $data;
    }

    private function resolveRange(Request $request, string $range): array
    {
        if ($request->filled('from') && $request->filled('to')) {
            return [
                Carbon::parse($request->input('from'))->toDateString(),
                Carbon::parse($request->input('to'))->toDateString(),
            ];
        }

        return match ($range) {
            'today' => [
                now()->toDateString(),
                now()->toDateString(),
            ],

            'yesterday' => [
                now()->subDay()->toDateString(),
                now()->subDay()->toDateString(),
            ],

            'week' => [
                now()->subDays(6)->toDateString(),
                now()->toDateString(),
            ],

            'last_30' => [
                now()->subDays(29)->toDateString(),
                now()->toDateString(),
            ],

            'month' => [
                now()->startOfMonth()->toDateString(),
                now()->toDateString(),
            ],

            'last_month' => [
                now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
                now()->subMonthNoOverflow()->endOfMonth()->toDateString(),
            ],

            'year' => [
                now()->startOfYear()->toDateString(),
                now()->toDateString(),
            ],

            'last_year' => [
                now()->subYearNoOverflow()->startOfYear()->toDateString(),
                now()->subYearNoOverflow()->endOfYear()->toDateString(),
            ],

            default => [
                now()->startOfMonth()->toDateString(),
                now()->toDateString(),
            ],
        };
    }
}
