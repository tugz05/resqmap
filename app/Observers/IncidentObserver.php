<?php

namespace App\Observers;

use App\Events\IncidentStatusChanged;
use App\Events\NewIncidentReported;
use App\Jobs\VerifyIncidentAi;
use App\Models\Incident;

class IncidentObserver
{
    /**
     * Auto-queue AI verification as soon as an incident is created so residents
     * get an instant response while the OpenAI vision analysis runs in the
     * background worker. Also notifies admins over Pusher.
     */
    public function created(Incident $incident): void
    {
        NewIncidentReported::dispatch($incident);

        if (! filled(config('services.openai.api_key')) && ! filled(env('OPENAI_API_KEY'))) {
            return;
        }

        $incident->forceFill([
            'ai_verification_status' => 'pending',
            'ai_verification_queued_at' => now(),
        ])->saveQuietly();

        VerifyIncidentAi::dispatch($incident->id);
    }

    /**
     * Fan out a Pusher event whenever the `status` column changes so every
     * dashboard (resident / rescuer / admin) reflects the new state in real
     * time without polling.
     */
    public function updated(Incident $incident): void
    {
        if ($incident->wasChanged('status')) {
            IncidentStatusChanged::dispatch($incident);
        }
    }
}
