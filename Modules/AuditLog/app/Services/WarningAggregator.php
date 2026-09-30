<?php

declare(strict_types=1);

namespace Modules\AuditLog\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Modules\AuditLog\Enums\LogChannel;
use Modules\AuditLog\Enums\LogLevel;
use Throwable;

/**
 * تجمیع هشدارها (Log::warning): به‌جای ثبت هر رخداد، شمارنده نگه می‌دارد و
 * در هر بازه (پیش‌فرض ساعتی) یک رکورد خلاصه به ازای هر پیام متمایز می‌نویسد.
 */
class WarningAggregator
{
    private const STATE_KEY   = 'auditlog:warnings:state';
    private const FLUSHED_KEY = 'auditlog:warnings:flushed-at';
    private const OVERFLOW    = '_overflow';
    private const MAX_PENDING = 500; // سقف پیام متمایز در حافظه‌ی یک درخواست

    /** @var array<string, array{message: string, exception: ?string, count: int, first_seen: string, last_seen: string}> */
    private array $pending = [];

    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $context */
    public function record(string $message, array $context = []): void
    {
        $exception = $context['exception'] ?? null;
        $class = $exception instanceof Throwable ? $exception::class : null;

        // عددها یکسان می‌شوند تا «id 12 پیدا نشد» و «id 13 پیدا نشد» یک گروه باشند
        $normalized = mb_substr((string) preg_replace('/\d+/', '#', $message), 0, 300);
        $key = md5($class . '|' . $normalized);
        $now = Carbon::now('UTC')->toIso8601String();

        if (! isset($this->pending[$key])) {
            if (count($this->pending) >= self::MAX_PENDING) {
                return;
            }

            $this->pending[$key] = [
                'message'    => mb_substr($message, 0, 500),
                'exception'  => $class,
                'count'      => 0,
                'first_seen' => $now,
                'last_seen'  => $now,
            ];
        }

        $this->pending[$key]['count']++;
        $this->pending[$key]['last_seen'] = $now;
    }

    public function flushIfDue(): void
    {
        $this->persist();

        $minutes = max(5, (int) config('auditlog.capture.warning.flush_minutes', 60));
        $last = Cache::get(self::FLUSHED_KEY);

        if (is_string($last) && Carbon::parse($last)->addMinutes($minutes)->isFuture()) {
            return;
        }

        Cache::put(self::FLUSHED_KEY, Carbon::now('UTC')->toIso8601String(), now()->addDays(2));

        $state = (array) Cache::pull(self::STATE_KEY, []);

        if ($state === []) {
            return;
        }

        uasort($state, static fn (array $a, array $b): int => $b['count'] <=> $a['count']);

        $limit = max(1, (int) config('auditlog.capture.warning.max_summaries', 20));

        foreach (array_slice($state, 0, $limit) as $item) {
            $this->audit->log(
                channel: LogChannel::System,
                action: 'warning.summary',
                description: $item['message'],
                level: LogLevel::Warning,
                context: [
                    'occurrences'    => $item['count'],
                    'first_seen'     => $item['first_seen'],
                    'last_seen'      => $item['last_seen'],
                    'exception'      => $item['exception'],
                    'window_minutes' => $minutes,
                ],
                tags: ['warning-summary'],
            );
        }
    }

    // ادغام شمارنده‌های این درخواست در حالت مشترک (کش)؛ یک نوشتن در هر درخواست
    private function persist(): void
    {
        if ($this->pending === []) {
            return;
        }

        $state = (array) Cache::get(self::STATE_KEY, []);
        $max = max(10, (int) config('auditlog.capture.warning.max_tracked', 200));

        foreach ($this->pending as $key => $item) {
            if (! isset($state[$key]) && count($state) >= $max) {
                $key = self::OVERFLOW;
                $item['message'] = 'سایر هشدارها (سقف پیام‌های متمایز پر شد)';
                $item['exception'] = null;
            }

            if (isset($state[$key])) {
                $state[$key]['count'] += $item['count'];
                $state[$key]['last_seen'] = $item['last_seen'];
            } else {
                $state[$key] = $item;
            }
        }

        $this->pending = [];
        Cache::put(self::STATE_KEY, $state, now()->addDays(2));
    }
}