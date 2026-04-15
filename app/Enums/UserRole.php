<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Rescuer = 'rescuer';
    case Resident = 'resident';

    /**
     * Get the scalar enum values.
     *
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $role): string => $role->value,
            self::cases(),
        );
    }
}
