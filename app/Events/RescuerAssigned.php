<?php

namespace App\Events;

use App\Models\Incident;
use App\Models\IncidentAssignment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired by the AI Dispatch Agent the instant a rescuer is matched with an
 * incident (Grab-food-delivery moment). Three audiences care:
 *   1. The resident – "a rescuer is coming!"
 *   2. The rescuer  – "you've got a new mission".
 *   3. Admins       – watch the queue light up.
 */
class RescuerAssigned implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public Incident $incident,
        public IncidentAssignment $assignment,
    ) {
        $this->assignment->loadMissing(['rescuer.rescuerProfile', 'rescuer.location']);
    }

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('incident.'.$this->incident->ulid),
            new PrivateChannel('rescuer.'.$this->assignment->rescuer_id),
            new PrivateChannel('admin.incidents'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'incident.rescuer-assigned';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        $rescuer = $this->assignment->rescuer;

        return [
            'incident_id' => $this->incident->ulid,
            'assignment_id' => $this->assignment->id,
            'rescuer' => [
                'id' => $rescuer?->id,
                'name' => $rescuer?->name,
                'agency' => $rescuer?->rescuerProfile?->agency_name,
                'contact' => $rescuer?->rescuerProfile?->contact_number,
                'latitude' => $rescuer?->location?->latitude !== null ? (float) $rescuer->location->latitude : null,
                'longitude' => $rescuer?->location?->longitude !== null ? (float) $rescuer->location->longitude : null,
            ],
            'status' => $this->assignment->status->value,
            'status_label' => $this->assignment->status->label(),
            'assigned_at' => $this->assignment->assigned_at?->toISOString(),
            'timestamp' => now()->toISOString(),
        ];
    }
}
