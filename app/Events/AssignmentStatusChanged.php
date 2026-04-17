<?php

namespace App\Events;

use App\Models\IncidentAssignment;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when the rescuer moves between assignment states
 * (assigned → accepted → en_route → on_scene → completed).
 *
 * The resident dashboard listens to this to animate the live timeline
 * ("your rescuer is on the way", "arrived on scene", etc.).
 */
class AssignmentStatusChanged implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public IncidentAssignment $assignment)
    {
        $this->assignment->loadMissing('incident');
    }

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        $incidentUlid = $this->assignment->incident?->ulid;

        $channels = [
            new PrivateChannel('admin.incidents'),
        ];

        if ($incidentUlid !== null) {
            $channels[] = new PrivateChannel('incident.'.$incidentUlid);
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'incident.assignment-status-changed';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'incident_id' => $this->assignment->incident?->ulid,
            'assignment_id' => $this->assignment->id,
            'rescuer_id' => $this->assignment->rescuer_id,
            'status' => $this->assignment->status->value,
            'status_label' => $this->assignment->status->label(),
            'accepted_at' => $this->assignment->accepted_at?->toISOString(),
            'arrived_at' => $this->assignment->arrived_at?->toISOString(),
            'completed_at' => $this->assignment->completed_at?->toISOString(),
            'timestamp' => now()->toISOString(),
        ];
    }
}
