<?php

namespace App\Jobs;

use App\Enums\UserRole;
use App\Models\Incident;
use App\Models\User;
use App\Services\Agentic\DispatchAgentService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * AI Dispatch Agent — background auto-assignment.
 *
 * Runs after verification (either AI or admin-confirmed) and deterministically
 * picks the nearest available rescuer using the Haversine-based
 * {@see DispatchAgentService}. Admins never pick rescuers manually — their
 * only responsibility is re-checking AI verification.
 *
 * This is the "Grab food delivery" moment: once the report is accepted the
 * system instantly matches the resident with the nearest driver/responder.
 */
class AutoAssignRescuerJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 15;

    public int $timeout = 60;

    public function __construct(public int $incidentId) {}

    public function handle(DispatchAgentService $dispatchAgent): void
    {
        $incident = Incident::with('reporter.location')->find($this->incidentId);

        if ($incident === null) {
            return;
        }

        // If a rescuer is already assigned, do nothing (idempotent).
        if ($incident->assignments()->exists()) {
            return;
        }

        // We still need an "assigner" for referential integrity. The first
        // admin acts as the system operator of record; the incident's
        // ai_dispatch.auto_dispatched flag makes it clear it was automated.
        $systemAdmin = User::query()
            ->where('role', UserRole::Admin->value)
            ->orderBy('id')
            ->first();

        if ($systemAdmin === null) {
            Log::warning('Auto-dispatch skipped: no admin user available.', [
                'incident_id' => $incident->id,
            ]);

            return;
        }

        try {
            $assignment = $dispatchAgent->autoDispatch($incident, $systemAdmin);
        } catch (Throwable $e) {
            Log::error('AI auto-dispatch job failed', [
                'incident_id' => $incident->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }

        if ($assignment === null) {
            Log::info('AI auto-dispatch found no rescuer in radius.', [
                'incident_id' => $incident->id,
            ]);

            return;
        }

        Log::info('AI auto-dispatch assigned rescuer.', [
            'incident_id' => $incident->id,
            'rescuer_id' => $assignment->rescuer_id,
        ]);
    }
}
