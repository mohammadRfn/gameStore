<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Modules\AuditLog\Enums\SyncStatus;
use Modules\AuditLog\Models\AuditLog;
use Modules\AuditLog\Services\AuditLogger;
use Modules\AuditLog\Services\LogPayloadBuilder;
use Modules\AuditLog\Services\StoreServerClient;
use ReflectionMethod;
use Throwable;

/**
 * ⚠️ موقتی — فقط برای تست لود StoreServer؛ بعد از تست همین فایل را پاک کن.
 *
 * چند دسته‌ی لاگ را «هم‌زمان» با همان امضا/توکن/هدرهای واقعی اپ (StoreServerClient) به StoreServer می‌فرستد.
 * هدرها و بدنه با reflection از همان کلاینت می‌آیند، پس مسیر احراز هویت سرور عیناً واقعی است.
 * (برای کارکرد درست، ارسال خودکار را موقتاً خاموش کن: AUDITLOG_AUTO_SHIP=false)
 *
 *   php artisan auditlog:burst --seed=20000 --parallel=20 --size=500
 */
class AuditBurstCommand extends Command
{
    protected $signature = 'auditlog:burst
        {--seed= : قبل از ارسال این تعداد لاگ ساختگی بساز (با زنجیره‌ی هش واقعی)}
        {--parallel=20 : تعداد درخواست هم‌زمان در هر دور}
        {--size=500 : تعداد لاگ در هر دسته (سقف سرور ۵۰۰)}
        {--rounds=1 : تعداد دورها}
        {--sleep=0 : مکث بین دورها (ثانیه)}
        {--timeout= : مهلت هر درخواست (ثانیه)؛ پیش‌فرض از کانفیگ اپ}
        {--yes : بدون سؤال تأیید}';

    protected $description = 'موقتی: ارسال هم‌زمان دسته‌های لاگ به StoreServer برای تست لود';

    public function handle(LogPayloadBuilder $builder, StoreServerClient $client): int
    {
        $parallel = max(1, min(100, (int) $this->option('parallel')));
        $size = max(1, min(500, (int) $this->option('size')));
        $rounds = max(1, (int) $this->option('rounds'));
        $pause = max(0, (int) $this->option('sleep'));

        $this->warn("هر دور: {$parallel} درخواست هم‌زمان × {$size} لاگ = " . number_format($parallel * $size) . " لاگ | دورها: {$rounds} | هدف: " . $client->ingestUrl());
        $this->warn('سقف سرور: ۲۰ درخواست در دقیقه برای هر لایسنس؛ مازاد 429 می‌گیرد.');

        if (! $this->option('yes') && ! $this->confirm('ادامه بدهم؟')) {
            return self::FAILURE;
        }

        if ((int) $this->option('seed') > 0) {
            $this->seed((int) $this->option('seed'));
        }

        $encode = new ReflectionMethod($client, 'encode');
        $headers = new ReflectionMethod($client, 'headers');

        $totalAccepted = 0;
        $totalStart = microtime(true);

        for ($round = 1; $round <= $rounds; $round++) {
            $logs = AuditLog::query()->shippable()->limit($parallel * $size)->get();

            if ($logs->isEmpty()) {
                $this->info('لاگ آماده‌ی ارسال نمانده است.');

                break;
            }

            $chunks = $logs->chunk($size)->values();

            // آماده‌سازی همه‌ی درخواست‌ها قبل از شروع زمان‌سنجی (nonce/timestamp تازه برای هر کدام)
            $requests = [];

            foreach ($chunks as $i => $chunk) {
                $batchUuid = (string) Str::uuid();
                $payload = $builder->build($batchUuid, $chunk);
                $gzip = false;
                $args = [$payload, &$gzip];
                $body = $encode->invokeArgs($client, $args);

                AuditLog::query()->whereIn('id', $chunk->modelKeys())->update([
                    'sync_status' => SyncStatus::Sending->value,
                    'batch_uuid'  => $batchUuid,
                    'updated_at'  => now(),
                ]);

                $requests[$i] = [
                    'uuid'    => $batchUuid,
                    'headers' => $headers->invoke($client, $gzip, $batchUuid),
                    'body'    => $body,
                    'gzip'    => $gzip,
                    'logs'    => $chunk,
                ];
            }

            $url = $client->ingestUrl();
            $timeout = (int) ($this->option('timeout') ?: config('auditlog.shipping.timeout', 20));
            $verify = (bool) config('auditlog.shipping.verify_ssl', true);

            $startedAt = microtime(true);

            $responses = Http::pool(function (Pool $pool) use ($requests, $url, $timeout, $verify): void {
                foreach ($requests as $i => $r) {
                    $req = $pool->as((string) $i)->withHeaders($r['headers'])->timeout($timeout)->connectTimeout(5);

                    if (! $verify) {
                        $req = $req->withoutVerifying();
                    }

                    $req->withBody($r['body'], 'application/json')->post($url);
                }
            });

            $wallSec = max(microtime(true) - $startedAt, 0.001);

            $statuses = [];
            $errors = [];
            $breakdown = [];
            $times = [];
            $accepted = 0;

            foreach ($requests as $i => $r) {
                $res = $responses[$i] ?? $responses[(string) $i] ?? null;

                if ($res instanceof Response) {
                    $statuses[(string) $res->status()] = ($statuses[(string) $res->status()] ?? 0) + 1;
                    $stats = $res->handlerStats();
                    $times[] = (int) round(((float) ($stats['total_time'] ?? 0)) * 1000);
                    $breakdown[] = sprintf(
                        'بدنه %s KB%s | اتصال %d ms | شروع انتقال %d ms | اولین بایت پاسخ %d ms | کل %d ms',
                        number_format(strlen((string) $r['body']) / 1024, 1),
                        $r['gzip'] ? ' (gzip)' : '',
                        (float) ($stats['connect_time'] ?? 0) * 1000,
                        (float) ($stats['pretransfer_time'] ?? 0) * 1000,
                        (float) ($stats['starttransfer_time'] ?? 0) * 1000,
                        (float) ($stats['total_time'] ?? 0) * 1000,
                    );
                    $json = is_array($res->json()) ? (array) $res->json() : [];

                    if ($res->successful() && ($json['ok'] ?? true) !== false) {
                        $accepted += $this->markDone($r['uuid'], $r['logs'], $json);

                        continue;
                    }

                    $this->markFailed($r['logs'], 'burst: HTTP ' . $res->status());

                    continue;
                }

                $statuses['error'] = ($statuses['error'] ?? 0) + 1;
                $errText = $res instanceof Throwable ? get_class($res) . ': ' . mb_substr($res->getMessage(), 0, 300) : 'no response (' . get_debug_type($res) . ')';
                $errors[$errText] = ($errors[$errText] ?? 0) + 1;
                $this->markFailed($r['logs'], 'burst: ' . $errText);
            }

            $totalAccepted += $accepted;
            sort($times);
            ksort($statuses);

            $this->newLine();
            $this->info("دور {$round}: " . count($requests) . ' درخواست در ' . round($wallSec, 2) . ' ثانیه');
            $this->table(['وضعیت‌ها', 'لاگ پذیرفته‌شده', 'لاگ/ثانیه', 'ms (avg)', 'p95', 'max'], [[
                json_encode($statuses),
                number_format($accepted),
                number_format((int) round($accepted / $wallSec)),
                $times === [] ? '-' : (int) round(array_sum($times) / count($times)),
                $times === [] ? '-' : $times[(int) max(0, (int) ceil(count($times) * 0.95) - 1)],
                $times === [] ? '-' : (int) end($times),
            ]]);

            foreach (array_slice($breakdown, 0, 3) as $line) {
                $this->line('  ' . $line);
            }

            foreach ($errors as $text => $n) {
                $this->error("{$n}× {$text}");
            }

            if ($round < $rounds && $pause > 0) {
                sleep($pause);
            }
        }

        $this->info('جمع پذیرفته‌شده: ' . number_format($totalAccepted) . ' لاگ در ' . round(microtime(true) - $totalStart, 1) . ' ثانیه (شامل ساخت درخواست‌ها)');

        return self::SUCCESS;
    }

