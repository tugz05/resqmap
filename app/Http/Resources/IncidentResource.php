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
            'ai_verification' => [
                'status' => $this->ai_verification_status ?? 'pending',
                'queued_at' => $this->ai_verification_queued_at?->toISOString(),
                'started_at' => $this->ai_verification_started_at?->toISOString(),
                'attempts' => (int) ($this->ai_verification_attempts ?? 0),
                'error' => $this->ai_verification_error,
                'verdict' => $this->ai_verdict,
                'confidence' => $this->ai_confidence,
                'summary_cebuano' => $this->ai_summary,
                'red_flags' => $this->ai_red_flags ?? [],
                'recommended_action' => $this->ai_recommended_action,
                'model' => $this->ai_verifier_model,
                'verified_at' => $this->ai_verified_at?->toISOString(),
            ],
            'ai_command_center' => [
                ...($this->ai_command_center ?? []),
                'commanded_at' => $this->ai_commanded_at?->toISOString(),
            ],
            'ai_dispatch' => [
                ...($this->ai_dispatch ?? []),
                'dispatched_at' => $this->ai_dispatched_at?->toISOString(),
            ],

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

            // Live Grab-style tracking: the latest GPS ping for the person
            // who filed the report and for the assigned rescuer (if any).
            'reporter_location' => $this->when(
                $this->relationLoaded('reporter') && $this->reporter?->relationLoaded('location') && $this->reporter->location,
                fn () => new UserLocationResource($this->reporter->location),
            ),
            'rescuer_location' => $this->when(
                $this->relationLoaded('assignments') && $this->assignments->isNotEmpty(),
                function () {
                    $primary = $this->assignments->first();
                    if (! $primary || ! $primary->relationLoaded('rescuer') || ! $primary->rescuer?->relationLoaded('location') || ! $primary->rescuer->location) {
                        return null;
                    }

                    return new UserLocationResource($primary->rescuer->location);
                },
            ),
            'assigned_rescuer' => $this->when(
                $this->relationLoaded('assignments') && $this->assignments->isNotEmpty(),
                function () {
                    $primary = $this->assignments->first();
                    if (! $primary) {
                        return null;
                    }

                    return [
                        'id' => $primary->rescuer_id,
                        'name' => $primary->rescuer?->name,
                        'agency' => $primary->rescuer?->rescuerProfile?->agency_name,
                        'contact' => $primary->rescuer?->rescuerProfile?->contact_number,
                        'status' => $primary->status->value,
                        'status_label' => $primary->status->label(),
                        'assigned_at' => $primary->assigned_at?->toISOString(),
                        'accepted_at' => $primary->accepted_at?->toISOString(),
                        'arrived_at' => $primary->arrived_at?->toISOString(),
                        'completed_at' => $primary->completed_at?->toISOString(),
                    ];
                },
            ),

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
