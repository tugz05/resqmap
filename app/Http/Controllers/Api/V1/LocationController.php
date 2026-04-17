<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\UserRole;
use App\Events\UserLocationMoved;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Location\UpdateLocationRequest;
use App\Http\Resources\UserLocationResource;
use App\Models\User;
use App\Models\UserLocation;
use App\Support\LocationBroadcastResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    /**
     * Push the authenticated user's current GPS position.
     * Called by the Flutter app every few seconds while active.
     * Uses updateOrCreate so there is always exactly one row per user.
     */
    public function update(UpdateLocationRequest $request): JsonResponse
    {
        $user = $request->user();

        $location = UserLocation::updateOrCreate(
            ['user_id' => $user->id],
            [
                ...$request->validated(),
                'is_active' => true,
                'located_at' => now(),
            ],
        );

        $ulids = LocationBroadcastResolver::activeIncidentUlidsFor($user);
        if ($ulids !== []) {
            UserLocationMoved::dispatch($user, $location, $ulids);
        }

        return response()->json([
            'location' => new UserLocationResource($location),
        ]);
    }

    /**
     * Mark the user as offline / stop broadcasting their location.
     */
    public function deactivate(Request $request): JsonResponse
    {
        UserLocation::where('user_id', $request->user()->id)
            ->update(['is_active' => false]);

        return response()->json(['message' => 'Location sharing paused.']);
    }

    /**
     * Return all active rescuer positions.
     * Used by the web dashboard map and the Flutter rescuer tracking screen.
     * Accessible by Admin and Rescuer roles only.
     */
    public function activeRescuers(Request $request): JsonResponse
    {
        $locations = UserLocation::with(['user:id,name,role'])
            ->active()
            ->fresh(minutes: 10)
            ->whereHas('user', static fn ($q) => $q->where('role', UserRole::Rescuer->value))
            ->get();

        return response()->json([
            'rescuers' => UserLocationResource::collection($locations),
            'total' => $locations->count(),
        ]);
    }

    /**
     * Return all active user positions within a radius (admin use / dispatch screen).
     */
    public function nearby(Request $request): JsonResponse
    {
        $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'numeric', 'between:1,100'],
        ]);

        $locations = UserLocation::with(['user:id,name,role'])
            ->active()
            ->fresh()
            ->nearby(
                lat: (float) $request->latitude,
                lng: (float) $request->longitude,
                radiusKm: (float) ($request->radius_km ?? 10),
            )
            ->get();

        return response()->json([
            'locations' => UserLocationResource::collection($locations),
            'total' => $locations->count(),
        ]);
    }
}
