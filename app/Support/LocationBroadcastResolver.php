<?php

namespace App\Support;

use App\Enums\AssignmentStatus;
use App\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\IncidentAssignment;
use App\Models\User;

/**
 * Resolves which incidents the given user's GPS pings should be broadcast
 * to, so that the other side (resident ↔ rescuer) sees the live map update.
 *
 * We only broadcast location for incidents that are *actively in flight*:
 * any closed (resolved / cancelled) incident is ignored to avoid leaking
 * the rescuer's off-duty position.
 */
class LocationBroadcastResolver
{
    /**
     * @return array<int, string> List of incident ULIDs the user's pings
     *                            should be fanned out to.
     */
    public static function activeIncidentUlidsFor(User $user): array
    {
        if ($user->isResident()) {
            return Incident::query()
                ->where('reporter_id', $user->id)
                ->whereNotIn('status', [
                    IncidentStatus::Resolved->value,
                    IncidentStatus::Cancelled->value,
                ])
                ->pluck('ulid')
                ->all();
        }

        if ($user->isRescuer()) {
            return IncidentAssignment::query()
                ->where('rescuer_id', $user->id)
                ->whereNotIn('status', [
                    AssignmentStatus::Completed->value,
                    AssignmentStatus::Cancelled->value,
                ])
                ->join('incidents', 'incidents.id', '=', 'incident_assignments.incident_id')
                ->pluck('incidents.ulid')
                ->all();
        }

        return [];
    }
}
