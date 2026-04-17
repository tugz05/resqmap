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
 * Fired once, when a resident submits a brand-new report. Admins watching
 * the ops console see the incident appear at the top of the queue with a
 * soft alert tone, no reload required.
 */
class NewIncidentReported implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public Incident $incident)
    {
        $this->incident->loadMissing('reporter');
    }

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('admin.incidents')];
    }

    public function broadcastAs(): string
    {
        return 'incident.new';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'incident_id' => $this->incident->ulid,
            'title' => $this->incident->title,
            'type' => $this->incident->type?->value,
            'severity' => $this->incident->severity?->value,
            'latitude' => (float) $this->incident->latitude,
            'longitude' => (float) $this->incident->longitude,
            'barangay' => $this->incident->barangay,
            'city' => $this->incident->city,
            'reporter_name' => $this->incident->reporter?->name,
            'reported_at' => $this->incident->reported_at?->toISOString(),
            'timestamp' => now()->toISOString(),
        ];
    }
}
