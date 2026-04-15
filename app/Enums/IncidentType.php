<?php

namespace App\Enums;

enum IncidentType: string
{
    case Fire = 'fire';
    case Flood = 'flood';
    case Medical = 'medical';
    case Earthquake = 'earthquake';
    case Landslide = 'landslide';
    case Accident = 'accident';
    case Missing = 'missing';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Fire       => 'Fire',
            self::Flood      => 'Flood',
            self::Medical    => 'Medical Emergency',
            self::Earthquake => 'Earthquake',
            self::Landslide  => 'Landslide',
            self::Accident   => 'Accident',
            self::Missing    => 'Missing Person',
            self::Other      => 'Other',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Fire       => '🔥',
            self::Flood      => '🌊',
            self::Medical    => '🚑',
            self::Earthquake => '🌍',
            self::Landslide  => '⛰️',
            self::Accident   => '🚗',
            self::Missing    => '👤',
            self::Other      => '⚠️',
        };
    }

    public static function values(): array
    {
        return array_map(static fn (self $type): string => $type->value, self::cases());
    }
}
