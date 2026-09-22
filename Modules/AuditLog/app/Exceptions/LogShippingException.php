<?php

declare(strict_types=1);

namespace Modules\AuditLog\Exceptions;

use RuntimeException;
use Throwable;

/**
 * خطای ارسال لاگ به StoreServer.
 */
class LogShippingException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'SHIPPING_FAILED',
        public readonly ?int $httpStatus = null,
        /** @var array<string, mixed> */
        public readonly array $response = [],
        public readonly bool $retryable = true,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus ?? 0, $previous);
    }

    public static function misconfigured(string $message): self
    {
        return new self($message, 'MISCONFIGURED', null, [], false);
    }

    /** @param array<string, mixed> $response */
    public static function rejected(int $status, string $message, array $response = []): self
    {
        // خطاهای 4xx (به‌جز 408/409/429) قابل تلاش مجدد نیستند
        $retryable = $status >= 500 || in_array($status, [408, 409, 425, 429], true);

        return new self($message, (string) ($response['error']['code'] ?? 'HTTP_' . $status), $status, $response, $retryable);
    }

    public static function transport(Throwable $e): self
    {
        return new self('ارتباط با StoreServer برقرار نشد: ' . $e->getMessage(), 'CONNECTION_FAILED', null, [], true, $e);
    }
}
