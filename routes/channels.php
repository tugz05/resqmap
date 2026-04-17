<?php

use App\Models\Incident;
use App\Models\IncidentAssignment;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Private channel authorisation for the real-time Pusher pipeline. All
| sensitive incident traffic flows over private channels; only users who
| "own" the incident (reporter, assigned rescuer, any admin) can listen.
|
| Channels:
|   - App.Models.User.{id} ........ Notifications channel per user (default).
|   - incident.{ulid} ............. Per-incident timeline (reporter + rescuer + admins).
|   - incident.{ulid}.rescuer ..... Location stream for the resident watching the rescuer.
|   - incident.{ulid}.reporter .... Location stream for the rescuer watching the resident.
|   - rescuer.{id} ................ Personal rescuer feed (new dispatches, cancellations).
|   - admin.incidents ............. Admin operations centre (new reports, status changes).
*/

Broadcast::channel('App.Models.User.{id}', static function (User $user, int $id): bool {
    return $user->id === $id;
});

/** Per-incident timeline – anyone materially involved can subscribe. */
Broadcast::channel('incident.{ulid}', static function (User $user, string $ulid): bool {
    $incident = Incident::where('ulid', $ulid)->first();
    if ($incident === null) {
        return false;
    }

    if ($user->isAdmin()) {
        return true;
    }

    if ($user->id === $incident->reporter_id) {
        return true;
    }

    return IncidentAssignment::where('incident_id', $incident->id)
        ->where('rescuer_id', $user->id)
        ->exists();
});

Broadcast::channel('incident.{ulid}.rescuer', static function (User $user, string $ulid): bool {
    $incident = Incident::where('ulid', $ulid)->first();
    if ($incident === null) {
        return false;
    }

    if ($user->isAdmin()) {
        return true;
    }

    if ($user->id === $incident->reporter_id) {
        return true;
    }

    return IncidentAssignment::where('incident_id', $incident->id)
        ->where('rescuer_id', $user->id)
        ->exists();
});

Broadcast::channel('incident.{ulid}.reporter', static function (User $user, string $ulid): bool {
    $incident = Incident::where('ulid', $ulid)->first();
    if ($incident === null) {
        return false;
    }

    if ($user->isAdmin()) {
        return true;
    }

    if ($user->id === $incident->reporter_id) {
        return true;
    }

    return IncidentAssignment::where('incident_id', $incident->id)
        ->where('rescuer_id', $user->id)
        ->exists();
});

/** Personal feed for a rescuer — they only hear about their own dispatches. */
Broadcast::channel('rescuer.{id}', static function (User $user, int $id): bool {
    return $user->id === $id && $user->isRescuer();
});

/** Admin operations centre — all incidents, all status changes, everywhere. */
Broadcast::channel('admin.incidents', static function (User $user): bool {
    return $user->isAdmin();
});
