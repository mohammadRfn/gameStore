<?php

declare(strict_types=1);

namespace Modules\Licensing\Exceptions;

use RuntimeException;
use Throwable;

/**
 * خطای ارتباط با StoreServer در فلوی فعال‌سازی/heartbeat.
 */
class LicenseServerException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode = 'LICENSE_SERVER_FAILED',
        public readonly ?int $httpStatus = null,
        /** @var array<string, mixed> */
        public readonly array $response = [],
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus ?? 0, $previous);
    }

    public static function misconfigured(string $message): self
    {
        return new self($message, 'MISCONFIGURED');
    }

    public static function transport(Throwable $e): self
    {
        return new self('ارتباط با StoreServer برقرار نشد: ' . $e->getMessage(), 'CONNECTION_FAILED', null, [], $e);
    }
}