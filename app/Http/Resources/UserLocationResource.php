<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\UserLocation
 */
class UserLocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'latitude'   => (float) $this->latitude,
            'longitude'  => (float) $this->longitude,
            'accuracy'   => $this->accuracy,
            'altitude'   => $this->altitude,
            'heading'    => $this->heading,
            'speed'      => $this->speed,
            'is_active'  => $this->is_active,
            'located_at' => $this->located_at?->toISOString(),

            'user' => new UserResource($this->whenLoaded('user')),
        ];
    }
}
