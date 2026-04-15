<?php

namespace App\Enums;

enum IncidentStatus: string
{
    case Pending    = 'pending';
    case Verified   = 'verified';
    case Dispatched = 'dispatched';
    case EnRoute    = 'en_route';
    case OnScene    = 'on_scene';
    case Resolved   = 'resolved';
    case Cancelled  = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending    => 'Pending',
            self::Verified   => 'Verified',
            self::Dispatched => 'Dispatched',
            self::EnRoute    => 'En Route',
            self::OnScene    => 'On Scene',
            self::Resolved   => 'Resolved',
            self::Cancelled  => 'Cancelled',
        };
    }

    public function isActive(): bool
    {
        return in_array($this, [
            self::Pending,
            self::Verified,
            self::Dispatched,
            self::EnRoute,
            self::OnScene,
        ], true);
    }

    /** Transitions allowed for a rescuer */
    public function rescuerTransitions(): array
    {
        return match ($this) {
            self::Dispatched => [self::EnRoute],
            self::EnRoute    => [self::OnScene],
            self::OnScene    => [self::Resolved],
            default          => [],
        };
    }

    /** Transitions allowed for an admin */
    public function adminTransitions(): array
    {
        return match ($this) {
            self::Pending    => [self::Verified, self::Cancelled],
            self::Verified   => [self::Dispatched, self::Cancelled],
            self::Dispatched => [self::EnRoute, self::Cancelled],
            self::EnRoute    => [self::OnScene, self::Cancelled],
            self::OnScene    => [self::Resolved, self::Cancelled],
            default          => [],
        };
    }

    public static function values(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }
}