    private function seed(int $count): void
    {
        // helper سراسری audit() در این پروژه لود نمی‌شود؛ همان سرویس را مستقیم می‌گیریم (audit() هم دقیقاً همین کار را می‌کند)
        $logger = app(AuditLogger::class);
        $bar = $this->output->createProgressBar($count);

        for ($i = 1; $i <= $count; $i++) {
            $logger->system('loadtest.synthetic', 'synthetic load test record ' . $i, ['n' => $i, 'pad' => Str::random(120)]);

            if ($i % 500 === 0) {
                $logger->flush();
            }

            $bar->advance();
        }

        $logger->flush();
        $bar->finish();
        $this->newLine();
    }

    /**
     * مثل LogShippingService: اگر سرور لیست accepted برگرداند همان‌ها، وگرنه کل دسته (2xx).
     *
     * @param  Collection<int, AuditLog>  $logs
     * @param  array<string, mixed>  $json
     */
    private function markDone(string $batchUuid, Collection $logs, array $json): int
    {
        $acceptedList = data_get($json, 'data.accepted');

        if (is_array($acceptedList) && $acceptedList !== [] && is_string($acceptedList[array_key_first($acceptedList)] ?? null)) {
            $accepted = collect($acceptedList)->values();
        } else {
            $rejectedRaw = data_get($json, 'data.rejected');
            $rejected = is_array($rejectedRaw)
                ? collect($rejectedRaw)->map(static fn ($x): string => is_array($x) ? (string) ($x['uuid'] ?? '') : (string) $x)->filter()->values()
                : collect();
            $accepted = $logs->pluck('uuid')->diff($rejected)->values();
        }

        $accepted->chunk(300)->each(function (Collection $chunk) use ($batchUuid): void {
            AuditLog::query()->whereIn('uuid', $chunk->all())->update([
                'sync_status'     => SyncStatus::Synced->value,
                'synced_at'       => now(),
                'batch_uuid'      => $batchUuid,
                'next_attempt_at' => null,
                'last_error'      => null,
                'updated_at'      => now(),
            ]);
        });

        $rest = $logs->pluck('uuid')->diff($accepted)->values();

        if ($rest->isNotEmpty()) {
            $this->markFailed($logs->whereIn('uuid', $rest->all()), 'burst: رکورد پذیرفته نشد');
        }

        return $accepted->count();
    }

    /** @param Collection<int, AuditLog> $logs */
    private function markFailed(Collection $logs, string $message): void
    {
        // بعد از پنجره‌ی یک‌دقیقه‌ای محدودکننده‌ی سرور دوباره قابل ارسال شود
        foreach ($logs->chunk(300) as $chunk) {
            AuditLog::query()->whereIn('id', $chunk->modelKeys())->update([
                'sync_status'     => SyncStatus::Failed->value,
                'next_attempt_at' => now()->addSeconds(65),
                'last_error'      => mb_substr($message, 0, 512),
                'updated_at'      => now(),
            ]);
        }
    }
}