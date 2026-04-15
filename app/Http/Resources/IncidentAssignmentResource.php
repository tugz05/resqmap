<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\IncidentAssignment
 */
class IncidentAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'     => $this->id,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'notes'  => $this->notes,

            'incident' => new IncidentResource($this->whenLoaded('incident')),
            'rescuer'  => new UserResource($this->whenLoaded('rescuer')),
            'assigner' => new UserResource($this->whenLoaded('assigner')),

            // Include the rescuer's live location when loaded
            'rescuer_location' => $this->when(
                $this->relationLoaded('rescuer') && $this->rescuer->relationLoaded('location'),
                fn () => new UserLocationResource($this->rescuer->location),
            ),

            'assigned_at'  => $this->assigned_at?->toISOString(),
            'accepted_at'  => $this->accepted_at?->toISOString(),
            'arrived_at'   => $this->arrived_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
        ];
    }
}
