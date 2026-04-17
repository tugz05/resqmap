<?php

namespace App\Events;

use App\Models\Incident;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast when the OpenAI vision verifier finishes analysing an incident.
 * Admins and the reporter both want to see the verdict appear instantly in
 * the UI without polling.
 */
class IncidentAiVerified implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Incident $incident) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('incident.'.$this->incident->ulid),
            new PrivateChannel('admin.incidents'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'incident.ai-verified';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'incident_id' => $this->incident->ulid,
            'verification_status' => $this->incident->ai_verification_status,
            'verdict' => $this->incident->ai_verdict,
            'confidence' => $this->incident->ai_confidence,
            'summary_cebuano' => $this->incident->ai_summary,
            'red_flags' => $this->incident->ai_red_flags ?? [],
            'recommended_action' => $this->incident->ai_recommended_action,
            'verified_at' => $this->incident->ai_verified_at?->toISOString(),
            'timestamp' => now()->toISOString(),
        ];
    }
}
