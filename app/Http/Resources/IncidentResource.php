<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Incident
 */
class IncidentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'       => $this->ulid,
            'type'     => $this->type->value,
            'type_label' => $this->type->label(),
            'type_icon'  => $this->type->icon(),
            'severity'   => $this->severity->value,
            'severity_label' => $this->severity->label(),
            'severity_color' => $this->severity->color(),
            'status'     => $this->status->value,
            'status_label' => $this->status->label(),
            'is_active'  => $this->isActive(),

            'title'       => $this->title,
            'description' => $this->description,

            'location' => [
                'latitude'  => (float) $this->latitude,
                'longitude' => (float) $this->longitude,
                'address'   => $this->address,
                'barangay'  => $this->barangay,
                'city'      => $this->city,
                'province'  => $this->province,
            ],

            'photos' => $this->photo_paths ?? [],

            'reporter'  => new UserResource($this->whenLoaded('reporter')),
            'verifier'  => new UserResource($this->whenLoaded('verifier')),
            'assignments' => IncidentAssignmentResource::collection($this->whenLoaded('assignments')),

            // distance_km is only present when using the nearby scope
            'distance_km' => $this->when(
                isset($this->distance_km),
                fn () => round((float) $this->distance_km, 2),
            ),

            'reported_at'   => $this->reported_at?->toISOString(),
            'verified_at'   => $this->verified_at?->toISOString(),
            'dispatched_at' => $this->dispatched_at?->toISOString(),
            'resolved_at'   => $this->resolved_at?->toISOString(),
            'created_at'    => $this->created_at?->toISOString(),
            'updated_at'    => $this->updated_at?->toISOString(),
        ];
    }
}
