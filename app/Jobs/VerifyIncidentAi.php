<?php

namespace App\Jobs;

use App\Events\IncidentAiVerified;
use App\Models\Incident;
use App\Services\Incident\OpenAiIncidentTruthVerifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Queued AI verification job.
 *
 * Runs OpenAI vision-based authenticity analysis against an incident in the
 * background so resident report submissions stay fast. Admins can re-dispatch
 * this job at any time to re-run verification on the same incident.
 */
class VerifyIncidentAi implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 120;

    public function __construct(public int $incidentId) {}

    public function handle(OpenAiIncidentTruthVerifier $verifier): void
    {
        $incident = Incident::with('reporter.location')->find($this->incidentId);

        if ($incident === null) {
            return;
        }

        $incident->update([
            'ai_verification_status' => 'processing',
            'ai_verification_started_at' => now(),
            'ai_verification_attempts' => ($incident->ai_verification_attempts ?? 0) + 1,
            'ai_verification_error' => null,
        ]);

        try {
            $result = $verifier->verify($incident);

            $verdict = $result['verdict'] ?? null;
            $confidence = (int) ($result['confidence'] ?? 0);

            $incident->update([
                'ai_verdict' => $verdict,
                'ai_confidence' => $confidence,
                'ai_summary' => $result['summary_cebuano'] ?? null,
                'ai_red_flags' => $result['red_flags'] ?? [],
                'ai_recommended_action' => $result['recommended_action'] ?? null,
                'ai_verifier_model' => $result['model'] ?? null,
                'ai_verified_at' => now(),
                'ai_verification_status' => 'completed',
                'ai_verification_error' => null,
            ]);

            IncidentAiVerified::dispatch($incident->fresh());

            // Grab-style handoff: if the AI trusts the report, instantly
            // match the resident with the nearest rescuer. Admins only
            // override if something looks off — they don't manually dispatch.
            $shouldAutoDispatch = match (true) {
                $verdict === 'true' && $confidence >= 60 => true,
                $verdict === 'uncertain' && $confidence >= 75 => true,
                default => false,
            };

            if ($shouldAutoDispatch) {
                AutoAssignRescuerJob::dispatch($incident->id);
            }
        } catch (Throwable $e) {
            Log::error('AI verification job failed', [
                'incident_id' => $incident->id,
                'attempt' => $incident->ai_verification_attempts,
                'error' => $e->getMessage(),
            ]);

            $incident->update([
                'ai_verification_status' => 'failed',
                'ai_verification_error' => $e->getMessage(),
            ]);

            IncidentAiVerified::dispatch($incident->fresh());

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $incident = Incident::find($this->incidentId);

        if ($incident === null) {
            return;
        }

        $incident->update([
            'ai_verification_status' => 'failed',
            'ai_verification_error' => $exception?->getMessage() ?? 'Unknown error',
        ]);
    }
}
