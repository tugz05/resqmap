<?php

namespace App\Services\Agentic;

use App\Enums\AssignmentStatus;
use App\Enums\IncidentStatus;
use App\Enums\UserRole;
use App\Events\RescuerAssigned;
use App\Models\Incident;
use App\Models\IncidentAssignment;
use App\Models\User;

/**
 * AI Dispatch Agent (Right side of flowchart).
 *
 * Follows the flowchart steps:
 *   1. GET LOCATION     – read the incident lat/lng.
 *   2. SEARCH RESCUERS  – find rescuers available in the province.
 *   3. RANK & RECOMMEND – score by distance, workload, and GPS freshness.
 *   4. DISPATCH         – optionally auto-assign the top-ranked rescuer.
 *
 * This agent is algorithmic (Haversine + weighted score). It does NOT rely on
 * an LLM to pick rescuers, because assignment must be deterministic and
 * provably nearest-location based.
 */
class DispatchAgentService
{
    /** Maximum search radius (km) before we consider a rescuer too far. */
    public const DEFAULT_SEARCH_RADIUS_KM = 50.0;

    /** Average ground response speed in km/h used for ETA estimation. */
    public const AVERAGE_SPEED_KMH = 40.0;

    /** GPS ping age (minutes) above which rescuer location is considered stale. */
    public const FRESH_LOCATION_MINUTES = 15;

    /**
     * Find nearest rescuers and return a ranked recommendation payload.
     *
     * @return array{
     *   incident_location: array{latitude: float, longitude: float},
     *   search_radius_km: float,
     *   candidates: array<int, array{
     *     rescuer_id:int,
     *     name:string,
     *     distance_km:float|null,
     *     eta_minutes:int|null,
     *     active_assignments:int,
     *     last_seen_minutes:int|null,
     *     is_fresh:bool,
     *     score:float,
     *     rank:int
     *   }>,
     *   recommended_rescuer_id:int|null,
     *   generated_at:string,
     *   strategy:string
     * }
     */
    public function findNearestRescuers(Incident $incident, int $limit = 5, float $radiusKm = self::DEFAULT_SEARCH_RADIUS_KM): array
    {
        // ── Step 1: GET LOCATION ──────────────────────────────────────────
        $lat = (float) $incident->latitude;
        $lng = (float) $incident->longitude;

        // ── Step 2: SEARCH RESCUERS ───────────────────────────────────────
        $rescuers = User::query()
            ->role(UserRole::Rescuer)
            ->with('location')
            ->withCount([
                'assignments as active_assignments_count' => fn ($query) => $query->whereNotIn('status', [
                    AssignmentStatus::Completed->value,
                    AssignmentStatus::Cancelled->value,
                ]),
            ])
            ->get();

        // ── Step 3: RANK & RECOMMEND ──────────────────────────────────────
        $candidates = $rescuers
            ->map(fn (User $rescuer): array => $this->scoreRescuer($rescuer, $lat, $lng))
            ->filter(fn (array $row): bool => $row['distance_km'] === null || $row['distance_km'] <= $radiusKm)
            ->sortByDesc('score')
            ->values()
            ->take($limit)
            ->map(function (array $row, int $index): array {
                $row['rank'] = $index + 1;

                return $row;
            })
            ->values()
            ->all();

        $top = $candidates[0] ?? null;

        return [
            'incident_location' => [
                'latitude' => $lat,
                'longitude' => $lng,
            ],
            'search_radius_km' => $radiusKm,
            'candidates' => $candidates,
            'recommended_rescuer_id' => $top['rescuer_id'] ?? null,
            'generated_at' => now()->toISOString(),
            'strategy' => 'haversine+workload+freshness',
        ];
    }

