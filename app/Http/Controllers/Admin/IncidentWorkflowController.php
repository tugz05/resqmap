<?php

namespace App\Http\Controllers\Admin;

use App\Enums\IncidentStatus;
use App\Http\Controllers\Controller;
use App\Jobs\AutoAssignRescuerJob;
use App\Jobs\VerifyIncidentAi;
use App\Models\Incident;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin incident workflow — oversight only.
 *
 * Following the "Grab food delivery" model, rescuer assignment is never done
 * manually by admins. The AI Dispatch Agent handles matching; admins can only:
 *   - Re-check / re-queue AI verification
 *   - Manually confirm a report as valid (which triggers auto-dispatch)
 *   - Manually reject a report
 *   - Resolve or cancel incidents
 */
class IncidentWorkflowController extends Controller
{
    /**
     * Queue AI verification for this incident. The heavy OpenAI vision call
     * runs in the background worker. Admins can hit this repeatedly to re-queue
     * (e.g. after a failure) or to double-check the AI's verdict.
     */
    public function aiVerify(Incident $incident): RedirectResponse
    {
        $incident->update([
            'ai_verification_status' => 'pending',
            'ai_verification_queued_at' => now(),
            'ai_verification_error' => null,
        ]);

        VerifyIncidentAi::dispatch($incident->id);

        return back()->with('success', 'AI verification queued. It will run in the background.');
    }

    /**
     * Manually re-queue the AI Dispatch Agent. Used when the first attempt
     * found no rescuer nearby (e.g. all were offline) and the admin wants to
     * try again after rescuers come online.
     */
    public function retryDispatch(Incident $incident): RedirectResponse
    {
        if ($incident->assignments()->exists()) {
            return back()->with('info', 'A rescuer is already assigned to this incident.');
        }

        AutoAssignRescuerJob::dispatch($incident->id);

        return back()->with('success', 'AI Dispatch Agent re-queued. A rescuer will be matched shortly.');
    }

    /**
     * Admin oversight action: change an incident's status.
     *
     * The only transitions that need admin judgement are:
     *   - pending → verified  (admin approved; triggers auto-dispatch)
     *   - pending → cancelled (admin rejected the report)
     *   - * → resolved        (admin closes the case)
     */
    public function updateStatus(Request $request, Incident $incident): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(IncidentStatus::values())],
        ]);

        $newStatus = IncidentStatus::from($validated['status']);
        $allowed = $incident->status->adminTransitions();

        if (! in_array($newStatus, $allowed, true)) {
            return back()->with('error', "Invalid status transition: {$incident->status->value} -> {$newStatus->value}");
        }

        $timestamps = match ($newStatus) {
            IncidentStatus::Verified => [
                'verified_at' => now(),
                'verified_by' => $request->user()->id,
            ],
            IncidentStatus::Dispatched => ['dispatched_at' => now()],
            IncidentStatus::Resolved => ['resolved_at' => now()],
            default => [],
        };

        $incident->update([
            'status' => $newStatus,
            ...$timestamps,
        ]);

        // When an admin confirms a report as valid, the AI Dispatch Agent
        // immediately picks the nearest rescuer — no manual assignment.
        if ($newStatus === IncidentStatus::Verified && ! $incident->assignments()->exists()) {
            AutoAssignRescuerJob::dispatch($incident->id);

            return back()->with('success', 'Incident verified. AI Dispatch Agent is matching the nearest rescuer.');
        }

        return back()->with('success', "Incident status updated to {$newStatus->label()}.");
    }
}
