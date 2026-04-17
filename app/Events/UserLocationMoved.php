<?php

namespace App\Events;

use App\Models\User;
use App\Models\UserLocation;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired on every GPS ping. Broadcasts only to the specific incident channels
 * the moving user is currently involved in, so the live tracking map on the
 * other side (resident ↔ rescuer) updates without polling.
 *
 * Uses ShouldBroadcastNow so it bypasses the queue – GPS pings are high
 * frequency and must not fall behind behind an AI job.
 *
 * @property array<int, string> $incidentUlids
 */
class UserLocationMoved implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  array<int, string>  $incidentUlids  Incident ULIDs the user is part of
     *                                             (either as reporter or as assigned
     *                                             rescuer) — used to fan the event
     *                                             out to only the right rooms.
     */
    public function __construct(
        public User $user,
        public UserLocation $location,
        public array $incidentUlids = [],
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        $role = $this->user->role?->value ?? 'user';

        $channels = [];

        foreach ($this->incidentUlids as $ulid) {
            // Route the GPS ping to the side that *watches* this user:
            //   - resident's pings → `incident.{ulid}.reporter`  (rescuer listens)
            //   - rescuer's pings  → `incident.{ulid}.rescuer`   (resident listens)
            $suffix = $role === 'rescuer' ? 'rescuer' : 'reporter';
            $channels[] = new PrivateChannel("incident.{$ulid}.{$suffix}");
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'user.location-moved';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return [
            'user_id' => $this->user->id,
            'role' => $this->user->role?->value,
            'latitude' => (float) $this->location->latitude,
            'longitude' => (float) $this->location->longitude,
            'accuracy' => $this->location->accuracy !== null ? (float) $this->location->accuracy : null,
            'heading' => $this->location->heading !== null ? (float) $this->location->heading : null,
            'speed' => $this->location->speed !== null ? (float) $this->location->speed : null,
            'located_at' => $this->location->located_at?->toISOString(),
        ];
    }
}
