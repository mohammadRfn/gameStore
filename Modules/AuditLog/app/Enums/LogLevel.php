<?php

declare(strict_types=1);

namespace Modules\AuditLog\Enums;

/**
 * سطوح لاگ سازگار با PSR-3.
 */
enum LogLevel: string
{
    case Debug     = 'debug';
    case Info      = 'info';
    case Notice    = 'notice';
    case Warning   = 'warning';
    case Error     = 'error';
    case Critical  = 'critical';
    case Alert     = 'alert';
    case Emergency = 'emergency';

    public function weight(): int
    {
        return match ($this) {
            self::Debug     => 10,
            self::Info      => 20,
            self::Notice    => 30,
            self::Warning   => 40,
            self::Error     => 50,
            self::Critical  => 60,
            self::Alert     => 70,
            self::Emergency => 80,
        };
    }

    public function atLeast(self $minimum): bool
    {
        return $this->weight() >= $minimum->weight();
    }

    public static function fromPsr(string $level): self
    {
        return self::tryFrom(strtolower($level)) ?? self::Info;
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }
}
