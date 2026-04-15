<?php

namespace App\Enums;

enum IncidentSeverity: string
{
    case Low      = 'low';
    case Medium   = 'medium';
    case High     = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Low      => 'Low',
            self::Medium   => 'Medium',
            self::High     => 'High',
            self::Critical => 'Critical',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Low      => 'green',
            self::Medium   => 'yellow',
            self::High     => 'orange',
            self::Critical => 'red',
        };
    }

    public static function values(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }
}
