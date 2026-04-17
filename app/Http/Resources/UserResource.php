<?php

namespace App\Http\Resources;

use BackedEnum;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        if ($this->resource === null) {
            return [];
        }

        $roleValue = match (true) {
            $this->role instanceof BackedEnum => $this->role->value,
            is_string($this->role) => $this->role,
            default => null,
        };

        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'email'      => $this->email,
            'role'       => $roleValue,
            'role_label' => $roleValue ? ucfirst($roleValue) : null,
            'verified'   => $this->email_verified_at !== null,

            'profile' => match (true) {
                method_exists($this->resource, 'isRescuer') && $this->isRescuer() => $this->whenLoaded('rescuerProfile'),
                method_exists($this->resource, 'isResident') && $this->isResident() => $this->whenLoaded('residentProfile'),
                default => null,
            },

            'location' => $this->whenLoaded(
                'location',
                fn () => $this->location ? new UserLocationResource($this->location) : null,
            ),

            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
