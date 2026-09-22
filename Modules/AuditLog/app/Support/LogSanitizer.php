<?php

declare(strict_types=1);

namespace Modules\AuditLog\Support;

use BackedEnum;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * حذف داده‌های حساس، کوتاه‌سازی مقادیر بزرگ و تبدیل انواع پیچیده
 * به ساختار قابل سریالایز. هیچ چیزی بدون عبور از اینجا لاگ نمی‌شود.
 */
class LogSanitizer
{
    /** @var list<string> */
    private array $redactedKeys;
    private string $mask;
    private int $maxValueLength;
    private int $maxDepth;
    private int $maxItems;

    public function __construct()
    {
        /** @var array<string, mixed> $config */
        $config = (array) config('auditlog.redaction', []);

        $this->redactedKeys   = array_map('strtolower', (array) ($config['keys'] ?? []));
        $this->mask           = (string) ($config['mask'] ?? '***REDACTED***');
        $this->maxValueLength = (int) ($config['max_value_length'] ?? 2000);
        $this->maxDepth       = (int) ($config['max_array_depth'] ?? 6);
        $this->maxItems       = (int) ($config['max_array_items'] ?? 200);
    }

    /**
     * @param  array<array-key, mixed>|null  $data
     * @return array<array-key, mixed>|null
     */
    public function sanitize(?array $data, int $depth = 0): ?array
    {
        if ($data === null) {
            return null;
        }

        $result = [];
        $count = 0;

        foreach ($data as $key => $value) {
            if (++$count > $this->maxItems) {
                $result['__truncated__'] = sprintf('%d مورد دیگر حذف شد', count($data) - $this->maxItems);
                break;
            }

            $result[$key] = $this->isSensitive((string) $key)
                ? $this->mask
                : $this->value($value, $depth + 1);
        }

        return $result;
    }

    public function isSensitive(string $key): bool
    {
        $needle = strtolower($key);

        foreach ($this->redactedKeys as $sensitive) {
            if ($needle === $sensitive || str_contains($needle, $sensitive)) {
                return true;
            }
        }

        return false;
    }

    /**
     * نرمال‌سازی هر مقدار به یک نوع قابل ذخیره در JSON.
     */
    public function value(mixed $value, int $depth = 0): mixed
    {
        if ($depth > $this->maxDepth) {
            return '__max_depth__';
        }

        return match (true) {
            $value === null, is_bool($value), is_int($value), is_float($value) => $value,
            is_string($value)                 => $this->truncate($value),
            $value instanceof BackedEnum      => $value->value,
            $value instanceof DateTimeInterface => $value->format(DateTimeInterface::ATOM),
            $value instanceof Arrayable        => $this->sanitize($value->toArray(), $depth),
            $value instanceof JsonSerializable => $this->value($value->jsonSerialize(), $depth + 1),
            is_array($value)                   => $this->sanitize($value, $depth),
            is_object($value)                  => $this->object($value, $depth),
            default                            => $this->truncate((string) json_encode($value)),
        };
    }

    private function object(object $value, int $depth): mixed
    {
        if (method_exists($value, '__toString')) {
            return $this->truncate((string) $value);
        }

        $vars = get_object_vars($value);

        return $vars === []
            ? ['__class__' => $value::class]
            : ['__class__' => $value::class] + (array) $this->sanitize($vars, $depth);
    }

    private function truncate(string $value): string
    {
        if (mb_strlen($value) <= $this->maxValueLength) {
            return $value;
        }

        return mb_substr($value, 0, $this->maxValueLength) . sprintf('…[%d چاراکتر کوتاه شد]', mb_strlen($value) - $this->maxValueLength);
    }

    /**
     * محدودکردن حجم payload بر حسب کیلوبایت (برای body درخواست‌ها).
     *
     * @param  array<array-key, mixed>  $payload
     * @return array<array-key, mixed>
     */
    public function limitSize(array $payload, int $maxKilobytes): array
    {
        $encoded = (string) json_encode($payload, JSON_UNESCAPED_UNICODE);

        if (strlen($encoded) <= $maxKilobytes * 1024) {
            return $payload;
        }

        return [
            '__oversized__' => true,
            'size_kb'       => (int) round(strlen($encoded) / 1024),
            'keys'          => array_slice(array_keys($payload), 0, 50),
            'sha256'        => hash('sha256', $encoded),
        ];
    }
}
