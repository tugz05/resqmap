<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\IncidentStatus;
use App\Enums\IncidentType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Incident\AssignRescuerRequest;
use App\Http\Requests\Api\V1\Incident\StoreIncidentRequest;
use App\Http\Requests\Api\V1\Incident\UpdateIncidentStatusRequest;
use App\Http\Resources\IncidentAssignmentResource;
use App\Http\Resources\IncidentResource;
use App\Models\Incident;
use App\Models\IncidentAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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
     * Photos are stored in storage/app/public/incidents/{ulid}/.
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

        return response()->json([
            'incident' => new IncidentResource($incident),
            'message'  => "Status updated to [{$newStatus->label()}].",
        ]);
    }

    /**
     * Assign a rescuer to an incident (Admin only).
     */
    public function assign(AssignRescuerRequest $request, Incident $incident): JsonResponse
    {
        $assignment = IncidentAssignment::updateOrCreate(
            ['incident_id' => $incident->id, 'rescuer_id' => $request->rescuer_id],
            [
                'assigned_by' => $request->user()->id,
                'status'      => \App\Enums\AssignmentStatus::Assigned,
                'notes'       => $request->notes,
                'assigned_at' => now(),
            ],
        );

        if ($incident->status === IncidentStatus::Verified) {
            $incident->update([
                'status'        => IncidentStatus::Dispatched,
                'dispatched_at' => now(),
            ]);
        }

        return response()->json([
            'assignment' => new IncidentAssignmentResource($assignment->load(['rescuer', 'assigner'])),
            'message'    => 'Rescuer assigned successfully.',
        ], 201);
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
