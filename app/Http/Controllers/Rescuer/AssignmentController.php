<?php

namespace App\Http\Controllers\Rescuer;

use App\Enums\AssignmentStatus;
use App\Events\AssignmentStatusChanged;
use App\Http\Controllers\Controller;
use App\Models\IncidentAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssignmentController extends Controller
{
    public function updateStatus(Request $request, IncidentAssignment $assignment): RedirectResponse
    {
        if ($assignment->rescuer_id !== $request->user()->id) {
            abort(403);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in(AssignmentStatus::values())],
        ]);

        $newStatus = AssignmentStatus::from($validated['status']);
        $allowed = $assignment->status->rescuerTransitions();

        if (! in_array($newStatus, $allowed, true)) {
            return back()->with('error', "Invalid transition: {$assignment->status->value} -> {$newStatus->value}");
        }

        $timestamps = match ($newStatus) {
            AssignmentStatus::Accepted => ['accepted_at' => now()],
            AssignmentStatus::OnScene => ['arrived_at' => now()],
            AssignmentStatus::Completed => ['completed_at' => now()],
            default => [],
        };

        $assignment->update([
            'status' => $newStatus,
            ...$timestamps,
        ]);

        AssignmentStatusChanged::dispatch($assignment->fresh());

        return back()->with('success', "Assignment updated to {$newStatus->label()}.");
    }
}
