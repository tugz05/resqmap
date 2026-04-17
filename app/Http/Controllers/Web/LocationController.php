<?php

namespace App\Http\Controllers\Web;

use App\Events\UserLocationMoved;
use App\Http\Controllers\Controller;
use App\Models\UserLocation;
use App\Support\LocationBroadcastResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Session-authenticated location pings for Inertia dashboards.
 *
 * Residents push their position so rescuers can navigate to them, and
 * rescuers push their position so residents can watch them close in on the
 * scene — exactly like Grab tracking a delivery driver.
 *
 * Every ping is fanned out over Pusher to the incident(s) the user is
 * currently involved in, so the other side sees the pin move in real time
 * instead of polling every few seconds.
 *
 * The Flutter app uses the Sanctum-authenticated /api/v1/location endpoint
 * instead; this route is only for the web client.
 */
class LocationController extends Controller
{
    public function ping(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric'],
            'heading' => ['nullable', 'numeric'],
            'speed' => ['nullable', 'numeric'],
        ]);

        $user = $request->user();

        $location = UserLocation::updateOrCreate(
            ['user_id' => $user->id],
            [
                ...$validated,
                'is_active' => true,
                'located_at' => now(),
            ],
        );

        $ulids = LocationBroadcastResolver::activeIncidentUlidsFor($user);
        if ($ulids !== []) {
            UserLocationMoved::dispatch($user, $location, $ulids);
        }

        return response()->json([
            'latitude' => (float) $location->latitude,
            'longitude' => (float) $location->longitude,
            'located_at' => $location->located_at?->toISOString(),
            'broadcasted_to' => $ulids,
        ]);
    }
}