    /**
     * Auto-dispatch the nearest ranked rescuer.
     *
     * Returns null if no eligible rescuer was found.
     */
    public function autoDispatch(Incident $incident, User $admin): ?IncidentAssignment
    {
        $ranking = $this->findNearestRescuers($incident, limit: 5);
        $rescuerId = $ranking['recommended_rescuer_id'] ?? null;

        if ($rescuerId === null) {
            return null;
        }

        $top = $ranking['candidates'][0] ?? [];
        $notes = sprintf(
            'Auto-dispatched by AI Dispatch Agent: ~%s km away, ETA %s min.',
            $top['distance_km'] !== null ? number_format((float) $top['distance_km'], 2) : '?',
            $top['eta_minutes'] ?? '?',
        );

        $assignment = IncidentAssignment::updateOrCreate(
            [
                'incident_id' => $incident->id,
                'rescuer_id' => $rescuerId,
            ],
            [
                'assigned_by' => $admin->id,
                'status' => AssignmentStatus::Assigned,
                'notes' => $notes,
                'assigned_at' => now(),
            ],
        );

        if (in_array($incident->status, [IncidentStatus::Pending, IncidentStatus::Verified], true)) {
            $incident->update([
                'status' => IncidentStatus::Dispatched,
                'verified_at' => $incident->verified_at ?? now(),
                'verified_by' => $incident->verified_by ?? $admin->id,
                'dispatched_at' => now(),
            ]);
        }

        $incident->update([
            'ai_dispatch' => [
                ...$ranking,
                'selected_rescuer_id' => $rescuerId,
                'auto_dispatched' => true,
            ],
            'ai_dispatched_at' => now(),
        ]);

        // Grab-style "driver found" moment — light up every dashboard.
        RescuerAssigned::dispatch($incident->fresh(), $assignment);

        return $assignment;
    }

    /**
     * Persist the ranking payload on the incident without dispatching.
     * Admin will still pick/confirm with a single click from the ranked list.
     */
    public function persistRanking(Incident $incident, array $ranking): void
    {
        $incident->update([
            'ai_dispatch' => [
                ...$ranking,
                'selected_rescuer_id' => null,
                'auto_dispatched' => false,
            ],
            'ai_dispatched_at' => now(),
        ]);
    }

    /**
     * Score a single rescuer against the incident coordinates.
     *
     * Composite score (higher is better):
     *   - 60% inverse distance (nearer is better)
     *   - 25% workload (fewer active assignments is better)
     *   - 15% GPS freshness (recent ping is better)
     */
    protected function scoreRescuer(User $rescuer, float $incidentLat, float $incidentLng): array
    {
        $location = $rescuer->location;
        $distance = null;
        $lastSeenMinutes = null;
        $isFresh = false;

        if ($location && $location->latitude !== null && $location->longitude !== null) {
            $distance = $this->haversine(
                $incidentLat,
                $incidentLng,
                (float) $location->latitude,
                (float) $location->longitude,
            );
        }

        if ($location?->located_at) {
            $lastSeenMinutes = (int) max(0, $location->located_at->diffInMinutes(now()));
            $isFresh = $lastSeenMinutes <= self::FRESH_LOCATION_MINUTES;
        }

        $activeAssignments = (int) ($rescuer->active_assignments_count ?? 0);

        // Distance score: 1.0 at 0km, 0.0 at 50km+.
        $distanceScore = $distance === null
            ? 0.1
            : max(0.0, 1.0 - ($distance / self::DEFAULT_SEARCH_RADIUS_KM));

        // Workload score: 1.0 with 0 jobs, decays by 0.2 per active job, min 0.
        $workloadScore = max(0.0, 1.0 - ($activeAssignments * 0.2));

        // Freshness score: fresh = 1.0, else decays over 60 minutes.
        $freshnessScore = $lastSeenMinutes === null
            ? 0.2
            : max(0.0, 1.0 - min(1.0, $lastSeenMinutes / 60));

        $score = round(($distanceScore * 0.60) + ($workloadScore * 0.25) + ($freshnessScore * 0.15), 4);

        return [
            'rescuer_id' => $rescuer->id,
            'name' => $rescuer->name,
            'distance_km' => $distance === null ? null : round($distance, 2),
            'eta_minutes' => $distance === null ? null : (int) max(1, round(($distance / self::AVERAGE_SPEED_KMH) * 60)),
            'active_assignments' => $activeAssignments,
            'last_seen_minutes' => $lastSeenMinutes,
            'is_fresh' => $isFresh,
            'score' => $score,
            'rank' => 0,
        ];
    }

    /**
     * Great-circle distance between two coordinates in kilometers.
     */
    protected function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadiusKm = 6371.0;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2))
            * sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }
}
