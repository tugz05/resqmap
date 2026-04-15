<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\AssignmentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\IncidentAssignmentResource;
use App\Models\IncidentAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssignmentController extends Controller
{
    /**
     * List the authenticated rescuer's assignments.
     */
    public function index(Request $request): JsonResponse
    {
        $assignments = IncidentAssignment::with(['incident', 'assigner:id,name'])
            ->forRescuer($request->user()->id)
            ->orderByDesc('assigned_at')
            ->paginate(20);

        return response()->json([
            'assignments' => IncidentAssignmentResource::collection($assignments->items()),
            'pagination'  => [
                'total'        => $assignments->total(),
                'per_page'     => $assignments->perPage(),
                'current_page' => $assignments->currentPage(),
                'last_page'    => $assignments->lastPage(),
            ],
        ]);
    }

    /**
     * Update the rescuer's own assignment status.
     * The Flutter app calls this when the rescuer taps "Accept", "En Route", "On Scene", or "Complete".
     */
    public function updateStatus(Request $request, IncidentAssignment $assignment): JsonResponse
    {
        // Rescuer may only update their own assignments
        if ($assignment->rescuer_id !== $request->user()->id) {
            abort(403);
        }

        $request->validate([
            'status' => ['required', Rule::enum(AssignmentStatus::class)],
        ]);

        $newStatus = AssignmentStatus::from($request->status);
        $allowed   = $assignment->status->rescuerTransitions();

        if (! in_array($newStatus, $allowed, true)) {
            return response()->json([
                'message' => "Cannot transition assignment from [{$assignment->status->value}] to [{$newStatus->value}].",
            ], 422);
        }

        $timestamps = match ($newStatus) {
            AssignmentStatus::Accepted  => ['accepted_at' => now()],
            AssignmentStatus::OnScene   => ['arrived_at' => now()],
            AssignmentStatus::Completed => ['completed_at' => now()],
            default                     => [],
        };

        $assignment->update(['status' => $newStatus, ...$timestamps]);

        return response()->json([
            'assignment' => new IncidentAssignmentResource($assignment->load(['incident', 'rescuer'])),
            'message'    => "Assignment status updated to [{$newStatus->label()}].",
        ]);
    }
}
