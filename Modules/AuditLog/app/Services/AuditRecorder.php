<?php

declare(strict_types=1);

namespace Modules\AuditLog\Services;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\AuditLog\Enums\LogLevel;
use Modules\AuditLog\Enums\SyncStatus;
use Modules\AuditLog\Support\AuditContext;
use Modules\AuditLog\Support\HashChain;
use Modules\AuditLog\Support\LogEntry;
use Throwable;

/**
 * تنها نقطه‌ای که در جدول audit_logs می‌نویسد.
 *
 * ویژگی‌ها:
 *  - بافر کردن رکوردها و درج دسته‌ای در پایان چرخه‌ی اجرا (کاهش I/O)
 *  - محاسبه‌ی زنجیره‌ی هش برای تشخیص دستکاری
 *  - «هرگز اپ را نشکن»: هر خطای داخلی فقط در لاگ فایل گزارش می‌شود
 */
class AuditRecorder
{
    /** @var list<LogEntry> */
    private array $buffer = [];

    private bool $recording = true;

    /** جلوگیری از بازگشت بی‌نهایت (لاگِ خطای لاگ‌گیری) */
    private bool $writing = false;

    public function __construct(
        private readonly AuditContext $context,
        private readonly HashChain $hashChain,
    ) {
    }

    public function record(LogEntry $entry): void
    {
        if (! $this->enabled($entry)) {
            return;
        }

        $this->buffer[] = $entry;

        $limit = (int) config('auditlog.storage.buffer_limit', 200);

        if (! (bool) config('auditlog.storage.buffer', true) || count($this->buffer) >= $limit) {
            $this->flush();
        }
    }

    /**
     * درج فوری و بازگرداندن آیدی رکورد (برای مسیرهایی که نیاز به رکورد قطعی دارند).
     */
    public function recordNow(LogEntry $entry): void
    {
        if (! $this->enabled($entry)) {
            return;
        }

        $this->buffer[] = $entry;
        $this->flush();
    }

    public function flush(): void
    {
        if ($this->buffer === [] || $this->writing) {
            return;
        }

        $entries = $this->buffer;
        $this->buffer = [];
        $this->writing = true;

        try {
            $this->insert($entries);
        } catch (Throwable $e) {
            // لاگ‌گیری هرگز نباید جریان اصلی برنامه را قطع کند
            Log::channel(config('logging.default'))->error('[AuditLog] درج رکوردها ناموفق بود: ' . $e->getMessage(), [
                'exception' => $e::class,
                'count'     => count($entries),
            ]);
        } finally {
            $this->writing = false;
        }
    }

    /** @param list<LogEntry> $entries */
    private function insert(array $entries): void
    {
        $connection = $this->connection();
        $now = now();
        $contextArray = $this->context->toArray();

        $previous = $this->hashChain->enabled()
            ? (array) $connection->table('audit_logs')->orderByDesc('id')->limit(1)->first(['hash', 'sequence'])
            : [];

        $previousHash = $previous['hash'] ?? null;
        $sequence = (int) ($previous['sequence'] ?? 0);

        $rows = [];
        $shippingEnabled = (bool) config('auditlog.shipping.enabled', true);

        foreach ($entries as $entry) {
            $row = $entry->toDatabaseRow($contextArray);

            $row['sequence']      = ++$sequence;
            $row['previous_hash'] = $previousHash;
            $row['hash']          = $this->hashChain->enabled() ? $this->hashChain->hash($row, $previousHash) : null;
            $previousHash         = $row['hash'];

            $row['sync_status']   = ($shippingEnabled && $this->shippable($entry))
                ? SyncStatus::Pending->value
                : SyncStatus::Skipped->value;
            $row['sync_attempts'] = 0;
            $row['created_at']    = $now;
            $row['updated_at']    = $now;

            foreach (['old_values', 'new_values', 'changed_keys', 'context', 'tags'] as $jsonColumn) {
                $row[$jsonColumn] = $row[$jsonColumn] === null
                    ? null
                    : json_encode($row[$jsonColumn], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
            }

            $rows[] = $row;
        }

        // درج تکه‌تکه برای سازگاری با محدودیت پارامترهای SQLite
        foreach (array_chunk($rows, 50) as $chunk) {
            $connection->table('audit_logs')->insert($chunk);
        }
    }

    /**
     * آیا این رکورد باید به StoreServer ارسال شود؟
     */
    private function shippable(LogEntry $entry): bool
    {
        /** @var list<string> $channels */
        $channels = (array) config('auditlog.shipping.channels', ['*']);

        if (! in_array('*', $channels, true) && ! in_array($entry->channel->value, $channels, true)) {
            return false;
        }

        $minimum = LogLevel::fromPsr((string) config('auditlog.shipping.minimum_level', 'info'));

        return $entry->level->atLeast($minimum);
    }

    private function enabled(LogEntry $entry): bool
    {
        if (! $this->recording || ! (bool) config('auditlog.enabled', true)) {
            return false;
        }

        if (! (bool) data_get(config('auditlog.channels'), $entry->channel->value, true)) {
            return false;
        }

        $minimum = LogLevel::fromPsr((string) config('auditlog.minimum_level', 'info'));

        return $entry->level->atLeast($minimum);
    }

    private function connection(): ConnectionInterface
    {
        return DB::connection(config('auditlog.storage.connection') ?: null);
    }

    /** اجرای یک بخش از کد بدون ثبت لاگ (مثلاً هنگام import انبوه). */
    public function withoutRecording(callable $callback): mixed
    {
        $previous = $this->recording;
        $this->recording = false;

        try {
            return $callback();
        } finally {
            $this->recording = $previous;
        }
    }

    public function pause(): void
    {
        $this->recording = false;
    }

    public function resume(): void
    {
        $this->recording = true;
    }

    public function isRecording(): bool
    {
        return $this->recording;
    }

    public function bufferCount(): int
    {
        return count($this->buffer);
    }
}
