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
 * Fired whenever an incident's `status` (pending / verified / dispatched /
 * en_route / on_scene / resolved / cancelled) changes.
 *
 * Listened to by the resident dashboard (to know a rescuer is incoming),
 * the rescuer dashboard (for new / cancelled missions), and the admin
 * ops console (for the live queue).
 */
class IncidentStatusChanged implements ShouldBroadcast
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
        return 'incident.status-changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'incident_id' => $this->incident->ulid,
            'status' => $this->incident->status->value,
            'status_label' => $this->incident->status->label(),
            'verified_at' => $this->incident->verified_at?->toISOString(),
            'dispatched_at' => $this->incident->dispatched_at?->toISOString(),
            'resolved_at' => $this->incident->resolved_at?->toISOString(),
            'timestamp' => now()->toISOString(),
        ];
    }
}
