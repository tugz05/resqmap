<?php

namespace App\Enums;

enum AssignmentStatus: string
{
    case Assigned  = 'assigned';
    case Accepted  = 'accepted';
    case EnRoute   = 'en_route';
    case OnScene   = 'on_scene';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Assigned  => 'Assigned',
            self::Accepted  => 'Accepted',
            self::EnRoute   => 'En Route',
            self::OnScene   => 'On Scene',
            self::Completed => 'Completed',
            self::Cancelled => 'Cancelled',
        };
    }

    /** Transitions the rescuer themselves can make */
    public function rescuerTransitions(): array
    {
        return match ($this) {
            self::Assigned => [self::Accepted, self::Cancelled],
            self::Accepted => [self::EnRoute],
            self::EnRoute  => [self::OnScene],
            self::OnScene  => [self::Completed],
            default        => [],
        };
    }

    public static function values(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }
}
