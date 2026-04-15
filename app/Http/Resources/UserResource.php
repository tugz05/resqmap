<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'role'       => $this->role->value,
            'role_label' => ucfirst($this->role->value),
            'verified'   => $this->email_verified_at !== null,

            'profile' => $this->when(
                $this->isRescuer(),
                fn () => $this->whenLoaded('rescuerProfile'),
            ) ?? $this->when(
                $this->isResident(),
                fn () => $this->whenLoaded('residentProfile'),
            ),

            'location' => new UserLocationResource($this->whenLoaded('location')),

            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
