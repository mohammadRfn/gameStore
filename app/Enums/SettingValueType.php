<?php

namespace App\Enums;

enum SettingValueType: string
{
    case String  = 'string';
    case Integer = 'integer';
    case Float   = 'float';
    case Boolean = 'boolean';
    case Json    = 'json';
    case Select  = 'select';

    /** Cast a raw DB string to the PHP-native type. */
    public function cast(?string $raw): mixed
    {
        return match ($this) {
            self::Integer => $raw === null ? null : (int) $raw,
            self::Float   => $raw === null ? null : (float) $raw,
            self::Boolean => $raw === null ? null : filter_var($raw, FILTER_VALIDATE_BOOL),
            self::Json    => $raw === null ? null : json_decode($raw, true),
            default       => $raw,
        };
    }

    /** Encode a PHP-native value into a storable string. */
    public function encode(mixed $value): ?string
    {
        return match ($this) {
            self::Boolean => $value === null ? null : ($value ? '1' : '0'),
            self::Json    => $value === null ? null : json_encode($value, JSON_UNESCAPED_UNICODE),
            default       => $value === null ? null : (string) $value,
        };
    }
}
