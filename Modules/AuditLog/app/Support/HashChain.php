<?php

declare(strict_types=1);

namespace Modules\AuditLog\Support;

/**
 * زنجیره‌ی هش برای اثبات دست‌نخوردگی لاگ‌ها.
 * hash(n) = HMAC(secret, canonical(n) . hash(n-1))
 */
class HashChain
{
    /** ستون‌هایی که وارد محاسبه‌ی هش می‌شوند (ترتیب مهم است). */
    private const CANONICAL_FIELDS = [
        'uuid', 'occurred_at', 'channel', 'level', 'action', 'description',
        'entity_type', 'entity_id', 'actor_id', 'actor_type', 'actor_name',
        'request_id', 'ip', 'old_values', 'new_values', 'context',
    ];

    public function enabled(): bool
    {
        return (bool) config('auditlog.integrity.enabled', true);
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public function hash(array $row, ?string $previousHash): string
    {
        $canonical = [];

        foreach (self::CANONICAL_FIELDS as $field) {
            $value = $row[$field] ?? null;

            if (is_array($value)) {
                ksort($value);
                $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            // فرمت باید با آنچه در دیتابیس ذخیره می‌شود یکسان باشد تا verify درست کار کند
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d H:i:s');
            }

            $canonical[] = $field . '=' . (is_scalar($value) ? (string) $value : '');
        }

        $payload = implode('|', $canonical) . '|prev=' . (string) $previousHash;

        return hash_hmac(
            (string) config('auditlog.integrity.algo', 'sha256'),
            $payload,
            (string) config('auditlog.integrity.secret', 'auditlog'),
        );
    }

    /**
     * @param  array<string, mixed>  $row  رکورد خوانده‌شده از دیتابیس
     */
    public function matches(array $row, ?string $previousHash): bool
    {
        if (! isset($row['hash']) || $row['hash'] === null) {
            return false;
        }

        return hash_equals((string) $row['hash'], $this->hash($row, $previousHash));
    }
}
