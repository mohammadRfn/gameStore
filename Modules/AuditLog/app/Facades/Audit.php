<?php

declare(strict_types=1);

namespace Modules\AuditLog\Facades;

use Illuminate\Support\Facades\Facade;
use Modules\AuditLog\Services\AuditLogger;

/**
 * @method static void log(\Modules\AuditLog\Enums\LogChannel $channel, \Modules\AuditLog\Enums\AuditAction|string $action, string $description, \Modules\AuditLog\Enums\LogLevel $level = \Modules\AuditLog\Enums\LogLevel::Info, ?\Illuminate\Database\Eloquent\Model $entity = null, ?array $old = null, ?array $new = null, array $context = [], array $tags = [], ?int $statusCode = null, ?int $durationMs = null)
 * @method static void business(\Modules\AuditLog\Enums\AuditAction|string $action, string $description, ?\Illuminate\Database\Eloquent\Model $entity = null, array $context = [], \Modules\AuditLog\Enums\LogLevel $level = \Modules\AuditLog\Enums\LogLevel::Info)
 * @method static void model(\Modules\AuditLog\Enums\AuditAction|string $action, \Illuminate\Database\Eloquent\Model $model, ?array $old = null, ?array $new = null, ?string $description = null)
 * @method static void auth(\Modules\AuditLog\Enums\AuditAction|string $action, string $description, array $context = [], \Modules\AuditLog\Enums\LogLevel $level = \Modules\AuditLog\Enums\LogLevel::Info)
 * @method static void security(string $type, string $description, array $context = [], \Modules\AuditLog\Enums\LogLevel $level = \Modules\AuditLog\Enums\LogLevel::Warning)
 * @method static void system(\Modules\AuditLog\Enums\AuditAction|string $action, string $description, array $context = [], \Modules\AuditLog\Enums\LogLevel $level = \Modules\AuditLog\Enums\LogLevel::Info)
 * @method static void sync(\Modules\AuditLog\Enums\AuditAction|string $action, string $description, array $context = [], \Modules\AuditLog\Enums\LogLevel $level = \Modules\AuditLog\Enums\LogLevel::Info)
 * @method static void exception(\Throwable $e, array $context = [], \Modules\AuditLog\Enums\LogLevel $level = \Modules\AuditLog\Enums\LogLevel::Error)
 * @method static void job(\Modules\AuditLog\Enums\AuditAction|string $action, string $description, array $context = [], \Modules\AuditLog\Enums\LogLevel $level = \Modules\AuditLog\Enums\LogLevel::Info)
 * @method static void console(string $command, string $description, array $context = [], \Modules\AuditLog\Enums\LogLevel $level = \Modules\AuditLog\Enums\LogLevel::Info)
 * @method static mixed withoutLogging(callable $callback)
 * @method static void flush()
 *
 * @see AuditLogger
 */
class Audit extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return 'auditlog';
    }
}
