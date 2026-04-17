<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Incident\StoreIncidentRequest;
use App\Http\Requests\Api\V1\Incident\UpdateIncidentStatusRequest;
use App\Http\Resources\IncidentResource;
use App\Jobs\AutoAssignRescuerJob;
use App\Jobs\VerifyIncidentAi;
use App\Models\Incident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class IncidentController extends Controller
{
    /**
     * List incidents, scoped by role.
     *   Admin   → all incidents (filterable)
     *   Rescuer → only their assigned incidents + nearby active ones
     *   Resident → only their own reports
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status'    => ['nullable', 'string'],
            'type'      => ['nullable', 'string'],
            'latitude'  => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'numeric', 'between:1,100'],
            'per_page'  => ['nullable', 'integer', 'between:5,100'],
        ]);

        $user  = $request->user();
        $query = Incident::with(['reporter:id,name', 'assignments.rescuer:id,name']);

        match ($user->role) {
            UserRole::Admin   => null, // no extra filter — sees everything
            UserRole::Rescuer => $query->whereHas('assignments', fn ($q) => $q->where('rescuer_id', $user->id)),
            UserRole::Resident => $query->where('reporter_id', $user->id),
        };

        if ($request->filled('status')) {
            $query->ofStatus(IncidentStatus::from($request->status));
        }

        if ($request->filled('type')) {
            $query->ofType(IncidentType::from($request->type));
        }

        if ($request->filled('latitude') && $request->filled('longitude')) {
            $query->nearby(
                lat: (float) $request->latitude,
                lng: (float) $request->longitude,
                radiusKm: (float) ($request->radius_km ?? 10),
            );
        }

        $incidents = $query
            ->orderByDesc('reported_at')
            ->paginate($request->integer('per_page', 20));

        return response()->json([
            'incidents'  => IncidentResource::collection($incidents->items()),
            'pagination' => [
                'total'        => $incidents->total(),
                'per_page'     => $incidents->perPage(),
                'current_page' => $incidents->currentPage(),
                'last_page'    => $incidents->lastPage(),
            ],
        ]);
    }

    /**
     * Create a new incident report from the mobile app.
     *
     * Photos are stored in storage/app/public/incidents/{ulid}/. The heavy
     * OpenAI vision verification is dispatched to the queue by IncidentObserver
     * so the reporter gets an instant response while analysis runs in the
     * background. Clients can poll GET /incidents/{ulid} to see the verdict.
     */
    public function store(StoreIncidentRequest $request): JsonResponse
    {
        $photoPaths = [];

        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $photo) {
                $photoPaths[] = $photo->store('incidents', 'public');
            }
        }

        $incident = Incident::create([
            ...$request->safe()->except('photos'),
            'reporter_id' => $request->user()->id,
            'photo_paths' => $photoPaths ?: null,
        ]);

        return response()->json([
            'incident' => new IncidentResource($incident->load('reporter')),
            'ai_verification' => [
                'status' => $incident->ai_verification_status,
                'queued_at' => $incident->ai_verification_queued_at?->toISOString(),
                'message' => 'AI verification is running in the background.',
            ],
            'message'  => 'Incident reported successfully.',
        ], 201);
    }

    /**
     * Get full details of a single incident.
     */
    public function show(Incident $incident): JsonResponse
    {
        $incident->load([
            'reporter',
            'verifier',
            'assignments.rescuer.location',
            'assignments.assigner',
        ]);

        return response()->json(['incident' => new IncidentResource($incident)]);
    }

    /**
     * Update incident status. Enforces role-based transition rules.
     *
     * When an admin flips a report to "verified", the AI Dispatch Agent is
     * triggered automatically — admins do not pick rescuers manually.
     */
    public function updateStatus(UpdateIncidentStatusRequest $request, Incident $incident): JsonResponse
    {
        $user      = $request->user();
        $newStatus = IncidentStatus::from($request->status);
        $allowed   = $user->isAdmin()
            ? $incident->status->adminTransitions()
            : $incident->status->rescuerTransitions();

        if (! in_array($newStatus, $allowed, true)) {
            return response()->json([
                'message' => "Cannot transition from [{$incident->status->value}] to [{$newStatus->value}].",
            ], 422);
        }

        $timestamps = match ($newStatus) {
            IncidentStatus::Verified   => ['verified_at' => now(), 'verified_by' => $user->id],
            IncidentStatus::Dispatched => ['dispatched_at' => now()],
            IncidentStatus::Resolved   => ['resolved_at' => now()],
            default                    => [],
        };

        $incident->update(['status' => $newStatus, ...$timestamps]);

        if (
            $user->isAdmin()
            && $newStatus === IncidentStatus::Verified
            && ! $incident->assignments()->exists()
        ) {
            AutoAssignRescuerJob::dispatch($incident->id);
        }

        return response()->json([
            'incident' => new IncidentResource($incident),
            'message'  => "Status updated to [{$newStatus->label()}].",
        ]);
    }

    /**
     * Re-queue the AI Dispatch Agent for an incident that didn't get a
     * rescuer the first time (e.g. none were online). Admin-only.
     */
    public function retryDispatch(Incident $incident): JsonResponse
    {
        if ($incident->assignments()->exists()) {
            return response()->json([
                'message' => 'A rescuer is already assigned.',
            ], 422);
        }

        AutoAssignRescuerJob::dispatch($incident->id);

        return response()->json([
            'incident_id' => $incident->ulid,
            'message' => 'AI Dispatch Agent queued. A rescuer will be matched shortly.',
        ], 202);
    }

    /**
     * Re-queue AI verification for an incident. The job runs in the background
     * via the database queue; poll GET /incidents/{ulid} for updates.
     */
    public function aiVerify(Incident $incident): JsonResponse
    {
        $incident->update([
            'ai_verification_status' => 'pending',
            'ai_verification_queued_at' => now(),
            'ai_verification_error' => null,
        ]);

        VerifyIncidentAi::dispatch($incident->id);

        return response()->json([
            'incident_id' => $incident->ulid,
            'ai_verification' => [
                'status' => 'pending',
                'queued_at' => $incident->ai_verification_queued_at?->toISOString(),
            ],
            'message' => 'AI verification queued. Poll the incident to see the result.',
        ], 202);
    }

    /**
     * List all active incidents in a geographic area (for the map view).
     */
    public function nearby(Request $request): JsonResponse
    {
        $request->validate([
            'latitude'  => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'radius_km' => ['nullable', 'numeric', 'between:1,100'],
        ]);

        $incidents = Incident::with(['reporter:id,name'])
            ->active()
            ->nearby(
                lat: (float) $request->latitude,
                lng: (float) $request->longitude,
                radiusKm: (float) ($request->radius_km ?? 10),
            )
            ->get();

        return response()->json([
            'incidents' => IncidentResource::collection($incidents),
            'total'     => $incidents->count(),
        ]);
    }
}
